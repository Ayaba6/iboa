FROM php:8.2-fpm

# Installer les dépendances système, Nginx et extensions PHP
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    nginx \
    && docker-php-ext-install pdo_mysql exif pcntl bcmath gd zip

# Installer Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Augmener le timeout de Composer et installer les dépendances PHP proprement
RUN composer config -g http-timeout 300 \
    && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Installer les dépendances Node et compiler les assets Laravel
RUN npm install --legacy-peer-deps && npm run build

# Permissions Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT