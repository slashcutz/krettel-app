#!/usr/bin/env bash
# =============================================================================
# Krettel — Oracle Cloud Always-Free Ubuntu setup
#
# Installs Nginx + PHP 8.3-FPM + MySQL + FFmpeg, deploys the Laravel app,
# and registers the queue worker as a systemd service so background jobs
# (TeraBox uploads, HLS transcodes) run 24/7 — Phase 2 never depends on the
# browser tab.
#
# Run on the VM (Ubuntu 24.04) as root or with sudo:
#     sudo bash scripts/oracle-deploy.sh
#
# Overridable via env vars (run before the script):
#     GIT_REPO=https://github.com/YOUR_ORG/YOUR_REPO.git   (required)
#     GIT_TOKEN=ghp_xxx                             (optional; PAT for private repos)
#     BRANCH=main
#     APP_DIR=/var/www/krettel
#     DB_NAME=krettel_app   DB_USER=krettel   DB_PASS=(auto-generated)
#     APP_URL=http://<public-ip>
#     TERABOX_EMAIL=... TERABOX_PASSWORD=... TERABOX_NDUS=...
#     TERABOX_REMOTE_DIR=/Apps/Krettel TERABOX_WEB_HOST=https://www.1024terabox.com
#
# TeraBox creds can alternatively be written to /root/terabox.env (see
# scripts/terabox-creds.sh); this script sources it automatically.
# =============================================================================

set -euo pipefail

# ------------------------------- Configuration ------------------------------
GIT_REPO="${GIT_REPO:-https://github.com/YOUR_ORG/YOUR_REPO.git}"
GIT_TOKEN="${GIT_TOKEN:-}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-/var/www/krettel}"
DB_NAME="${DB_NAME:-krettel_app}"
DB_USER="${DB_USER:-krettel}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 16)}"
TERABOX_REMOTE_DIR="${TERABOX_REMOTE_DIR:-/Apps/Krettel}"
TERABOX_WEB_HOST="${TERABOX_WEB_HOST:-https://www.1024terabox.com}"

# TeraBox credentials saved by terabox-creds.sh are picked up automatically.
if [ -f /root/terabox.env ]; then
    # shellcheck disable=SC1091
    . /root/terabox.env
fi

CRED_FILE="/root/krettel-credentials.txt"

log()  { echo -e "\n\033[1;32m[krettel]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[krettel!]\033[0m $*"; }

if [ "${GIT_REPO}" = "https://github.com/YOUR_ORG/YOUR_REPO.git" ]; then
    warn "GIT_REPO is still a placeholder. Push the repo to GitHub first, then"
    warn "re-run with:  GIT_REPO=https://github.com/you/your-repo.git bash scripts/oracle-deploy.sh"
    exit 1
fi

# ------------------------------- OS checks -----------------------------------
if ! grep -q "24.04" /etc/os-release; then
    warn "This script targets Ubuntu 24.04 (PHP 8.3 in default repos)."
    warn "Ubuntu 22.04 ships PHP 8.1; add the ondrej PPA or use 24.04."
    read -r -p "Continue anyway? [y/N] " ans
    [[ "${ans,,}" == "y" ]] || exit 1
fi

PUBLIC_IP="$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"
APP_URL="${APP_URL:-http://${PUBLIC_IP}}"
export DEBIAN_FRONTEND=noninteractive

# ------------------------------- Packages ------------------------------------
log "Installing system packages (nginx, php 8.3, mysql, ffmpeg, composer)..."
apt-get update
apt-get install -y \
    nginx \
    php8.3-fpm php8.3-cli \
    php8.3-mysql php8.3-bcmath php8.3-mbstring php8.3-xml \
    php8.3-zip php8.3-gd php8.3-curl php8.3-intl \
    mysql-server \
    ffmpeg \
    composer \
    git curl openssl

FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
if [ -z "${FPM_SOCK}" ]; then
    FPM_SOCK="/run/php/php8.3-fpm.sock"
fi

# ------------------------------- PHP limits ----------------------------------
log "Raising PHP upload limits (MKV uploads can be >3GB)..."
cat > /etc/php/8.3/fpm/conf.d/99-krettel.ini <<'INI'
upload_max_filesize = 4096M
post_max_size = 4100M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
INI
cat > /etc/php/8.3/cli/conf.d/99-krettel.ini <<'INI'
memory_limit = 512M
INI
systemctl restart php8.3-fpm

# ------------------------------- MySQL ----------------------------------------
log "Starting MySQL and creating the application database/user..."
systemctl enable --now mysql
for _ in $(seq 1 30); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
done

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

umask 077
cat > "${CRED_FILE}" <<EOF
Krettel deployment credentials
===============================
DB_NAME:   ${DB_NAME}
DB_USER:   ${DB_USER}
DB_PASS:   ${DB_PASS}
APP_URL:   ${APP_URL}
EOF
log "Credentials saved to ${CRED_FILE}"

# ------------------------------- Application ---------------------------------
log "Fetching application code (branch ${BRANCH})..."
mkdir -p "$(dirname "${APP_DIR}")"

