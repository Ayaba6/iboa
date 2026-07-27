# Registre — réceptions historiques NON CERTIFIÉES (base dev `iboa_erp`)

> **Lecture seule.** Aucune reclassification automatique, aucun mouvement supprimé,
> aucun stock déplacé, aucun coût recalculé. Ces lignes ne sont PAS certifiées et
> ne servent PAS de preuve des nouveaux workflows. Traitement **manuel** via le
> workflow de certification historique (autorisation spécifique requise avant
> toute correction). Statut : **anomalies critiques de données** — signalées par
> `a3:audit-receptions` (exit 1), et bloquantes pour la certification globale.

## Ligne 1 — reception_items #3

| Champ | Valeur |
|---|---|
| Réception | REC-2026-001 (validée 2026-07-22 17:36, user_id 1) |
| Commande fournisseur | CA-2026-001 |
| Fournisseur | Fournisseur Test SARL |
| Article | Bobine prélaquée beige 27/100 |
| Lot / bobine | non renseigné |
| Quantité reçue | 10,0000 |
| Disposition | **UNKNOWN** (accepted NULL, `legacy_unclassified`) |
| Quantité reconstruite (informative) | 10,0000 — `RECONSTRUCTED` |
| Coût unitaire ligne | **0** (coût nul — anomalie supplémentaire) |
| Mouvement lié | stock_movements #69, entrée 10, entrepôt 2 |
| Documents disponibles | aucun justificatif qualité tracé |
| Action manuelle attendue | examiner → certifier (avec justificatif) OU maintenir non certifiée OU régulariser |

## Ligne 2 — reception_items #4

| Champ | Valeur |
|---|---|
| Réception | REC-2026-002 (validée 2026-07-23 17:16, user_id 1) |
| Commande fournisseur | CA-2026-002 |
| Fournisseur | Fournisseur Test SARL |
| Article | Bobine prélaquée beige 27/100 |
| Lot / bobine | non renseigné |
| Quantité reçue | 2,0000 |
| Disposition | **UNKNOWN** (accepted NULL, `legacy_unclassified`) |
| Quantité reconstruite (informative) | 2,0000 — `RECONSTRUCTED` |
| Coût unitaire ligne | 8 850 |
| Mouvement lié | stock_movements #75, entrée 2, entrepôt 2 |
| Documents disponibles | aucun justificatif qualité tracé |
| Action manuelle attendue | idem ligne 1 |

## État de stock consolidé (article commun)

Reçu historique cumulé 12,0000 ; stock actuel produit **2,7548** → ~9,25 consommé
en production (mouvements aval réels). Une régularisation éventuelle devra tenir
compte des consommations déjà réalisées — raison supplémentaire de ne rien
corriger automatiquement.

## Workflow de certification historique (préparé, non exécuté)

```
À examiner → Justificatifs disponibles → Certification proposée → Certification approuvée
À examiner → Justificatifs insuffisants → Maintenue non certifiée
À examiner → Anomalie confirmée → Régularisation requise
```

Exigences à l'exécution (autorisation spécifique préalable) : motif, document ou
référence, quantité, auteur de la proposition, **approbateur distinct**
(auto-approbation interdite), date, empreinte des justificatifs, journal d'audit.
