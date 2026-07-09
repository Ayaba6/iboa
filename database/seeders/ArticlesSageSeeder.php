<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Import des articles du fichier « EXEMPLE ARTICLES A IMPLEMENTER.xlsx »
 * (référentiel SAGE X3) : dépôts, unités, familles 3 niveaux + catégorie,
 * puis 26 articles. Idempotent — updateOrCreate sur les codes.
 *
 * Source de données : database/data/articles_sage.json (extrait du xlsx).
 */
class ArticlesSageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/articles_sage.json')), true);

        $companyId = \App\Models\Company::query()->value('id');

        // ── Dépôts (propriétés CDC : Production / Ventes / Achat / Stock) ────
        // DEPFAB : production+achat+stock, PAS de ventes (les produits fer à
        // béton doivent être transférés vers le dépôt principal avant vente).
        $warehouses = [];
        $depotDefs = [
            'DEPTBC' => ['Dépôt Tôles Bacs',  true,  true],
            'DEPFAB' => ['Dépôt Fer à Béton', true,  false],
            'DEPPRI' => ['Dépôt Principal Vente', false, true],
        ];
        foreach ($depotDefs as $code => [$name, $prod, $sale]) {
            $warehouses[$code] = Warehouse::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name, 'is_active' => true, 'company_id' => $companyId,
                    'can_production' => $prod, 'can_sale' => $sale,
                    'can_purchase' => true, 'can_stock' => true,
                ]
            );
        }

        // ── Unités ───────────────────────────────────────────────────────────
        $units = [];
        $unitNames = ['KG' => 'Kilogramme', 'MTL' => 'Mètre linéaire', 'UN' => 'Unité'];
        foreach ($unitNames as $abbr => $name) {
            $units[$abbr] = Unit::firstOrCreate(
                ['abbreviation' => $abbr],
                ['name' => $name, 'is_active' => true]
            );
        }

        // ── Propriétés des catégories (tableau CDC « Règles de gestion ») ────
        // code => [S<0, GL, UA, US, UV, flux]
        $catProps = [
            'MPTBC' => [false, true,  'KG',  'MTL', 'KG',  ['achete', 'vendu']],
            'MPFAB' => [false, true,  'KG',  'KG',  'KG',  ['achete', 'vendu']],
            'PFTBC' => [false, false, 'MTL', 'MTL', 'MTL', ['vendu', 'fabrique']],
            'AVTBC' => [false, false, 'KG',  'KG',  'KG',  ['vendu', 'fabrique']],
            'CHTBC' => [false, false, 'KG',  'KG',  'KG',  ['vendu', 'fabrique']],
            'PFFAB' => [false, false, 'UN',  'UN',  'UN',  ['vendu', 'fabrique']],
            'AVFAB' => [false, false, 'KG',  'KG',  'KG',  ['vendu', 'fabrique']],
            'CHFAB' => [false, false, 'KG',  'KG',  'KG',  ['vendu', 'fabrique']],
            'MTOND' => [true,  false, 'UN',  'UN',  'UN',  ['achete', 'vendu']],
            'MPROF' => [true,  false, 'UN',  'UN',  'UN',  ['achete', 'vendu']],
        ];

        // ── Familles (catégorie = famille principale + 3 niveaux SAGE) ───────
        $families = [];
        $family = function (string $code, string $name, int $depth) use (&$families, $catProps, $units) {
            if ($code === '') {
                return null;
            }
            $attrs = ['name' => $name, 'depth' => $depth, 'is_active' => true];
            if (isset($catProps[$code])) {
                [$sneg, $gl, $ua, $us, $uv, $flux] = $catProps[$code];

                // Type catégorie + acier (densité 7,85) pour les familles tôle/fer
                $typeCat = match (substr($code, 0, 2)) {
                    'MP'    => 'matiere_premiere',
                    'PF'    => 'produit_fini',
                    default => 'marchandise',
                };
                $isSteel = str_contains($code, 'TBC') || str_contains($code, 'FAB');

                $attrs += [
                    'code_prefix'    => $code,
                    'type_categorie' => $typeCat,
                    'gestion_stock'  => true,
                    'stock_negatif'  => $sneg,
                    'gestion_lot'    => $gl,
                    'lot_obligatoire'=> $gl, // bobines tracées → lot obligatoire
                    'unite_achat_id' => $units[$ua]->id ?? null,
                    'unite_stock_id' => $units[$us]->id ?? null,
                    'unite_vente_id' => $units[$uv]->id ?? null,
                    'type_flux'      => $flux,
                    'controle_qualite' => $typeCat !== 'marchandise',
                    'cq_entree'      => $typeCat === 'matiere_premiere',
                    'cq_sortie'      => $typeCat === 'produit_fini',
                    'numerotation_auto' => true,
                    'utilisable_production' => in_array('fabrique', $flux) || $typeCat === 'matiere_premiere',
                    'densite'        => $isSteel ? 7.850 : null,
                ];
            }
            return $families[$code] ??= ProductFamily::updateOrCreate(['code' => $code], $attrs);
        };

        // ── Articles ─────────────────────────────────────────────────────────
        foreach ($rows as $r) {
            $cat = $family($r['cat_code'], $r['cat_name'], 0);
            $f1  = $family($r['f1_code'], $r['f1_name'], 0);
            $f2  = $family($r['f2_code'], $r['f2_name'], 1);
            $f3  = $family($r['f3_code'], $r['f3_name'], 2);

            // Type métier dérivé du préfixe de catégorie SAGE
            $typeArticle = match (substr($r['cat_code'], 0, 2)) {
                'MP'    => 'matiere_premiere',
                'PF'    => 'produit_fini',
                default => 'marchandise', // AV (avaries), CH (chutes), MT (marchandises)
            };

            $isMp = $typeArticle === 'matiere_premiere';
            $isPf = $typeArticle === 'produit_fini';

            // Flux hérités de la catégorie (tableau CDC) — l'article prend les
            // propriétés de sa catégorie à la création.
            $flux = $catProps[$r['cat_code']][5] ?? [];
            $sneg = $catProps[$r['cat_code']][0] ?? false;

            $descParts = ['Réf. SAGE X3 : ' . $r['reference']];

            Product::updateOrCreate(
                ['code_article' => $r['code']],
                [
                    'reference'           => $r['reference'],
                    'name'                => $r['name'],
                    'designation_courte'  => mb_substr($r['name'], 0, 80),
                    'description'         => implode(' · ', $descParts),
                    'family_id'           => $cat?->id,
                    'famille1_id'         => $f1?->id,
                    'famille2_id'         => $f2?->id,
                    'famille3_id'         => $f3?->id,
                    'type_article'        => $typeArticle,
                    'type'                => 'simple',
                    'statut'              => 'actif',
                    'is_active'           => true,
                    'is_stockable'        => strtolower($r['stock']) === 'oui',
                    'is_purchasable'      => in_array('achete', $flux),
                    'is_sellable'         => in_array('vendu', $flux),
                    'is_manufacturable'   => in_array('fabrique', $flux),
                    'allow_negative_stock'=> $sneg,
                    'production_mode'     => $isPf ? 'mto' : null,
                    'has_lot_number'      => $isMp, // bobines tracées par lot
                    'controle_qualite'    => $isMp || $isPf,
                    'unit_id'             => $units[$r['u_stock']]->id ?? null,
                    'purchase_unit_id'    => $units[$r['u_achat']]->id ?? null,
                    'sale_unit_id'        => $units[$r['u_vente']]->id ?? null,
                    'net_weight_per_us'   => $r['poids_net'] ?: 0,
                    'gross_weight_per_us' => $r['poids_brut'] ?: 0,
                    'thickness'           => $r['epaisseur'] ?: null,
                    'linear_meters'       => $r['metrage'] ?: null,
                    'density'             => $r['densite'] ?: null,
                    'weight_unit'         => 'kg',
                    'main_warehouse_id'   => $warehouses[$r['depot1']]->id ?? $warehouses[$r['depot2']]->id ?? null,
                ]
            );
        }

        // ── Dépôts autorisés par catégorie (pivot category_warehouse) ────────
        // Chaque catégorie hérite des capacités du dépôt (Production/Vente/
        // Achat/Stock) tel que défini sur la fiche dépôt.
        $depotPivot = [];
        foreach ($warehouses as $wh) {
            $depotPivot[$wh->id] = [
                'can_production' => (bool) $wh->can_production,
                'can_sale'       => (bool) $wh->can_sale,
                'can_purchase'   => (bool) $wh->can_purchase,
                'can_stock'      => (bool) $wh->can_stock,
            ];
        }
        foreach ($families as $code => $fam) {
            if (isset($catProps[$code])) { // uniquement les catégories principales SAGE
                $fam->warehouses()->sync($depotPivot);
            }
        }

        $this->command?->info(count($rows) . ' articles SAGE importés (familles, unités, dépôts et pivots créés au besoin).');
    }
}
