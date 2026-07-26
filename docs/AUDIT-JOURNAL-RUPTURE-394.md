# Rapport de preuves — rupture de chaîne du journal d'audit (entrée 394)

> **Lecture seule.** Aucune ligne de `audit_logs` n'a été modifiée, aucun hash
> recalculé/réécrit, aucune commande `--fix` exécutée. Base concernée :
> `iboa_erp` (développement local). Rapport anonymisé (IP masquée, aucun secret
> ni donnée personnelle).

## 1. Faits établis (mesurés en lecture seule)

| Élément | Valeur |
|---|---|
| Base | `iboa_erp` (dev local) |
| Table | `audit_logs` |
| Nombre de lignes | **111** |
| Identifiant minimum | **275** |
| Identifiant maximum | **394** |
| Plage d'identifiants présents | 275 → 394 (120 ids possibles) |
| Identifiants non présents dans la plage | 9 (120 − 111) — **absence ≠ suppression prouvée** |
| Première rupture de chaîne | **entrée 394** |
| Nature de la rupture | **`row_hash_mismatch`** (hash de la ligne recalculé ≠ hash stocké) |
| Rupture de chaînage prev→prev | **NON** — le chaînage `prev_hash` est intact jusqu'à 394 |
| Dernière entrée vérifiable | **393** |
| `row_hash` attendu (recalculé) à 394 | `ee945ce9384b1628…` (tronqué) |
| `row_hash` rencontré (stocké) à 394 | `8e420d8c66ba1e84…` (tronqué) |
| Entrée 394 — horodatage | 2026-07-26 11:27:50 |
| Entrée 394 — événement | `auth.login` |
| Entrée 394 — modèle | `App\Models\User#22` |
| Entrée 394 — `user_id` | **NULL** |
| Entrée 394 — IP (masquée) | `127.0.0.xxx` (localhost) |
| Date de création de la base | indéterminée (non tracée) |
| Dernière restauration connue | indéterminée (non tracée) |

## 2. Ce que ces faits permettent — et ne permettent PAS — d'affirmer

- **Faux :** « 283 entrées ont été supprimées. » Les identifiants **commencent à
  275**, pas à 1 ; l'écart `COUNT=111` / `MAX=394` s'explique d'abord par le point
  de départ des identifiants, pas par des suppressions massives.
- **À ne pas conclure non plus :** la plage 275–394 contient **9 identifiants non
  présents** dans l'état actuel de la table, mais **cela ne prouve pas que 9 lignes
  ont été supprimées**. Leur absence peut provenir de transactions annulées,
  d'auto-incréments consommés, d'imports, de restaurations ou de suppressions.
  **Aucune conclusion définitive n'est possible sans preuves complémentaires.**
- **Établi :** la chaîne cryptographique est **rompue à l'entrée 394**, par un
  **`row_hash_mismatch`** : le hash recalculé de la ligne 394 diffère du hash
  stocké. Le chaînage `prev_hash` (lien vers la ligne précédente) est **intact**
  jusqu'à 394. L'historique présent ne permet pas, à ce stade, d'identifier avec
  certitude la cause ni un éventuel nombre d'entrées absentes.

## 3. Orientation de cause (à confirmer par tests reproducteurs)

La rupture est un **`row_hash_mismatch`** (et non un `prev_hash_mismatch`) : le
`prev_hash` de 394 peut rester cohérent avec la chaîne précédente, mais les
données actuelles de la ligne 394 ne reproduisent pas son `row_hash`. C'est
**compatible avec plusieurs scénarios, sans certitude** :

- **Cas B — défaut de payload / génération.** `user_id` présent au moment du hash
  mais stocké/relu NULL, divergence de sérialisation des valeurs nulles, ancienne
  version du calcul du hash, ou payload modifié par un observer.
- **Cas A — altération réelle** de la ligne après insertion.
- **Cas C — base reconstruite / reseedée** : moins typique ici (casserait plutôt
  le chaînage `prev_hash`), mais non exclu.

**La présence de `user_id = NULL` ne prouve pas à elle seule le cas B.**
Classification retenue : *cause compatible avec un défaut de payload OU une
altération de la ligne, non déterminée avec certitude*. Statut `iboa_erp` :
ROUGE ; aucun re-scellement autorisé.

## 4. Procédure interdite / autorisée

- **Interdit sans autorisation explicite et procédure formelle :** modifier la
  table, recalculer/réécrire un `row_hash`, exécuter `--fix` sur `iboa_erp`,
  « re-sceller » la chaîne. Le journal ne doit jamais être réparé de manière à
  masquer la rupture.
- **Autorisé (fait ici) :** lecture seule, export anonymisé, empreinte SHA-256.
- **Distinction à conserver :** *chaîne historique certifiée* (≤ 393) ·
  *rupture* (394) · *nouveau segment* éventuel démarré après incident, via un
  **événement de reprise explicite** conservant les anciennes valeurs, le motif,
  l'opérateur, l'horodatage et une empreinte du rapport — sans jamais prétendre
  que l'historique rompu reste authentifié.

## 5. Statut

Sécurité base locale `iboa_erp` : **ROUGE / NON CERTIFIÉ** tant que la rupture 394
n'est pas investiguée et tracée par une procédure formelle. Le développement
Achats se poursuit **exclusivement sur `iboa_erp_test`** (journal de test propre),
sans `--fix` sur `iboa_erp`, sans GO staging/production.
