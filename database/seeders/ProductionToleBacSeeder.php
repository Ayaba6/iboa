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
use App\Models\WarehouseLocation;
use App\Services\StockService;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  Jeu de données de démonstration — fabrication de TÔLES BAC.
 *
 *      php artisan db:seed --class=ProductionToleBacSeeder
 *
 *  Génère, pour la société courante et de bout en bout, tout le référentiel
 *  nécessaire pour tester le cycle métier complet :
 *
 *      Commande client → vérification stock → OF → réservation matière →
 *      production → contrôle qualité → entrée PF → livraison → facturation →
 *      comptabilisation (SYSCOHADA).
 *
 *  Contenu :
 *    • 10 familles d'articles (matières, bobines, semi-finis, PF, accessoires,
 *      consommables, emballages, pièces de rechange, services).
 *    • ~50 articles (bobines, tôles bac couleur×épaisseur, accessoires,
 *      fixations, consommables, emballages, services) — codes/comptes/TVA.
 *    • Stocks initiaux réalistes (bobines en tonnes, tôles en ml, etc.).
 *    • Entrepôts (matières / produits finis) + emplacements.
 *    • Machines de profilage, lignes de production, centres de charge.
 *    • Nomenclatures (BOM) + gammes opératoires pour chaque tôle finie.
 *
 *  100 % idempotent (updateOrCreate sur clés naturelles), transactionnel,
 *  multi-société. NON branché sur DatabaseSeeder.
 * ════════════════════════════════════════════════════════════════════════════
 */
class ProductionToleBacSeeder extends Seeder
{
    private const COLORS    = ['R' => 'Rouge', 'B' => 'Bleu', 'V' => 'Verte'];
    private const THICKNESS = ['035' => 0.35, '040' => 0.40, '045' => 0.45, '050' => 0.50, '060' => 0.60];

    /** Prix de vente tôle finie par épaisseur (FCFA / mètre linéaire). */
    private const TOLE_PRICE = ['035' => 3000, '040' => 3500, '045' => 4200, '050' => 5000, '060' => 6500];

    /** Prix d'achat bobine prélaquée par épaisseur (FCFA / tonne). */
    private const COIL_PRICE = ['035' => 760000, '040' => 790000, '045' => 815000, '050' => 845000, '060' => 890000];

    private array $units    = [];
    private array $families = [];
    private array $acctCache = [];
    private int $taxId;
    private ?int $supplierCoil = null;

    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command?->warn('Aucune société — seed Tôle Bac annulé.');

