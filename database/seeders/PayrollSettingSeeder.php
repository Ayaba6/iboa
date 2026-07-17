<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Database\Seeder;

/**
 * [RH-PRO] Initialise les paramètres de paie par défaut (Burkina Faso).
 */
class PayrollSettingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command->warn('Aucune entreprise trouvée. Paramètres de paie non créés.');
            return;
        }

        PayrollSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                // CNSS — taux légaux Burkina Faso
                // Source : Décret n°2019-0013/PRES/PM/MFPTSS
                'cnss_employee_rate' => 5.50,   // pension, part salariale
                'cnss_employer_rate' => 16.00,  // total = pension 8,5 + RP 1,5 + PF 6
                'cnss_employer_pension_rate' => 8.50,
                'cnss_employer_rp_rate'      => 1.50,
                'cnss_employer_pf_rate'      => 6.00,
                'cnss_ceiling'        => 800_000,    // Plafond mensuel BF
                'cnss_annual_ceiling' => 9_600_000,  // Plafond annuel BF

                // Temps de travail & heures supplémentaires
                'work_days_month'  => 26,
                'work_hours_day'   => 8,
                'leave_days_year'  => 30,
                'hs_rate_25'       => 25.00,
                'hs_rate_50'       => 50.00,
                'hs_rate_nuit'     => 75.00,

                // Ancienneté (BF : 2 %/an, plafond 25 %)
                'anc_rate_per_year' => 2.0,
                'anc_rate_max_pct'  => 25.0,

                // SMIG
                'smig' => 45_000,

                // Quotient familial (legacy — plus utilisé dans le calcul,
                // conservé pour l'affichage des bulletins historiques)
                'nb_parts_max'        => 5,
                'parts_per_child'     => 0.5,
                'parts_base_single'   => 1.0,
                'parts_base_married'  => 2.0,
                'parts_base_widowed'  => 1.5,

                // IUTS — abattement frais professionnels + charges de famille
                'iuts_abattement_rate' => 20.0,
                'iuts_family_reductions' => PayrollSetting::defaultFamilyReductions(),
                'iuts_max_charges'     => 4,

                // Effort de paix
                'effort_paix_enabled' => true,
                'effort_paix_rate'    => 1.0,

                // Barème IUTS mensuel officiel (annexe fiscale CGI BF) —
                // tranches progressives sur le revenu imposable TOTAL
                'iuts_brackets' => PayrollSetting::defaultIutsBrackets(),

                'bulletin_prefix' => 'BUL',
                'currency_code'   => 'FCFA',
                'country_code'    => 'BF',
                'notes'           => 'Paramètres initiaux Burkina Faso — à réviser chaque année selon les textes légaux.',
            ]
        );

        $this->command->info("✓ Paramètres de paie initialisés pour : {$company->name}");
    }
}
