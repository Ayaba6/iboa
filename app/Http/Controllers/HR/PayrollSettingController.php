<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * [RH-PRO] Paramétrage de la paie (singleton par entreprise).
 * Taux CNSS, barème IUTS, jours ouvrés, HS, etc.
 */
class PayrollSettingController extends Controller
{
    public function edit()
    {
        $company = Company::findOrFail(Auth::user()->company_id);
        $setting = PayrollSetting::forCompany($company->id);

        // Garantir que iuts_brackets est un tableau utilisable dans la vue
        $brackets = $setting->iuts_brackets ?: PayrollSetting::defaultIutsBrackets();

        // Config complète pour Alpine.js — calculée en PHP, transmise via @json($cfg)
        // On gère ici le cas "old input" (retour après erreur de validation)
        if (old('brackets')) {
            $bracketsCfg = array_values(array_map(
                fn($b) => ['limit' => (float) $b['limit'], 'rate' => (float) $b['rate']],
                old('brackets')
            ));
        } else {
            $bracketsCfg = array_values(array_map(
                fn($b) => ['limit' => (float) $b[0], 'rate' => (float) $b[1]],
                $brackets
            ));
        }

        $cfg = [
            'smig'             => (float) (old('smig')               ?? $setting->smig),
            'workDaysMonth'    => (int)   (old('work_days_month')    ?? $setting->work_days_month),
            'workHoursDay'     => (int)   (old('work_hours_day')     ?? $setting->work_hours_day),
            'cnssEmployee'     => (float) (old('cnss_employee_rate') ?? $setting->cnss_employee_rate),
            'cnssEmployer'     => (float) (old('cnss_employer_rate') ?? $setting->cnss_employer_rate),
            'cnssCeiling'      => (int)   (old('cnss_ceiling')       ?? $setting->cnss_ceiling),
            // Charges de famille (réduction IUTS) — remplace le quotient familial
            'familyReductions' => collect($setting->iuts_family_reductions ?: PayrollSetting::defaultFamilyReductions())
                                    ->mapWithKeys(fn($r) => [(int) $r[0] => (float) $r[1]])->toArray(),
            'maxCharges'       => (int)   (old('iuts_max_charges')   ?? $setting->iuts_max_charges ?? 4),
            'cnssPension'      => (float) (old('cnss_employer_pension_rate') ?? $setting->cnss_employer_pension_rate ?? 8.5),
            'cnssRp'           => (float) (old('cnss_employer_rp_rate')      ?? $setting->cnss_employer_rp_rate ?? 1.5),
            'cnssPf'           => (float) (old('cnss_employer_pf_rate')      ?? $setting->cnss_employer_pf_rate ?? 6.0),
            'bulletinPrefix'   => (string)(old('bulletin_prefix')    ?? $setting->bulletin_prefix ?? ''),
            // [P3.B] Effort de paix
            'effortPaixEnabled'=> (bool)  (old('effort_paix_enabled') ?? $setting->effort_paix_enabled ?? true),
            'effortPaixRate'   => (float) (old('effort_paix_rate')    ?? $setting->effort_paix_rate ?? 1),
            // [NO-HARDCODE] Ancienneté
            'ancRatePerYear'   => (float) (old('anc_rate_per_year') ?? $setting->anc_rate_per_year ?? 2.0),
            'ancRateMaxPct'    => (float) (old('anc_rate_max_pct')  ?? $setting->anc_rate_max_pct  ?? 25.0),
            'brackets'         => $bracketsCfg,
        ];

        return view('rh.parametrage.edit', compact('setting', 'brackets', 'cfg', 'company'));
    }

