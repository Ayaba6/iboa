<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

/**
 * [X3 §3] Les 10 catégories de gestion d'OA METAL INDUSTRIE.
 * Idempotent : updateOrCreate par code — ne duplique jamais, ne touche pas
 * aux surcharges faites ensuite via l'écran (seuls les défauts absents sont posés).
 */
class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'PF_TOLE_MTO', 'name' => 'Tôles fabriquées sur commande', 'nature' => 'produit_fini',
             'strategy' => 'mto', 'is_manufactured' => true, 'is_sellable' => true, 'is_stockable' => true,
             'usable_as_finished' => true, 'bom_required' => true, 'routing_required' => true, 'auto_of' => true,
             'qc_required' => true, 'floor_price_required' => true, 'coil_managed' => false,
             'offcut_managed' => true, 'cutting_optimized' => true, 'sort_order' => 10],
            ['code' => 'PF_FER_MTS', 'name' => 'Fer à béton produit pour stock', 'nature' => 'produit_fini',
             'strategy' => 'mts', 'is_manufactured' => true, 'is_sellable' => true, 'is_stockable' => true,
             'usable_as_finished' => true, 'bom_required' => true, 'qc_required' => true,
             'mrp_planned' => true, 'floor_price_required' => true, 'sort_order' => 20],
            ['code' => 'MP_BOBINE', 'name' => 'Bobines matières premières', 'nature' => 'matiere_premiere',
             'strategy' => 'achat_revente', 'is_purchasable' => true, 'is_stockable' => true,
             'usable_in_bom' => true, 'lot_managed' => true, 'coil_managed' => true,
             'qc_on_receipt' => true, 'sort_order' => 30],
            ['code' => 'MP_STANDARD', 'name' => 'Matières premières standards', 'nature' => 'matiere_premiere',
             'strategy' => 'achat_revente', 'is_purchasable' => true, 'is_stockable' => true,
             'usable_in_bom' => true, 'sort_order' => 40],
            ['code' => 'MARCHANDISE', 'name' => 'Marchandises achetées-revendues', 'nature' => 'marchandise',
             'strategy' => 'achat_revente', 'is_purchasable' => true, 'is_sellable' => true,
             'is_stockable' => true, 'sort_order' => 50],
            ['code' => 'CONSOMMABLE', 'name' => 'Consommables industriels', 'nature' => 'consommable',
             'strategy' => 'conso_interne', 'is_purchasable' => true, 'is_stockable' => true,
             'usable_in_bom' => true, 'sort_order' => 60],
            ['code' => 'SERVICE_ACHAT', 'name' => 'Services achetés', 'nature' => 'service',
             'strategy' => 'service', 'is_purchasable' => true, 'is_stockable' => false, 'sort_order' => 70],
            ['code' => 'SERVICE_VENTE', 'name' => 'Prestations vendues', 'nature' => 'service',
             'strategy' => 'service', 'is_sellable' => true, 'is_stockable' => false, 'sort_order' => 80],
            ['code' => 'SOUS_PRODUIT', 'name' => 'Sous-produits et chutes réutilisables', 'nature' => 'sous_produit',
             'strategy' => 'mts', 'is_sellable' => true, 'is_stockable' => true, 'sort_order' => 90],
            ['code' => 'REBUT', 'name' => 'Rebuts industriels', 'nature' => 'rebut',
             'is_sellable' => false, 'is_stockable' => true, 'sort_order' => 100],
        ];

        foreach ($rows as $row) {
            ItemCategory::withTrashed()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'valuation_method' => 'cmp']
            );
        }
    }
}
