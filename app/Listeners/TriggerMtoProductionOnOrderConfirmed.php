<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Services\Sales\FulfillmentStrategyResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * [VENTE → PRODUCTION] Déclenchement automatique d'un ordre de fabrication
 * pour chaque ligne de commande dont l'article est en mode MTO (Make To Order).
 *
 * [D5] L'OF porte la quantité commandée COMPLÈTE, sans déduction du stock
 * général : un même code article ne garantit ni la couleur, ni l'épaisseur, ni
 * le profil, ni l'absence d'affectation à un autre client.
 *
 * Crée un OF en brouillon lié à la commande ;
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

            // Uniquement les articles MTO avec une nomenclature active. Le mode
            // passe par le résolveur, pas par le champ brut : la stratégie peut
            // venir de la catégorie quand l'article ne la porte pas.
            if (! $product
                || app(FulfillmentStrategyResolver::class)->resolve($product) !== FulfillmentStrategyResolver::MTO) {
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

            // [D5] L'OF porte la quantité commandée COMPLÈTE.
            //
            // Cette ligne calculait auparavant `commandé − stock général
            // disponible`, et ne produisait que le manquant. La déduction
            // supposait, sans jamais le vérifier, que du stock portant le même
            // `product_id` est interchangeable avec la commande. Il ne l'est pas :
            // un même code article couvre des couleurs, épaisseurs, profils,
            // largeurs, longueurs, nuances et revêtements différents ; le stock
            // peut être en quarantaine, affecté à un autre client, ou issu d'un
            // autre OF. Aucune de ces dimensions n'entrait dans le calcul.
            //
            // Conséquence de l'ancienne règle : un stock couvrant la commande
            // faisait qu'AUCUN OF n'était créé, et la tôle bac partait sur un
            // reliquat dont rien ne prouvait la compatibilité.
            //
            // La réutilisation d'un reliquat MTO relèvera d'une réaffectation
            // explicite — produit identique, caractéristiques compatibles, lot
            // libéré, non affecté ailleurs, motif et auteur. Tant que ce workflow
            // n'existe pas, l'OF couvre la totalité.
            $quantite = (float) $item->quantity;
            if ($quantite <= 0) {
                continue;
            }

            try {
                app(ProductionService::class)->create([
                    'client_id'           => $order->client_id,
                    'order_id'            => $order->id,
                    'product_id'          => $product->id,
                    'bill_of_material_id' => $bom->id,
                    'quantity_requested'  => $quantite,

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
