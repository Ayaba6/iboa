# RAPPORT DE TEST FONCTIONNEL — CYCLE DE VENTE ET DE FABRICATION MAKE-TO-ORDER

**ERP A3/IBOA — OA METAL INDUSTRIE**
Version du document : 1.0 · Date : 14/07/2026 · Auteur : Cellule Test ERP (assistée) · Document interne

---

## 1. Résumé exécutif

Le scénario complet de fabrication à la commande (Make-to-Order) d'une tôle bac prélaquée beige 27/100 — du devis à l'encaissement intégral — a été **exécuté réellement dans l'application**, avec vérification en base de données après chaque étape et rotation des profils utilisateurs (commercial, directeur, chef de production, directeur d'usine, DAF, magasinier, comptable).

**Décision finale : TEST RÉUSSI AVEC RÉSERVES.**
Le parcours nominal fonctionne de bout en bout : tous les documents sont générés, liés, aux bons montants (400 000 HT / 72 000 TVA / 472 000 TTC), les stocks matière et produit fini sont mouvementés correctement, les écritures comptables SYSCOHADA sont équilibrées, le compte client est lettré et la facture réglée est verrouillée. Neuf anomalies ont été relevées (1 majeure, 3 moyennes, 5 mineures/informatives) — voir §18.

## 2. Objectif du test

1. Vérifier l'exécution réelle du processus métier Devis → Validation → Commande → Contrôle crédit → OF → Réservation matière → Production → Qualité → Entrée stock → Clôture OF → Préparation → BL → Facture → Encaissement → Lettrage.
2. Contrôler les effets de chaque étape dans les modules liés (stock, comptabilité, trésorerie, crédit client).
3. Alimenter le manuel d'utilisation (document joint).

## 3. Périmètre testé

Modules : Ventes (devis, commandes, BP, BL, factures), Production (OF, consommation bobine, déclaration, chutes, qualité, clôture), Stock (MP, PF, mouvements, réservations), Comptabilité (écritures, lettrage), Trésorerie (encaissement), Gestion (client, crédit, dossier client). Environnement : local (Laragon), société **OA METAL INDUSTRIE**, base réelle avec **données de test exclusivement identifiées « TEST GUIDE »**.

## 4. Données utilisées

| Élément | Valeur |
|---|---|
| Client | CLIENT TEST GUIDE SARL (id 16) — actif, crédit, plafond 10 000 000 F, encours initial 0, virement, 30 jours |
| Produit | TOLE BAC PRELAQUEE BEIGE 27/100 4 ONDES [TEST GUIDE] (id 28) — fabriqué, **MTO**, unité ml, 4 000 F/ml |
| Nomenclature | NOM-TEST-GUIDE (BOM 9) — bobine beige 27/100, 1,7513 kg/m, taux de perte 3,5 % |
| Bobine | **BOB-TEST-GUIDE-001** / lot **LOT-TEST-GUIDE-001** — beige, 0,27 mm, larg. 1 000 mm, 500 kg, 850 F/kg, fournisseur Métal Bobines Sahel, dépôt MP |
| Commande | 100 mètres linéaires (= 20 feuilles × 5 m), 4 000 F/ml, TVA 18 % |
| Montants attendus | HT 400 000 · TVA 72 000 · TTC 472 000 F |

## 5. Utilisateurs et rôles (séparation des responsabilités)

| Étape | Utilisateur | Rôle |
|---|---|---|
| Devis (création, soumission), conversion commande | Aminata Sawadogo — commercial@iboa.bf | commercial |
| Validation devis + commande, création BL, génération facture | Moussa Kaboré — directeur@iboa.bf | directeur |
| Soumission OF, validation Responsable Prod., lancement, exécution, QC, clôture | chef.production@iboa.bf | chef_production |
| Validation Chef Atelier de l'OF | directeur.usine@iboa.bf | directeur_usine |
| Autorisation financière de l'OF | daf@iboa.bf | daf |
| Chargement bon de préparation, validation BL | magasinier@iboa.bf | magasinier |
| Validation facture, encaissement | comptable@iboa.bf | comptable |

