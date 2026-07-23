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

### Constats du 23/07 (cartographie middleware, 1 098 routes)

| Constat | Verdict |
|---|---|
| Routes mutantes (POST/PUT/DELETE) sans authentification | **2** : webhooks moov-money / orange-money — légitime, HMAC vérifié sur corps brut (contrôleur inspecté) |
| Routes mutantes auth-seule (sans middleware permission) | **19**, toutes inspectées : profil/password/notifications (scopées utilisateur), redirects, edit-lock (release scopé, force-release = gate admin), attachments (policy `authorize`), company switch (appartenance ou super_admin), api/auth/token (login par credentials) — **saines** |
| Toutes les autres routes mutantes | middleware `PermissionMiddleware` présent |

### Granularité par action (audit du 23/07, ~70 routes critiques analysées)

Sains : production (permissions dédiées par étape validate_chef/responsable/
declaration/cancel), circuit interne ventes (sales.validate/cancel/reject),
factures (invoices.validate pour validate ET cancel), DA
(purchase_requests.approve), clôtures caisse (treasury.write), périodes RH
(rh.settings), exercices (settings.manage), inventaires (inventory.validate).
Rate limiting : login web (throttle 5/min + email+IP), api/auth/token
(throttle:api_auth), API authentifiée (60/min/user) — PROUVÉ par lecture,
gardes présentes.

**3 trous corrigés (commit associé)** :
1. `encaissements/{id}/cancel` héritait de treasury.view|payments.view → un
   lecteur pouvait déclencher extourne + débit caisse. Middleware
   `permission:treasury.write` ajouté (test 403 + test passant avec write).
2. `decaissements/{id}/cancel|approve|reject` : idem → treasury.write pour
   cancel, treasury.validate pour approve/reject (le service gardait déjà
   l'approbation par seuil ; la route est désormais alignée).
3. `PoApprovalService::approve` sans règle de seuil configurée = approbation
   libre pour tout porteur de purchase_orders.view → garde de base ajoutée
   (purchase_requests.approve ou super_admin exigé quand aucune règle).

Notables non corrigés (choix à confirmer, MOYEN) : `credit_notes.create`
autorise la validation d'avoir (créer ≠ valider) ; `receptions.create`
autorise validate/cancel de réception ; `purchase_orders.create` autorise
confirm. Cohérents avec un profil « acheteur/magasinier » unique, mais une
séparation créateur/valideur exigerait des permissions dédiées.

### Session, journal d'audit (23/07, 3e incrément)

- **Utilisateur désactivé en session** : PROUVÉ — `is_active` n'était contrôlé
  qu'au login ; middleware `EnsureUserIsActive` (global web) coupe la session
  au premier aller-retour (test : profil accessible → désactivation →
  redirection login + session invalidée).
- **Journal d'audit** : table `audit_logs` + `AuditService` existaient mais
  quasi débranchés. Branchés sur les 4 annulations financières
  (encaissement, décaissement, facture, avoir) : action, modèle, motif,
  montant, user, IP, URL. PROUVÉ sur encaissement.annulation.
- Rate limiting : PROUVÉ par lecture (login 5/min + email+IP,
  api/auth/token throttle:api_auth, API 60/min/user).

### Matrice des permissions par action (4e incrément, 23/07)

Migration `2026_07_23_120000_add_granular_action_permissions` : 10 permissions
dédiées créées ; compatibilité assurée (chaque nouvelle permission est donnée
aux rôles porteurs de la permission source — personne ne perd d'accès au
déploiement ; la séparation stricte se fait ensuite par retrait, écran Rôles).

| Action | Permission avant | Permission cible (appliquée) | Séparation possible | Seuil | Journal |
|---|---|---|---|---|---|
| Annuler encaissement | treasury.view (hérité) → treasury.write (1er fix) | **treasury.cancel** | write ≠ cancel PROUVÉ (test 403) | — | ✅ encaissement.annulation |
| Annuler décaissement | treasury.view (hérité) | **treasury.cancel** | idem | — | ✅ decaissement.annulation |
| Approuver/rejeter décaissement | treasury.view (hérité) | **treasury.validate** (route) + seuil TreasuryApprovalService | maker ≠ checker : NON IMPLÉMENTÉ | ✅ par montant | à brancher |
| Valider avoir (stock+GL) | credit_notes.create | **credit_notes.validate** | create ≠ validate | — | à brancher |
| Annuler avoir | sales.cancel | credit_notes.cancel (créée, route déjà sur sales.cancel — équivalent) | ✅ | — | ✅ avoir.annulation |
| Valider réception (stock) | receptions.create | **receptions.validate** | create ≠ validate PROUVÉ (test 403) | — | à brancher |
| Annuler réception | receptions.create | **receptions.cancel** | ✅ | — | à brancher |
| Confirmer CF | purchase_orders.create | **purchase_orders.confirm** | create ≠ confirm | — | à brancher |
| Approuver CF (seuils) | purchase_orders.view | purchase_orders.view + garde service (règle de seuil OU purchase_requests.approve) | maker ≠ checker : NON IMPLÉMENTÉ | ✅ par règle | à brancher |
| Valider clôture caisse | treasury.write | **cash_closures.validate** | ✅ | — | à brancher |
| Rouvrir clôture caisse | (pas de route) | cash_closures.reopen (créée, réservée) | ✅ | — | — |
| Valider facture / annuler | invoices.validate | invoices.validate (déjà dédiée) | — | — | ✅ facture.annulation |
| Valider écriture | accounting.validate | déjà dédiée | auteur ≠ posteur : NON IMPLÉMENTÉ | — | à brancher |
| Payer virement paie | rh.payroll.validate | déjà dédiée | préparateur ≠ valideur : NON IMPLÉMENTÉ | — | à brancher |

### Requêtes forgées / cohérence inter-entités (4e incrément)

- Allocation paiement→facture d'un AUTRE client : REFUSÉE sur les deux chemins
  (create + addAllocation, contrôle client_id + lockForUpdate) — vérifié code.
- Allocation décaissement→FF d'un autre fournisseur : REFUSÉE (contrôle
  supplier_id l.128) — vérifié code.
