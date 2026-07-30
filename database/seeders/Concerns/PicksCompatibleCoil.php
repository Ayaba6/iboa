<?php

namespace Database\Seeders\Concerns;

use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilCompatibilityService;
use Illuminate\Support\Facades\DB;

/**
 * [MTO §9] Choix d'une bobine COHÉRENTE avec l'OF, pour les jeux de démonstration.
 *
 * Les seeders tiraient jusqu'ici une bobine `inRandomOrder()` sans regarder ce
 * que l'OF fabriquait : une bobine verte pouvait alimenter un OF gris, une
 * bobine de 40/100 un OF de 35/100. Ces attelages étaient physiquement
 * impossibles, et personne ne s'en apercevait puisque rien ne les vérifiait.
 * `CoilCompatibilityService` les refuse désormais — il a révélé le défaut, il ne
 * l'a pas créé.
 *
 * La correction est prise à la source : on choisit une bobine compatible, et à
 * défaut on en crée une qui l'est. Créer une bobine dans un SEEDER n'invente
 * aucune donnée métier : un jeu de démonstration a précisément pour objet de
 * produire l'état de départ dont il a besoin. La garantie compte, car
 * ProductionSeederTest exige au moins une consommation.
 */
trait PicksCompatibleCoil
{
    protected function pickOrCreateCompatibleCoil(ProductionOrder $order, float $need): ?Coil
    {
        $compat = app(CoilCompatibilityService::class);

        $candidate = $compat->compatibleCoilsQuery($order)
            ->where('remaining_weight', '>=', $need)
            ->inRandomOrder()
            ->get()
            // `compatibleCoilsQuery` filtre sur l'article et le dépôt ; la
            // vérification complète (couleur, épaisseur, largeur, lot, société)
            // ne s'exprime pas en SQL et se fait ici, bobine par bobine.
            ->first(fn (Coil $c) => $compat->isCompatible($order, $c));

        return $candidate ?? $this->createCoilMatching($order, $need);
    }

    /** Bobine taillée pour cet OF : même article, même dépôt, mêmes caractéristiques. */
    private function createCoilMatching(ProductionOrder $order, float $need): ?Coil
    {
        $productId = $order->bill_of_material_id
            ? DB::table('bom_lines')
                ->where('bill_of_material_id', $order->bill_of_material_id)
                ->where(fn ($q) => $q->whereNull('statut')->orWhere('statut', 'actif'))
                ->value('product_id')
            : null;

        if (! $productId) {
            return null; // sans composant identifiable, rien de cohérent à fabriquer
        }

        $warehouseId = $order->depot_matiere_id
            ?? Warehouse::where('company_id', $order->company_id)
                ->orderByDesc('is_default')->value('id');

        $poids = max(500.0, $need * 3);

        return Coil::create([
            'company_id'       => $order->company_id,
            'product_id'       => $productId,
            'warehouse_id'     => $warehouseId,
            'reference'        => 'BOB-COMP-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT).'-'.random_int(100, 999),
            'initial_weight'   => $poids,
            'remaining_weight' => $poids,
            'cost_per_kg'      => 800,
            'status'           => 'disponible',
            'valuation_status' => 'valorisation_definitive',
            // Caractéristiques reprises de l'OF : c'est ce qui rend l'attelage
            // cohérent, et ce que le tirage aléatoire ne pouvait pas garantir.
            'color'            => $order->color,
            'thickness'        => $order->thickness,
            'width'            => $order->largeur_totale,
            'coating'          => $order->revetement,
        ]);
    }
}
