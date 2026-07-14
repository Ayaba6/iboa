<?php

namespace App\Modules\Production\Services;

use App\Models\ProductStock;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION ↔ VENTES/STOCK] Réservation du produit fini fabriqué pour le
 * client de la commande liée à l'OF. Bump `product_stocks.reserved_quantity`
 * → la quantité disponible (quantity − reserved) exclut le PF promis au client.
 */
class ReservationService
{
    /** Réserve le produit fini de l'OF terminé pour son client. */
    public function reserveForOrder(ProductionOrder $order): StockReservation
    {
        if ($order->status !== 'termine') {
            throw ValidationException::withMessages(['status' => 'Réservation possible seulement sur un OF terminé.']);
        }
        if (! $order->product_id) {
            throw ValidationException::withMessages(['product' => 'Aucun produit fini défini sur l\'OF.']);
        }

        // On réserve strictement le produit fini RÉELLEMENT fabriqué (en stock).
        // Pas de repli sur quantity_requested : réserver une quantité non produite
        // créerait une réservation sans stock en face (dispo négative).
        $qty = (float) $order->quantity_produced;
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantité produite nulle — rien à réserver.']);
        }

        if (StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->exists()) {
            throw ValidationException::withMessages(['status' => 'Le produit fini de cet OF est déjà réservé.']);
        }

        $warehouseId = $order->outputs()->whereNotNull('warehouse_id')->value('warehouse_id')
            ?? Warehouse::where('company_id', $order->company_id)->orderByDesc('is_default')->value('id');

