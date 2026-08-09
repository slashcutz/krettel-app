#!/usr/bin/env bash
# Deploy krettel-app on an Oracle Cloud Always Free VM.
# Run from the repo root:  bash deploy/oracle/deploy.sh
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Checking prerequisites (docker + compose)"
command -v docker >/dev/null || { echo "docker not found. Install it first."; exit 1; }
docker compose version >/dev/null 2>&1 || docker-compose version >/dev/null 2>&1 || {
    echo "docker compose not found. Install it first."; exit 1;
}

echo "==> Preparing .env"
if [ ! -f "$DIR/.env" ]; then
    cp "$DIR/.env.example" "$DIR/.env"
    echo "Created $DIR/.env — EDIT IT now (APP_KEY, SITE_DOMAIN, TERABOX_*, PIXELDRAIN_API_KEY)."
    echo "Generate APP_KEY with: openssl rand -base64 32"
    exit 0
fi

# Refuse to boot without the required secrets.
grep -q '^APP_KEY=.\+' "$DIR/.env"   || { echo "APP_KEY is empty in $DIR/.env"; exit 1; }
grep -q '^SITE_DOMAIN=.\+' "$DIR/.env" || { echo "SITE_DOMAIN is empty in $DIR/.env"; exit 1; }

echo "==> Building image (first build can take several minutes)"
docker compose -f "$DIR/docker-compose.yml" build app

echo "==> Starting app + caddy"
docker compose -f "$DIR/docker-compose.yml" up -d

echo "==> Done. Follow logs with:"
echo "    docker compose -f $DIR/docker-compose.yml logs -f app"
echo
echo "Next steps:"
echo "  1. Point your domain A/AAAA record at this VM's public IP."
echo "  2. Caddy will auto-issue the Let's Encrypt cert for SITE_DOMAIN."
echo "  3. Run bash deploy/oracle/migrate-data.md to move data from Render."
