# MATRICE DES PERMISSIONS VENTES

| Domaine | Permissions actuelles | Couverture | Écart cible |
|---|---|---|---|
| Devis | `quotes.*`, `sales.submit/validate/reject/cancel/transform`, `sales_below_floor.*` | PARTIELLEMENT PROUVÉE | séparer envoyer/accepter et seuils de dérogation |
| Commandes | `orders.*`, `sales.*`, `production.approve_financial` | PROUVÉE pour workflow courant | ajouter suspendre/clôturer explicites |
| Préparations | `bon_preparations.view/update` | INCOMPLÈTE | créer/lancer/préparer/contrôler/valider/annuler distincts |
| Livraisons | `deliveries.view/create/edit/validate`, `sales.*` | PARTIELLEMENT PROUVÉE | expédier/confirmer/annuler techniquement distincts |
| Factures | `invoices.view/create/edit/delete/validate/send` | PARTIELLEMENT PROUVÉE | comptabiliser/extourner/imprimer distincts |
| Avoirs | `credit_notes.view/create/edit`, `sales.*` | PARTIELLEMENT PROUVÉE | approuver/comptabiliser/rembourser/contre-opérer distincts |
| Maker-checker | `MakerCheckerService`, bypass contrôlé | PROUVÉE sur actions couvertes | étendre à toutes les actions sensibles |

## Rôles observés

Commercial, responsable commercial, comptable, DAF, magasinier, chef production, directeur usine, directeur et super administrateur. Les affectations sont centralisées dans `RolesAndPermissionsSeeder`.

## Réserve

Les routes `resource` sont parfois groupées sous une permission de consultation ; la défense finale repose aussi sur les policies et autorisations des contrôleurs. Une campagne dédiée route×permission reste requise avant certification.