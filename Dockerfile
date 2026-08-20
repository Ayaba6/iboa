# --- Étape 1 : Build de l'image PHP ---
FROM php:8.2-fpm

# Installer les dépendances système, Nginx et les extensions PHP requises
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    nginx \
    && docker-php-ext-install pdo_mysql pdo_pgsql pgsql exif pcntl bcmath gd zip

# Installer Node.js 20 (pour compiler les assets Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html
COPY . .

# Définir un timeout plus large pour Composer
ENV COMPOSER_PROCESS_TIMEOUT=600

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Installer les dépendances Node et compiler les assets Laravel/Vite
RUN npm install --legacy-peer-deps && npm run build

# Configurer les permissions pour Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copier et rendre exécutable le script de démarrage
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Exposer le port pour Render
EXPOSE 80

# Utiliser le script de démarrage au lieu des commandes directes
CMD ["/usr/local/bin/start.sh"]