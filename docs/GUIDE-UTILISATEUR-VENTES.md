# GUIDE UTILISATEUR — MODULE VENTES

## Navigation

La barre Ventes donne accès à : Tableau de bord, Devis, Commandes, Préparations, Livraisons, Factures et Avoirs. Les onglets visibles dépendent des permissions.

## Vente standard MTS

1. Créer le devis et renseigner client, articles, quantités, prix, taxes et échéance.
2. Soumettre le devis. Toute ligne sous prix plancher doit disposer d’une dérogation approuvée.
3. Faire valider puis convertir le devis en commande.
4. Soumettre la commande. Le système calcule l’encours prévisionnel et bloque un dépassement de plafond.
5. Après validation financière, réserver le stock et lancer la préparation.
6. Terminer le chargement, créer et valider le BL.
7. Générer la facture depuis le BL, la valider puis enregistrer les règlements.

## Vente MTO

Après confirmation financière, la commande peut générer un OF pour le manque à produire. La livraison reste bloquée tant que la quantité produite et le contrôle qualité ne sont pas conformes.

## Prix sous plancher

La soumission est bloquée. Créer une demande de dérogation avec motif et justificatif. Un autre acteur autorisé doit l’approuver. Toute modification économique invalide l’autorisation.

## Avoir et retour

Créer l’avoir depuis la facture, renseigner le motif et la disposition de chaque ligne. Seules les lignes retournées en stock vendable restaurent automatiquement le stock. Le rebut ne le restaure pas. L’avoir peut être appliqué ou remboursé selon les permissions.

## Annulations

Ne jamais supprimer un document validé. Utiliser l’action d’annulation avec motif. Le système contrôle les documents aval et génère les contre-effets permis. Une facture payée ou un BL déjà facturé ne peut pas être annulé directement.

## Messages de confirmation

Toutes les actions sensibles passent par la modale applicative. Si elle n’est pas disponible, l’action est bloquée et un message visible demande de recharger la page.