- Avoir←facture, BL←commande, FF←CF : identifiants du tiers TOUJOURS hérités
  du document parent, jamais du payload — vérifié code.
- Mass assignment : aucun Form Request critique n'accepte status/paid_amount/
  approved_by/created_by ; aucun `request->all()` dans les contrôleurs
  financiers — scan programmatique.

### Maker-checker + désactivation multi-canaux (5e incrément, 23/07)

- **Maker-checker configurable** : config/security.php
  (`SECURITY_MAKER_CHECKER`, désactivé par défaut, À ACTIVER EN PRODUCTION,
  exemptable par action). Branché au niveau service sur 5 points :
  approbation décaissement, approbation CF, validation avoir, validation
  écriture comptable, validation run de paie — l'auteur (`created_by`) ne
  valide pas sa propre opération, super_admin exempté. PROUVÉ : auto-
  approbation refusée quand actif + un autre utilisateur habilité passe ;
  inactif → l'auteur passe (mode petites équipes).
- **Tokens API révoqués à la désactivation** : User::booted() supprime tous
  les tokens Sanctum quand is_active passe à false — couvre API/mobile en
  plus du web (middleware). PROUVÉ (2 tokens → désactivation → 0).
- **BUG DE COMPLÉTUDE DÉCOUVERT PAR CE TEST** : la table
  `personal_access_tokens` n'avait JAMAIS été migrée (ni test, ni MySQL
  production) — `api/auth/token` répondait 500 au premier appel : l'API
  Sanctum n'avait jamais fonctionné. Migration standard ajoutée.
