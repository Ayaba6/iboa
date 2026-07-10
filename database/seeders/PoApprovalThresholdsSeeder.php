<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PoApprovalThreshold;
use Illuminate\Database\Seeder;

/**
 * [§13.4 CDC] Seuils d'approbation des bons de commande achat.
 *
 * Règles CDC :
 *   < 500 000 FCFA  → Chef Service (role: acheteur ou chef_service_achat)
 *   < 5 000 000 FCFA → Directeur (role: directeur_usine)
 *   ≥ 5 000 000 FCFA → DG (role: dg)
 *
 * Idempotent : si les seuils existent déjà, on ne les recrée pas.
 */
class PoApprovalThresholdsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrFail();

        $seuils = [
            [
                'name'                => 'Seuil Chef Service (< 500 000 FCFA)',
                'min_amount'          => 0,
                'max_amount'          => 500_000,
                'required_role'       => 'acheteur',
                'required_permission' => 'purchase_orders.validate',
                'is_active'           => true,
                'sort_order'          => 1,
            ],
            [
                'name'                => 'Seuil Directeur (500 000 – 5 000 000 FCFA)',
                'min_amount'          => 500_000,
                'max_amount'          => 5_000_000,
                'required_role'       => 'directeur_usine',
                'required_permission' => null,
                'is_active'           => true,
                'sort_order'          => 2,
            ],
            [
                'name'                => 'Seuil DG (≥ 5 000 000 FCFA)',
                'min_amount'          => 5_000_000,
                'max_amount'          => null,
                'required_role'       => 'dg',
                'required_permission' => null,
                'is_active'           => true,
                'sort_order'          => 3,
            ],
        ];

        foreach ($seuils as $s) {
            PoApprovalThreshold::firstOrCreate(
                ['company_id' => $company->id, 'name' => $s['name']],
                array_merge($s, ['company_id' => $company->id])
            );
            $this->command->line("  ✓ {$s['name']}");
        }

        $this->command->info('PoApprovalThresholdsSeeder terminé — 3 seuils CDC configurés.');
    }
}