            return;
        }
        app()->instance('current_company', $company);

        DB::transaction(function () use ($company) {
            $this->taxId = TaxRate::where('is_default', true)->value('id')
                ?? TaxRate::value('id');
            $this->supplierCoil = $this->supplier();

            $this->seedUnits();
            $this->seedFamilies();

            [$whMp, $whPf] = $this->seedWarehouses($company);
            $machines = $this->seedMachines($company);
            $this->seedLines($company, $machines);
            $centers = $this->seedWorkCenters($company, $machines);

            $coils    = $this->seedRawMaterials($company, $whMp);
            $finished = $this->seedFinishedTole($company, $whPf, $coils, $centers);
            $this->seedAccessories($whPf);
            $this->seedFixations($whPf);
            $this->seedConsumables($whMp);
            $this->seedPackaging($whMp);
            $this->seedServices();

            $this->command?->info(sprintf(
                '  ✓ Tôle Bac : %d familles, %d bobines, %d tôles finies (BOM+gamme), '
                . '%d machines, %d centres.',
                count($this->families), count($coils), $finished, count($machines), count($centers)
            ));
        });
    }

    // ── Référentiels ─────────────────────────────────────────────────────────

    private function seedUnits(): void
    {
        $defs = [
            ['ml', 'Mètre linéaire', 'longueur'],
            ['t',  'Tonne',          'poids'],
            ['kg', 'Kilogramme',     'poids'],
            ['pcs', 'Pièce',         'quantite'],
            ['L',  'Litre',          'volume'],
        ];
        foreach ($defs as [$abbr, $name, $type]) {
            $this->units[$abbr] = Unit::updateOrCreate(
                ['abbreviation' => $abbr],
                ['name' => $name, 'type' => $type, 'decimal_places' => 2, 'is_active' => true]
            )->id;
        }
    }

    private function seedFamilies(): void
    {
        // [code, nom, compte_vente, compte_achat, compte_stock]
        $defs = [
            ['MP',   'Matières Premières',     null,  '602', '311'],
            ['BPRE', 'Bobines Prélaquées',     null,  '602', '311'],
            ['BGAL', 'Bobines Galvanisées',    null,  '602', '311'],
            ['SF',   'Produits Semi-Finis',    '702', null,  '311'],
            ['PF',   'Produits Finis',         '702', null,  '311'],
            ['ACC',  'Accessoires de Toiture', '701', '601', '311'],
            ['CONS', 'Consommables',           null,  '605', '311'],
            ['EMB',  'Emballages',             null,  '605', '311'],
            ['PR',   'Pièces de Rechange',     null,  '605', '311'],
            ['SRV',  'Services de Production',  '706', null,  null],
        ];
        foreach ($defs as [$code, $name, $sale, $purchase, $stock]) {
            $this->families[$code] = ProductFamily::updateOrCreate(
                ['code' => $code],
                [
                    'name'               => $name,
                    'sale_account_id'    => $this->acct($sale),
                    'purchase_account_id' => $this->acct($purchase),
                    'stock_account_id'   => $this->acct($stock),
                    'depth'              => 0,
                    'is_active'          => true,
                ]
            )->id;
        }
    }

    private function seedWarehouses(Company $company): array
    {
        $whMp = Warehouse::updateOrCreate(
            ['code' => 'DEP-MP'],
            ['company_id' => $company->id, 'name' => 'Dépôt Matières Premières', 'type' => 'matiere_premiere',
             'city' => 'Ouagadougou', 'is_default' => false, 'is_active' => true]
        );
        $whPf = Warehouse::updateOrCreate(
            ['code' => 'DEP-PF'],
            ['company_id' => $company->id, 'name' => 'Dépôt Produits Finis', 'type' => 'produit_fini',
             'city' => 'Ouagadougou', 'is_default' => false, 'is_active' => true]
        );

        foreach ([[$whMp, 'MP'], [$whPf, 'PF']] as [$wh, $zone]) {
            foreach (['A', 'B', 'C'] as $aisle) {
                WarehouseLocation::updateOrCreate(
                    ['warehouse_id' => $wh->id, 'code' => "{$zone}-{$aisle}01"],
                    ['name' => "Allée {$aisle} - Rack 01", 'zone' => $zone,
                     'aisle' => $aisle, 'rack' => '01', 'level' => '1', 'is_active' => true]
                );
            }
        }

        return [$whMp, $whPf];
    }

    private function seedMachines(Company $company): array
    {
        $defs = [
            ['MCH-PROF-01', 'Profileuse Tôle Bac n°1', 'profilage', 6000],
            ['MCH-PROF-02', 'Profileuse Tôle Bac n°2', 'profilage', 6500],
            ['MCH-REFEND-01', 'Refendeuse / Slitter',  'decoupe',   4500],
            ['MCH-CISAILLE-01', 'Cisaille guillotine',  'decoupe',   3500],
        ];
        $machines = [];
        foreach ($defs as [$code, $name, $type, $cost]) {
            $machines[$code] = ProductionMachine::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'hourly_cost' => $cost,
                 'status' => 'active', 'maintenance_frequency_days' => 60, 'is_active' => true]
            );
        }

        return $machines;
    }

    private function seedLines(Company $company, array $machines): void
    {
        $defs = [
            ['LIGNE-BAC-01', 'Ligne de profilage Bac n°1', 'MCH-PROF-01'],
            ['LIGNE-BAC-02', 'Ligne de profilage Bac n°2', 'MCH-PROF-02'],
        ];
        foreach ($defs as [$code, $name, $mch]) {
            ProductionLine::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'machine_id' => $machines[$mch]->id, 'is_active' => true]
            );
        }
    }

    private function seedWorkCenters(Company $company, array $machines): array
    {
        $centers = [];
        foreach ($machines as $code => $m) {
            $centers[$code] = WorkCenter::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'CT-' . $code],
                ['machine_id' => $m->id, 'name' => 'Centre ' . $m->name,
                 'capacity_hours_per_day' => 8, 'cost_per_hour' => $m->hourly_cost,
                 'efficiency_rate' => 90, 'is_active' => true]
            );
        }

        return $centers;
    }

    // ── Articles ───────────────────────────────────────────────────────────

    /** Bobines (matières premières) — retourne [épaisseur => Product coil]. */
    private function seedRawMaterials(Company $company, Warehouse $whMp): array
    {
        $coils = [];
        // Bobines prélaquées (toutes épaisseurs)
        foreach (self::THICKNESS as $key => $mm) {
            $pricePerTon = self::COIL_PRICE[$key];
            $p = $this->product("MP-PRE-{$key}", [
                'name'       => "Bobine Prélaquée {$mm} mm",
                'family_id'  => $this->families['BPRE'],
                'unit_id'    => $this->units['t'],
                'type'       => 'simple',
                'is_sellable' => false,
                'purchase_price' => $pricePerTon,
                'sale_price' => 0,
                'stock_min'  => 5, 'stock_max' => 120, 'reorder_point' => 15,
                'default_supplier_id' => $this->supplierCoil,
                'sale_account_id'    => null,
                'purchase_account_id' => $this->acct('602'),
                'stock_account_id'   => $this->acct('311'),
            ]);
            // Stock initial : 50 à 100 tonnes ; coût/kg
            $this->stock($p, $whMp, rand(50, 100), (int) round($pricePerTon / 1000));
            $coils[$key] = $p;
        }
        // Bobines galvanisées (0.45 / 0.50) — ~12 % moins chères
        foreach (['045' => 0.45, '050' => 0.50] as $key => $mm) {
            $pricePerTon = (int) round(self::COIL_PRICE[$key] * 0.88);
            $p = $this->product("MP-GAL-{$key}", [
                'name'       => "Bobine Galvanisée {$mm} mm",
                'family_id'  => $this->families['BGAL'],
                'unit_id'    => $this->units['t'],
                'type'       => 'simple',
                'is_sellable' => false,
                'purchase_price' => $pricePerTon,
                'sale_price' => 0,
                'stock_min'  => 5, 'stock_max' => 120, 'reorder_point' => 15,
                'default_supplier_id' => $this->supplierCoil,
                'purchase_account_id' => $this->acct('602'),
                'stock_account_id'   => $this->acct('311'),
            ]);
            $this->stock($p, $whMp, rand(50, 100), (int) round($pricePerTon / 1000));
        }

        return $coils;
    }

    /**
     * Tôles bac finies (couleur × épaisseur) + BOM + gamme opératoire.
     * Retourne le nombre de tôles créées.
     */
    private function seedFinishedTole(Company $company, Warehouse $whPf, array $coils, array $centers): int
    {
        $count = 0;
        foreach (self::THICKNESS as $key => $mm) {
            $coil = $coils[$key] ?? null;
            if (! $coil) {
                continue;
            }
            // kg de bobine consommés par mètre linéaire (acier 7,85 kg/dm³, largeur utile 1 m)
            $consoPerMeter = round($mm * 7.85, 2);
            $coilCostPerKg = ($coil->purchase_price ?: 0) / 1000;

            foreach (self::COLORS as $cc => $color) {
                $ref  = "PF-BAC-{$cc}-{$key}";
                $sale = self::TOLE_PRICE[$key];
                $tole = $this->product($ref, [
                    'name'       => "Tôle Bac {$color} {$mm} mm",
                    'family_id'  => $this->families['PF'],
                    'unit_id'    => $this->units['ml'],
                    'type'       => 'compose',
                    'production_mode' => 'mto', // tôle bac = production à la commande
                    'is_purchasable' => false,
                    'purchase_price' => 0,
                    'sale_price' => $sale,
                    'min_sale_price' => (int) round($sale * 0.92),
                    'stock_min'  => 200, 'stock_max' => 6000, 'reorder_point' => 500,
                    'sale_account_id'  => $this->acct('702'),
                    'stock_account_id' => $this->acct('311'),
                ]);
                // Stock initial : 500 à 5000 ml — valorisé au coût standard
                $stdCost = (int) round($consoPerMeter * $coilCostPerKg) + 650; // matière + MO/machine/frais
                $this->stock($tole, $whPf, rand(500, 5000), $stdCost);

                // Nomenclature (BOM)
                $bom = BillOfMaterial::updateOrCreate(
                    ['company_id' => $company->id, 'product_id' => $tole->id],
                    [
                        'name'               => "Nomenclature {$tole->name}",
                        'sheet_type'         => 'bac',
                        'thickness'          => $mm,
                        'coil_width'         => 1200,
                        'usable_width'       => 1000,
                        'standard_waste_rate' => 5,
                        'consumption_per_meter' => $consoPerMeter,
                        'machine_time_per_unit' => 0.6,
                        'labor_per_unit'     => 0.4,
                        'std_material_cost'  => (int) round($consoPerMeter * $coilCostPerKg),
                        'std_labor_cost'     => 300,
                        'std_machine_cost'   => 200,
                        'std_overhead_cost'  => 150,
                        'is_active'          => true,
                    ]
                );
                // Ligne de nomenclature : la bobine matière
                $bom->lines()->updateOrCreate(
                    ['product_id' => $coil->id],
                    ['label' => $coil->name, 'quantity_per_meter' => $consoPerMeter,
                     'unit_id' => $this->units['kg'], 'waste_rate' => 5, 'sort_order' => 1]
                );

                // Gamme opératoire (3 opérations)
                $routing = Routing::updateOrCreate(
                    ['company_id' => $company->id, 'bill_of_material_id' => $bom->id],
                    ['code' => "GAM-{$ref}", 'name' => "Gamme {$tole->name}", 'is_active' => true]
                );
                $ops = [
                    ['Refendage bobine',  'MCH-REFEND-01', 10, 1, 10],
                    ['Profilage tôle bac', 'MCH-PROF-01',  15, 2, 20],
                    ['Coupe & contrôle',  'MCH-CISAILLE-01', 8, 1, 30],
                ];
                foreach ($ops as [$opName, $mch, $setup, $run, $seq]) {
                    $wc = $centers[$mch] ?? null;
                    if (! $wc) {
                        continue;
                    }
                    $routing->operations()->updateOrCreate(
                        ['sequence' => $seq],
                        ['work_center_id' => $wc->id, 'name' => $opName,
                         'setup_minutes' => $setup, 'run_minutes_per_unit' => $run]
                    );
                }
                $count++;
            }
        }

        return $count;
    }

    private function seedAccessories(Warehouse $whPf): void
    {
        // [ref, nom, prix_achat, prix_vente]
        $defs = [
            ['ACC-FAIT-2M', 'Faîtière 2m',          2200, 3500],
            ['ACC-FAIT-3M', 'Faîtière 3m',          3200, 5000],
            ['ACC-RIVE-LAT', 'Rive Latérale',       1800, 2800],
            ['ACC-RIVE-MUR', 'Rive Murale',         1900, 2900],
            ['ACC-NOUE-CEN', 'Noue Centrale',       2600, 4000],
            ['ACC-GOUT', 'Gouttière',               2800, 4200],
            ['ACC-DEP', 'Descente Eau Pluviale',    2400, 3600],
            ['ACC-CHEN', 'Chéneau',                 3500, 5200],
        ];
        foreach ($defs as [$ref, $name, $buy, $sell]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['ACC'],
                'unit_id' => $this->units['pcs'], 'type' => 'simple',
                'purchase_price' => $buy, 'sale_price' => $sell,
                'stock_min' => 50, 'stock_max' => 2000, 'reorder_point' => 100,
                'default_supplier_id' => $this->supplierCoil,
                'sale_account_id' => $this->acct('701'),
                'purchase_account_id' => $this->acct('601'),
                'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whPf, rand(100, 2000), $buy);
        }
    }

    private function seedFixations(Warehouse $whPf): void
    {
        $defs = [
            ['FIX-VIS-55', 'Vis Auto Perceuse 55 mm', 20, 35],
            ['FIX-VIS-65', 'Vis Auto Perceuse 65 mm', 25, 45],
            ['FIX-RONDELLE', 'Rondelle Étanche',      8,  15],
            ['FIX-BOUCHON', 'Bouchon de Faîtière',    50, 90],
        ];
        foreach ($defs as [$ref, $name, $buy, $sell]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['ACC'],
                'unit_id' => $this->units['pcs'], 'type' => 'simple',
                'purchase_price' => $buy, 'sale_price' => $sell,
                'stock_min' => 2000, 'stock_max' => 20000, 'reorder_point' => 4000,
                'default_supplier_id' => $this->supplierCoil,
                'sale_account_id' => $this->acct('701'),
                'purchase_account_id' => $this->acct('601'),
                'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whPf, 10000, $buy);
        }
    }

    private function seedConsumables(Warehouse $whMp): void
    {
        $defs = [
            ['CON-PEINT-R', 'Peinture Rouge',      'L',   8000],
            ['CON-PEINT-B', 'Peinture Bleue',      'L',   8000],
            ['CON-PEINT-V', 'Peinture Verte',      'L',   8000],
            ['CON-SOLVANT', 'Solvant',             'L',   3000],
            ['CON-GRAISSE', 'Graisse Industrielle', 'kg', 5000],
        ];
        foreach ($defs as [$ref, $name, $unit, $buy]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['CONS'],
                'unit_id' => $this->units[$unit], 'type' => 'simple',
                'is_sellable' => false, 'purchase_price' => $buy, 'sale_price' => 0,
                'stock_min' => 20, 'stock_max' => 500, 'reorder_point' => 40,
                'default_supplier_id' => $this->supplierCoil,
                'purchase_account_id' => $this->acct('605'),
                'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whMp, rand(50, 300), $buy);
        }
    }

    private function seedPackaging(Warehouse $whMp): void
    {
        $defs = [
            ['EMB-PALETTE', 'Palette Bois',         'pcs', 5000],
            ['EMB-FILM', 'Film Plastique',          'pcs', 15000],
            ['EMB-FEUILLARD', 'Feuillard de Cerclage', 'pcs', 20000],
        ];
        foreach ($defs as [$ref, $name, $unit, $buy]) {
            $p = $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['EMB'],
                'unit_id' => $this->units[$unit], 'type' => 'simple',
                'is_sellable' => false, 'purchase_price' => $buy, 'sale_price' => 0,
                'stock_min' => 20, 'stock_max' => 1000, 'reorder_point' => 50,
                'default_supplier_id' => $this->supplierCoil,
                'purchase_account_id' => $this->acct('605'),
                'stock_account_id' => $this->acct('311'),
            ]);
            $this->stock($p, $whMp, rand(100, 800), $buy);
        }
    }

    private function seedServices(): void
    {
        $defs = [
            ['SRV-PROFIL', 'Service de profilage à façon', 1500],
            ['SRV-DECOUPE', 'Service de découpe sur mesure', 1000],
            ['SRV-POSE', 'Service de pose toiture',        25000],
        ];
        foreach ($defs as [$ref, $name, $sell]) {
            $this->product($ref, [
                'name' => $name, 'family_id' => $this->families['SRV'],
                'unit_id' => $this->units['pcs'], 'type' => 'service',
                'is_stockable' => false, 'is_purchasable' => false,
                'purchase_price' => 0, 'sale_price' => $sell,
                'sale_account_id' => $this->acct('706'),
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Crée/maj un article par référence ; applique des valeurs par défaut sûres. */
    private function product(string $reference, array $attrs): Product
    {
        $defaults = [
            'tax_rate_id'     => $this->taxId,
            'type'            => 'simple',
            'is_stockable'    => true,
            'is_semi_finished' => false,
            'is_purchasable'  => true,
            'is_sellable'     => true,
            'purchase_price'  => 0,
            'sale_price'      => 0,
            'stock_min'       => 0,
            'stock_max'       => 0,
            'reorder_point'   => 0,
            'valuation_method' => 'cmp',
            'is_active'       => true,
        ];

        return Product::updateOrCreate(
            ['reference' => $reference],
            array_merge($defaults, $attrs)
        );
    }

    /**
     * Stock initial par produit/entrepôt — posé via un MOUVEMENT d'entrée
     * (source de vérité), pour rester cohérent avec les audits
     * (quantity = Σ mouvements). Idempotent : ne refait rien si le mouvement
     * de stock initial existe déjà.
     */
    private function stock(Product $product, Warehouse $wh, float $qty, int $avgCost): void
    {
        $note = 'Stock initial — démo Tôle Bac';

        $already = StockMovement::where('product_id', $product->id)
            ->where('warehouse_id', $wh->id)
            ->where('notes', $note)
            ->exists();
        if ($already) {
            return;
        }

        // Repart de 0 pour que le mouvement soit la seule source du niveau.
        ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $wh->id)
            ->update(['quantity' => 0]);

        app(StockService::class)->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $wh->id,
            'type'         => 'entree',
            'quantity'     => $qty,
            'unit_cost'    => $avgCost,
            'occurred_at'  => now()->subDays(30),
            'notes'        => $note,
        ]);
    }

    /** Résout l'id d'un compte SYSCOHADA par préfixe de code (null si absent). */
    private function acct(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        if (array_key_exists($code, $this->acctCache)) {
            return $this->acctCache[$code];
        }

        return $this->acctCache[$code] = Account::where('company_id', 1)
            ->where('code', 'like', $code . '%')
            ->where('is_detail', true)
            ->orderBy('code')
            ->value('id');
    }

    /** Fournisseur principal des bobines (créé si absent). */
    private function supplier(): int
    {
        return Supplier::updateOrCreate(
            ['code' => 'FOUR-TOLE-01'],
            ['name' => 'Métal Bobines Sahel', 'type' => 'entreprise',
             'phone' => '+226 25 40 00 00', 'city' => 'Ouagadougou',
             'country' => 'Burkina Faso', 'avg_delivery_days' => 21, 'is_active' => true]
        )->id;
    }
}