Aucune étape n'a été réalisée avec le compte administrateur.

## 6. Chronologie du test (14/07/2026)

| Heure | Événement |
|---|---|
| 15:20 | Création devis DEV-2026-00036 (commercial) |
| 15:22 | Soumission à validation (+1 notification au valideur) |
| 15:23 | Validation du devis (directeur) puis conversion → CMD-2026-046 |
| 15:24–15:25 | Soumission commande (contrôle crédit passé) puis validation → **confirmée** ; **OF-2026-0151 généré automatiquement** (MTO, stock PF = 0) |
| 15:27–15:29 | Allocation matière ; circuit OF : soumission → validation Chef Atelier → validation Responsable Production |
| 15:31 | **Autorisation financière DAF : approuvée** (client dans son plafond : 472 000 / 10 000 000) |
| 15:33 | Lancement + démarrage OF (**en cours**) |
| 15:36 | Consommation bobine 182 kg / 101 m ; déclaration 100 ml (lot LOT-PF-TEST-GUIDE-001) ; chute réutilisable 0,9 kg ; rebut 1,8 kg |
| 15:40 | Contrôle qualité **conforme** (épaisseur, longueur, couleur, visuel) ; visa chef sur la déclaration ; **clôture OF (terminé)** ; lot auto LOT-OF-2026-0151-01 ; réservation PF 100 ml pour le client |
| 15:43 | Bon de préparation BP-2026-0028 (auto) → **chargé** (magasinier) |
| 15:45 | BL-2026-025 créé puis **validé** → sortie stock −100 ; commande **livrée** |
| 15:47 | Facture FA-2026-018 générée depuis le BL ; tentative de double génération **refusée** |
| 15:48 | Validation facture (comptable) → écriture de vente comptabilisée |
| 15:50 | Encaissement ENC-2026-012 — virement VIR-TEST-GUIDE-001, 472 000 F, imputation intégrale → facture **payée**, compte client **lettré**, facture **verrouillée** (re-validation refusée 403) |

## 7–8. Documents générés et numéros

| Document | Numéro | Statut final |
|---|---|---|
| Devis | **DEV-2026-00036** | Converti |
| Commande client | **CMD-2026-046** | Facturée (après livrée) |
| Ordre de fabrication | **OF-2026-0151** | Terminé |
| Bobine consommée | **BOB-TEST-GUIDE-001** (lot LOT-TEST-GUIDE-001) | En production (reste 318 kg) |
| Lot produit fini | **LOT-PF-TEST-GUIDE-001** + lot fabrication auto **LOT-OF-2026-0151-01** | Conforme |
| Bon de préparation | **BP-2026-0028** | Chargé |
| Bon de livraison | **BL-2026-025** | Livré (validé) |
| Facture | **FA-2026-018** | Payée (verrouillée) |
| Encaissement | **ENC-2026-012** (réf. VIR-TEST-GUIDE-001) | Imputé |
| Écritures | FA-2026-018 · ENC-2026-012 · OF-2026-0151-CONS · OF-2026-0151-PROD | Équilibrées |

## 9–10. Résultats obtenus et montants vérifiés

- Devis : HT **400 000** / TVA **72 000** / TTC **472 000** — calcul automatique exact, PU 4 000 repris du catalogue, numérotation automatique.
- Reprise à l'identique devis → commande → BL → facture (mêmes montants, même client, mêmes conditions 30 j / virement).
- Échéance facture : 13/08/2026 (30 jours) ✔.
- Encaissement : 472 000 imputés, solde facture **0**, encours client **0**, solde banque 1 091 500 → **1 563 500** (+472 000) ✔.

## 11. Mouvements de stock (chaîne complète observée)

