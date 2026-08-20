# BUG-A3-SALES-MONEY-PRECISION-031 — Tolérance monétaire en dur dans la garde du prix plancher

**Statut** : OUVERT — non corrigé
**Ouvert le** : 2026-08-07
**Origine** : audit §9 de la correction BUG-A3-SALES-ZERO-PRICE-026
**Sévérité** : faible en exploitation actuelle, structurelle à terme

## Constat

`app/Services/SalesFloorWaiverService.php`, garde 2 de `assertDocumentMayProceed()` :

```php
if ($pricing['minimum_price'] <= $pricing['net_price'] + 0.005) {
    continue;
}
```

Le `0.005` est une tolérance écrite en dur. Elle vaut « un demi-centime » — une
subdivision qui suppose deux décimales.

Le même service calcule par ailleurs `net_price` avec `round($net, 2)`
(méthode `pricing()`), qui repose sur la même supposition.

## Pourquoi c'est faux

La devise de l'installation est le franc CFA (XOF), dont
`currencies.decimal_places` vaut **0**. Le franc n'a pas de centime : la
tolérance invente une subdivision que la monnaie ignore.

Effet concret : une vente à 5 999,996 F sous un plancher de 6 000 F passe la
garde. Le montant n'existe pas — il n'est pas représentable en XOF — mais aucun
arrondi préalable ne le ramène à sa devise avant la comparaison.

Symétriquement, sur une devise à deux décimales, `+ 0.005` avale un demi-centime
réel et laisse passer un écart que la monnaie sait exprimer.

## Portée exacte

Ce défaut affecte **la garde 2 uniquement** — le prix plancher.

La garde 1 (prix nul, BUG-026) n'est pas concernée : elle est passée à
`CommercialLinePriceRule::estGratuit()`, qui arrondit à la précision de la devise
lue sur le document (`quotes.currency_code`, `orders.currency_code`,
`invoices.currency_code`) avant toute comparaison, sans aucun seuil.

## Correction attendue

Remplacer la comparaison tolérancée par un arrondi à la devise du document, sur
le modèle de `CommercialLinePriceRule::arrondiMonetaire()` :

```php
$devise = $document->currency_code ?? null;
$net     = CommercialLinePriceRule::arrondiMonetaire($pricing['net_price'], $devise);
$plancher = CommercialLinePriceRule::arrondiMonetaire($pricing['minimum_price'], $devise);
if ($plancher <= $net) {
    continue;
}
```

Le `round($net, 2)` de `pricing()` doit suivre le même traitement — mais il
alimente aussi `pricing_signature`, qui est stockée en base sur les dérogations
existantes. **Changer l'arrondi change la signature** : les dérogations déjà
approuvées cesseraient de correspondre à leur ligne et seraient rejetées comme
périmées.

## Pourquoi ce n'est pas corrigé ici

Deux raisons, aucune n'étant l'urgence :

1. **Périmètre.** BUG-026 traite la vente à zéro. La précision du plancher est un
   défaut voisin mais distinct ; les mélanger produirait un commit dont on ne
   pourrait pas défaire une moitié.
2. **Migration de données.** Le point `pricing_signature` ci-dessus exige une
   décision sur le sort des dérogations en cours (recalcul, invalidation
   explicite, ou versionnement de la signature). Cette décision est métier.

## Vérification à produire lors de la correction

- Une vente sous plancher d'un montant non représentable dans la devise est
  refusée (XOF : 5 999,996 F sous plancher 6 000 F).
- Une dérogation approuvée avant la correction reste valide, ou est explicitement
  invalidée avec trace — jamais silencieusement ignorée.
- Suite MySQL complète : `./vendor/bin/pest -c phpunit.mysql.xml`.
