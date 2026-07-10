<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  Jeu de données de démonstration — fabrication de FER À BÉTON (mode MTS).
 *
 *      php artisan db:seed --class=ProductionFerBetonSeeder
 *
 *  Complète le référentiel pour le cycle fer à béton :
 *    • Matières : fil machine Ø6 / Ø8 / Ø9 / Ø10 (au kg).
 *    • Produits finis : fer à béton Ø6 / Ø8 / Ø10 / Ø12 — barres de 12 m.
 *    • Chutes & avariés (articles dédiés, vendus au kg / déclassés).
 *    • Nomenclatures fil machine → fer à béton (ex. Ø8 12 m = 4,52 kg de fil).
 *    • Gammes (redressage + coupe), machine, centre de charge.
 *    • Stock initial (fil machine en kg, fer à béton en barres) via mouvements.
 *
 *  100 % idempotent (updateOrCreate + garde mouvement), transactionnel.
 *  NON branché sur DatabaseSeeder.
 * ════════════════════════════════════════════════════════════════════════════
 */
class ProductionFerBetonSeeder extends Seeder
{
    /**
     * Fer à béton : [clé => [diamètre mm, prix vente/barre, fil machine source (réf), kg fil/barre]].
     * Poids ≈ poids théorique acier (kg/m × 12 m) + perte process.
     */
    private const REBAR = [
        '06' => ['d' => 6,  'price' => 2000, 'coil' => 'MP-FIL-06', 'kg' => 2.55],
        '08' => ['d' => 8,  'price' => 3500, 'coil' => 'MP-FIL-09', 'kg' => 4.52], // exemple du cahier des charges
        '10' => ['d' => 10, 'price' => 5500, 'coil' => 'MP-FIL-10', 'kg' => 7.10],
        '12' => ['d' => 12, 'price' => 8000, 'coil' => 'MP-FIL-10', 'kg' => 10.20],
    ];

    /** Fil machine : [réf => [Ø, prix achat/kg]]. */
    private const WIRE = [
        'MP-FIL-06' => [6,  520],
        'MP-FIL-08' => [8,  530],
        'MP-FIL-09' => [9,  540],
        'MP-FIL-10' => [10, 550],
    ];

    private array $units = [];
    private array $families = [];
    private array $acctCache = [];
    private int $taxId;
    private ?int $supplier = null;

    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command?->warn('Aucune société — seed Fer à béton annulé.');

