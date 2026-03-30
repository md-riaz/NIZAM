#!/bin/sh
# Ensure storage directories are writable by the PHP-FPM worker (www-data)
# before handing off to php-fpm. This is needed when the host directory is
# bind-mounted and owned by a different UID than www-data (uid 82 on Alpine).
set -e

APP_ROOT=/var/www/html
ENV_FILE="$APP_ROOT/.env"
VITE_HOT_FILE="$APP_ROOT/public/hot"
VITE_MANIFEST="$APP_ROOT/public/build/manifest.json"

chown -R www-data:www-data \
    "$APP_ROOT/storage" \
    "$APP_ROOT/bootstrap/cache"

if [ -f "$ENV_FILE" ] && ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    echo "[entrypoint] APP_KEY missing. Generating one..."
    php artisan key:generate --force --no-interaction
fi

if [ -f "$VITE_HOT_FILE" ]; then
    echo "[entrypoint] Removing stale Vite hot file..."
    rm -f "$VITE_HOT_FILE"
fi

if [ -f "$APP_ROOT/package.json" ]; then
    SHOULD_BUILD_FRONTEND=0

    if [ ! -f "$VITE_MANIFEST" ]; then
        SHOULD_BUILD_FRONTEND=1
        echo "[entrypoint] Vite manifest missing. Building frontend assets..."
    elif find \
        "$APP_ROOT/resources/css" \
        "$APP_ROOT/resources/js" \
        -type f -newer "$VITE_MANIFEST" | grep -q .; then
        SHOULD_BUILD_FRONTEND=1
        echo "[entrypoint] Frontend sources changed. Rebuilding Vite assets..."
    fi

    if [ "$SHOULD_BUILD_FRONTEND" -eq 1 ]; then
        if [ -f "$APP_ROOT/package-lock.json" ]; then
            npm ci --no-audit --no-fund --force
        else
            npm install --no-audit --no-fund --force
        fi

        npm run build
        php artisan optimize:clear --no-interaction
    fi
fi

exec "$@"
