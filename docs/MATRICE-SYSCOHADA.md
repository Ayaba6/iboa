# Matrice comptable SYSCOHADA — A3 ERP

> Phase 2.2 (23/07/2026). Événement métier → écriture générée → comptes →
> journal → pièce → preuve. Générateurs centralisés : `AccountingService`
> (20 posteurs), `PayrollAccountingService`, `FixedAssetService`,
> `VatDeclarationService`. Verdicts : ✓ conforme / ✎ corrigé cette phase /
> ⚠ à arbitrer.

## 1. Cycle ventes

| Événement | Écriture | Pièce | Verdict | Preuve |
|---|---|---|---|---|
| Facture client validée | D 411 (net à payer) [+ D 4473 retenue] [+ D 7085 remise] / C 7011 ventes mses OU C 702 produits fabriqués (par famille) + C 4431 TVA facturée | n° facture | ✓ (702/7011 par famille, retenue à la source au bilan) | E2E OrderToCash + tests compta vente |
| Sortie de stock sur vente (CMP) | D 6031 variation stocks / C 311x stocks famille (marchandises) — produits fabriqués : via 36/736 au cycle production | n° facture | ✓ | tests BL/CMP |
| Encaissement client | D 521/571/523 (banque/caisse/mobile money) / C 411 | n° encaissement | ✓ (mobile money → 523 dédié) | AutoAllocationFifo + MobileMoney |
| Avoir client validé | D 7085 [⚠] + D 4431 / C 411 | n° avoir | ⚠ 7085 « Remises accordées » : SYSCOHADA prévoit 709 (RRR accordés) — subdivision locale à arbitrer avec le comptable | CreditNoteReturnDisposition |
| Annulation (tout doc) | extourne symétrique datée du jour, liée reversed_by_entry_id | n° origine | ✓ | matrice annulations (8 PROUVÉ) |
| Créance irrécouvrable | D 6514 / C 411 | dossier contentieux | ✓ | test contentieux |

## 2. Cycle achats

| Événement | Écriture | Pièce | Verdict | Preuve |
|---|---|---|---|---|
| Facture fournisseur validée | D 601x achats (ventilés par famille) + D 4452 TVA récupérable ✎ / C 401 | n° FF | ✎ 4432→4452 (445 = État TVA récupérable ; 4432 était une subdivision de 443 TVA facturée) | AchatsAcceptance |
| Entrée stock sur achat | D 311x/321 stocks / C 6031/6032 variation | n° réception | ✓ | AchatsAcceptance |
| Décaissement fournisseur | D 401 / C 521/571 | n° décaissement | ✓ | AchatsAcceptance (miroir) |
| Retour fournisseur validé | D 401 / C 6011 + C 4452 ✎ | n° retour | ✎ (même correction TVA) | test retours |

## 3. Trésorerie

| Événement | Écriture | Pièce | Verdict |
|---|---|---|---|
| Transfert caisse→banque | D 585 virements internes / C 571 puis D 521 / C 585 | n° transfert | ✓ (585 transite, solde nul après complétion) |
| Frais bancaires | D 6312 / C 521 | relevé | ✓ |
| Écart de clôture caisse | excédent : D 571 / C 7588 ; manquant : D 6588 / C 571 | n° clôture | ✓ |
| Remise en banque | D 521 / C 571 (via 585) | n° remise | ✓ |

## 4. Production et stocks

| Événement | Écriture | Pièce | Verdict |
|---|---|---|---|
| Consommation matières (OF) | D 6032 variation MP / C 321 stocks MP | n° OF | ✓ |
| Déclaration production PF | D 361 stocks PF / C 736 production stockée | n° OF | ✓ |
| Vente produit fabriqué | C 702 (pas 7011) + sortie D 6031→ non : produits finis sortent par 36/736 | n° facture | ✓ (corrigé session antérieure) |
| Écarts d'inventaire | gain : D 311x / C 7097 ; perte : D 6097 / C 311x | n° session | ✓ |
| Valorisation chutes (PRO-08) | entrée stock chute valorisée | n° OF | ✓ |

## 5. Paie (PayrollAccountingService)

| Événement | Écriture | Pièce | Verdict |
|---|---|---|---|
| Validation run de paie | D 661 rémunérations + D 664 charges sociales patronales + D 671 indemnités / C 422 personnel + C 431 CNSS ✎ + C 447 État impôts retenus (IUTS + effort de paix) | run période | ✎ 451→431 (43 = organismes sociaux ; 451 = opérations groupe) |
| Paiement salaires | D 422 / C 521 | virement | ✓ |
| Déclarations sociales/fiscales | D 431/447 / C 521 | déclaration | ✓ (comptes suivent la correction) |

## 6. Immobilisations (FixedAssetService)

| Événement | Écriture | Verdict |
|---|---|---|
| Acquisition | D 2xx (+ frais accessoires incorporés) / C 481/401 | ✓ (frais accessoires corrigés session immobilisations) |
| Dotation amortissement | D 681x / C 28xx | ✓ (prorata jours, étalement N+1, VNC résiduelle — testés) |

## 7. Écarts corrigés cette phase / à arbitrer

1. ✎ **4432 → 4452** (TVA récupérable) — mapping + renommage du compte
   (1 ligne existante suit le compte, aucune écriture modifiée).
2. ✎ **451 → 431** (CNSS) — mapping + renommage (0 ligne existante).
3. ⚠ **7085 pour remises/avoirs** : SYSCOHADA prévoit **709** (RRR accordés).
   Subdivision locale fonctionnelle — arbitrage comptable requis avant le
   premier arrêté (renommage aussi simple que les deux précédents tant que
   la base est en recette).

## 8. Réconciliations exigées (tests à produire — suite de la phase)

- GL (somme lignes par compte) = soldes `accounts.debit_balance/credit_balance`
- Balance générale équilibrée = Σ débits = Σ crédits
- Balance auxiliaire clients (411 par tiers) = Σ relevés clients = Σ `invoices.remaining_amount` (tiers non soldés)
- Balance auxiliaire fournisseurs (401) = relevés fournisseurs
- Résultat (7x − 6x) cohérent avec le compte de résultat affiché
