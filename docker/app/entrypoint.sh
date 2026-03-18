#!/bin/sh
# Ensure storage directories are writable by the PHP-FPM worker (www-data)
# before handing off to php-fpm. This is needed when the host directory is
# bind-mounted and owned by a different UID than www-data (uid 82 on Alpine).
set -e

APP_ROOT=/var/www/html
ENV_FILE="$APP_ROOT/.env"
VITE_MANIFEST="$APP_ROOT/public/build/manifest.json"

chown -R www-data:www-data \
    "$APP_ROOT/storage" \
    "$APP_ROOT/bootstrap/cache"

if [ -f "$ENV_FILE" ] && ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    echo "[entrypoint] APP_KEY missing. Generating one..."
    php artisan key:generate --force --no-interaction
fi

if [ -f "$APP_ROOT/package.json" ] && [ ! -f "$VITE_MANIFEST" ]; then
    echo "[entrypoint] Vite manifest missing. Building frontend assets..."

    if [ -f "$APP_ROOT/package-lock.json" ]; then
        npm ci --no-audit --no-fund --force
    else
        npm install --no-audit --no-fund --force
    fi

    npm run build
fi

exec "$@"
