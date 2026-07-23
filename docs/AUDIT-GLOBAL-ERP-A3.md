# AUDIT GLOBAL ERP A3 — Registre de certification

> Ouvert le 23/07/2026 (phase 2 — après stabilisation des lots correctifs).
> Règle de formulation : jamais « risque nul » ni « couvert de bout en bout ».
> États : **PROUVÉ** (test automatisé vert ciblant l'exigence) / **INCOMPLET** /
> **NON TESTÉ** (code présent, pas de preuve) / **NON IMPLÉMENTÉ**.
> Priorités : **CRITIQUE / ÉLEVÉ / MOYEN / FAIBLE**.

## 0. Métriques globales (mesurées le 23/07/2026)

| Mesure | Valeur |
|---|---|
| Routes web+api | 1 098 |
| Contrôleurs | 190 |
| Services | 115 |
| Modèles | 212 |
| Migrations | 321 |
| Fichiers de tests | 179 |
| Tests exécutés (suite parallèle) | 775 passed / 2 657 assertions / 0 échec |
| Commandes artisan | 23 |
| Policies | 22 |
| Permissions / rôles | 151 / 21 |

Limite de la preuve : 775 tests couvrent les scénarios écrits et leurs assertions,
rien d'autre. Les parcours UI réels (navigateur), la concurrence multi-processus
réelle et les volumes de production ne sont pas couverts par cette suite.

## 1. Inventaire des modules

| Module | Routes | État global | Priorité restante | Détail |
|---|---|---|---|---|
| Ventes (devis→commande→BL→facture→avoir) | 100 | PROUVÉ sur chaîne O2C (E2E OrderToCashFullChain) + CDC ventes livré + révisions devis | ÉLEVÉ | §3 reproductibilité PDF ; §4 durcissement révisions (contrainte DB, concurrence) |
| Achats (DA→CF→réception→FF→décaissement) | 74 | PROUVÉ sur chaîne E2E + annulation réception + gardes update/delete | MOYEN | avoirs fournisseurs : flux retour prouvé, avoir financier pur NON TESTÉ |
| Stocks (mouvements, lots, transferts, inventaires) | 57 | PROUVÉ transferts/réservations/lots bobines ; inventaires NON TESTÉ e2e | ÉLEVÉ | décision métier à instruire : transfert partiellement reçu ; BL par lot/emplacement (§5) |
| Production (OF, conso, déclarations, chutes) | 132 | PROUVÉ machine à états OF + gate financier + backflush | MOYEN | production avec anomalie (rebut→NC→décision QC→clôture) : parcours complet NON TESTÉ d'un seul tenant |
| Qualité (CQ, NC, certificats) | 36 | livré + testé (QUA-01/05/07/08) | MOYEN | intégration au parcours production-anomalie ci-dessus |
| Comptabilité (écritures, extournes, périodes, immos, budgets) | 87 | PROUVÉ équilibre/extournes/period locks/plan amortissement | **CRITIQUE** | matrice SYSCOHADA événement→comptes→journal→pièce NON PRODUITE ; réconciliation GL/balance/relevés/bilan NON TESTÉE globalement |
| Trésorerie (caisses, encaissements, décaissements, clôtures) | 79 | PROUVÉ annulations + anti-doublon + clôtures | MOYEN | rapprochement bancaire : NON TESTÉ ; frais mobile money/commissions : comptabilisation à vérifier dans la matrice SYSCOHADA |
| RH / Paie | 212 | PROUVÉ IUTS/CNSS/versionnement/runs/pointages/congés | ÉLEVÉ | un profil salarié standard testé ; cas variés (HS, absences, prêts, rappels, régularisations, temps partiel) NON TESTÉS |
| CRM | 24 | audité (session 22/07) | FAIBLE | — |
| Maintenance | (dans production) | audité | FAIBLE | — |
| Analytique / Budgets | 10 | testé (BudgetModuleTest) | FAIBLE | répartitions analytiques sur écritures : NON TESTÉ |
| Intégrations (mobile money, webhooks) | 23 | PROUVÉ délégation paiement central | ÉLEVÉ | intégration fiscale NON IMPLÉMENTÉE — points d'ancrage à concevoir (§6) |
| Référentiels (articles, familles, catégories, unités, tarifs) | ~100 | PROUVÉ phases 1+2 (héritage, gardes, propagation) | FAIBLE | — |
| Paramètres / Admin / Sécurité | 43 | policies présentes (22) | **CRITIQUE** | audit systématique de couverture permission par route NON FAIT ; séparation des tâches NON TESTÉE (§2) |

## 2. Chantier n°1 — Sécurité et permissions (EN COURS, priorité 1)

À produire : matrice route sensible → permission serveur → policy → test.
Contrôles exigés : accès direct URL refusé, requête forgée (IDs d'autrui,
champs cachés), utilisateur désactivé, rôle modifié en session, exports et
actions de masse soumis aux mêmes permissions, journal d'audit des actions
sensibles, séparation des tâches (auto-validation interdite : caissier ≠
comptable, magasinier ≠ valideur d'ajustement, commercial ≤ plafond crédit,
opérateur ≠ clôtureur de son OF).

État : NON TESTÉ systématiquement (des tests 403 ponctuels existent).

## 3. Reproductibilité documentaire — INCOMPLET / ÉLEVÉ

Constat honnête : le figement du document (gardes update) fige les lignes et
totaux, PAS le rendu. Le PDF régénéré dépend de référentiels vivants :
société (nom, logo, adresse, RCCM, IFU), client (nom, adresse), libellés
produits, taux de taxe affichés, modèle Blade, arrondis, devise.

Décision d'architecture retenue à implémenter : **archivage du PDF émis +
empreinte SHA-256** au moment de la validation/envoi (devis envoyé, facture
émise, BL validé, avoir validé, reçu de paiement), stocké en storage privé,
métadonnées (hash, date, user, version du modèle) en base. La régénération
reste possible mais l'exemplaire archivé fait foi.
État : NON IMPLÉMENTÉ.

## 4. Révisions de devis — durcissement requis / ÉLEVÉ

Livré : revise() service (lockForUpdate), unicité applicative de révision
active, gardes conversion, bandeaux UI, mention PDF, expiration batch.
Manque (NON TESTÉ / NON IMPLÉMENTÉ) :
- contrainte d'unicité EN BASE d'une révision active (l'unicité vit dans le
  service uniquement — deux requêtes hors service pourraient la violer) ;
- test de concurrence (2 revise() simultanés) ;
- recopie à compléter : pièces jointes, conditions de paiement, adresses,
  contact, commercial, champs maquette (price_mode, net_prices, incoterm…) —
  duplicate() ne copie qu'un sous-ensemble ;
- re-validation obligatoire d'une révision (elle naît en brouillon : circuit
  déjà imposé de fait — à prouver par test) ;