- **Limite consignée** : le log d'audit d'un refus maker-checker est écrit
  dans la transaction du service, que l'exception fait rollback — la
  journalisation fiable des REFUS exige une connexion DB dédiée pour
  audit_logs (planifiée avec l'incrément « intégrité du journal »).

### Séparation EFFECTIVE + fail-closed (6e incrément, 23/07 — réponse à la critique)

**Matrice réelle des rôles (21 rôles, 21 utilisateurs actifs, 0 permission
directe hors rôle)** — après retraits :

| Rôle | Créer | Valider | Annuler | Payer | Clôturer caisse | Conflit restant |
|---|---|---|---|---|---|---|
| caissier | enc./déc. ✓ | ✗ | **✗ (retiré)** | ✓ saisie | crée ✓ / **valide ✗ (retiré)** | aucun |
| commercial | devis/avoirs ✓ | **avoirs ✗ (retiré)** | ✗ | ✗ | ✗ | aucun |
| acheteur | DA/CF ✓ | **réceptions ✗ (retiré)** | **CF ✗ (retiré)** | ✗ | ✗ | aucun |
| magasinier | réceptions ✓ | réceptions ✓ (constat physique) | **✗ (retiré)** | ✗ | ✗ | valide sa propre saisie — assumé (constat physique), maker-checker à étendre si exigé |
| comptable | écritures ✓ | écritures ✓ + clôtures ✓ | trésorerie ✓ | ✓ | ✓ | rôle de contrôle — maker-checker impose l'altérité individuelle |
| daf / directeur | supervision | ✓ | ✓ | ✓ | ✓ | rôles de contrôle assumés |
| autres (RH, production, qualité, lecture) | périmètre propre | par étape dédiée | — | — | — | RAS |

Retraits appliqués EN BASE (migration 130000, réversible) : caissier
−treasury.cancel/−cash_closures.validate/−payments.cancel ; commercial
−credit_notes.validate ; acheteur −purchase_orders.confirm/cancel
−receptions.validate/cancel ; magasinier −receptions.cancel. Ajout : daf
+purchase_orders.confirm/cancel (l'acheteur ne peut plus confirmer).
Utilisateurs affectés : 4 (1 par rôle opérationnel) — vérifié en base.

**Fail closed** : maker-checker ACTIF PAR DÉFAUT en production (variable
absente = actif) ; exemptions par action IGNORÉES en production pour les
actions non exemptables (annulations/validations financières listées en
config). `a3:audit-security` (nouvelle commande, lecture seule, exit 1) :
maker-checker off en prod = CRITIQUE, conflits de séparation par rôle,
permissions critiques sans détenteur ou >10 détenteurs, >2 super-admins,
actifs sans rôle, permissions directes, tokens de comptes désactivés.
Premier passage : a détecté un compte factory actif sans rôle en base
réelle (désactivé) — AUDIT SÉCURITÉ PROPRE ensuite.

**Contrainte DB unicité révision active** : colonne générée MySQL
`active_revision_key` + index UNIQUE. PROUVÉ EN SQL BRUT (hors service) :
2e révision active → Duplicate entry refusé par MySQL ; révision annulée
libère l'emplacement. SQLite tests : couverture par le service (consigné).

**Canal API** : middleware EnsureUserIsActive ajouté au groupe api — un
compte désactivé est refusé (403) à chaque requête MÊME SI la révocation de
ses tokens a échoué (update de masse, SQL brut).

**Refus maker-checker** : journal de sécurité FICHIER dédié
(storage/logs/security.log, rotation 365 j, hors transaction — survit aux
rollbacks) + best-effort audit_logs. Jamais de secret dans ces journaux.

### Bénéficiaire + audit de dérive de schéma (7e incrément, 23/07)

- **Validateur ≠ bénéficiaire** : `assertNotBeneficiary` — TOUJOURS actif,
  indépendant de la config maker-checker, sans exemption, journalisé
  (security.log + audit_logs). Branché où la relation métier est fiable
  (`employees.user_id`) : approbation de prêt et d'avance sur salaire.
  PROUVÉ : le salarié lié refuse (approved_by inchangé), un autre
  utilisateur RH habilité passe. Fournisseurs/clients : AUCUN lien
  user_id modélisé — le contrôle bénéficiaire n'y est PAS implémentable en
  l'état (consigné : décision de modélisation si besoin).
- **`a3:audit-schema`** (nouvelle commande, lecture seule, exit 1) :
  1) tables exigées par la CONFIG active (Sanctum, queue/cache/session
  database, audit) ; 2) chaque modèle Eloquent → table existante ;
  3) migrations enregistrées sans fichier / jamais exécutées ;
  4) colonnes $fillable des 11 modèles financiers → colonnes réelles.
  Le DÉTECTEUR est prouvé : tests qui suppriment une table ou insèrent une
  migration fantôme → exit 1. Base réelle : AUDIT SCHÉMA PROPRE.

### Webhooks Mobile Money durcis (8e incrément, 23/07)

3 bugs réels corrigés, dont 2 découverts par les nouveaux tests :

1. **`ltrim($signature, 'sha256=')`** strippait une LISTE de caractères, pas
   un préfixe : toute signature commençant par s/h/a/2/5/6/= était tronquée →
   faux rejets. Corrigé (preg_replace du préfixe) et PROUVÉ par un test qui
   force une signature commençant par un caractère de la liste.
2. **Le job de traitement n'était JAMAIS lancé sur une première livraison
   SUCCESS** : la transaction se créait directement en 'confirmed' et le test
   « status !== confirmed » (évalué APRÈS création) était toujours faux. Le
   cas nominal (webhook unique confirmant le paiement) ne déclenchait rien.
   Corrigé : dispatch si la confirmation est NOUVELLE (état mémorisé avant).
3. **Recherche par `orWhere` avec référence nulle** → `whereNull` pouvait
   rattacher un webhook à une transaction étrangère. Recherche stricte par
   (provider, external_reference) puis (provider, internal_reference).

Durcissements : contrainte UNIQUE (provider, external_reference) en base —
deux livraisons simultanées ne créent qu'une transaction (le perdant recharge
l'existante) ; **référence mutée rejetée** (même référence, montant différent
→ rejected + security.log : le HMAC prouve l'origine, pas la légitimité) ;
rejeu exact neutre (1 transaction, 1 job — prouvé).

