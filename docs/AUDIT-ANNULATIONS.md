# A3 ERP — Matrice de preuve des annulations (23/07/2026)

Convention : **PROUVÉ** = test automatisé + contrôle base · **PARTIEL** = mécanisme présent, cas limites non tous testés · **MANQUANT** = chemin à implémenter.

| Opération | Effets initiaux | Annulation | Effets inversés | Preuve | Restes |
|---|---|---|---|---|---|
| **Encaissement client** | allocations, factures (paid/remaining/statut), GL, caisse, solde client, éligibilité MTO | `ClientPaymentService::cancel` (nouveau) | factures restaurées (seules SES allocations — multi-paiements testé), allocations supprimées, extourne liée à l'origine datée du jour (période ouverte), caisse mouvement inverse (refus si fonds absents, message remboursement dédié), statut+motif, solde client recalculé, éligibilité recalculée en direct (aucune mémoïsation) | **PROUVÉ** (4 tests : complet, partiel 2 paiements, OF actif refusé, double annulation refusée) | commissions mobile money : non modélisées (aucune table de frais) — consigné |
| **Décaissement fournisseur** | allocations FF, GL, caisse | `SupplierPaymentService::cancel` (existant) | factures restaurées, extourne, caisse re-créditée, statut+motif, double annulation refusée | **PROUVÉ** (test miroir complet) | — |
| **Facture client** | GL, soldes client | `InvoiceService::cancel` | extourne ; garde : refus si paiements alloués ; **période close : écriture d.origine INTACTE (lignes+date), extourne datée du jour, liée** | **PROUVÉ** (test lignes/date intactes + extourne équilibrée du jour) | intégration fiscale : non branchée (aucune) |
| **Bon de livraison validé** | sortie stock, statut commande | `cancelValidated` | réintégration stock (idempotence testée mission BL) | **RISQUE RÉSIDUEL** : réintégration globale prouvée ; la ventilation par lot/emplacement n.est pas modélisée sur les BL (sortie agrégée) — comportement cohérent avec le flux actuel | à revoir si la ventilation par emplacement est ajoutée aux BL |
| **Avoir** | stock (retour), GL, solde facture | `CreditNoteService::cancel` (nouveau, routé du workflow) | application facture défaite (applied_amount), contre-mouvements stock idempotents, extourne de toutes les écritures, statut+motif, double annulation refusée | **PROUVÉ** (test complet stock/facture/GL/idempotence) | — |
| **OF** | réservations, conso, PF | `cancel` production | réservations libérées + reliquat testés ; **RÈGLE FORMELLE : OF avec consommation ou déclaration VIVANTE inannulable** — guidage vers clôture avec écart assumé OU extourne préalable (reverse conso/output) puis annulation | **PROUVÉ** (test : refus avec conso vivante, annulation possible après extourne) | — |
| **Transfert stock** | sortie source/entrée destination | `cancel` (lockForUpdate + canCancel) | en transit : réintégration source exacte ; reçu : refus ; double annulation : refus sans double réintégration | **PROUVÉ** (test 3 scénarios) | partiellement reçu : statut non modélisé (réception = tout) — RISQUE RÉSIDUEL faible documenté |
| **Réception fournisseur** | stock, bobines/lots, PO reçu | `PurchaseOrderService::cancelReception` (nouveau) | contre-mouvements de sortie (idempotents), bobines/lots retirés (garde : non consommés), PO réouvert (quantités reprises, statut recalculé), statut+motif ; REFUS si facture fournisseur existante ou bobine consommée (→ retour fournisseur) | **PROUVÉ** (3 tests : annulation complète, refus si facturée, double annulation refusée) | réception partielle multi-lignes : couverte par ligne, cas mixte à observer en recette |
| **Écriture comptable** | GL | `reverseEntry` | extourne liée (`reversed_by_entry_id`), idempotente, refus extourne d'extourne, datée du jour (période ouverte) | **PROUVÉ** (gardes + usage testé via annulations) | — |
| **Clôture de caisse** | verrou période caisse | — | mouvements antidatés dans une clôture validée REFUSÉS (`TRESO-P7`) | **PROUVÉ** (garde code) | réouverture de clôture : inexistante (voulu) |

## Règles métier actées
1. **Annulation ≠ remboursement** : l'annulation neutralise la saisie (extourne + mouvement caisse inverse du jour). Le remboursement physique du client est un décaissement dédié. Si la caisse ne contient plus les fonds → annulation refusée avec guidage.
2. **Production financée** : un encaissement dont dépend l'éligibilité d'un OF actif est inannulable tant que l'OF vit (ou approbation gérant).
3. **Jamais de suppression physique** d'une opération financière : statut `annule` + motif + auteur + horodatage.

## Prochain lot (ordre)
1. Réception : chemin d'annulation technique (règles ci-dessus).
2. Tests miroirs : cancel décaissement, avoir partiellement utilisé, transfert concurrent.
3. Lot 3 : modifications post-validation (inventaire des `update()` directs sur documents validés).
