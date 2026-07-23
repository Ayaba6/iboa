# A3 ERP — Recette staging (checklist manuelle)

Version candidate : tag `staging-2026-07-22` (746 tests verts, audits propres).
Données prêtes : client **CLIENT TEST GUIDE SARL** (crédit, plafond 10 M),
fournisseur **Fournisseur Test SARL**, 27 articles paramétrés, référentiel complet,
0 transactionnel, compteurs à zéro.

Avant de démarrer : saisir les **prix de vente** des articles testés (fiches articles)
— repères : tôle ~5 000 F/ML, fer ~5 000 F/barre.

## Pré-vol (automatisé — déjà vérifié le 22/07)
- [x] `php artisan migrate:status` : 0 en attente
- [x] `php artisan a3:audit-database` : AUDIT PROPRE
- [x] `php artisan a3:clean-database` : rien à nettoyer
- [x] Caches production (config/route/view) : compatibles, page 200
- [x] Build Vite : OK — `composer audit` / `npm audit` : 0

## Scénario E — Achats (à faire EN PREMIER : crée le stock matière)
- [ ] Demande d'achat (bobines MP) → soumettre → approuver
- [ ] Convertir en commande fournisseur (Fournisseur Test SARL) → confirmer
- [ ] Réception : lots/bobines créés, stock MP crédité au Dépôt Matières Premières
- [ ] Facture fournisseur → écriture équilibrée → paiement → facture soldée « payée »

## Scénario A — MTO comptant (tôle bac)
- [ ] Passer CLIENT TEST GUIDE SARL en mode **comptant** (sa fiche)
- [ ] Devis tôle bac → convertir en commande → confirmer (OF auto créé)
- [ ] Tableau « Éligibles MTO » : commande ABSENTE (0 encaissé)
- [ ] Encaisser 100 % (caisse/BP) → commande présente aux éligibles
- [ ] OF : allouer matière → valider ×2 → lancer (gate passe) → consommer bobine
- [ ] Déclarer production → CQ conforme (par un AUTRE utilisateur que le déclarant)
- [ ] Clôturer : PF réservé pour la commande
- [ ] BL (gate chargement BP) → facture → encaissement du solde → écritures
- [ ] PDF facture : identité OA METAL, FCFA, QR

## Scénario B — MTO approuvé gérant
- [ ] Client en mode **crédit**, nouvelle commande tôle, AUCUN règlement
- [ ] Lancement OF refusé (gate) → fiche commande : « Approuver production » (motif ≥ 5 car.)
- [ ] Notification reçue par chef production/atelier
- [ ] Lancement OF passe → production → livraison

## Scénario C — MTS (fer à béton)
- [ ] Fixer stock min/max sur un article fer (fiche article)
- [ ] « Planification MTS » : article en rupture, besoin net calculé
- [ ] Créer OF MTS (prérempli, sans client) → produire → CQ → stock GÉNÉRAL
- [ ] Vendre sur stock : commande confirmée SANS nouvel OF, stock réservé

## Scénario D — Marchandise (tôle ondulée)
- [ ] Achat → réception → stock
- [ ] Vente → BL : **vérifier qu'AUCUN OF n'existe** pour cette commande

## Scénario F — Paie
- [ ] Pointage du mois (présences, heures POSITIVES)
- [ ] Congé : demande → approbation (solde décrémenté) ; demande chevauchante REFUSÉE
- [ ] Run de paie : calculer → valider → écriture équilibrée → bulletin PDF

## Scénario G — Annulations et révisions (nouveautés 23/07)
Chaque annulation doit inverser TOUS les effets (voir docs/AUDIT-ANNULATIONS.md — matrice PROUVÉ).
- [ ] Encaissement client annulé (motif obligatoire) : facture redevient impayée, caisse débitée en retour, écriture extournée datée du jour
- [ ] Décaissement fournisseur annulé : miroir du précédent
- [ ] Réception annulée : stock ressorti, quantités reçues reprises sur la CF, bobines/lots supprimés — refus si FF validée ou bobine entamée
- [ ] Avoir appliqué annulé : facture restaurée, stock ressorti, extourne — et l'avoir APPLIQUÉ figure toujours au relevé client
- [ ] Règlement annulé absent des relevés/balances (client ET fournisseur)
- [ ] OF avec consommation vivante : annulation refusée avec guidage (clôturer avec écart OU extourner)
- [ ] Transfert en transit annulé : stock revenu au dépôt source ; transfert reçu inannulable
- [ ] Suppression physique refusée : facture annulée, CF confirmée, CF réceptionnée (modification aussi)
- [ ] Devis envoyé → « Réviser » : nouvelle version liée (bandeau bleu), original non convertible (bandeau ambre), PDF de la révision porte « Révision n°2 — remplace DEV-... »
- [ ] Devis expiré : conversion refusée ; après le batch de 05:45 (ou `php artisan automation:daily`), statut « Expiré » sur devis envoyés ET validés

## Transverses
- [ ] Mode sombre : bandeaux X3 sombres (familles, catégories, production)
- [ ] Mobile 375 px : tables défilent dans leur carte, pas la page
- [ ] Un utilisateur SANS permission : 403 sur module interdit, boutons absents
- [ ] `php artisan a3:audit-database` en fin de recette : AUDIT PROPRE attendu

## Clôture
- [ ] Anomalies relevées → corrigées → re-testées
- [ ] `php artisan erp:pre-production-clean` (dry-run puis --execute) pour purger la recette
- [ ] Décision : PRÊT POUR PRODUCTION (voir docs/DEPLOIEMENT.md §3)
