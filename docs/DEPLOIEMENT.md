# A3 ERP — Plan de déploiement, staging et rollback

## 1. Pré-requis serveur
- PHP 8.2+ (extensions : pdo_mysql, gd, zip, intl, mbstring)
- MySQL 8.x, Node 22+/npm 11+ (build uniquement), Composer 2
- Apache/Nginx pointé sur `public/`

## 2. Recette staging (avant toute production)
1. Cloner le dépôt, `composer install --no-dev`, `npm ci && npm run build`.
2. Copier `.env` (APP_ENV=staging, APP_DEBUG=false, base dédiée).
3. `php artisan migrate --force` (migrations additives uniquement — jamais fresh/wipe).
4. Restaurer un dump anonymisé ou partir du référentiel seedé.
5. Dérouler la recette manuelle :
   - Scénario A : devis → commande MTO comptant → paiement caisse → OF → production → CQ → BL → facture → encaissement → écritures.
   - Scénario B : commande crédit non réglée → approbation gérant → OF → production → livraison.
   - Scénario C : article MTS sous seuil → OF sans client → stock → vente sur stock.
   - Scénario D : marchandise achat → réception → stock → vente (vérifier : AUCUN OF).
   - Scénario E : DA → validation → commande fournisseur → réception → facture → paiement.
   - Scénario F : salarié → pointage → calcul paie → bulletin → validation → écriture.
6. Vérifier les PDF (facture, devis, BL, OF, bulletin) : identité société paramétrée, FCFA, accents.
7. `php artisan erp:pre-production-clean` (dry-run) → lire le tableau → `--execute --drop-backups` pour purger les essais de recette.

## 3. Mise en production
1. **Sauvegarde** : `mysqldump` complet + copie `.env` + tag git de la version déployée.
2. `php artisan down` (maintenance).
3. `git pull` / déploiement de l'artefact ; `composer install --no-dev --optimize-autoloader`.
4. `php artisan migrate --force` ; `php artisan config:cache route:cache view:cache`.
5. Saisie/vérification des paramètres métier : identité société, séquences, prix de vente
   catalogue (PFTBC/PFFAB/MTOND/chutes/avaries), seuils stock par catégorie, taux d'acompte.
6. Stock initial : réceptions bobines MP réelles (lots), inventaire d'ouverture PF.
7. `php artisan up`.

## 4. Rollback
- **Code** : `git checkout <tag précédent>` + `composer install` + caches. Les migrations
  étant additives, l'ancien code fonctionne sur le nouveau schéma.
- **Données** : restaurer le dump de l'étape 3.1 (`mysql < dump.sql`). Aucune migration
  de ce projet ne supprime de colonne existante.
- **Corrections de données** : chaque correction passée a sa trace (mouvements
  opening_reconciliation, journaux d'audit) — réversibles unitairement.

## 5. Surveillance post-déploiement
- `storage/logs/laravel-*.log` : 0 ERROR attendu en fonctionnement normal.
- Écritures comptables : équilibre débit/crédit (déjà garanti par les services).
- Commande d'audit périodique : `php artisan stock:audit-coil-lots` (dry-run).

---

## Exploitation (Phase 2.7) — vérifié le 24/07/2026

### Audit de préparation à la production
Avant toute bascule : `php artisan a3:audit-production` (exit 1 si bloquant).
Contrôle : APP_DEBUG off, APP_KEY, drivers queue/cache/session non-sync en
prod, maker-checker actif, HTTPS, tables d'infra, chaîne du journal d'audit.

### Sauvegarde / restauration — TESTÉE
Procédure `scripts/backup-database.sh` (mysqldump --single-transaction
--routines --triggers, gzip, empreinte SHA-256, rotation 30 j).

**Test de restauration réalisé** (base jetable `iboa_backup_test`, MySQL
8.4.3, 24/07/2026) : 3 lignes témoin → dump → suppression de 2 lignes
(sinistre simulé) → drop + restauration → **3 lignes identiques à
l'avant-sinistre** (vérification par GROUP_CONCAT). Restauration fidèle
confirmée.

Cron recommandé : `0 2 * * * /var/www/iboa/scripts/backup-database.sh /var/backups/iboa`
Restauration : `gunzip -c <fichier>.sql.gz | mysql -u <user> -p <db>`

### Reste à durcir avant GO (Phase 2.7 non close)
- Superviser queue:work (supervisord) + scheduler (cron `* * * * * artisan schedule:run`).
- Rotation des logs applicatifs (logrotate ou canal daily déjà en place pour security.log).
- Test de charge concurrent (non réalisé).
