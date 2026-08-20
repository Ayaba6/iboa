# BUG-A3-SETTINGS-MASS-ASSIGNMENT-037 — Le paramétrage général accepte des champs non validés et laisse modifier des contrôles métier sensibles

**Statut** : CONFIRMÉ
**Priorité** : P1
**Ouvert le** : 2026-08-08
**Origine** : audit de `http://127.0.0.1/iboa/public/parametrage`
**Correction** : PAS dans le lot Sales — branche dédiée

## Chaîne exacte

```
vue         resources/views/company/edit.blade.php   (43 champs, 6 onglets)
route       PUT /parametrage/general                 (permission settings.manage)
contrôleur  CompanyController::updateGeneral()
              $this->service->updateGeneral($company, $request->except('logo'), …)
                                                     ^^^^^^^^^^^^^^^^^^^^^^^^
service     CompanyService::updateGeneral()
              $company->update($data);               ← mass assignment direct
modèle      Company::$fillable                       (40+ champs)
```

`except('logo')` transmet **l'intégralité du corps de la requête**, et non
`validated()`. Tout champ présent dans `$fillable` devient donc modifiable,
qu'une règle de validation existe ou non.

## Champs sensibles, fillable, SANS règle de validation

Mesuré sur `app/Http/Requests/Company/UpdateGeneralRequest.php` (43 règles) :

| Champ | Règle | Présent dans le formulaire | Effet d'un POST forgé |
|---|---|---|---|
| `allow_self_validation` | 0 | non | **désactive le maker-checker** |
| `validation_mode` | 0 | non | change le circuit de validation |
| `current_fiscal_year_id` | 0 | non | bascule l'exercice comptable courant |
| `is_vat_subject` | 0 | non | change l'assujettissement TVA |
| `default_currency_id` | 0 | **oui** | change la devise société, sans `exists:` |

Les quatre premiers ne figurent pas dans l'écran : aucun usage légitime ne
passe par cette route pour les modifier.

## Preuve d'impact — `allow_self_validation`

Ce drapeau est la seule condition qui protège la séparation des tâches :

```php
// app/Traits/HasCommercialWorkflow.php:317-326
if (
    $company
    && $company->validation_mode === 'double'
    && ! ($company->allow_self_validation ?? false)   // ← ce drapeau
    && $this->submitted_by
    && $this->submitted_by === Auth::id()
) {
    throw new \RuntimeException(
        'Validation impossible : en mode double validation, le soumetteur ne peut pas valider son propre document.'
    );
}
```

Un `PUT /parametrage/general` portant `allow_self_validation=1` neutralise donc
le contrôle qui empêche un utilisateur de valider ses propres documents.

## Pourquoi P1 malgré `settings.manage`

Ce n'est pas une élévation de privilège : la route exige déjà une permission
élevée. C'est un **contournement de contrôle interne par un canal non prévu** —
sans validation, sans écran dédié, sans permission spécialisée, et sans que
l'intention de modifier ces politiques soit exprimée nulle part.

Les invariants atteignables ainsi :

```
maker-checker (séparation des tâches)
exercice comptable de rattachement
assujettissement TVA
mode de validation des documents
devise de la société
```

Le principe même du maker-checker est qu'il ne se désactive pas par effet de
bord d'un formulaire d'informations générales.

## Couverture de test

```
grep -rl "updateGeneral\|company.update.general" tests/   →  0 fichier
```

Aucun test ne couvre ce chemin.

## Direction de correction — à instruire dans le lot dédié

`$request->except('logo')` ne doit pas alimenter `$company->update()`.
La cible est `$request->validated()` ou une liste blanche explicite.

**Mais `validated()` n'est sûr que si les règles couvrent réellement tous les
champs légitimes de la page.** Le lot devra donc d'abord établir :

```
43 champs affichés
  → règle de validation correspondante
  → champs réellement modifiables par cette page
  → champs sensibles à EXCLURE du mass assignment général
```

Points particuliers :

- **`default_currency_id`** — ajouter `exists:currencies,id`, et vérifier si A3
  interdit une devise `is_active = false`.
- **`allow_self_validation` / `validation_mode`** — décider s'ils doivent
  disparaître de `/parametrage/general` ou recevoir un écran dédié avec
  permission spécialisée et journalisation. Une permission spécialisée assortie
  d'un audit est préférable à une modification indirecte via `settings.manage`.
- **`current_fiscal_year_id`** — le plus sensible. La bascule d'exercice ne peut
  pas être un effet de bord : il faut instruire le service métier de bascule,
  les périodes ouvertes, les écritures, le verrouillage, l'autorisation et la
  journalisation. Un simple `exists:` ne suffit pas ; le champ doit
  probablement sortir du mass assignment général.
- **`is_vat_subject`** — un passage assujetti → non assujetti change les
  traitements fiscaux. Validation, autorisation, audit et impact documenté.

## Ce qui n'a pas été fait

Aucune modification. `CompanyController`, `CompanyService`, les requêtes de
paramétrage et le modèle `Company` sont intacts.