        return DB::transaction(function () use ($order, $qty, $warehouseId) {
            $reservation = StockReservation::create([
                'company_id'          => $order->company_id,
                'order_id'            => $order->order_id,
                'production_order_id' => $order->id,
                'product_id'          => $order->product_id,
                'warehouse_id'        => $warehouseId,
                'quantity'            => $qty,
                'status'              => 'reserved',
                'reserved_at'         => now(),
                'created_by'          => Auth::id(),
            ]);

            $this->adjustReserved($order->product_id, $warehouseId, $qty);

            return $reservation;
        });
    }

    /**
     * Réserve le produit fini DISPONIBLE EN STOCK pour les lignes d'une commande
     * (réservation directe stock, sans OF). Répartit sur les entrepôts disposant
     * de stock. Retourne la quantité totale réservée.
     */
    public function reserveStockForOrder(\App\Models\Order $order): float
    {
        $analysis = app(SalesProductionService::class)->stockAnalysis($order);
        $totalReserved = 0.0;

        DB::transaction(function () use ($order, $analysis, &$totalReserved) {
            foreach ($analysis['lines'] as $line) {
                $need = (float) $line['reservable'];
                if ($need <= 0) {
                    continue;
                }

                $stocks = ProductStock::where('product_id', $line['product_id'])
                    ->whereRaw('(quantity - reserved_quantity) > 0')
                    ->orderByRaw('(quantity - reserved_quantity) DESC')
                    ->lockForUpdate()->get();

                foreach ($stocks as $stock) {
                    if ($need <= 0) {
                        break;
                    }
                    $avail = (float) $stock->quantity - (float) $stock->reserved_quantity;
                    $take  = min($need, $avail);
                    if ($take <= 0) {
                        continue;
                    }

                    StockReservation::create([
                        'company_id'   => $order->company_id,
                        'order_id'     => $order->id,
                        'product_id'   => $line['product_id'],
                        'warehouse_id' => $stock->warehouse_id,
                        'quantity'     => $take,
                        'status'       => 'reserved',
                        'reserved_at'  => now(),
                        'created_by'   => Auth::id(),
                    ]);
                    $stock->update(['reserved_quantity' => (float) $stock->reserved_quantity + $take]);

                    $need -= $take;
                    $totalReserved += $take;
                }
            }
        });

        return $totalReserved;
    }

    /**
     * [FIX A4 — rapport de test MTO] Réservation FERME de la matière première à
     * l'allocation de l'OF : pour chaque composant de la nomenclature suivi en
     * product_stocks, réserve min(besoin théorique, disponible) au dépôt de sortie.
     * Le besoin = quantité demandée × qté/m × (1 + taux de perte). La réservation
     * (production_order_id, sans order_id → aucune interaction avec les réservations
     * de vente) bloque la même matière pour un autre OF ; elle est libérée au
     * backflush de la déclaration, à la clôture ou à l'annulation de l'OF.
     * Retourne la quantité totale réservée. Idempotent par produit/OF.
     */
    public function reserveMaterialsForOrder(ProductionOrder $order): float
    {
        $order->loadMissing('billOfMaterial.lines.product');
        $bom = $order->billOfMaterial;
        if (! $bom) {
            return 0.0;
        }

        $qty = (float) $order->quantity_requested;
        $totalReserved = 0.0;

        DB::transaction(function () use ($order, $bom, $qty, &$totalReserved) {
            foreach ($bom->lines as $line) {
                $product = $line->product;
                $per     = (float) $line->quantity_per_meter;
                if (! $product || $per <= 0 || $qty <= 0) {
                    continue;
                }

                // Idempotence : matière déjà réservée pour ce produit sur cet OF.
                if (StockReservation::where('production_order_id', $order->id)
                    ->where('product_id', $product->id)
                    ->where('status', 'reserved')->exists()) {
                    continue;
                }

                $need = round($per * $qty * (1 + (float) ($line->waste_rate ?? 0) / 100), 4);

                $stockQuery = ProductStock::where('product_id', $product->id)
                    ->whereRaw('(quantity - reserved_quantity) > 0');
                if ($line->depot_sortie_id) {
                    $stockQuery->where('warehouse_id', $line->depot_sortie_id);
                }
                $stock = $stockQuery->orderByRaw('(quantity - reserved_quantity) DESC')->lockForUpdate()->first();
                if (! $stock) {
                    continue; // pas de stock suivi (ex. bobine hors product_stocks) — signalé par materialShortages
                }

                $avail = (float) $stock->quantity - (float) $stock->reserved_quantity;
                $take  = min($need, $avail);
                if ($take <= 0) {
                    continue;
                }

                StockReservation::create([
                    'company_id'          => $order->company_id,
                    'production_order_id' => $order->id,
                    'product_id'          => $product->id,
                    'warehouse_id'        => $stock->warehouse_id,
                    'quantity'            => $take,
                    'status'              => 'reserved',
                    'reserved_at'         => now(),
                    'created_by'          => Auth::id(),
                ]);
                $stock->update(['reserved_quantity' => (float) $stock->reserved_quantity + $take]);
                $totalReserved += $take;
            }
        });

        return $totalReserved;
    }

    /**
     * [FIX A4] Libère les réservations MATIÈRE actives d'un OF — toutes, ou celles
     * d'un produit donné (appelé avant le backflush pour que la propre réservation
     * de l'OF ne bloque pas sa sortie de composant).
     */
    public function releaseMaterialReservations(ProductionOrder $order, ?int $productId = null): int
    {
        return $this->releaseMany(
            StockReservation::where('production_order_id', $order->id)
                ->where('status', 'reserved')
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                // Jamais la réservation du produit fini (posée à la clôture pour le client).
                ->when($order->product_id, fn ($q) => $q->where('product_id', '!=', $order->product_id))
                ->get()
        );
    }

    /** Libère toutes les réservations actives d'une commande (annulation commande). */
    public function releaseForOrder(\App\Models\Order $order): int
    {
        return $this->releaseMany(
            StockReservation::where('order_id', $order->id)->where('status', 'reserved')->get()
        );
    }

    /** Libère toutes les réservations actives d'un OF (annulation production). */
    public function releaseForProductionOrder(ProductionOrder $order): int
    {
        return $this->releaseMany(
            StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->get()
        );
    }

    private function releaseMany(\Illuminate\Support\Collection $reservations): int
    {
        $n = 0;
        foreach ($reservations as $r) {
            $this->release($r);
            $n++;
        }

        return $n;
    }

    /** Libère une réservation (restitue la disponibilité). */
    public function release(StockReservation $reservation): void
    {
        if ($reservation->status !== 'reserved') {
            throw ValidationException::withMessages(['status' => 'Réservation déjà libérée ou consommée.']);
        }

        DB::transaction(function () use ($reservation) {
            $this->adjustReserved($reservation->product_id, $reservation->warehouse_id, -(float) $reservation->quantity);
            $reservation->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    private function adjustReserved(int $productId, ?int $warehouseId, float $delta): void
    {
        if (! $warehouseId) {
            return;
        }
        $stock = ProductStock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0],
        );
        $stock = ProductStock::lockForUpdate()->find($stock->id);
        $new = max(0, (float) $stock->reserved_quantity + $delta);
        $stock->update(['reserved_quantity' => $new]);
    }
}
