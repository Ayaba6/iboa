# Réponse aux anomalies — Campagne de test n°2 (scénario MTO CLI-00008)

**Date :** 14/07/2026 · **Périmètre :** rapport de test fonctionnel « TEST MTO OA METAL SARL »
(DEV-2026-00037 → CMD-2026-047 → OF-2026-0152/0153 → BL-2026-026 → FA-2026-019 → ENC-2026-013)

Toutes les anomalies ont été reproduites en base, diagnostiquées à la racine et corrigées.
Tests de non-régression : `tests/Feature/MtoReportRound2FixesTest.php` (6 tests).

---

## Anomalie n°1 — Déclaration de production instable (élevée) — CORRIGÉE

**Diagnostic réel.** Les déclarations de l'OF-2026-0153 n'étaient pas « ignorées » : elles
étaient **refusées puis annulées intégralement (rollback)** par la consommation automatique
des composants de la nomenclature (backflush). La nomenclature NOM-PFTBC0001 sort son
composant bobine du dépôt « Dépôt Tôles Bacs », dont le stock théorique (4,558) était
inférieur au besoin (10 × 1,7513 = 17,513) — **alors que la matière réelle avait déjà été
consommée via la bobine BOB-TEST-GUIDE-001** (conso n°7, 36 kg / 17,51 m). Double comptage
matière : la bobine (source de vérité, lot + poids) ET le stock théorique étaient débités.
Sur l'OF-2026-0152, ce double comptage a réussi (70,05 débités à tort du dépôt Tôles Bacs),
vidant le stock théorique et bloquant l'OF-2026-0153.

**Correctif.** `ProductionStockService::consumeBomComponents()` : un composant dont l'OF a
déjà une consommation bobine du même article n'est **plus backflushé** (même règle que le
dédoublonnage du coût de revient livré après la campagne n°1). L'annulation d'une
déclaration contre-passe désormais les mouvements réellement générés (pas de recalcul BOM →
pas de stock fantôme). La déclaration de l'OF-2026-0153 passe désormais.

## Anomalie n°2 — Message « Stock insuffisant 4,56 / 17,51 » incohérent (moyenne) — CORRIGÉE

**Diagnostic.** Pas une erreur de conversion d'unités : c'était le refus du backflush
ci-dessus, avec le message générique du module stock qui ne nommait **ni le composant ni le
dépôt** — incompréhensible pour l'opérateur qui déclarait « 10 feuilles ».

**Correctif.** Le cas du test disparaît avec le correctif n°1. Si un backflush échoue
réellement (pas de consommation bobine), le message nomme désormais le composant, le dépôt
de sortie de la nomenclature, et les actions possibles : *« Déclaration refusée —
consommation automatique du composant « X » impossible : … (dépôt de sortie nomenclature :
Y). Réapprovisionnez ce dépôt, corrigez le dépôt de sortie de la nomenclature, ou déclarez
la consommation bobine avant la déclaration de production. »*

## Anomalie n°3 — Consommation « refusée » mais stock décrémenté (moyenne) — CORRIGÉE

**Diagnostic.** La consommation de 36 kg (21:06) a en réalité **réussi** et est bien tracée
sur l'OF-2026-0152 (consommation n°6 en base — aucune sortie sans traçabilité). La confusion
vient du statut « Terminé partiellement » : par conception, la clôture en écart laisse l'OF
actif (reliquat à produire), donc les saisies de suivi restent acceptées — mais rien ne
l'indiquait à l'écran.

**Correctif.** Bannière explicite sur la fiche OF au statut « Terminé partiellement » :
reliquat restant, rappel que les saisies de suivi restent possibles jusqu'à la clôture
définitive. Nota : la consommation n°6 (36,01 kg) fait doublon avec la n°7 (36 kg sur
l'OF-2026-0153) pour les mêmes 10 unités — annulable depuis la fiche OF-2026-0152
(« poids restitué à la bobine »).

## Anomalie n°4 — Quantité BL par défaut > stock (moyenne) — CORRIGÉE

**Correctif.** `DeliveryNoteService::createFromOrder()` : la quantité proposée est désormais
le **reliquat non livré, plafonné au stock disponible pour la commande** au dépôt du BL
(disponible général + réservations propres de la commande). Commande de 50 avec 40 en
stock → BL proposé à 40. La ligne reste éditable ; le contrôle bloquant à la validation du
BL est inchangé (testé).

## Anomalie n°5 — Ligne « Facture supprimée » + Total imputé faux (faible) — CORRIGÉE

**Correctif.** Fiches encaissement **et** décaissement : les imputations pointant une
facture supprimée sont exclues du tableau et du « Total imputé » (ENC-2026-013 affiche
désormais 377 600, plus 495 600). Elles restent signalées par une note d'audit ambrée :
*« N imputation(s) liée(s) à des factures supprimées (montant) — exclue(s) du total »*.

---

## Note d'environnement

Une partie des retours « silencieux » observés s'explique aussi par un incident
d'environnement corrigé depuis : un fichier `public/hot` orphelin (serveur Vite arrêté)
empêchait le chargement du JavaScript — donc des **toasts de succès/erreur** — pendant une
partie de la campagne. Les messages flash et bannières d'erreurs s'affichent normalement.

## Données de test résiduelles (aucune action automatique)

- Dépôt « Dépôt Tôles Bacs » : 70,05 unités de composant débitées à tort par le double
  comptage de l'OF-2026-0152 (avant correctif) — à régulariser par ajustement d'inventaire
  si souhaité.
- Consommation n°6 (36,01 kg sur OF-2026-0152) : doublon fonctionnel — annulable depuis la
  fiche OF (restitue le poids à la bobine).
- OF-2026-0153 : peut désormais être déclaré (10 unités) puis clôturé.
