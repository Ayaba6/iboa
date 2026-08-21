#!/bin/bash

echo "Création de la base SQLite si besoin..."
mkdir -p database
touch database/database.sqlite

echo "Nettoyage des caches..."
php artisan config:clear
php artisan cache:clear

echo "Migrations..."
php artisan migrate --force

echo "Lancement du serveur..."
php artisan serve --host=0.0.0.0 --port=$PORT