FROM php:8.4-apache

# System deps + Node + required libs
RUN apt-get update && apt-get install -y \
    git curl unzip \
    libpng-dev libonig-dev libxml2-dev \
    libpq-dev libzip-dev zip \
    nodejs npm \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Apache rewrite
RUN a2enmod rewrite

# Composer (pin version, avoid "latest")
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

# IMPORTANT: show PHP version in Render build logs
RUN php -v

# Install PHP deps using the container PHP version
RUN php /usr/local/bin/composer install --no-dev --optimize-autoloader

# Laravel caches (do NOT generate APP_KEY here — set APP_KEY in Render env vars)
RUN php artisan config:clear \
    && php artisan cache:clear \
    && php artisan config:cache

# Frontend build
RUN npm ci && npm run build

# Apache document root -> /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Laravel permissions
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
