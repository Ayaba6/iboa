<?php

namespace Database\Seeders;

use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Client;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\Employee;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 100 ordres de fabrication à statuts variés, pilotés par les services métier
 * (numérotation, transitions, consommation bobine, sortie stock PF, chutes,
 * coût de revient, contrôle qualité) — données 100 % cohérentes.
 */
class ProductionOrderSeeder extends Seeder
{
    private const DISTRIBUTION = [
        'brouillon' => 15,
        'lance'     => 15,
        'en_cours'  => 20,
        'termine'   => 40,
        'annule'    => 10,
    ];

    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        if (ProductionOrder::where('company_id', $company->id)->count() >= 100) {
            $this->command?->info('  ✓ Ordres de fabrication déjà présents (≥100)');

            return;
        }

        $production = app(ProductionService::class);
        $consume    = app(CoilConsumptionService::class);
        $stock      = app(ProductionStockService::class);
        $costing    = app(ProductionCostService::class);

        $boms       = BillOfMaterial::where('company_id', $company->id)->with('product')->get();
        $lines      = ProductionLine::where('company_id', $company->id)->get();
        $clients    = Client::pluck('id')->all();
        $employees  = Employee::pluck('id')->all();
        $responsible = User::query()->value('id');
        $warehouse  = Warehouse::where('company_id', $company->id)->orderByDesc('is_default')->value('id')
            ?? Warehouse::create(['company_id' => $company->id, 'code' => 'WH-PROD', 'name' => 'Magasin production', 'is_active' => true, 'is_default' => true])->id;

        if ($boms->isEmpty() || $lines->isEmpty()) {
            $this->command?->warn('  ! Nomenclatures/lignes manquantes — OF ignorés.');

            return;
        }

        $plan = [];
        foreach (self::DISTRIBUTION as $status => $count) {
            $plan = array_merge($plan, array_fill(0, $count, $status));
        }
        shuffle($plan);

        $created = 0;
        foreach ($plan as $target) {
            $bom  = $boms->random();
            $line = $lines->random();

            $launchedAt = Carbon::now()->subDays(random_int(5, 75));

            $order = $production->create([
                'client_id'           => $clients && random_int(1, 10) <= 7 ? $clients[array_rand($clients)] : null,
                'product_id'          => $bom->product_id,
                'bill_of_material_id' => $bom->id,
                'production_line_id'  => $line->id,
                'responsible_id'      => $responsible,
                'sheet_type'          => 'bac',
                'thickness'           => $bom->thickness,
                'color'               => $this->colorFromName($bom->name),
                'usable_width'        => $bom->usable_width,
            ], [
                ['length' => 6, 'quantity' => random_int(10, 60)],
                ['length' => 4, 'quantity' => random_int(5, 30)],
                ['length' => 3, 'quantity' => random_int(0, 20)],
            ]);

            $created++;

            if ($target === 'brouillon') {
                continue;
            }

            // Données de démo : force le lancement même en rupture matière simulée.
            $production->launch($order, true);
            $order->update(['launched_at' => $launchedAt]);

            if ($target === 'lance') {
                continue;
            }

            if ($target === 'annule') {
                $production->cancel($order, 'Annulé par le client');
                continue;
            }

            // en_cours / termine → exécution réelle
            $production->start($order);
            $this->execute($order, $bom, $warehouse, $employees, $launchedAt, $consume, $stock, $costing);

            if ($target === 'termine') {
                // Démo : clôture forcée (production partielle simulée < demande).
                $production->finish($order, true);
                $order->update(['finished_at' => $launchedAt->copy()->addDays(random_int(1, 6))]);
            }
        }

        $this->command?->info("  ✓ {$created} ordres de fabrication (avec conso, sorties, chutes, coûts, QC)");
    }

    private function execute(
        ProductionOrder $order,
        BillOfMaterial $bom,
        int $warehouse,
        array $employees,
        Carbon $launchedAt,
        CoilConsumptionService $consume,
        ProductionStockService $stock,
        ProductionCostService $costing,
    ): void {
        $operator = $employees ? $employees[array_rand($employees)] : null;

        // 1-2 consommations de bobines disponibles
        foreach (range(1, random_int(1, 2)) as $_) {
            $need = random_int(80, 220);
            $coil = Coil::where('company_id', $order->company_id)
                ->where('status', '!=', 'epuisee')
                ->where('remaining_weight', '>=', $need)
                ->inRandomOrder()->first();
            if ($coil) {
                $c = $consume->consume($order, $coil, $need, round($need / 4, 2));
                $c->update(['consumed_at' => $launchedAt->copy()->addDay()]);
            }
        }

        // Sortie produit fini
        $qty = max(1, (int) round((float) $order->quantity_requested * (random_int(80, 100) / 100)));
        $out = $stock->recordOutput($order, [
            'warehouse_id' => $warehouse,
            'length'       => 6,
            'quantity'     => $qty,
            'unit_cost'    => (int) round((float) $bom->consumption_per_meter * 6 * (float) ($order->product->purchase_price ?: 500) / 100 + random_int(2_000, 4_000)),
        ]);
        // [CDC §13.3] Données démo : visa chef d'équipe posé pour permettre la clôture
        $out->update([
            'produced_at'  => $launchedAt->copy()->addDays(random_int(1, 5)),
            'status'       => 'validee',
            'validated_at' => $launchedAt->copy()->addDays(random_int(1, 5)),
        ]);

        // Chutes (1 OF sur 2)
        if (random_int(0, 1)) {
            $stock->recordWaste($order, [
                'type'        => ['reutilisable', 'non_reutilisable', 'rebut'][array_rand([0, 1, 2])],
                'weight'      => random_int(5, 30),
                'machine_id'  => $order->productionLine?->machine_id,
                'operator_id' => $operator,
                'reason'      => ['Bord abîmé', 'Mauvaise coupe', 'Défaut couleur', 'Réglage machine'][array_rand([0, 1, 2, 3])],
            ]);
        }

        // Coût de revient
        $costing->compute($order, ['overhead_rate' => 5]);

        // Contrôle qualité (7 sur 10)
        if (random_int(1, 10) <= 7) {
            $verdict = ['conforme', 'conforme', 'conforme', 'a_reprendre', 'non_conforme'][array_rand([0, 1, 2, 3, 4])];
            ProductionQualityControl::create([
                'company_id'          => $order->company_id,
                'production_order_id' => $order->id,
                'thickness_ok'        => $verdict === 'conforme',
                'length_ok'           => $verdict !== 'non_conforme',
                'color_ok'            => $verdict === 'conforme',
                'visual_ok'           => $verdict === 'conforme',
                'status'              => $verdict,
                'rejected_quantity'   => $verdict === 'non_conforme' ? random_int(1, 10) : 0,
                'reason'              => $verdict === 'conforme' ? null : 'Écart détecté au contrôle',
                'controller_id'       => $operator,
                'controlled_at'       => $launchedAt->copy()->addDays(random_int(1, 5)),
            ]);
        }
    }

    private function colorFromName(string $name): string
    {
        foreach (CoilSeeder::COLORS as $c) {
            if (str_contains($name, $c)) {
                return $c;
            }
        }

        return 'Galva';
    }
}
