FROM php:8.4-fpm-alpine

# Host UID/GID — keep PHP-created files (logs, compiled views, cache) owned by
# the developer who owns the bind-mounted sources.
#
# Named APP_UID/APP_GID rather than UID/GID: those are shell specials, and
# Docker injects build args as environment variables, which makes the intent
# ambiguous to read even where it works.
ARG APP_UID=1000
ARG APP_GID=1000

# ── System dependencies ──
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    supervisor

# ── PHP extensions ──
# WebP matters for more than generating thumbnails: the upload validation uses
# the `dimensions` rule, which has to decode the file to read its size.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        gd \
        bcmath

# ── Redis extension (phpredis) ──
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# ── Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── User matching the host UID/GID ──
RUN (addgroup -g ${APP_GID} app 2>/dev/null || true) \
    && (adduser -u ${APP_UID} -G app -D -s /bin/sh app 2>/dev/null || true) \
    && id app

# ── php-fpm workers run as the host user ──
# The master process stays root so it can bind port 9000 and drop privileges.
#
# The guard checks the *name* on purpose: busybox `id` does not resolve a
# numeric uid, so `id -u ${APP_UID}` fails even when the user exists — which
# silently left the pool on www-data and made every PHP-written file
# root/www-data owned on the host.
RUN if id -u app >/dev/null 2>&1; then \
        sed -i "s/^user = www-data/user = ${APP_UID}/; s/^group = www-data/group = ${APP_GID}/" \
            /usr/local/etc/php-fpm.d/www.conf; \
    else \
        echo "WARNING: user 'app' missing, keeping www-data"; \
    fi \
    && grep -E '^(user|group) =' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

# ── Application code ──
COPY ./laravel /var/www

# ── Writable paths ──
RUN mkdir -p /var/www/storage/framework/views \
             /var/www/storage/framework/cache \
             /var/www/storage/framework/sessions \
             /var/www/storage/logs \
    && chown -R ${APP_UID}:${APP_GID} /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
