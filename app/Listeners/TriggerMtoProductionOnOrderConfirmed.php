<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Models\ProductStock;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * [VENTE → PRODUCTION] Déclenchement automatique d'un ordre de fabrication
 * pour chaque ligne de commande dont l'article est en mode MTO (Make To Order)
 * et dont le stock disponible est insuffisant.
 *
 * Crée un OF en brouillon (pour la quantité manquante) lié à la commande ;
 * l'équipe production complète ensuite l'allocation matière et le lance.
 *
 * Synchrone (même transaction que la confirmation). Jamais bloquant : un échec
 * de création d'OF est journalisé sans faire échouer la confirmation — le
 * bouton « Lancer en production » reste le filet manuel.
 */
class TriggerMtoProductionOnOrderConfirmed
{
    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;

            // Uniquement les articles MTO avec une nomenclature active.
            if (! $product || $product->production_mode !== 'mto') {
                continue;
            }
            $bom = BillOfMaterial::where('product_id', $product->id)->where('is_active', true)->first();
            if (! $bom) {
                continue;
            }

            // Idempotence : un OF existe déjà pour cette commande + article.
            if (ProductionOrder::where('order_id', $order->id)->where('product_id', $product->id)->exists()) {
                continue;
            }

            // Quantité manquante = commandé − disponible RÉEL pour cette commande.
            // [FIX BUG-003] La réservation propre à la commande (posée par OrderConfirmed
            // AVANT ce listener) est rajoutée : sinon elle réduit `available` à ~0 et
            // l'OF réclame la quantité totale commandée au lieu du seul manquant.
            $physicalAvailable = (float) ProductStock::where('product_id', $product->id)
                ->get(['quantity', 'reserved_quantity'])
                ->sum(fn ($s) => (float) $s->quantity - (float) $s->reserved_quantity);
            $ownReservation = (float) \App\Models\StockReservation::where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->where('status', 'reserved')
                ->sum('quantity');
            $available = $physicalAvailable + $ownReservation;
            $shortfall = (float) $item->quantity - max(0, $available);
            if ($shortfall <= 0) {
                continue;
            }

            try {
                app(ProductionService::class)->create([
                    'client_id'           => $order->client_id,
                    'order_id'            => $order->id,
                    'product_id'          => $product->id,
                    'bill_of_material_id' => $bom->id,
                    'quantity_requested'  => $shortfall,
                    'sheet_type'          => $bom->sheet_type,
                    'thickness'           => $bom->thickness,
                    'usable_width'        => $bom->usable_width,
                    'responsible_id'      => Auth::id(),
                    'notes'               => 'OF auto (MTO) depuis commande ' . $order->number,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Auto-OF MTO non créé pour la commande ' . $order->number, [
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
