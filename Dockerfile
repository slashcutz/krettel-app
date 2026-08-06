# syntax=docker/dockerfile:1

# ---------------------------------------------------------------
# Stage 1: Build frontend assets (Vite)
# ---------------------------------------------------------------
FROM node:22-alpine AS node

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ---------------------------------------------------------------
# Stage 2: Runtime — PHP-FPM + Nginx + FFmpeg + queue worker
# ---------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        nginx \
        ffmpeg \
        supervisor \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libsqlite3-dev \
        libpq-dev \
        curl \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        pdo_pgsql \
        pgsql \
        bcmath \
        mbstring \
        exif \
        opcache \
        pcntl \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=node /build/public/build public/build

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

# Nginx (template rendered to the real PORT at boot) + supervisor + entrypoint
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/php.ini /usr/local/etc/php/conf.d/krettel.ini
COPY docker/nginx.conf /etc/nginx/conf.d/krettel.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
