#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# ---------------------------------------------------------------------------
# Runtime prep. The Render Disk mounts over /var/www/html/storage and starts
# empty, so recreate the writable tree the framework expects.
# ---------------------------------------------------------------------------
mkdir -p storage/app/public/hls storage/app/private/pending-uploads
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

rm -rf public/storage
php artisan storage:link

# Nginx must bind Render's $PORT (defaults to 80 locally / in plain Docker).
export PORT="${PORT:-80}"
sed "s|{{PORT}}|$PORT|g" /etc/nginx/conf.d/krettel.conf.template > /etc/nginx/conf.d/krettel.conf

chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# Migrate (with a short wait for the DB to come up), then cache config/routes.
# ---------------------------------------------------------------------------
migrated=false
for i in $(seq 1 30); do
    if php artisan migrate --force --no-interaction; then
        migrated=true
        break
    fi
    echo "Waiting for database... (attempt $i)"
    sleep 2
done

if [ "$migrated" != "true" ]; then
    echo "Database unreachable after 30 attempts. Aborting."
    exit 1
fi

php artisan optimize || true

# ---------------------------------------------------------------------------
# Run nginx + php-fpm + the queue worker under supervisord.
# ---------------------------------------------------------------------------
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
