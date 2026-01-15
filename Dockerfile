FROM php:8.4-apache

# System deps (incl. GD deps + ZIP) + Node/NPM
RUN apt-get update && apt-get install -y \
    git curl unzip \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    libpq-dev \
    libzip-dev zip \
    nodejs npm \
  && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
    pdo pdo_pgsql pgsql \
    mbstring exif pcntl bcmath \
    gd zip

# Apache
RUN a2enmod rewrite

# Point Apache to /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader

# Build frontend assets (Vite)
RUN npm ci && npm run build

# Laravel writable dirs + permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Render Free: run migrations on container start (no Pre-Deploy command available)
CMD ["sh", "-c", "php artisan config:clear && php artisan migrate --force && apache2-foreground"]
