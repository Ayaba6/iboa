# Skill : erp-paie-burkina

Expert paie et droit social Burkina Faso pour l'ERP IBOA.
Intervient sur le module RH/Paie : calcul des bulletins, cotisations sociales,
IUTS, états de paiement, clôture mensuelle.

## Contexte projet

- **Pays** : Burkina Faso — législation du travail + convention collective
- **Devise** : XOF (pas de décimales, arrondi à l'entier)
- **Modèles clés** :
  `Employee`, `EmployeeContract`, `PayrollRun`, `PayrollItem`,
  `PayrollSetting`, `PayrollPlan`, `PayRubric`, `PayrollProfile`,
  `IutsBracket`, `SocialContribution`, `PayrollPeriod`,
  `SalaryAdvance`, `EmployeeLoan`, `EmployeeAllowance`
- **Contrôleurs** : `app/Http/Controllers/HR/`
- **Vues** : `resources/views/rh/`
- **Services** : `app/Services/BulletinNumberingService.php`, `app/Services/PayrollPeriodService.php`

## Barèmes légaux Burkina Faso

### CNSS (Caisse Nationale de Sécurité Sociale)
> Référence validée 17/07/2026 — implémentée dans `PayrollService` + `PayrollSettingSeeder`.

| Branche | Salarié | Employeur |
|---------|---------|-----------|
| Pension | 5,5% | 8,5% |
| Risques professionnels (RP) | 0% | 1,5% |
| Prestations familiales (PF) | 0% | 6% |
| **Total** | **5,5%** | **16%** |

- **Plafond mensuel : 800 000 XOF** (`payroll_settings.cnss_ceiling`) ; plafond annuel 9 600 000 (`cnss_annual_ceiling`)
- Base CNSS = MIN(salaire soumis, 800 000)
- La ventilation patronale (pension/RP/PF) est stockée par bulletin
  (`payroll_items.cnss_employer_pension/rp/pf`) et comptabilisée en 3 lignes distinctes.

### IUTS (Impôt Unique sur les Traitements et Salaires)
> Référence validée 17/07/2026 — le calcul se fait sur le revenu imposable TOTAL
> (PAS de quotient familial par parts), puis réduction pour charges de famille.

Base imposable = Salaire brut − CNSS salarié − abattement 20 %

Barème progressif mensuel (`payroll_settings.iuts_brackets`) :

| Tranche (XOF) | Taux |
|---------------|------|
| 0 – 30 000 | 0% |
| 30 001 – 50 000 | 12,1% |
| 50 001 – 80 000 | 13,9% |
| 80 001 – 120 000 | 15,7% |
| 120 001 – 170 000 | 18,4% |
| 170 001 – 250 000 | 21,7% |
| > 250 000 | 25% |

Réduction pour charges de famille, appliquée sur l'**IUTS brut**
(`payroll_settings.iuts_family_reductions`, plafond `iuts_max_charges` = 4) :

| Charges | Réduction |
|---------|-----------|
| 0 | 0% | 
| 1 | 8% |
| 2 | 10% |
| 3 | 12% |
| 4 et + | 14% |

`IUTS net = IUTS brut − réduction` — calcul : `PayrollSetting::computeIutsDetail()`
(détail par tranche persisté dans `payroll_items.iuts_detail`).

> Abattement forfaitaire IUTS : **20%** (configurable dans `payroll_settings.iuts_abattement_rate`)

### Effort de Paix
- Taux salarial : configurable (`payroll_settings.effort_paix_rate_salarie`)
- Taux patronal : configurable (`payroll_settings.effort_paix_rate_patronal`)

### Ancienneté
- Calculée en années complètes depuis `employee_contracts.start_date`
- Taux progressif stocké dans `payroll_settings` (champs `anc_*`)
- Typiquement : +2% par tranche de 5 ans

## Structure d'un bulletin IBOA

```
RUBRIQUE                    GAIN        RETENUE
─────────────────────────────────────────────
Salaire de base             500 000
Heures supplémentaires       20 000
Prime d'ancienneté           10 000
Indemnité transport          15 000
                            ─────────
Salaire brut imposable      530 000

CNSS salarié (5,5%)                      29 150
IUTS                                      XX XXX
Effort de paix salarial                    X XXX
Avances sur salaire                        X XXX
                                         ─────────
NET À PAYER                             XXX XXX
─────────────────────────────────────────────
CHARGES PATRONALES
CNSS patronal PF (5,75%)                 30 475
CNSS patronal AT                          X XXX
CNSS patronal RP (5,5%)                  29 150
Effort de paix patronal                   X XXX
```

## Tables DB et colonnes importantes

### `payroll_settings` — configuration paie par société (noms réels)
- `cnss_ceiling` — plafond CNSS mensuel (800 000) ; `cnss_annual_ceiling` (9 600 000)
- `cnss_employee_rate` — 5,5 %
- `cnss_employer_pension_rate` (8,5) / `cnss_employer_rp_rate` (1,5) / `cnss_employer_pf_rate` (6)
- `cnss_employer_rate` — total patronal (16, somme de la ventilation)
- `iuts_brackets` (JSON [[plafond, taux], …]) ; `iuts_abattement_rate` — 20 %
- `iuts_family_reductions` (JSON [[charges, pct], …]) ; `iuts_max_charges` — 4
- `effort_paix_enabled`, `effort_paix_rate`
- `smig` — SMIG mensuel (Burkina : 45 000 XOF)
- `anc_rate_per_year`, `anc_rate_max_pct` — ancienneté

### `payroll_items` — lignes de bulletin (une par employé et par run, noms réels)
- `salaire_brut`, `cnss_base`, `cnss_employee`, `cnss_employer`
- `cnss_employer_pension` / `cnss_employer_rp` / `cnss_employer_pf` — ventilation
- `salaire_imposable`, `family_charges`, `iuts_amount`, `iuts_detail` (JSON par tranche)
- `effort_paix_amount`, `salaire_net`, `cout_employeur`
- `nb_parts` — legacy quotient familial (bulletins antérieurs au 17/07/2026)

### `payroll_runs`
- `calculation_parameters_snapshot` (JSON) — paramètres figés au calcul ;
  un changement de barème ne modifie JAMAIS un run déjà calculé/validé

### `employee_contracts`
- `gross_salary` — salaire brut de base
- `type` — CDI / CDD / Stage / Consultant
- `status` — actif / termine / resilie
- `payroll_profile_id` — profil de paie associé

## Algorithme de calcul d'un bulletin (PayrollService::calculateItem)

```
1. Contrat actif + primes fixes + variables mensuelles (HS, absences, avances)
2. Salaire brut = base proratisé + HS + primes imposables + ancienneté
3. Base CNSS = min(brut − exclusions, 800 000)
4. CNSS salarié = base × 5,5 %
5. CNSS patronal = base × 8,5 % (pension) + base × 1,5 % (RP) + base × 6 % (PF)
   — arrondis séparés, total = somme des trois
6. Base imposable = brut − exclusions IUTS − CNSS salarié, puis abattement 20 %
7. Charges = min(nb_children, iuts_max_charges)
8. IUTS brut = barème progressif sur la base imposable TOTALE (computeIutsDetail)
9. IUTS net = IUTS brut − réduction charges (8/10/12/14 % de l'IUTS brut)
10. Effort de paix = (brut + non-imposables − CNSS sal. − IUTS) × 1 %
11. NET = brut + non-imposables + autres gains − CNSS sal. − IUTS − EP
        − avances − prêts − autres retenues
```

## Vérifications obligatoires avant clôture

```bash
# Vérifier que tous les employés actifs ont un bulletin
php artisan tinker --execute="
\$run = App\Models\PayrollRun::find(ID);
\$covered = \$run->items->pluck('employee_id')->unique();
\$active  = App\Models\Employee::where('status','actif')->pluck('id');
\$missing = \$active->diff(\$covered);
echo 'Employés sans bulletin: ' . \$missing->count();
"
```

## Fichiers clés

```
app/Http/Controllers/HR/PayrollRunController.php
app/Http/Controllers/HR/PayrollBulletinTemplateController.php
app/Services/BulletinNumberingService.php
app/Services/PayrollPeriodService.php
resources/views/rh/bulletins/
resources/views/rh/paie/
```

## Règles de réponse

- Toujours arrondir en XOF entier (pas de centimes).
- Toujours vérifier le plafond CNSS avant d'appliquer le taux.
- Mentionner la référence légale (Code du Travail BF, Décret CNSS, etc.) quand pertinent.
- Proposer le journal comptable SYSCOHADA pour chaque écriture de paie.
