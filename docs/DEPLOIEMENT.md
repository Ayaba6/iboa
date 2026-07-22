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
