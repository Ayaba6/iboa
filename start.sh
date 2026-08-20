#!/bin/bash

# Attendre que la base de données soit prête (optionnel mais recommandé)
echo "Attente de la base de données..."
# Ceci est une boucle simple qui vérifie la connexion avant de lancer les migrations
until php -r "try { new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); exit(0); } catch (Exception \$e) { exit(1); }"; do
  echo "Base de données non disponible, réessai dans 2 secondes..."
  sleep 2
done

echo "Base de données disponible, lancement des migrations..."
php artisan migrate --force

echo "Configuration et routage..."
php artisan config:cache
php artisan route:cache

echo "Lancement du serveur..."
php artisan serve --host=0.0.0.0 --port=$PORT