<?php

namespace Database\Seeders;

use App\Models\PayrollParameterVersion;
use App\Services\PayrollRegulationService;
use Illuminate\Database\Seeder;

/**
 * Versions réglementaires initiales — Burkina Faso.
 * Idempotent : ne recrée pas une version déjà publiée.
 */
class PayrollRegulationSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(PayrollRegulationService::class);

        $versions = [
            [
                'code'        => PayrollParameterVersion::CODE_IUTS_BAREME,
                'libelle'     => 'Barème IUTS mensuel progressif',
                'type_valeur' => 'bareme',
                'valeur'      => ['unite' => 'FCFA/mois', 'application' => 'revenu_imposable_total'],
                'reference_legale' => 'Annexe fiscale CGI Burkina Faso',
                'brackets'    => [
                    [0,        30_000,   0],
                    [30_001,   50_000, 12.1],
                    [50_001,   80_000, 13.9],
                    [80_001,  120_000, 15.7],
                    [120_001, 170_000, 18.4],
                    [170_001, 250_000, 21.7],
                    [250_001, null,      25],
                ],
            ],
            [
                'code'        => PayrollParameterVersion::CODE_IUTS_REDUCTIONS,
                'libelle'     => 'Réduction IUTS pour charges de famille',
                'type_valeur' => 'json',
                'valeur'      => ['reductions' => [[0, 0], [1, 8], [2, 10], [3, 12], [4, 14]], 'max_charges' => 4],
                'reference_legale' => 'CGI Burkina Faso — charges de famille',
            ],
            [
                'code'        => PayrollParameterVersion::CODE_CNSS_PLAFOND,
                'libelle'     => 'Plafond CNSS mensuel',
                'type_valeur' => 'montant',
                'valeur'      => ['montant' => 800_000],
                'reference_legale' => 'Décret CNSS Burkina Faso',
            ],
            [
                'code'        => PayrollParameterVersion::CODE_CNSS_PLAFOND_AN,
                'libelle'     => 'Plafond CNSS annuel',
                'type_valeur' => 'montant',
                'valeur'      => ['montant' => 9_600_000],
                'reference_legale' => 'Décret CNSS Burkina Faso',
            ],
            [
                'code'        => PayrollParameterVersion::CODE_CNSS_SALARIE,
                'libelle'     => 'Taux CNSS salarié (pension)',
                'type_valeur' => 'taux',
                'valeur'      => ['taux' => 5.5],
                'reference_legale' => 'Décret n°2019-0013/PRES/PM/MFPTSS',
            ],
            [
                'code'        => PayrollParameterVersion::CODE_CNSS_PATRONAL,
                'libelle'     => 'Taux CNSS patronal ventilé',
                'type_valeur' => 'json',
                'valeur'      => ['pension' => 8.5, 'risques_professionnels' => 1.5, 'prestations_familiales' => 6.0, 'total' => 16.0],
                'reference_legale' => 'Décret n°2019-0013/PRES/PM/MFPTSS',
            ],
            [
                'code'        => PayrollParameterVersion::CODE_SMIG,
                'libelle'     => 'Salaire minimum interprofessionnel garanti',
                'type_valeur' => 'montant',
                'valeur'      => ['montant' => 45_000],
                'reference_legale' => 'Décret SMIG Burkina Faso',
            ],
        ];

        foreach ($versions as $data) {
            if (PayrollParameterVersion::where('code', $data['code'])->where('statut', 'actif')->exists()) {
                continue;
            }
            $svc->publish($data + ['date_debut' => '2026-01-01']);
        }

        $this->command?->info('✓ Versions réglementaires BF initialisées.');
    }
}