- écran de comparaison de versions (NON IMPLÉMENTÉ, MOYEN).

## 5. Décisions métier à instruire (ne pas requalifier en « résiduel »)

| Sujet | Question à trancher | Options | État |
|---|---|---|---|
| Transfert partiellement reçu | Les navettes inter-dépôts d'OA METAL peuvent-elles arriver incomplètes (perte, casse, dates multiples) ? | a) implémenter réception partielle par ligne avec écarts ; b) décision documentée « réception = tout » signée gérant | EN ATTENTE DE DÉCISION |
| BL par lot/bobine/emplacement | Une livraison de tôles doit-elle tracer bobine d'origine, lot production, OF, machine, CQ jusqu'au client ? | a) ventilation lot/emplacement sur lignes BL ; b) traçabilité via OF lié (existante) jugée suffisante, documentée | EN ATTENTE DE DÉCISION — l'OF lié donne déjà bobine+machine+CQ, le chaînon BL→lot PF n'est pas formalisé |
| Intégration fiscale (facture normalisée) | Échéance réglementaire au Burkina ? | concevoir dès maintenant les ancrages : statut fiscal distinct, référence externe, idempotence, payload conservé, interdiction de modifier un document transmis | NON IMPLÉMENTÉ — conception à faire |

## 6. Contrôles de `a3:audit-database` — liste exacte actuelle

1. **Orphelins FK** : toutes les relations de la constante RELATIONS (~28 paires enfant→parent).
2. **Doublons de références** : quotes/orders/invoices/delivery_notes/client_payments/production_orders/purchase_orders (number), products.code_article, clients.name, suppliers.name, item_categories.code, product_families.code — insensible casse/espaces.
3. **Données mal formées** : emails invalides (clients/suppliers/users), espaces parasites (code_article, noms tiers).
4. **Cohérence financière** : écritures déséquilibrées (>0,01), factures au solde incohérent (total − allocations confirmées ≠ restant), factures « payée » avec reste dû.
5. **Cohérence stocks** : stocks négatifs non autorisés, réservations > stock, réservations fantômes.

Compléments à ajouter (directive 23/07) — NON IMPLÉMENTÉS :
documents sans lignes ; mouvements sans pièce source ; écritures sans origine ;
allocations > paiement ; bobines consommées au-delà du poids ; PF sans
déclaration de production ; paiements confirmés sans transaction de trésorerie
et inverse ; trous de numérotation ; statuts hors énumération ; dates
incohérentes (échéance < émission…) ; écritures en période fermée ;
bulletins sans run ; totaux bulletin ≠ somme des rubriques.

## 7. Parcours E2E UI (navigateur) — NON TESTÉ / ÉLEVÉ

La suite actuelle teste services et contrôleurs HTTP. Six parcours navigateur
à dérouler (rôles réels, écrans, boutons, messages, PDF, statuts, écritures,
agrégats) :
A. MTO complet (devis→…→balance client) — logique déjà PROUVÉE côté services (E2E code), UI NON TESTÉE
B. MTS ; C. Achat ; D. Retour client ; E. Production avec anomalie ; F. Paie.

## 8. Performance / volumes — PARTIELLEMENT MESURÉ

PerformanceSmokeTest (jeux 100/1000 lignes) : listes principales, pas de N+1
détecté sur les écrans testés. NON MESURÉ : balance âgée, grand livre annuel,
valorisation stock, calcul de paie à 100+ salariés, exports volumineux,
clôtures. À mesurer avant/après.

## 9. Exploitation production — INCOMPLET / ÉLEVÉ

docs/DEPLOIEMENT.md + .env.staging.example existent. NON FAIT : test documenté
de sauvegarde/restauration sur base de test ; vérification queues/scheduler en
service (supervisord/tâche planifiée Windows) ; rotation des logs ; en-têtes
sécurité (CSP, HSTS) ; failed_jobs ; limitation de tentatives sur login.

## 10. Ordre de traitement (directive)

1. ~~Registre initial~~ (ce document)
2. Sécurité et permissions (§2) ← PROCHAIN
3. Intégrité comptable — matrice SYSCOHADA + réconciliation
4. Intégrité stock/production (+ décisions §5 instruites)
5. RH/paie — profils salariés variés
6. E2E UI (§7)
7. Performance (§8)
8. Exploitation (§9)
9. Ergonomie et modules incomplets

Chaque chantier met à jour ce registre (état + preuve + limites de la preuve).
