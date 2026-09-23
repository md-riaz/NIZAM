#!/bin/sh
# Ensure storage directories are writable by the PHP-FPM worker (www-data)
# before handing off to php-fpm. This is needed when the host directory is
# bind-mounted and owned by a different UID than www-data (uid 82 on Alpine).
set -e

APP_ROOT=/var/www/html
ENV_FILE="$APP_ROOT/.env"
VITE_HOT_FILE="$APP_ROOT/public/hot"
VITE_MANIFEST="$APP_ROOT/public/build/manifest.json"

mkdir -p \
    "$APP_ROOT/storage/framework/cache/data" \
    "$APP_ROOT/storage/framework/sessions" \
    "$APP_ROOT/storage/framework/views" \
    "$APP_ROOT/storage/logs" \
    "$APP_ROOT/bootstrap/cache"

chown -R www-data:www-data \
    "$APP_ROOT/storage" \
    "$APP_ROOT/bootstrap/cache"

# The generated Sofia profiles are read *and written* by FreeSWITCH, which runs
# as a different user in another container. The chown above would otherwise
# leave this tree www-data-only and FreeSWITCH would refuse to start.
#
# It is a bind mount, so unlike the recordings volume the image cannot seed its
# ownership — the host's wins, and this is the only place running as root on
# either side. setgid so anything the application creates underneath stays
# group-writable.
FS_SHARED_GROUP=${FS_SHARED_GROUP:-fsshared}
SIP_PROFILE_DIR="$APP_ROOT/storage/app/freeswitch/sip_profiles"

if getent group "$FS_SHARED_GROUP" >/dev/null 2>&1; then
    mkdir -p "$SIP_PROFILE_DIR/external"
    chgrp -R "$FS_SHARED_GROUP" "$SIP_PROFILE_DIR"
    chmod -R g+w "$SIP_PROFILE_DIR"
    find "$SIP_PROFILE_DIR" -type d -exec chmod g+s {} +

    # The recordings tree and the XML CDR spool are named volumes, and the
    # FreeSWITCH image sets their ownership so that Docker seeds a *fresh* one
    # correctly. A volume that already existed keeps whatever it was created
    # with — root-owned, if it predates that — and the image layer never
    # revisits it. FreeSWITCH then refuses to start on the spool and silently
    # records nothing to the other, which is exactly what an upgrade of an
    # already-running deployment would hit.
    # Directories only, not files. A recordings tree holds one file per call
    # and walking those on every boot would cost more than it fixes, while the
    # directories are what has to be writable for FreeSWITCH to create the next
    # one — and on a date-partitioned tree there are only a few per day.
    # Two separate settings point at the CDR spool and they do not have to
    # agree: FreeSWITCH writes to FREESWITCH_XML_CDR_LOG_DIR, which its own
    # preflight checks, while the spool reader scans
    # telephony.xml_cdr.directory, which comes from FREESWITCH_XML_CDR_DIRECTORY.
    # log_dir falls back to it, so the default has them the same — but setting
    # only the log dir splits them, and then repairing one leaves the other
    # unwritable. Repair both; the repeat is harmless when they match.
    xml_cdr_log_dir="${FREESWITCH_XML_CDR_LOG_DIR:-${FREESWITCH_XML_CDR_DIRECTORY:-/var/log/freeswitch/xml_cdr}}"
    xml_cdr_dir="${FREESWITCH_XML_CDR_DIRECTORY:-/var/log/freeswitch/xml_cdr}"

    for shared_dir in \
        "${RECORDING_PATH:-/var/lib/freeswitch/recordings}" \
        "$xml_cdr_log_dir" \
        "$xml_cdr_dir"; do
        [ -d "$shared_dir" ] || mkdir -p "$shared_dir" 2>/dev/null || continue
        find "$shared_dir" -type d -exec chgrp "$FS_SHARED_GROUP" {} + 2>/dev/null || true
        find "$shared_dir" -type d -exec chmod 2775 {} + 2>/dev/null || true
    done
else
    echo "[entrypoint] WARNING: group $FS_SHARED_GROUP is missing; FreeSWITCH will not be able to write the SIP profile tree."
fi

rm -f \
    "$APP_ROOT/bootstrap/cache/packages.php" \
    "$APP_ROOT/bootstrap/cache/services.php"

if [ ! -f "$ENV_FILE" ] && [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY missing and no local .env mapped. Generating a volatile key..."
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
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Running pending migrations..."
    php artisan migrate --force --no-interaction 2>&1 || echo "[entrypoint] Migration failed — continuing anyway"

    # Permissions are deny-by-default, so the permissions table must be
    # populated before any non-admin user is created — otherwise their role
    # baseline has no rows to attach and they land with no access at all.
    # Matches what install.sh already does for bare-metal deployments.
    echo "[entrypoint] Syncing permissions..."
    php artisan nizam:sync-permissions --no-interaction 2>&1 || echo "[entrypoint] Permission sync failed — continuing anyway"

    # FreeSWITCH refuses to start without internal.xml and external.xml, and
    # until now the only thing that ever wrote them was a model observer. A
    # deployment that never edited a profile left FreeSWITCH restarting forever.
    # The seeder is infrastructure rather than demo data and is idempotent, so
    # it runs unconditionally — unlike db:seed below, which is gated on the
    # admin credentials because it also loads sample data.
    echo "[entrypoint] Seeding SIP profiles..."
    php artisan db:seed --class=Database\\Seeders\\SipProfileSeeder --force --no-interaction 2>&1 || echo "[entrypoint] SIP profile seeding failed — continuing anyway"

    echo "[entrypoint] Compiling SIP profiles..."
    php artisan nizam:compile-sip-profiles --no-interaction 2>&1 || echo "[entrypoint] SIP profile compilation failed — continuing anyway"

    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        echo "[entrypoint] ADMIN_EMAIL + ADMIN_PASSWORD set — running seeders..."
        php artisan db:seed --force --no-interaction 2>&1 || echo "[entrypoint] Seeding failed — continuing anyway"
    fi
fi

exec "$@"
