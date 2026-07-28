# MACHINE D’ÉTATS VENTES

## Devis

`brouillon → en_attente_validation → valide → converti`

Branches : `en_attente_validation → brouillon` (refus), puis `annule`; expiration `valide/envoye → expire`; révision liée et versionnée. Conversion partielle non implémentée.

## Commande

Axe principal actuel :

`brouillon → en_attente_validation → confirme → en_preparation → partiellement_livre → livre → facture`

Branches : `annule`, réouverture contrôlée. Axes complémentaires : approbation production, paiement/éligibilité financière, progression OF et quantités livrées/facturées. L’objectif multidimensionnel finance/logistique/facturation n’est que partiellement matérialisé.

## Préparation

États actuels principalement liés au chargement (`à charger/en chargement/chargé`). La cible brouillon→préparer→préparation partielle→préparé→contrôlé→validé→annulé est incomplète.

## Livraison

`brouillon → en_attente_validation → valide`, puis `annule` par contre-opération. Les étapes expédié/livré/retourné partiellement ne sont pas toutes distinctes.

## Facture

`brouillon → en_attente_validation → emise/envoyee → partiellement_payee → payee`, branche `en_retard`, annulation `annulee`. Comptabilisation est actuellement un effet de validation plutôt qu’un axe séparé.

## Avoir

`brouillon → en_attente_validation → valide → applique/rembourse`, branche annulation par contre-opération. Les dimensions financier/quantitatif/retour sont portées par les lignes et dispositions.

## Invariants prouvés

- verrou pessimiste et relecture du statut pour validations sensibles ;
- maker-checker sur dérogations et avoirs sensibles ;
- pas de livraison avant stock/qualité ;
- pas de facture en double depuis un BL ;
- annulation avec contre-effets, sans suppression physique des documents validés.