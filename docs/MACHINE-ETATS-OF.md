# Machine d’états des ordres de fabrication

```text
brouillon → soumis → attente_validation_chef → attente_validation_responsable
→ matiere_a_allouer → matiere_allouee
→ autorisation_financiere_requise → pret_a_lancer → lance → en_cours
→ suspendu ↔ en_cours → partiellement_produit → attente_qualite
→ termine → cloture
                         ↘ annule (selon gardes et contre-mouvements)
```

| Transition | Permission | Conditions minimales | Effets |
|---|---|---|---|
| Soumettre | `production.submit_validation` | article, quantité, dates, origine | audit + notification |
| Visa chef | `production.validate_chef` | auteur différent si requis | statut uniquement |
| Visa responsable | `production.validate_responsable` | visa chef | statut uniquement |
| Allouer | `production.update` | stock disponible, bobine compatible | réservation idempotente |
| Autoriser finance | `production.approve_financial` | commande bloquée/exception | trace acteur, motif, date |
| Lancer | `production.launch` | validations, matière, ressource disponible | gel références BOM/gamme attendu |
| Déclarer | `production.declare` | OF lancé/en cours | consommations/sorties/rebuts tracés |
| Suspendre/reprendre | `production.update` | motif obligatoire | temps/charge recalculés |
| Terminer | `production.update` | quantités et contrôles cohérents | attente qualité ou terminé |
| Clôturer | `production.validate` | écarts traités, qualité libérée | coûts et comptabilité finaux |
| Annuler | `production.cancel` | garde selon avancement | libération ou contre-mouvements ; aucune suppression |

Cas d’annulation à distinguer : non lancé, matière réservée, matière consommée, production partielle, PF entré, PF livré, OF clôturé. Les deux derniers exigent une procédure d’extourne/dérogation et ne doivent jamais effacer l’historique.
