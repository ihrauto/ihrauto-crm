# syntax=docker/dockerfile:1.6
#
# D-05: multi-stage build. The previous single-stage image pulled in
# Node.js, npm dev dependencies, and node_modules into the production
# image (~1GB). The Node build now lives in a disposable stage and only
# the compiled public/build artifacts land in the final image.

# ------------------------------------------------------------------
# Stage 1 — frontend assets (Vite/Tailwind)
# ------------------------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app

# Install exact deps first for a cacheable layer.
COPY package.json package-lock.json ./
RUN npm ci

# Copy only what Vite needs to build.
COPY vite.config.js tailwind.config.js postcss.config.js eslint.config.js ./
COPY resources/ resources/
# Blade views are scanned by Tailwind's content globs.
COPY resources/views/ resources/views/

RUN npm run build

# ------------------------------------------------------------------
# Stage 2 — PHP vendor dir (composer install --no-dev)
# ------------------------------------------------------------------
FROM composer:2 AS composer_deps
WORKDIR /app

# Install system libs composer might need for the install step itself.
# (We don't need gd/zip/pgsql here — those are runtime, installed in the
# final stage.)
COPY composer.json composer.lock ./
COPY database/ database/
COPY app/helpers.php app/helpers.php

RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

# ------------------------------------------------------------------
# Stage 3 — runtime (Apache + PHP)
# ------------------------------------------------------------------
# S-17: pin to a specific minor/patch tag for reproducible builds.
# Update deliberately (quarterly + on security advisories); do not rely
# on "php:8.4-apache" drifting us forward silently.
FROM php:8.4.2-apache AS runtime

# Runtime system deps only (no Node, no build toolchain).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip \
        libpng16-16 libjpeg62-turbo libfreetype6 \
        libonig5 libxml2 \
        libpq5 \
        libzip4 zip \
        postgresql-client \
        supervisor \
    && rm -rf /var/lib/apt/lists/*

# Build-only deps for the PHP extension compile step.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libpq-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql \
        mbstring exif pcntl bcmath \
        gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libpq-dev libzip-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# Apache tuning.
RUN a2enmod rewrite \
    && echo 'SetEnvIf X-Forwarded-Proto "https" HTTPS=on' >> /etc/apache2/apache2.conf

# D-12: opinionated PHP tuning (OPcache + JIT + memory / session hardening).
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ihrauto.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# App source.
COPY . /var/www/html

# Pull in pre-built artifacts from earlier stages.
COPY --from=composer_deps /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public/build /var/www/html/public/build

# Composer is used at runtime by a few artisan commands; include the binary
# but no dev deps.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Build-time validation: migrations parse against SQLite before we ship.
RUN touch database/database.sqlite \
    && DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite \
       php artisan migrate --force --no-interaction \
    && rm -f database/database.sqlite \
    && echo "BUILD: migration validation passed"

# Supervisord (Apache + queue workers + scheduler).
# Scalability B-6: default to 3 queue workers per container. Override via
# QUEUE_WORKERS env at orchestrator level. Supervisord reads this env var
# to decide how many worker processes to spawn.
ENV QUEUE_WORKERS=3
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Laravel writable dirs + permissions.
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Minimal in-container health check so orchestrators can detect a wedged
# Apache even when the platform health path returns success intermittently.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/up || exit 1

EXPOSE 80

CMD ["sh", "-lc", "\
echo \"BOOT: Docker CMD started\"; \
php artisan optimize:clear; \
echo \"BOOT: waiting for Postgres\"; \
until php -r 'try{$pdo=new PDO(\"pgsql:host=\".getenv(\"DB_HOST\").\";port=\".getenv(\"DB_PORT\").\";dbname=\".getenv(\"DB_DATABASE\"), getenv(\"DB_USERNAME\"), getenv(\"DB_PASSWORD\"));}catch(Exception $e){exit(1);}'; \
do echo 'Waiting for Postgres...'; sleep 2; done; \
echo \"BOOT: running migrate\"; \
php artisan migrate --force; \
echo \"BOOT: migrate finished with exit=$?\"; \
echo \"BOOT: bootstrapping platform roles and super-admin\"; \
php artisan ops:bootstrap-super-admin --no-interaction; \
echo \"BOOT: caching config, routes, views\"; \
php artisan storage:link || true; \
php artisan config:cache; \
php artisan route:cache; \
php artisan view:cache; \
echo \"BOOT: starting supervisord (Apache + Queue Worker + Scheduler)\"; \
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf \
"]
