FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    libxml2-dev \
    oniguruma-dev \
    linux-headers \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    ffmpeg

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    xml \
    bcmath \
    pcntl \
    sockets \
    opcache \
    exif \
    gd

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer (pin exact image digest for reproducible builds)
COPY --from=composer:latest@sha256:743aebe48ca67097c36819040633ea77e44a561eca135e4fc84c002e63a1ba07 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── Layer-cache optimisation ────────────────────────────────────────────────
# Copy only the manifest files first so that `composer install` is cached by
# Docker and is NOT re-run on every source-code change — only when the
# dependency list itself changes.  vendor/ is excluded from the build context
# via .dockerignore, so the COPY . . step below will NOT overwrite it.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

# Copy full application source (vendor/ is in .dockerignore — safe to overlay)
COPY . .

# Generate optimised classmap and run post-install scripts (package:discover)
RUN composer dump-autoload --optimize --no-interaction

# Build frontend assets step removed as frontend is completely decoupled.

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and wire up entrypoint (fixes bind-mount permissions then execs php-fpm)
COPY docker/app/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# COMMENTED OUT - Uncomment to enable FreeSWITCH ESL listener daemon
# COPY docker/app/supervisord-freeswitch.conf /etc/supervisor/conf.d/freeswitch-listener.conf

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