    public function update(Request $request)
    {
        $company = Company::findOrFail(Auth::user()->company_id);

        $validated = $request->validate([
            'cnss_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_ceiling'       => ['required', 'integer', 'min:0'],
            'cnss_at_rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'smig'               => ['required', 'integer', 'min:0'],
            'work_days_month'    => ['required', 'integer', 'min:1', 'max:31'],
            'work_hours_day'     => ['required', 'integer', 'min:1', 'max:24'],
            'leave_days_year'    => ['required', 'integer', 'min:1', 'max:365'],
            'hs_rate_25'         => ['required', 'numeric', 'min:0', 'max:500'],
            'hs_rate_50'         => ['required', 'numeric', 'min:0', 'max:500'],
            'hs_rate_nuit'       => ['required', 'numeric', 'min:0', 'max:500'],
            // Charges de famille — réduction sur l'IUTS brut
            'family_reductions'   => ['required', 'array'],
            'family_reductions.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'iuts_max_charges'    => ['required', 'integer', 'min:0', 'max:10'],
            // Ventilation CNSS patronale (pension / risques pro / prestations familiales)
            'cnss_employer_pension_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_employer_rp_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_employer_pf_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'bulletin_prefix'     => ['required', 'string', 'max:10'],
            'currency_code'       => ['required', 'string', 'max:10'],
            'country_code'        => ['required', 'string', 'max:5'],
            'cnss_affiliation'    => ['nullable', 'string', 'max:30'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'address_bulletin'    => ['nullable', 'string', 'max:200'],
            'notes'               => ['nullable', 'string', 'max:2000'],

            // [P3.B] Effort de paix
            'effort_paix_enabled' => ['nullable', 'boolean'],
            'effort_paix_rate'    => ['required', 'numeric', 'min:0', 'max:20'],

            // [NO-HARDCODE] Ancienneté
            'anc_rate_per_year'   => ['required', 'numeric', 'min:0', 'max:10'],
            'anc_rate_max_pct'    => ['required', 'numeric', 'min:0', 'max:100'],

            // Barème IUTS : tableau d'entrées [plafond, taux]
            'brackets'           => ['required', 'array', 'min:1'],
            'brackets.*.limit'   => ['required', 'numeric', 'min:1'],
            'brackets.*.rate'    => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Reconstruire le JSON du barème, trié par plafond croissant
        $brackets = collect($validated['brackets'])
            ->sortBy('limit')
            ->values()
            ->map(fn($b) => [(int) $b['limit'], (float) $b['rate']])
            ->toArray();

        // La dernière tranche doit avoir un plafond très élevé (∞ simulé)
        // On force la dernière à 9_999_999_999 si l'utilisateur a saisi autre chose
        if (count($brackets) > 0) {
            $last = &$brackets[count($brackets) - 1];
            $last[0] = 9_999_999_999;
        }

        $setting = PayrollSetting::firstOrNew(['company_id' => $company->id]);
        // Les checkbox non cochées n'envoient rien — forcer false si absent
        $validated['effort_paix_enabled'] = $request->boolean('effort_paix_enabled');

        // Réductions charges de famille : {n => pct} → [[n, pct], ...] trié
        $reductions = collect($validated['family_reductions'])
            ->map(fn($pct, $n) => [(int) $n, (float) $pct])
            ->sortBy(0)->values()->toArray();

        // Le taux patronal total est TOUJOURS la somme de la ventilation —
        // la valeur saisie séparément ne peut pas diverger des composantes.
        $validated['cnss_employer_rate'] = round(
            (float) $validated['cnss_employer_pension_rate']
            + (float) $validated['cnss_employer_rp_rate']
            + (float) $validated['cnss_employer_pf_rate'],
            2
        );

        $setting->fill(array_merge(
            \Arr::except($validated, ['brackets', 'family_reductions']),
            [
                'iuts_brackets'          => $brackets,
                'iuts_family_reductions' => $reductions,
                'updated_by'             => Auth::id(),
            ]
        ));
        $setting->company_id = $company->id;
        $setting->save();

        // Invalider le cache
        PayrollSetting::clearCache($company->id);

        return redirect()->route('rh.parametrage.edit')
            ->with('success', 'Paramètres de paie mis à jour avec succès.');
    }
}
