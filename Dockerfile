FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    git curl unzip \
    libpng-dev libonig-dev libxml2-dev \
    libpq-dev \
    libzip-dev zip \
    nodejs npm \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) \
    pdo pdo_pgsql pgsql \
    mbstring exif pcntl bcmath \
    gd zip

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

RUN composer install --no-dev --optimize-autoloader

RUN npm ci && npm run build

EXPOSE 80
CMD ["apache2-foreground"]
