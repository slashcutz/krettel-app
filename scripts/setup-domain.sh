#!/usr/bin/env bash
# =============================================================================
# Krettel — DuckDNS + Let's Encrypt HTTPS for the Oracle VM
#
# Run AFTER scripts/oracle-deploy.sh, once a free subdomain exists and points
# at the VM's public IP (DuckDNS / pp.ua / etc):
#     sudo bash scripts/setup-domain.sh
#
# Env vars (run before the script):
#     DOMAIN=krettel.duckdns.org      (required)
#     EMAIL=you@example.com           (required for Let's Encrypt)
#     APP_DIR=/var/www/krettel
# =============================================================================

set -euo pipefail

DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
APP_DIR="${APP_DIR:-/var/www/krettel}"

log()  { echo -e "\n\033[1;32m[krettel]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[krettel!]\033[0m $*"; }

if [ -z "${DOMAIN}" ] || [ -z "${EMAIL}" ]; then
    warn "Usage: sudo DOMAIN=krettel.duckdns.org EMAIL=you@example.com bash scripts/setup-domain.sh"
    exit 1
fi

if [ ! -f "${APP_DIR}/artisan" ]; then
    warn "App not found at ${APP_DIR}. Is oracle-deploy.sh finished?"
    exit 1
fi

# ------------------------------- DNS check -----------------------------------
PUBLIC_IP="$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"
RESOLVED_IP="$(getent hosts "${DOMAIN}" | awk '{print $1}' | head -1 || true)"
if [ -n "${RESOLVED_IP}" ] && [ "${RESOLVED_IP}" != "${PUBLIC_IP}" ]; then
    warn "${DOMAIN} resolves to ${RESOLVED_IP}, but this VM is ${PUBLIC_IP}."
    warn "Fix the DNS record (DuckDNS -> update IP) before continuing."
    read -r -p "Continue anyway? [y/N] " ans
    [[ "${ans,,}" == "y" ]] || exit 1
fi

# ------------------------------- certbot --------------------------------------
log "Installing certbot..."
export DEBIAN_FRONTEND=noninteractive
apt-get install -y python3-certbot-nginx

log "Obtaining Let's Encrypt certificate for ${DOMAIN}..."
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect

log "Reloading Nginx..."
systemctl reload nginx

# ------------------------------- App URL --------------------------------------
log "Updating APP_URL to https://${DOMAIN} ..."
ENV_FILE="${APP_DIR}/.env"
esc="${DOMAIN}"
if grep -q "^APP_URL=" "${ENV_FILE}"; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${esc}|" "${ENV_FILE}"
else
    echo "APP_URL=https://${esc}" >> "${ENV_FILE}"
fi

log "Recaching config and restarting worker/FPM..."
cd "${APP_DIR}"
php artisan config:cache || true
systemctl restart krettel-worker php8.3-fpm

# ------------------------------- Summary ---------------------------------------
echo
echo "============================================================================"
log "HTTPS is live!"
echo "    URL:   https://${DOMAIN}"
echo
warn "Remember:"
echo "    1. If your IP ever changes, update DuckDNS and re-run this script"
echo "       (Let's Encrypt renews itself daily via certbot.timer)."
echo "    2. Open port 443 in Oracle Cloud (VCN -> Security List) if not done."
echo "    3. DuckDNS IP updater (run once, then forget):"
echo "         curl \"https://www.duckdns.org/update?domains=YOURDOMAIN&token=YOURTOKEN&ip=\""
echo "============================================================================"
