#!/bin/sh
# Ensure storage directories are writable by the PHP-FPM worker (www-data)
# before handing off to php-fpm. This is needed when the host directory is
# bind-mounted and owned by a different UID than www-data (uid 82 on Alpine).
set -e

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

exec "$@"