            return;
        }
        app()->instance('current_company', $company);

        DB::transaction(function () use ($company) {
            $this->taxId    = TaxRate::where('is_default', true)->value('id') ?? TaxRate::value('id');
            $this->supplier = $this->supplier();
            $this->units();
            $this->families();

            [$whMp, $whPf] = $this->warehouses($company);
            $center = $this->workCenter($company);

            $wires = $this->wires($whMp);
            $this->scrapAndDefect($whPf);          // chutes/avariés d'abord (liés ensuite au BOM)
            $rebars = $this->rebars($company, $whPf, $wires, $center);
            $this->linkToleByproducts($company);   // relie aussi les nomenclatures tôle bac

            $this->command?->info(sprintf(
                '  ✓ Fer à béton : %d fils machine, %d fers (BOM+gamme), chutes & avariés.',
                count($wires), $rebars
            ));
        });
    }

    private function units(): void
    {
        foreach ([['kg', 'Kilogramme', 'poids'], ['barre', 'Barre', 'quantite'], ['t', 'Tonne', 'poids']] as [$a, $n, $t]) {
            $this->units[$a] = Unit::updateOrCreate(
                ['abbreviation' => $a],
                ['name' => $n, 'type' => $t, 'decimal_places' => 2, 'is_active' => true]
            )->id;
        }
    }

    private function families(): void
    {
        $defs = [
            ['MP',   'Matières Premières', null,  '602', '311'],
            ['PF',   'Produits Finis',     '702', null,  '311'],
            ['CHUT', 'Chutes',             '758', null,  '311'],
            ['AVAR', 'Avariés',            '758', null,  '311'],
        ];
        foreach ($defs as [$code, $name, $sale, $purchase, $stock]) {
            $this->families[$code] = ProductFamily::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sale_account_id' => $this->acct($sale),
                 'purchase_account_id' => $this->acct($purchase),
                 'stock_account_id' => $this->acct($stock), 'depth' => 0, 'is_active' => true]
            )->id;
        }
    }

    private function warehouses(Company $company): array
    {
        $whMp = Warehouse::updateOrCreate(['code' => 'DEP-MP'],
            ['company_id' => $company->id, 'name' => 'Dépôt Matières Premières', 'type' => 'matiere_premiere', 'city' => 'Ouagadougou', 'is_active' => true]);
        $whPf = Warehouse::updateOrCreate(['code' => 'DEP-PF'],
            ['company_id' => $company->id, 'name' => 'Dépôt Produits Finis', 'type' => 'produit_fini', 'city' => 'Ouagadougou', 'is_active' => true]);

        return [$whMp, $whPf];
    }

    private function workCenter(Company $company): WorkCenter
    {
        $machine = ProductionMachine::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'MCH-FER-01'],
            ['name' => 'Redresseuse-cisaille à fer', 'type' => 'mixte', 'hourly_cost' => 5000,
             'status' => 'active', 'maintenance_frequency_days' => 60, 'is_active' => true]
        );
        ProductionLine::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LIGNE-FER-01'],
            ['name' => 'Ligne fer à béton', 'machine_id' => $machine->id, 'is_active' => true]
        );

        return WorkCenter::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CT-MCH-FER-01'],
            ['machine_id' => $machine->id, 'name' => 'Centre fer à béton',
             'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 90, 'is_active' => true]
        );
    }

    /** Fil machine (matières). Retourne [réf => Product]. */
    private function wires(Warehouse $whMp): array
    {
        $wires = [];
        foreach (self::WIRE as $ref => [$d, $pricePerKg]) {
            $p = $this->product($ref, [
                'name' => "Fil machine Ø{$d}",
                'family_id' => $this->families['MP'], 'unit_id' => $this->units['kg'],
                'type' => 'simple', 'is_sellable' => false,
                'purchase_price' => $pricePerKg, 'sale_price' => 0,
                'stock_min' => 2000, 'stock_max' => 120000, 'reorder_point' => 5000,
                'default_supplier_id' => $this->supplier,
                'purchase_account_id' => $this->acct('602'), 'stock_account_id' => $this->acct('311'),
            ]);
            // Stock initial : 50 à 100 tonnes → kg
            $this->stock($p, $whMp, rand(50, 100) * 1000, $pricePerKg);
            $wires[$ref] = $p;
        }

        return $wires;
    }

    /** Fer à béton (PF) + BOM fil machine + gamme. Retourne le nombre créé. */
    private function rebars(Company $company, Warehouse $whPf, array $wires, WorkCenter $center): int
    {
        $count = 0;
        foreach (self::REBAR as $key => $r) {
            $wire = $wires[$r['coil']] ?? null;
            if (! $wire) {
                continue;
            }
            $ref = "PF-FER-{$key}";
            $rebar = $this->product($ref, [
                'name' => "Fer à béton Ø{$r['d']} 12m",
                'family_id' => $this->families['PF'], 'unit_id' => $this->units['barre'],
                'type' => 'compose', 'production_mode' => 'mts', 'is_purchasable' => false,
                'purchase_price' => 0, 'sale_price' => $r['price'],
                'min_sale_price' => (int) round($r['price'] * 0.9),
                'stock_min' => 100, 'stock_max' => 5000, 'reorder_point' => 300,
                'weight' => $r['kg'], 'weight_unit' => 'kg',
                'sale_account_id' => $this->acct('702'), 'stock_account_id' => $this->acct('311'),
            ]);

            $wireCostPerKg = $wire->purchase_price ?: 0;
            $matCost = (int) round($r['kg'] * $wireCostPerKg);
            // Stock initial : barres
            $this->stock($rebar, $whPf, rand(300, 3000), $matCost + 300);

            // Nomenclature : kg de fil machine par barre
            $bom = BillOfMaterial::updateOrCreate(
                ['company_id' => $company->id, 'product_id' => $rebar->id],
                ['name' => "Nomenclature {$rebar->name}", 'sheet_type' => 'fer_beton',
                 'scrap_product_id'  => Product::where('reference', 'CHUT-FER')->value('id'),
                 'defect_product_id' => Product::where('reference', 'AVAR-FER')->value('id'),
                 'thickness' => $r['d'], 'standard_waste_rate' => 4,
                 'consumption_per_meter' => $r['kg'],   // kg de fil par barre produite
                 'machine_time_per_unit' => 0.3, 'labor_per_unit' => 0.2,
                 'std_material_cost' => $matCost, 'std_labor_cost' => 150,
                 'std_machine_cost' => 120, 'std_overhead_cost' => 80, 'is_active' => true]
            );
            $bom->lines()->updateOrCreate(
                ['product_id' => $wire->id],
                ['label' => $wire->name, 'quantity_per_meter' => $r['kg'],
                 'unit_id' => $this->units['kg'], 'waste_rate' => 4, 'sort_order' => 1]
            );

            // Gamme (2 opérations)
            $routing = Routing::updateOrCreate(
                ['company_id' => $company->id, 'bill_of_material_id' => $bom->id],
                ['code' => "GAM-{$ref}", 'name' => "Gamme {$rebar->name}", 'is_active' => true]
            );
            foreach ([['Redressage du fil', 10, 1, 10], ['Coupe & façonnage', 8, 1, 20]] as [$op, $setup, $run, $seq]) {
                $routing->operations()->updateOrCreate(
                    ['sequence' => $seq],
                    ['work_center_id' => $center->id, 'name' => $op, 'setup_minutes' => $setup, 'run_minutes_per_unit' => $run]
                );
            }
            $count++;
        }

        return $count;
    }

    private function scrapAndDefect(Warehouse $whPf): void
    {
        // Chutes (vendues au kg) — cf. cahier des charges §8
        $scraps = [
            ['CHUT-FER',  'Chute fer à béton',      350],
            ['CHUT-TOLE', 'Chute tôle prélaquée',   300],
            ['CHUT-GALVA', 'Chute bobine galvanisée', 280],
        ];
        foreach ($scraps as [$ref, $name, $sell]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['CHUT'], 'unit_id' => $this->units['kg'],
                'type' => 'simple', 'is_purchasable' => false,
                'purchase_price' => 0, 'sale_price' => $sell,
                'stock_min' => 0, 'stock_max' => 50000, 'reorder_point' => 0,
                'sale_account_id' => $this->acct('758'), 'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whPf, rand(50, 500), (int) round($sell * 0.5));
        }

        // Avariés (déclassés)
        $defects = [
            ['AVAR-TOLE', 'Tôle bac avariée',   'barre', 1500],
            ['AVAR-FER',  'Fer à béton avarié',  'barre', 1200],
        ];
        foreach ($defects as [$ref, $name, $unit, $sell]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['AVAR'], 'unit_id' => $this->units[$unit],
                'type' => 'simple', 'is_purchasable' => false,
                'purchase_price' => 0, 'sale_price' => $sell,
                'stock_min' => 0, 'stock_max' => 2000, 'reorder_point' => 0,
                'sale_account_id' => $this->acct('758'), 'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whPf, rand(5, 50), (int) round($sell * 0.5));
        }
    }

    /** Relie les nomenclatures tôle bac existantes à leurs chute/avarié. */
    private function linkToleByproducts(Company $company): void
    {
        $scrap  = Product::where('reference', 'CHUT-TOLE')->value('id');
        $defect = Product::where('reference', 'AVAR-TOLE')->value('id');
        if (! $scrap && ! $defect) {
            return;
        }
        BillOfMaterial::where('company_id', $company->id)
            ->whereHas('product', fn ($q) => $q->where('reference', 'like', 'PF-BAC-%'))
            ->update(['scrap_product_id' => $scrap, 'defect_product_id' => $defect]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function product(string $reference, array $attrs): Product
    {
        $defaults = [
            'tax_rate_id' => $this->taxId, 'type' => 'simple', 'is_stockable' => true,
            'is_semi_finished' => false, 'is_purchasable' => true, 'is_sellable' => true,
            'purchase_price' => 0, 'sale_price' => 0, 'stock_min' => 0, 'stock_max' => 0,
            'reorder_point' => 0, 'valuation_method' => 'cmp', 'is_active' => true,
        ];

        return Product::updateOrCreate(['reference' => $reference], array_merge($defaults, $attrs));
    }

    private function stock(Product $product, Warehouse $wh, float $qty, int $avgCost): void
    {
        $note = 'Stock initial — démo Fer à béton';
        if (StockMovement::where('product_id', $product->id)->where('warehouse_id', $wh->id)->where('notes', $note)->exists()) {
            return;
        }
        ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->update(['quantity' => 0]);
        app(StockService::class)->recordMovement([
            'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'entree',
            'quantity' => $qty, 'unit_cost' => $avgCost, 'occurred_at' => now()->subDays(30), 'notes' => $note,
        ]);
    }

    private function acct(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        if (array_key_exists($code, $this->acctCache)) {
            return $this->acctCache[$code];
        }

        return $this->acctCache[$code] = Account::where('company_id', 1)
            ->where('code', 'like', $code . '%')->where('is_detail', true)->orderBy('code')->value('id');
    }

    private function supplier(): int
    {
        return Supplier::updateOrCreate(
            ['code' => 'FOUR-FER-01'],
            ['name' => 'Aciéries du Sahel', 'type' => 'entreprise', 'phone' => '+226 25 41 00 00',
             'city' => 'Ouagadougou', 'country' => 'Burkina Faso', 'avg_delivery_days' => 30, 'is_active' => true]
        )->id;
    }
}
