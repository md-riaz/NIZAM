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

rm -f \
    "$APP_ROOT/bootstrap/cache/packages.php" \
    "$APP_ROOT/bootstrap/cache/services.php"

if [ -f "$ENV_FILE" ] && ! grep -q '^APP_KEY=base64:' "$ENV_FILE"; then
    echo "[entrypoint] APP_KEY missing. Generating one..."
    php artisan key:generate --force --no-interaction
fi

if [ -f "$VITE_HOT_FILE" ]; then
    echo "[entrypoint] Removing stale Vite hot file..."
    rm -f "$VITE_HOT_FILE"
fi

if [ -f "$APP_ROOT/package.json" ] && [ ! -f "$VITE_MANIFEST" ]; then
    echo "[entrypoint] Vite manifest missing. Restoring prebuilt frontend assets..."
    mkdir -p "$APP_ROOT/public/build"
    cp -R /opt/app-build/public-build/. "$APP_ROOT/public/build/"
    php artisan optimize:clear --no-interaction
fi

# ── Auto-run migrations on boot (safe: Laravel skips already-run migrations) ──
if [ -f "$ENV_FILE" ] && grep -q '^DB_HOST=' "$ENV_FILE"; then
    echo "[entrypoint] Running pending migrations..."
    php artisan migrate --force --no-interaction 2>&1 || echo "[entrypoint] Migration failed — continuing anyway"

    if grep -q '^ADMIN_EMAIL=.\+' "$ENV_FILE" && grep -q '^ADMIN_PASSWORD=.\+' "$ENV_FILE"; then
        echo "[entrypoint] ADMIN_EMAIL + ADMIN_PASSWORD set — running seeders..."
        php artisan db:seed --force --no-interaction 2>&1 || echo "[entrypoint] Seeding failed — continuing anyway"
    fi
fi

exec "$@"
