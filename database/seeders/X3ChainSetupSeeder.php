<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ItemCategory;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * [Paramétrage X3 §6-9] Compléments de la chaîne tôle bac — IDEMPOTENT.
 * Dépôts qualité/rebuts/chutes/expédition + câblage, catégorie ACCESSOIRE,
 * sous-familles de couverture, unités manquantes.
 */
class X3ChainSetupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $co = Company::first();

            // ── §6 Dépôts manquants ──────────────────────────────────────────
            $depots = [
                ['code' => 'DEP-QUAR', 'name' => 'Dépôt Quarantaine', 'type' => 'quarantaine',
                 'can_production' => 0, 'can_sale' => 0, 'can_purchase' => 0, 'can_stock' => 1,
                 'can_delivery' => 0, 'can_transfer' => 1, 'allow_negative_stock' => 0,
                 'requires_quality_control' => 1],
                ['code' => 'DEP-REBUT', 'name' => 'Dépôt Rebuts', 'type' => 'rebuts',
                 'can_production' => 0, 'can_sale' => 0, 'can_purchase' => 0, 'can_stock' => 1,
                 'can_delivery' => 0, 'can_transfer' => 1, 'allow_negative_stock' => 0,
                 'requires_quality_control' => 0],
                ['code' => 'DEP-CHUTE', 'name' => 'Dépôt Chutes', 'type' => 'chutes',
                 'can_production' => 1, 'can_sale' => 1, 'can_purchase' => 0, 'can_stock' => 1,
                 'can_delivery' => 1, 'can_transfer' => 1, 'allow_negative_stock' => 0,
                 'requires_quality_control' => 0],
                ['code' => 'DEP-EXPED', 'name' => 'Dépôt Expédition', 'type' => 'expedition',
                 'can_production' => 0, 'can_sale' => 1, 'can_purchase' => 0, 'can_stock' => 1,
                 'can_delivery' => 1, 'can_transfer' => 1, 'allow_negative_stock' => 0,
                 'requires_quality_control' => 0],
            ];
            foreach ($depots as $d) {
                Warehouse::firstOrCreate(
                    ['code' => $d['code']],
                    array_merge($d, ['company_id' => $co->id, 'is_active' => 1, 'site' => 'USINE'])
                );
            }

            // Câblage : les dépôts de production pointent quarantaine + rebuts.
            $quar  = Warehouse::where('code', 'DEP-QUAR')->value('id');
            $rebut = Warehouse::where('code', 'DEP-REBUT')->value('id');
            Warehouse::whereIn('code', ['DEPTBC', 'DEPFAB', 'DEP-PF', 'DEP-MP'])
                ->each(function (Warehouse $w) use ($quar, $rebut) {
                    $w->update([
                        'quality_warehouse_id' => $w->quality_warehouse_id ?: $quar,
                        'scrap_warehouse_id'   => $w->scrap_warehouse_id ?: $rebut,
                    ]);
                });

            // ── §7 Catégorie ACCESSOIRE ─────────────────────────────────────
            ItemCategory::firstOrCreate(
                ['code' => 'ACCESSOIRE'],
                [
                    'name' => 'Accessoires de couverture', 'nature' => 'marchandise',
                    'strategy' => 'achat_revente', 'is_active' => 1, 'sort_order' => 55,
                    'is_purchasable' => 1, 'is_sellable' => 1, 'is_stockable' => 1,
                    'is_manufactured' => 0, 'usable_in_bom' => 0, 'usable_as_finished' => 0,
                    'valuation_method' => 'cmp',
                    'default_tax_rate_id' => 1,
                    'default_sale_unit_id' => Unit::where('name', 'Pièce')->value('id'),
                    'default_purchase_unit_id' => Unit::where('name', 'Pièce')->value('id'),
                    'stock_account_id' => \App\Models\Account::where('code', '3111')->value('id'),
                    'purchase_account_id' => \App\Models\Account::where('code', '601')->value('id'),
                    'sale_account_id' => \App\Models\Account::where('code', '701')->value('id'),
                    'variation_account_id' => \App\Models\Account::where('code', '6031')->value('id'),
                    'description' => 'Faîtières, rives, noues, visserie et accessoires — achetés-revendus par défaut ; un accessoire plié fabriqué relèvera de PF_TOLE_MTO.',
                ]
            );

            // ── §7 Sous-familles de couverture manquantes ────────────────────
            $tolesBac = ProductFamily::where('code', 'TOLES_BAC')->whereNull('parent_id')->first();
            if ($tolesBac) {
                foreach ([
                    ['TB_FAITIERE', 'Faîtières'],
                    ['TB_RIVE', 'Rives'],
                    ['TB_NOUE', 'Noues'],
                    ['TB_ACC_PLIE', 'Accessoires pliés'],
                ] as [$code, $name]) {
                    ProductFamily::firstOrCreate(
                        ['code' => $code],
                        ['name' => $name, 'parent_id' => $tolesBac->id, 'depth' => 1, 'is_active' => 1]
                    );
                }
            }

            // ── §9 Unités manquantes ────────────────────────────────────────
            foreach ([
                ['Tôle', 'tôle'], ['Mètre', 'm'], ['Mètre carré', 'm²'], ['Paquet', 'paq'],
            ] as [$name, $abbr]) {
                Unit::firstOrCreate(['name' => $name], ['abbreviation' => $abbr, 'is_active' => 1]);
            }
        });
    }
}