Vérifiés sains : hash_equals (timing-safe), secret chiffré en base (cast
encrypted), secrets séparés par intégration, throttling. NON COUVERT
(consigné) : validation d'horodatage (aucun champ timestamp standard dans
les payloads opérateurs — le rejeu tardif est neutralisé par l'idempotence),
rotation des secrets (procédure d'exploitation à documenter Phase 2.7).

### Chaînage du journal + maker-checker étendu + cache session (10e incrément, 23/07)

- **Chaînage cryptographique du journal** : `row_hash = SHA-256(prev_hash |
  user | action | modèle | valeurs | ip)` sur chaque entrée ;
  `AuditService::verifyChain()` intégrée à `a3:audit-security` (§8).
  PROUVÉ : altération d'une entrée par SQL direct → détectée ; suppression
  d'une entrée du milieu → rupture prev_hash détectée ; chaîne saine → [].
  Entrées antérieures au déploiement (hash null) ignorées.
- **Maker-checker étendu à 7 points** : + validation de clôture de caisse
  (le caissier n'auto-approuve pas ses écarts), + validation de réception
  (celui qui saisit ne certifie pas l'entrée de stock — couvre le conflit
  magasinier documenté à la matrice des rôles).
- **Retrait de permission en session** : PROUVÉ — révocation de
  treasury.cancel au rôle pendant la session → 403 immédiat (Spatie
  invalide son cache au retrait). Limite : cache multi-serveurs non testé
  (mono-serveur en production actuelle).

### Clôture Phase 2.1 (11e incrément, 23/07)

- **E2E API réel PROUVÉ** : cycle complet — 401 sans token, émission par
  credentials, 200 avec permission, 403 sans permission, 422 mauvais
  identifiants, désactivation → refus (double couche : token révoqué +
  middleware). Bug corrigé au passage : EnsureUserIsActive interrogeait
  Auth::user() (guard web) — le canal sanctum passait au travers ;
  désormais $request->user() (guard résolu) + fresh().
- **Permissions orphelines** : 17/161 sans référence route/code —
  5 créées en réserve par la phase (payments.confirm/cancel,
  purchase_orders.cancel, credit_notes.cancel, cash_closures.reopen :
  routes à brancher quand les flux correspondants seront exposés),
  12 historiques (purchase_requests.validate_l1/l2/l3, sales.create,
  company.view, families.view/disable, quality.nc.manage,
  stocks.lot.trace, production.approve_modification,
  articles.change_category/override_category_defaults). Aucune
  suppression (inoffensives) — liste publiée pour arbitrage.
- **Décisions consignées** :
  1. Seuils par agence/dépôt/nature : NON IMPLÉMENTÉ par décision —
     OA METAL est mono-site (une usine, Ouagadougou) ; l'axe « agence »
     n'existe pas dans le modèle. Les seuils par MONTANT existent
     (trésorerie + CF) et « absence de règle ≠ autorisation libre » est
     garanti. À rouvrir si multi-agences.
  2. user_id sur fournisseurs/clients : NON MODÉLISÉ par décision — le
     contrôle bénéficiaire couvre les salariés (seul lien fiable).
     À rouvrir si des tiers reçoivent des comptes ERP.

**BILAN PHASE 2.1 — 11 incréments, 9 bugs réels corrigés** (annulation
par lecteur ×3, approbation sans règle, session survivante, table Sanctum
jamais migrée, signature webhook tronquée, job webhook jamais lancé,
rattachement webhook erroné, FK audit_logs bloquant la suppression de
compte, canal sanctum ignoré par le middleware). 10 permissions dédiées +
retraits effectifs sur 4 rôles, maker-checker 7 points fail-closed,
bénéficiaire toujours actif, journal chaîné cryptographiquement,
3 commandes d'audit permanentes. ~35 tests de sécurité automatisés.
Risque résiduel : FAIBLE sur les chemins couverts ; scénarios non
couverts : multi-serveurs (cache), multi-agences (hors modèle), E2E UI
navigateur (Phase 2.5).

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
| Transfert partiellement reçu | TRANCHÉ 23/07 : la saisie partielle existait déjà (quantités par ligne) mais l'écart s'évaporait sans comptabilisation | Décision : PERTE EN TRANSIT comptabilisée — D 6097 / C 3111 à la réception partielle, idempotente, journalisée (audit_logs) | **LIVRÉ + PROUVÉ** (écart 5×6 000 = 30 000 : écriture équilibrée, réception complète = aucune écriture) |
| BL par lot/bobine/emplacement | TRANCHÉ 23/07 : lot_number déclaratif (texte) insuffisant | Décision : LIEN FORMEL lot_id sur lignes BL + décrément stock_lots à la validation + lot imprimé sur le BL | À IMPLÉMENTER (prochain incrément, ~1-1,5 j) |
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
