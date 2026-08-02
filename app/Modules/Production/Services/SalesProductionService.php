<?php

namespace App\Modules\Production\Services;

use App\Models\Order;
use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Services\Sales\FulfillmentStrategyResolver;
use Illuminate\Support\Collection;

/**
 * [VENTE → PRODUCTION] Cockpit production d'une commande client.
 * Agrège l'état des ordres de fabrication liés à la commande (lecture seule)
 * pour l'afficher sur la fiche commande.
 */
class SalesProductionService
{
    public function summary(Order $order): array
    {
        $ofs = $order->productionOrders()
            ->with(['qualityControls', 'outputs'])
            ->orderByDesc('id')->get();

        $rows = $ofs->map(function ($of) {
            $qc = $of->qualityControls->sortByDesc('id')->first();

            return [
                'id'             => $of->id,
                'number'         => $of->number,
                'status'         => $of->status,
                'status_label'   => $of->statusLabel(),
                'qty_requested'  => (float) $of->quantity_requested,
                'qty_produced'   => (float) $of->quantity_produced,
                'qc_status'      => $qc?->status,
                'qc_label'       => $qc?->statusLabel(),
                'has_output'     => $of->outputs->isNotEmpty(),
            ];
        });

        return [
            'orders'    => $rows,
            'count'     => $rows->count(),
            'aggregate' => $this->aggregate($rows),
        ];
    }

    /**
     * Analyse de disponibilité du produit fini par ligne de commande :
     * dispo stock vs commandé → réserver (dispo) ou produire (déficit).
     */
    public function stockAnalysis(Order $order): array
    {
        $order->loadMissing('items.product');

        $resolver = app(FulfillmentStrategyResolver::class);

        $lines = $order->items->filter(fn ($i) => $i->product_id)->map(function ($item) use ($order, $resolver) {
            $ordered   = (float) $item->quantity;
            $mode      = $resolver->resolve($item->product);

            // [D5] MTO — la production est déclenchée par la commande, pas par un
            // manque de stock. Aucun stock général n'est retenu : un même code
            // article couvre des couleurs, épaisseurs, profils, largeurs et
            // longueurs différents, peut être en quarantaine, affecté à un autre
            // client ou issu d'un autre OF. Rien de tout cela n'est vérifié par
            // une simple égalité de `product_id`.
            //
            // Une réutilisation de stock MTO passera par une réaffectation
            // explicite et tracée ; tant que ce workflow n'existe pas, l'absence
            // de déduction est la seule réponse sûre.
            if ($mode === FulfillmentStrategyResolver::MTO) {
                $delivered = (float) $item->delivered_quantity;
                $reste     = max(0, $ordered - $delivered);

                return [
                    'product_id' => $item->product_id,
                    'product'    => $item->product?->name ?? $item->description,
                    'mode'       => $mode,
                    'source'     => 'commande',
                    'ordered'    => $ordered,
                    'delivered'  => round($delivered, 2),
                    'available'  => 0.0,
                    'reserved'   => 0.0,
                    'reservable' => 0.0,
                    'to_produce' => round($reste, 2),
                    'decision'   => $reste <= 0 ? 'livre' : 'produce',
                ];
            }

            // [FIX BUG-007] Cohérence commande ↔ BL : si la commande fixe un dépôt de
            // livraison, la dispo se calcule sur CE dépôt (le BL y puisera). Sinon
            // tous dépôts confondus (le BL résout alors le dépôt détenant le stock).
            $available = (float) ProductStock::where('product_id', $item->product_id)
                            ->when($order->delivery_warehouse_id, fn ($q) => $q->where('warehouse_id', $order->delivery_warehouse_id))
                            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity),0) a')->value('a');
            $reserved  = (float) StockReservation::where('order_id', $order->id)
                            ->where('product_id', $item->product_id)->where('status', 'reserved')->sum('quantity');
            // [FIX widget] La quantité déjà livrée ne reste ni à réserver ni à produire.
            // Sans ce retrait, une commande livrée à 100 % affichait encore « à produire ».
            $delivered = (float) $item->delivered_quantity;

            $netNeeded  = max(0, $ordered - $delivered - $reserved);
            $reservable = min($netNeeded, max(0, $available));
            $toProduce  = max(0, $netNeeded - $available);

            $decision = $delivered >= $ordered
                ? 'livre'
                : ($toProduce <= 0 ? 'stock' : ($reservable <= 0 ? 'produce' : 'mixed'));

            return [
                'product_id' => $item->product_id,
                'product'    => $item->product?->name ?? $item->description,
                'mode'       => $mode,
                'source'     => 'stock',
                'ordered'    => $ordered,
                'delivered'  => round($delivered, 2),
                'available'  => round($available, 2),
                'reserved'   => round($reserved, 2),
                'reservable' => round($reservable, 2),
                'to_produce' => round($toProduce, 2),
                'decision'   => $decision,
            ];
        })->values();

        return [
            'lines'      => $lines,
            'reservable' => (float) $lines->sum('reservable'),
            'to_produce' => (float) $lines->sum('to_produce'),
        ];
    }

    /** KPI Vente orientés production pour le dashboard. */
    public function dashboardKpis(): array
    {
        $enProduction = Order::whereHas('productionOrders', fn ($q) => $q->whereIn('status', ['lance', 'en_cours']))->count();

        $pretesALivrer = Order::whereNotIn('status', ['livre', 'facture', 'annule'])
            ->whereHas('productionOrders', fn ($q) => $q->where('status', 'termine')->whereHas('outputs'))
            ->count();

        $livreesNonFacturees = Order::where('status', 'livre')
            ->whereDoesntHave('invoices', fn ($q) => $q->where('status', '!=', 'annulee'))
            ->count();

        $totalQuotes = \App\Models\Quote::count();
        $converted   = \App\Models\Quote::where('status', 'converti')->count();
        $tauxTransfo = $totalQuotes > 0 ? round($converted / $totalQuotes * 100, 1) : 0;

        return [
            'en_production'           => $enProduction,
            'pretes_a_livrer'         => $pretesALivrer,
            'livrees_non_facturees'   => $livreesNonFacturees,
            'taux_transfo'            => $tauxTransfo,
        ];
    }

    private function aggregate(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['label' => 'Aucun OF', 'color' => 'gray', 'none' => true];
        }

        $statuses = $rows->pluck('status');

        if ($rows->contains('qc_status', 'non_conforme')) {
            return ['label' => 'Qualité non conforme', 'color' => 'red'];
        }
        if ($statuses->every(fn ($s) => $s === 'termine')) {
            return ['label' => 'Produit fini disponible', 'color' => 'green'];
        }
        if ($statuses->contains('en_cours')) {
            return ['label' => 'En production', 'color' => 'sky'];
        }
        if ($statuses->contains('lance')) {
            return ['label' => 'OF lancé', 'color' => 'amber'];
        }
        if ($statuses->contains('termine')) {
            return ['label' => 'Partiellement produit', 'color' => 'teal'];
        }

        return ['label' => 'OF créé (brouillon)', 'color' => 'gray'];
    }
}
