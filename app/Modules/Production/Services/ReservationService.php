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
