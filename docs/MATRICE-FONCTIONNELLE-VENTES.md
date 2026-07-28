# MATRICE FONCTIONNELLE VENTES

Légende : **PROUVÉE**, **PARTIELLEMENT PROUVÉE**, **INCOMPLÈTE**, **NON TESTÉE**, **NON IMPLÉMENTÉE**.

| Fonction | Statut | Preuves principales | Écart restant |
|---|---|---|---|
| KPI CA, marge, pipeline, retards | PROUVÉE | `SalesInsightsService`, tests marge/isolation | filtres avancés incomplets |
| Devis et calculs tôle bac | PROUVÉE | `QuoteWorkflowTest`, `SalesLinePhase5Test` | poids/spécifications à homogénéiser |
| Révision/version de devis | PROUVÉE | `QuoteRevisionTest` | diff visuel détaillé incomplet |
| Prix plancher central | PROUVÉE | `SalesPriceGuardService`, `SalesFloorWaiverTest` | frais/zone/coût lot non tous intégrés |
| Dérogation maker-checker | PROUVÉE | `SalesFloorWaiverService` et test dédié | double seuil financier à approfondir |
| Validation applicative | PROUVÉE | modale centrale + `SalesConfirmationModalTest` | résumé économique enrichi incomplet |
| Conversion devis→commande | PROUVÉE | `QuoteWorkflowTest` | conversion partielle NON IMPLÉMENTÉE |
| Contrôle crédit prévisionnel | PROUVÉE | `CustomerCreditExposureService`, tests SQLite/MySQL | garanties/impayés chèque non intégrés |
| Commande cash/acompte | PROUVÉE | `ComptantAcompteChainTest` | autorisation exceptionnelle à formaliser |
| MTO | PROUVÉE | `SalesMtoTriggerTest`, chaîne O2C | clôture avec OF ouvert à renforcer |
| MTS/réservation | PROUVÉE | `SalesStockReserveTest` | concurrence multi-connexion à ajouter |
| Libération réservations | PROUVÉE | `ReservationReleaseTest` | audit périodique à généraliser |
| Bon de préparation | PARTIELLEMENT PROUVÉE | `DeliveryAfterLoadingTest` | contrôle, scan et écarts incomplets |
| Livraison partielle | PROUVÉE | services + tests lots/stock | parcours UI authentifié non testé |
| Qualité avant livraison | PROUVÉE | `ProductionDeliveryGuardTest` | règles quarantaine à étendre par lot |
| Annulation BL | PARTIELLEMENT PROUVÉE | contre-mouvements implémentés | concurrence facture/annulation à prouver |
| Facture depuis BL | PROUVÉE | verrou BL + garde doublon | contrainte DB unique à évaluer |
| Facturation partielle | PARTIELLEMENT PROUVÉE | quantités livrées/facturées suivies | facture de situation/solde incomplète |
| Comptabilisation SYSCOHADA | PROUVÉE | `VentesAcceptanceTest`, `AccountingService` | certification exhaustive par nature à poursuivre |
| Coût des ventes multi-lots | PROUVÉE | `DeliveryLotAllocationTest`, audit allocations | FIFO paramétrable à documenter |
| Paiements/lettrage | PROUVÉE | tests paiements et acompte | concurrence double encaissement à étendre |
| Avoir financier | PROUVÉE | `CreditNoteService` | origine sans facture sous permission à formaliser |
| Retour physique/disposition | PROUVÉE PARTIELLEMENT | `CreditNoteReturnDispositionTest` | objet RMA distinct absent |
| Rapports/export/PDF | PARTIELLEMENT PROUVÉE | PDF devis/BL/facture/avoir, exports | rapports agence/zone/service incomplets |
| Parcours navigateur A–G | NON TESTÉE | authentification bloquante | recette UI requise |