# Auth wrapper: private repos use GIT_TOKEN via http.extraHeader so the token
# never lands in the URL, .git/config, or the process list.
run_git() {
    if [ -n "${GIT_TOKEN}" ]; then
        local basic
        basic="$(printf 'x-access-token:%s' "${GIT_TOKEN}" | base64 -w0)"
        git -c "http.extraHeader=Authorization: Basic ${basic}" "$@"
    else
        git "$@"
    fi
}

if [ ! -d "${APP_DIR}/vendor" ] && [ ! -f "${APP_DIR}/artisan" ]; then
    run_git clone -b "${BRANCH}" "${GIT_REPO}" "${APP_DIR}"
else
    log "App already present at ${APP_DIR} — skipping clone."
    run_git -C "${APP_DIR}" pull --ff-only || true
fi
cd "${APP_DIR}"

log "Installing PHP dependencies (no-dev, optimized)..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "Configuring .env ..."
if [ ! -f .env ]; then
    cp .env.example .env
fi

set_env() {
    local key="$1" val="$2" file="${APP_DIR}/.env"
    # Escape sed-replacement metacharacters so passwords/keys with special
    # characters (e.g. & | \) survive the rewrite.
    local esc="${val//\\/\\\\}"
    esc="${esc//&/\\&}"
    esc="${esc//|/\\|}"
    if grep -q "^${key}=" "${file}"; then
        sed -i "s|^${key}=.*|${key}=${esc}|" "${file}"
    else
        echo "${key}=${val}" >> "${file}"
    fi
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "${APP_URL}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_NAME}"
set_env DB_USERNAME "${DB_USER}"
set_env DB_PASSWORD "${DB_PASS}"
set_env SESSION_DRIVER database
set_env CACHE_STORE database
set_env QUEUE_CONNECTION database
set_env TERABOX_REMOTE_DIR "${TERABOX_REMOTE_DIR}"
set_env TERABOX_WEB_HOST "${TERABOX_WEB_HOST}"
[ -n "${TERABOX_EMAIL:-}" ]    && set_env TERABOX_EMAIL "${TERABOX_EMAIL}"
[ -n "${TERABOX_PASSWORD:-}" ] && set_env TERABOX_PASSWORD "${TERABOX_PASSWORD}"
[ -n "${TERABOX_NDUS:-}" ]     && set_env TERABOX_NDUS "${TERABOX_NDUS}"

# FFMPEG_BINARY / FFPROBE_BINARY: left unset -> defaults to ffmpeg/ffprobe on PATH

log "Generating app key, linking storage, migrating, caching..."
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi
rm -rf public/storage
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force || true
php artisan optimize || true

# ------------------------------- Nginx ----------------------------------------
log "Configuring Nginx (port 80, 4GB uploads, Laravel)..."
cat > /etc/nginx/sites-available/krettel <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name _;
    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 4100m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass unix:${FPM_SOCK};
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_read_timeout 300;
    }

    location ~* \\.(?:js|css|png|jpe?g|gif|ico|svg|webp|woff2?|eot|ttf|map)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location ~ /\\. { deny all; }
}
EOF
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/krettel /etc/nginx/sites-enabled/krettel
nginx -t
systemctl enable --now nginx

# ------------------------------- Queue worker ---------------------------------
log "Registering the queue worker as a systemd service (runs forever)..."
cat > /etc/systemd/system/krettel-worker.service <<EOF
[Unit]
Description=Krettel queue worker (TeraBox uploads / HLS transcodes)
After=network.target mysql.service
Wants=mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=2 --tries=3 --timeout=7300
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now krettel-worker
systemctl restart php8.3-fpm

# ------------------------------- Firewall --------------------------------------
if command -v ufw >/dev/null 2>&1; then
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    warn "ufw is present. Enable it with 'ufw enable' if you want the firewall on."
fi

# ------------------------------- Summary ---------------------------------------
echo
echo "============================================================================"
log "Deployment complete!"
echo "    Site:           ${APP_URL}"
echo "    App directory:  ${APP_DIR}"
echo "    Credentials:    ${CRED_FILE}  (cat to view)"
echo "    Worker:         systemctl status krettel-worker"
echo "    Logs:           tail -f ${APP_DIR}/storage/logs/laravel.log"
echo
warn "Next steps:"
echo "    1. Open port 80 (and 443) in Oracle Cloud: VCN -> Security List ->"
echo "       Add Ingress Rule (Source 0.0.0.0/0, TCP 80/443)."
echo "    2. If TERABOX_EMAIL / TERABOX_PASSWORD / TERABOX_NDUS were not passed"
echo "       as env vars, add them to ${APP_DIR}/.env then run:"
echo "           sudo systemctl restart krettel-worker php8.3-fpm"
echo "    3. Point a free subdomain (e.g. *.duckdns.org) at ${PUBLIC_IP} and"
echo "       set APP_URL, then run 'sudo certbot --nginx' for HTTPS."
echo "    4. Uploads land in storage/app/private then the worker pushes them to"
echo "       TeraBox and streams HLS from storage/app/public — both persist."
echo "============================================================================"
