# syntax=docker/dockerfile:1
#
# Generative Topical — production image for Render free tier.
# Multi-stage: composer deps → vite build → nginx + php-fpm runtime.
# Migrations run at container startup (start.sh), not at build time.

# ─────────────────────────────────────────────────────────────────
# Stage 1: PHP dependencies via Composer
# ─────────────────────────────────────────────────────────────────
FROM composer:2 AS composer-deps

WORKDIR /app

# Cache composer install on lockfile changes only
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress

# Now bring in the rest of the source and build the optimized autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev


# ─────────────────────────────────────────────────────────────────
# Stage 2: Frontend (Vite + Tailwind + Livewire/Alpine assets)
# ─────────────────────────────────────────────────────────────────
FROM node:20-alpine AS node-build

WORKDIR /app

# Cache npm install on lockfile changes only
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

# Source files Vite needs
COPY vite.config.js postcss.config.js* tailwind.config.js* ./
COPY resources ./resources
COPY public ./public

# Vite scans @source paths inside vendor/ — copy compiled vendor from previous stage
COPY --from=composer-deps /app/vendor ./vendor

RUN npm run build


# ─────────────────────────────────────────────────────────────────
# Stage 3: Runtime — nginx + PHP-FPM in one container
# ─────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# System packages: nginx, supervisor, gettext (envsubst), bash, plus libs PHP exts need
RUN apk add --no-cache \
        bash \
        gettext \
        nginx \
        supervisor \
        ca-certificates \
        tzdata \
        oniguruma \
        libzip \
        icu-libs \
        libpng \
        libjpeg-turbo \
        freetype \
        libwebp \
    && update-ca-certificates \
    && mkdir -p /run/nginx /var/log/supervisor

# PHP extensions for Laravel + Livewire
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        bcmath \
        exif \
        opcache \
        pcntl

# PHP runtime tuning (production)
RUN { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=20M'; \
    echo 'max_execution_time=60'; \
    echo 'expose_php=Off'; \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.interned_strings_buffer=16'; \
} > /usr/local/etc/php/conf.d/zz-app.ini

# nginx + supervisor configs
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf

# App
WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=composer-deps /app /var/www/html
COPY --chown=www-data:www-data --from=node-build /app/public/build /var/www/html/public/build

# Storage + cache directories must be writable by www-data
RUN mkdir -p \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Entry script (runs migrations, builds caches, starts supervisor)
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Render injects $PORT (default 10000). nginx is configured via envsubst.
EXPOSE 10000

CMD ["/start.sh"]
