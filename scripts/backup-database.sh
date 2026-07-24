#!/usr/bin/env bash
#
# [Phase 2.7] Sauvegarde de la base A3 ERP — testée (restauration fidèle
# vérifiée le 24/07/2026 sur base jetable). À planifier en cron quotidien.
#
# Usage : ./scripts/backup-database.sh [répertoire_cible]
# Cron  : 0 2 * * *  /var/www/iboa/scripts/backup-database.sh /var/backups/iboa
#
set -euo pipefail

# Charge la config depuis .env
ENV_FILE="$(dirname "$0")/../.env"
DB_DATABASE=$(grep -E '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2- | tr -d '"')
DB_USERNAME=$(grep -E '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2- | tr -d '"')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2- | tr -d '"')
DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" | cut -d= -f2- | tr -d '"')

TARGET_DIR="${1:-./storage/backups}"
mkdir -p "$TARGET_DIR"
STAMP=$(date +%Y%m%d_%H%M%S)
FILE="$TARGET_DIR/${DB_DATABASE}_${STAMP}.sql.gz"

# --single-transaction : cohérence sans verrouiller (InnoDB)
# --routines --triggers : procédures et déclencheurs inclus
mysqldump -h "${DB_HOST:-127.0.0.1}" -u "$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
  --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  "$DB_DATABASE" | gzip > "$FILE"

# Empreinte pour vérifier l'intégrité de l'archive
sha256sum "$FILE" > "$FILE.sha256"

# Rotation : conserver 30 jours
find "$TARGET_DIR" -name "${DB_DATABASE}_*.sql.gz" -mtime +30 -delete 2>/dev/null || true

echo "Sauvegarde : $FILE ($(du -h "$FILE" | cut -f1))"
echo "Restauration : gunzip -c $FILE | mysql -u <user> -p $DB_DATABASE"
