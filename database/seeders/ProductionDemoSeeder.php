<?php

namespace Database\Seeders;

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\PicksCompatibleCoil;

/**
 * Données de démonstration du module Production (fabrication tôles bac).
 *
 * Lancement manuel : php artisan db:seed --class=ProductionDemoSeeder
 * Idempotent : ne fait rien si des machines de production existent déjà.
 * N'est PAS branché sur DatabaseSeeder (jamais exécuté automatiquement).
 */
class ProductionDemoSeeder extends Seeder
{
    use PicksCompatibleCoil;

    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command?->warn('Aucune société — seed démo production ignoré.');

            return;
        }
        app()->instance('current_company', $company);

        if (ProductionMachine::where('company_id', $company->id)->exists()) {
            $this->command?->info('Données production déjà présentes — seed ignoré.');

            return;
        }

        // Référentiel
        $machines = ProductionMachine::factory()->count(3)->create(['company_id' => $company->id]);
        $lines    = collect();
        foreach ($machines as $m) {
            $lines->push(ProductionLine::factory()->create(['company_id' => $company->id, 'machine_id' => $m->id]));
        }

        $product = Product::query()->where('is_stockable', true)->first()
            ?? Product::factory()->create(['name' => 'Tôle bac galva', 'is_stockable' => true, 'valuation_method' => 'cmp']);

        $bom = BillOfMaterial::factory()->create(['company_id' => $company->id, 'product_id' => $product->id]);

        $coils = Coil::factory()->count(6)->create(['company_id' => $company->id, 'product_id' => $product->id]);

        $warehouse = Warehouse::where('company_id', $company->id)->orderByDesc('is_default')->first()
            ?? Warehouse::create(['company_id' => $company->id, 'code' => 'WH-PROD', 'name' => 'Magasin production', 'is_active' => true, 'is_default' => true]);

        $production = app(ProductionService::class);
        $consume    = app(CoilConsumptionService::class);
        $stock      = app(ProductionStockService::class);
        $costing    = app(ProductionCostService::class);

        // 5 OF à différents stades
        for ($i = 1; $i <= 5; $i++) {
            $order = $production->create([
                'product_id'          => $product->id,
                'bill_of_material_id' => $bom->id,
                'production_line_id'  => $lines->random()->id,
                'sheet_type'          => 'bac',
                'thickness'           => 0.40,
                'color'               => ['Rouge', 'Bleu', 'Galva'][array_rand(['Rouge', 'Bleu', 'Galva'])],
            ], [
                ['length' => 6, 'quantity' => rand(10, 40)],
                ['length' => 4, 'quantity' => rand(5, 20)],
            ]);

            if ($i === 1) {
                continue; // reste brouillon
            }

            $production->launch($order, true); // démo : force malgré rupture simulée
            if ($i === 2) {
                continue; // reste lancé
            }

            $production->start($order); // en_cours

            // Consommation + production + chute
            // [MTO §9] Bobine COMPATIBLE avec l'OF, non plus un tirage dans le lot.
            $besoin = rand(100, 200);
            $coil   = $this->pickOrCreateCompatibleCoil($order, $besoin);
            if ($coil) {
                $consume->consume($order, $coil, $besoin, rand(20, 60));
            }
            // Déclaration plafonnée au demandé (garde « Autoriser dépassement qté » = Non par défaut).
            $out = $stock->recordOutput($order, ['warehouse_id' => $warehouse->id, 'length' => 6, 'quantity' => min(rand(8, 30), (int) $order->quantity_requested), 'unit_cost' => rand(2500, 4000)]);
            // [CDC §13.3] Données démo : visa chef d'équipe posé pour permettre la clôture
            $out->update(['status' => 'validee', 'validated_at' => now()]);
            $stock->recordWaste($order, ['type' => 'rebut', 'weight' => rand(5, 20), 'reason' => 'Bord abîmé']);

            $costing->compute($order, ['overhead_rate' => 5]);

            if ($i >= 4) {
                $production->finish($order, true); // démo : clôture forcée (prod partielle)
            }
        }

        $this->command?->info('Démo production créée : 3 machines, ' . $coils->count() . ' bobines, 5 OF.');
    }
}
