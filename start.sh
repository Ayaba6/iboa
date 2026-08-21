#!/bin/bash

echo "Préparation de la base de données SQLite..."

# Création du dossier et du fichier SQLite si besoin
mkdir -p database
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    echo "Fichier database.sqlite créé avec succès."
fi

echo "Exécution des migrations..."
php artisan migrate --force

echo "Configuration et routage..."
php artisan config:cache
php artisan route:cache

echo "Lancement du serveur..."
php artisan serve --host=0.0.0.0 --port=$PORT