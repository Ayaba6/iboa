<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionOrder;
use App\Services\AccountingService;
use Illuminate\Support\Facades\Log;

/**
 * [PRODUCTION] Pont comptable SYSCOHADA de la fabrication.
 *
 * DÉSACTIVÉ PAR DÉFAUT (config/production.php → accounting.enabled).
 * Quand activé, la clôture d'un OF génère les écritures :
 *   - consommation matière  : DR 6032 / CR 321  (= coût matière réel consommé)
 *   - production stockée PF  : DR 361  / CR 736  (= valeur entrée en stock PF)
 *
 * Idempotent : AccountingService refuse de re-poster une référence déjà émise
 * (OF-xxxx-CONS / OF-xxxx-PROD), donc un appel répété est sans effet.
 */
class ProductionAccountingService
{
    public function __construct(private AccountingService $accounting) {}

    public function enabled(): bool
    {
        return (bool) config('production.accounting.enabled', false);
    }

    /**
     * Comptabilise un OF terminé (matière consommée + production stockée).
     * Sans effet si le pont est désactivé. Toute erreur est journalisée
     * sans interrompre le flux de production.
     */
    public function postForOrder(ProductionOrder $order): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $order->loadMissing(['consumptions', 'outputs.stockMovement', 'cost']);

            $materialAmount = (int) $order->consumptions->sum('cost');
            if ($materialAmount > 0) {
                $this->accounting->postProductionConsumption($order, $materialAmount);
            }

            $finishedAmount = $this->finishedGoodsValue($order);
            if ($finishedAmount > 0) {
                $this->accounting->postProductionStockEntry($order, $finishedAmount);
            }
        } catch (\Throwable $e) {
            Log::error('ProductionAccounting: échec comptabilisation OF '.$order->number, [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Valeur des produits finis entrés en stock.
     * Priorité au coût réel des mouvements de stock ; repli sur le coût de
     * revient calculé (ProductionCost) si les sorties n'ont pas de coût unitaire.
     */
    private function finishedGoodsValue(ProductionOrder $order): int
    {
        return (int) ($order->cost?->total_cost ?? $order->outputs
            ->sum(fn ($output) => (int) ($output->stockMovement?->total_cost ?? 0)));
    }
}
