FROM php:8.2-fpm

# Installer les dépendances système et extensions PHP nécessaires
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
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Installer Node.js 18 (plus stable avec les plugins Vite actuels)
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Nettoyer
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Installer les dépendances Node et compiler les assets
RUN npm install && npm run build

# Configurer les permissions pour Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Exposer le port 80 pour Render
EXPOSE 80

# Script de démarrage
CMD php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT