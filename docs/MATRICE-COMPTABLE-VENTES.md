# MATRICE COMPTABLE VENTES — SYSCOHADA

| Événement | Débit | Crédit | Service | Statut |
|---|---|---|---|---|
| Facture client | Client 411 | Ventes 70x + TVA 4431 | `AccountingService::postClientInvoice` | PROUVÉE |
| Retenue à la source | compte paramétré | créance client selon schéma | `InvoiceService`/`AccountingService` | PROUVÉE PARTIELLEMENT |
| Sortie/COGS | Coût des ventes | Stock | `postSaleStockMovement` | PROUVÉE |
| Avoir commercial | Retours/remises + TVA | Client | `postCreditNote` | PROUVÉE |
| Retour physique | Stock historique | Coût des ventes | `postCreditNoteStockMovement` | PROUVÉE par dispositions couvertes |
| Remboursement avoir | Client/avoir | Trésorerie | `postCreditNoteRefund` | PROUVÉE |
| Annulation facture | Contre-écriture liée | Contreparties initiales | `InvoiceService::cancel` | PROUVÉE PARTIELLEMENT |
| Annulation BL | Restauration stock au coût figé | Contre-mouvement | `DeliveryNoteService::cancelValidated` | PROUVÉE PARTIELLEMENT |

Principes constatés : comptes paramétrés, écriture liée au document, idempotence par synchronisation, coûts d’allocations de lots figés. Restent à certifier exhaustivement : périodes fermées sur toutes les contre-opérations, simultanéité facture/annulation, réconciliation globale stock–COGS et toutes les natures d’avoirs.