| # | Type | Article | Dépôt | Qté | Référence |
|---|---|---|---|---|---|
| 24 | Entrée | Bobine (MP) | DEP-MP | +500 kg | Approvisionnement bobine test |
| 29 | Entrée | Tôle bac PF | DEP-PF | **+100 ml** | Production OF-2026-0151 |
| 30 | Sortie | Bobine (MP) | DEP-MP | −175,13 kg | Consommation composant (backflush BOM) |
| 31 | Sortie | Tôle bac PF | DEP-PF | **−100 ml** | BL-2026-025 |

Les deux opérations distinctes exigées — **entrée production +100** puis **sortie livraison −100** — sont présentes ; le stock PF termine à 0 et la réservation client est soldée. Une correction en cours de test (annulation/redéclaration) a été **auto-contre-passée proprement** par le système (mouvements 25–28).

## 12. Consommation de bobine (traçabilité)

BOB-TEST-GUIDE-001 (lot LOT-TEST-GUIDE-001, beige 0,27 × 1 000 mm, fournisseur Métal Bobines Sahel) : poids initial 500 kg → consommé **182 kg / 101 m** (besoin théorique 181,3 kg = 100 × 1,7513 × 1,035) → **reste 318 kg**, statut « en production ». Chaîne : Client → CMD-2026-046 → OF-2026-0151 → BOB-TEST-GUIDE-001 → Métal Bobines Sahel (visible dans l'onglet **Traçabilité** de la fiche OF).

## 13. Production réalisée

Prévu 100 ml — produit **100 ml conformes** (20 feuilles × 5 m, documentées en notes et lignes de coupe de l'OF) ; chute réutilisable 0,9 kg (765 F) ; rebut 1,8 kg (1 530 F) ; rendement matière ≈ **98,5 %**.

## 14. Contrôle qualité

Verdict **conforme** — critères épaisseur / longueur / couleur / visuel tous OK, horodaté, motif détaillé saisi. La livraison est bloquée par le système en cas de non-conformité (garde-fou vérifié par les tests automatisés `ProductionDegradedPathTest`).

## 15. Coût de revient de l'OF

Coût total calculé : **323 560 F** (matière 303 560, MO/machine 20 000). ⚠ Voir anomalie n°4 : la composante matière additionne la consommation bobine (154 700) **et** le backflush BOM (148 860) qui tracent la même matière → coût matière surévalué (~×2).

## 16. Écritures comptables (toutes équilibrées débit = crédit)

**Facture FA-2026-018** — journal des ventes :

| Compte | Libellé | Débit | Crédit |
|---|---|---|---|
| 411 | Clients | 472 000 | |
| 7011 | Ventes de marchandises | | 400 000 |
| 4431 | TVA facturée sur ventes | | 72 000 |

**Encaissement ENC-2026-012** — banque :

| Compte | Libellé | Débit | Crédit |
|---|---|---|---|
| 521 | Banque principale | 472 000 | |
| 411 | Clients | | 472 000 |

**Production** : OF-2026-0151-CONS (154 700 / 154 700) et OF-2026-0151-PROD (160 000 / 160 000).
**Lettrage** : les deux lignes 411 (facture + règlement) portent la lettre **« D »** — compte client lettré ✔.

## 17. Encaissement

ENC-2026-012 · virement bancaire · Compte Coris Bank · 472 000 F · référence **VIR-TEST-GUIDE-001** · imputation intégrale sur FA-2026-018 · solde restant 0 · facture **Payée** et **verrouillée** (toute re-validation renvoie 403).

## 18–19. Anomalies détectées

| N° | Module | Étape | Anomalie | Attendu | Obtenu | Gravité | Recommandation | Statut |
|---|---|---|---|---|---|---|---|---|
| A1 | Production/Coût | Clôture OF | Coût matière = conso bobine **+** backflush BOM de la même matière (154 700 + 148 860) | Matière comptée une fois (~154 700) | 303 560 | **Majeure** | Ne compter qu'un des deux flux quand bobine et composant BOM tracent la même matière | À corriger |
| A2 | Production/Droits | Déclaration | L'opérateur (production.declare) reçoit **403** sur consommation/déclaration/chutes (routes exigent production.update) | Opérateur déclare sa production | Seul chef production/atelier peut | Moyenne | Ouvrir consume/output/waste à production.declare | À corriger |
| A3 | Qualité/Droits | Contrôle qualité OF | Le responsable qualité reçoit **403** sur le QC de l'OF (exige production.update) | Qualité saisit le contrôle | Fait par la production | Moyenne | Permission qualité dédiée sur ProductionQualityController | À corriger |
| A4 | Stock | Réservation matière | L'« allocation matière » est un statut logique ; aucun poids de bobine n'est réservé fermement avant lancement (pas de blocage inter-OF) | Réservation dure de la matière | Statut seul | Moyenne | Réservation ferme du poids bobine à l'allocation | À évaluer |
| A5 | Production | Déclaration | Pas de double unité feuilles + ml sur la déclaration : saisie en unité de vente (ml), les feuilles passent en notes/lignes de coupe | Saisie 20 feuilles × 5 m native | quantity=100 ml + note | Mineure | Champ « nb feuilles × longueur » sur la déclaration alimentant quantité et total_meters | À évaluer |
| A6 | Audit | OF | Les transitions de statut OF ne sont pas journalisées dans audit_logs (devis/commande/facture le sont) | Historique OF dans l'audit | Workflow + notifications seulement | Mineure | Ajouter l'observer d'audit sur ProductionOrder | À évaluer |
| A7 | Ventes | Envoi facture | Envoi e-mail non testé (pas de SMTP local) ; le statut « Envoyée » est posé à la validation | Envoi + accusé | Statut sans envoi réel | Mineure | Tester en environnement doté d'un SMTP | Hors périmètre local |
| A8 | Ventes/Droits | Création BL/facture | Créer le BL et générer la facture depuis le BL exigent orders.edit_validated / policy update (direction), pas le commercial ni le magasinier ni le comptable | Rôles métier dédiés | Direction requise | Info | Confirmer la répartition voulue ou l'assouplir | À arbitrer |
| A9 | Infra test | Devis | Une expiration de session (proxy navigateur) a fait échouer la 1ʳᵉ soumission (aucun enregistrement créé — echec propre) | — | Reconnexion puis succès | Info | — | Clos |

## 20. Recommandations

1. Corriger A1 (coût de revient) en priorité — impact direct sur la marge industrielle.
2. Revoir la matrice des droits d'exécution atelier et de qualité (A2, A3).
3. Étudier la réservation ferme de bobine (A4) et la saisie feuilles × longueur (A5).
4. Étendre l'audit aux OF (A6) et tester l'envoi e-mail en pré-production (A7).

## 21. Conclusion

Le cycle Make-to-Order complet est **opérationnel de bout en bout** dans A3/IBOA pour OA METAL INDUSTRIE : chaîne documentaire liée, quantités et montants exacts, TVA correcte, stock matière décrémenté, produit fini entré puis sorti, bobine traçable jusqu'au fournisseur, contrôle qualité bloquant, chutes et rebuts enregistrés, OF clôturé avec coût, BL décrémentant le stock, facture conforme au BL, paiement soldant la facture, encours client à jour, écritures équilibrées, lettrage automatique, historique horodaté et facture réglée verrouillée. Les réserves listées ne bloquent pas l'exploitation du scénario nominal mais doivent être traitées avant une mise en production complète (notamment A1).

## 22. Décision finale

> **TEST RÉUSSI AVEC RÉSERVES** (1 anomalie majeure — coût matière ; 3 moyennes — droits atelier/qualité et réservation matière ; 5 mineures/informatives).

---

### Note sur les captures d'écran

Le test a été piloté dans le navigateur intégré ; les vérifications visuelles ont été faites en séance mais **l'environnement d'exécution ne permet pas d'exporter les captures en fichiers image**. Le guide utilisateur référence des emplacements de capture numérotés `[Capture NN]` avec la description exacte de l'écran à photographier lors d'une passe de documentation sur poste standard.
