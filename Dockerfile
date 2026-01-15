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
RUN echo 'SetEnvIf X-Forwarded-Proto "https" HTTPS=on' >> /etc/apache2/apache2.conf

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
CMD ["sh", "-lc", "\
echo \"BOOT: Docker CMD started\"; \
echo \"BOOT: RUN_SEED=$RUN_SEED\"; \
php artisan optimize:clear; \
echo \"BOOT: waiting for Postgres\"; \
until php -r 'try{$pdo=new PDO(\"pgsql:host=\".getenv(\"DB_HOST\").\";port=\".getenv(\"DB_PORT\").\";dbname=\".getenv(\"DB_DATABASE\"), getenv(\"DB_USERNAME\"), getenv(\"DB_PASSWORD\"));}catch(Exception $e){exit(1);}'; \
do echo 'Waiting for Postgres...'; sleep 2; done; \
echo \"BOOT: running migrate\"; \
php artisan migrate --force; \
echo \"BOOT: migrate finished with exit=$?\"; \
if [ \"$RUN_SEED\" = \"true\" ]; then \
  echo \"BOOT: running seed\"; \
  php artisan db:seed --force; \
  echo \"BOOT: seed finished with exit=$?\"; \
fi; \
echo \"BOOT: starting Apache\"; \
apache2-foreground \
"]



