#!/bin/bash

echo "Tentative de connexion à la base de données..."

# Boucle propre avec affichage de l'erreur exacte
until php -r "
try {
    \$host = getenv('DB_HOST');
    \$port = getenv('DB_PORT');
    \$db   = getenv('DB_DATABASE');
    \$user = getenv('DB_USERNAME');
    \$pass = getenv('DB_PASSWORD');

    \$dsn = \"pgsql:host=\$host;port=\$port;dbname=\$db\";
    \$pdo = new PDO(\$dsn, \$user, \$pass);
    echo 'Connexion PDO réussie !';
    exit(0);
} catch (Exception \$e) {
    echo 'Erreur : ' . \$e->getMessage();
    exit(1);
}
"; do
  echo " - En attente de la base de données... nouvel essai dans 3 secondes."
  sleep 3
done

echo "Base de données connectée avec succès !"
php artisan migrate --force

echo "Configuration et routage..."
php artisan config:cache
php artisan route:cache

echo "Lancement du serveur..."
php artisan serve --host=0.0.0.0 --port=$PORT