<?php

namespace App\Modules\Production\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [MTS §2] Calcul du BESOIN NET des articles fabriqués pour le stock.
 *
 *   besoin = cible + sécurité + demande client ferme
 *          − disponible − OF déjà planifiés − réceptions attendues
 *
 * Ce calcul servait deux écrans qui l'auraient dupliqué : la planification MTS,
 * qui l'affiche article par article, et les propositions d'OF du MRP, qui le
 * traitent par lot. Deux implémentations d'une même règle de gestion finissent
 * toujours par diverger — elle vit donc ici, et une seule fois.
 *
 * DEUX PRÉCAUTIONS qui ne sont pas cosmétiques :
 *
 *   - les seuils sont lus en NUMÉRIQUE. Une colonne decimal remonte la chaîne
 *     « 0.00 », qui est VRAIE en PHP : un `?:` retenait « 0.00 » comme cible et
 *     annulait tout besoin, quel que soit l'état du stock ;
 *   - les statuts de commande fournisseur sont ceux de l'énumération réelle,
 *     au MASCULIN (`envoye`, `confirme`, `partiellement_recu`). Écrits au
 *     féminin ils ne correspondent à aucune ligne et le terme vaut
 *     silencieusement zéro.
 */
class NetRequirementService
{
    /** Statuts de commande fournisseur dont la marchandise est encore attendue. */
    public const PO_OPEN = ['envoye', 'confirme', 'partiellement_recu'];

    /** Statuts de commande client portant une demande ferme non encore livrée. */
    public const SO_OPEN = ['confirme', 'en_preparation', 'partiellement_livre'];

    /**
     * Besoins nets des articles fabriqués pour le stock (MTS), actifs et stockés.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function forMtsProducts(): Collection
    {
        return $this->forProducts(
            Product::where('production_mode', 'mts')
                ->where('is_stockable', true)
                ->where('is_active', true)
                ->orderBy('name')->get()
        );
    }

    /**
     * Ne retient que les articles présentant un besoin ET fabricables — un besoin
     * sans nomenclature active ne peut pas devenir un ordre de fabrication.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function proposals(): Collection
    {
        return $this->forMtsProducts()
            ->filter(fn ($r) => $r['besoin'] > 0 && $r['bom_id'] !== null)
            ->values();
    }

    /**
     * @param  Collection<int,Product>  $products
     * @return Collection<int, array<string,mixed>>
     */
    public function forProducts(Collection $products): Collection
    {
        $ids = $products->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $stocks = ProductStock::whereIn('product_id', $ids)
            ->selectRaw('product_id, SUM(quantity) qty, SUM(reserved_quantity) reserved')
            ->groupBy('product_id')->get()->keyBy('product_id');

        $planned = ProductionOrder::whereIn('product_id', $ids)
            ->whereNotIn('status', ['termine', 'annule'])
            ->selectRaw('product_id, SUM(quantity_requested) qty')
            ->groupBy('product_id')->pluck('qty', 'product_id');

        $attendu = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->whereNull('po.deleted_at')
            ->whereIn('po.status', self::PO_OPEN)
            ->whereIn('poi.product_id', $ids)
            ->whereColumn('poi.received_quantity', '<', 'poi.quantity')
            ->selectRaw('poi.product_id AS pid, SUM(poi.quantity - poi.received_quantity) AS qte')
            ->groupBy('poi.product_id')->get()->pluck('qte', 'pid');

        $demande = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', self::SO_OPEN)
            ->whereIn('oi.product_id', $ids)
            ->whereColumn('oi.delivered_quantity', '<', 'oi.quantity')
            ->selectRaw('oi.product_id AS pid, SUM(oi.quantity - oi.delivered_quantity) AS qte')
            ->groupBy('oi.product_id')->get()->pluck('qte', 'pid');

        // Nomenclature active par article — sans elle, pas d'OF possible.
        $boms = BillOfMaterial::whereIn('product_id', $ids)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get(['id', 'product_id'])
            ->keyBy('product_id');

        return $products->map(function (Product $p) use ($stocks, $planned, $attendu, $demande, $boms) {
            $physique = (float) ($stocks[$p->id]->qty ?? 0);
            $reserve  = (float) ($stocks[$p->id]->reserved ?? 0);
            $dispo    = $physique - $reserve;
            $plan     = (float) ($planned[$p->id] ?? 0);
            $recu     = (float) ($attendu[$p->id] ?? 0);
            $client   = (float) ($demande[$p->id] ?? 0);

            $min     = (float) ($p->stock_min ?? 0);
            $max     = (float) ($p->stock_max ?? 0);
            $reorder = (float) ($p->reorder_point ?? 0);
            $secu    = (float) ($p->stock_securite ?? 0);

            // Cible = niveau de recomplètement. À défaut de maximum, le point de
            // commande puis le minimum en tiennent lieu.
            $cible = $max > 0 ? $max : ($reorder > 0 ? $reorder : $min);
            // Seuil de déclenchement : le point de commande prime sur le minimum.
            $seuil = $reorder > 0 ? $reorder : $min;

            $parametre = $cible > 0 || $seuil > 0 || $secu > 0;

            // Borne flottante : `max(0, …)` rendrait un entier quand elle
            // s'applique et un flottant sinon — deux types pour une même grandeur.
            $besoin = max(0.0, $cible + $secu + $client - $dispo - $plan - $recu);

            // Un article sans aucun seuil n'est pas « en rupture » : il n'est pas
            // piloté. Le dire donne la bonne action — compléter la fiche article —
            // au lieu d'accuser un stock qui n'a jamais eu de cible.
            $etat = match (true) {
                ! $parametre                  => 'non_parametre',
                $dispo <= 0                   => 'rupture',
                $seuil > 0 && $dispo < $seuil => 'sous_min',
                default                       => 'ok',
            };

            return [
                'p' => $p, 'physique' => $physique, 'reserve' => $reserve, 'dispo' => $dispo,
                'plan' => $plan, 'recu' => $recu, 'client' => $client,
                'cible' => $cible, 'seuil' => $seuil, 'secu' => $secu,
                'besoin' => $besoin, 'etat' => $etat, 'parametre' => $parametre,
                'bom_id' => $boms[$p->id]->id ?? null,
            ];
        });
    }
}
