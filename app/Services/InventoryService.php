<?php

namespace App\Services;

use App\Events\StockAlertTriggered;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\ProductStock;
use App\Repositories\InventorySessionRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        public readonly InventorySessionRepository $repository,
        protected DocumentSequenceService $sequenceService,
        protected AccountingService $accountingService,
    ) {}

    /**
     * Create a new inventory session and auto-populate items from current stock.
     */
    public function create(array $data): InventorySession
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();
            $data['status']     = 'en_cours';
            $data['started_at'] = now();
            $data['type']       = $data['type'] ?? 'complet';

            // Attach company and generate unique inventory number via sequence service
            $company            = currentCompany();
            $data['company_id'] = $company?->id ?? 1;
            $data['number']     = $company
                ? $this->sequenceService->nextNumber($company, 'inventaire')
                : 'INV-' . now()->year . '-' . str_pad(InventorySession::whereYear('created_at', now()->year)->count() + 1, 3, '0', STR_PAD_LEFT);

            /** @var InventorySession $session */
            $session = $this->repository->create($data);

            // Auto-populate items with the current theoretical stock
            // [Cohérence lignes] whereHas('product') : les lignes product_stocks
            // orphelines (produit supprimé/soft-deleted) sont exclues — sinon la
            // session affiche des lignes « — » impossibles à compter.
            $stockQuery = ProductStock::with('product')
                ->where('warehouse_id', $session->warehouse_id)
                ->whereHas('product');

            // For 'tournant', we still load all products — user filters manually
            $stocks = $stockQuery->get();

            foreach ($stocks as $stock) {
                // Skip orphaned stock rows with no product
                if (empty($stock->product_id) || ! $stock->product) {
                    continue;
                }

                $session->items()->create([
                    'product_id'           => $stock->product_id,
                    'warehouse_id'         => $session->warehouse_id,
                    'theoretical_quantity' => (float) $stock->quantity,
                    'counted_quantity'     => null,
                    'variance_quantity'    => 0,
                    'unit_cost'            => (float) ($stock->avg_cost ?? $stock->product?->purchase_price ?? 0),
                    'variance_value'       => 0,
                ]);
            }

            return $session;
        });
    }

    /**
     * Save counted quantities for items in a session.
     */
    public function saveCount(InventorySession $session, array $items): void
    {
        DB::transaction(function () use ($session, $items) {
            foreach ($items as $itemData) {
                /** @var InventoryItem|null $item */
                $item = $session->items()->find($itemData['id']);
                if (!$item) {
                    continue;
                }

                // [FIX inventaire] Un article NON saisi (champ vide) reste « non compté »
                // (counted_quantity = null) — il ne doit PAS être écrasé à 0, sinon la
                // validation ramènerait tout le stock non recompté à zéro. NB : (float) ''
                // vaut 0, donc « ?? 0 » ne protégeait pas la chaîne vide — on la teste.
                $raw = $itemData['counted_quantity'] ?? null;
                if ($raw === null || $raw === '') {
                    continue; // laisse counted_quantity inchangé (null si jamais compté)
                }

                $counted       = (float) $raw;
                $variance      = $counted - (float) $item->theoretical_quantity;
                $varianceValue = round(abs($variance) * (float) $item->unit_cost, 2);

                $item->update([
                    'counted_quantity'  => $counted,
                    'variance_quantity' => $variance,
                    'variance_value'    => $varianceValue,
                    'counted_at'        => now(),
                    'counted_by'        => Auth::id(),
                    'notes'             => $itemData['notes'] ?? null,
                ]);
            }
        });
    }

    /**
     * Validate an inventory session: apply variances to ProductStock.
     */
    public function validate(InventorySession $session): InventorySession
    {
        if ($session->status !== 'en_cours') {
            throw new \RuntimeException("L'inventaire doit être en cours pour être validé.");
        }

        return DB::transaction(function () use ($session) {
            $session->load('items');

            foreach ($session->items as $item) {
                if ($item->counted_quantity === null) {
                    continue;
                }

                // Skip orphaned items with no product (data integrity guard)
                if (empty($item->product_id)) {
                    continue;
                }

                $stock = ProductStock::firstOrCreate(
                    [
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $item->warehouse_id,
                    ],
                    [
                        'quantity'          => 0,
                        'reserved_quantity' => 0,
                        'avg_cost'          => 0,
                    ]
                );

                $counted  = (float) $item->counted_quantity;
                $variance = $counted - (float) $item->theoretical_quantity;

                // Set stock to counted quantity
                $stock->update([
                    'quantity'         => $counted,
                    'last_movement_at' => now(),
                ]);

                // [FIX-STOCK-02] Cap reserved_quantity if physical stock now below it
                if ($counted < (float) $stock->reserved_quantity) {
                    $stock->update(['reserved_quantity' => $counted]);
                }

                // Fire stock alert if counted quantity is below min
                $product  = Product::find($item->product_id);
                $stockMin = (float) ($product?->stock_min ?? 0);
                $available = $counted - (float) $stock->reserved_quantity;
                if ($stockMin > 0 && $available <= $stockMin) {
                    event(new StockAlertTriggered($stock->fresh(), $available, $stockMin));
                }

                // Record an 'inventaire' stock movement for the variance (if any)
                if (abs($variance) > 0.0001) {
                    \App\Models\StockMovement::create([
                        'product_id'       => $item->product_id,
                        'warehouse_id'     => $item->warehouse_id,
                        'type'             => 'inventaire',
                        'quantity'         => $variance,
                        'unit_cost'        => (float) ($stock->avg_cost ?? $item->unit_cost),
                        'total_cost'       => abs($variance) * (float) ($stock->avg_cost ?? $item->unit_cost),
                        'valuation_method' => 'cmp',
                        'avg_cost_after'   => (float) $stock->avg_cost,
                        'occurred_at'      => now(),
                        'reference_type'   => 'inventory_session',
                        'reference_id'     => $session->id,
                        'notes'            => 'Écart d\'inventaire — ' . ($session->number ?? 'INV'),
                        'created_by'       => Auth::id(),
                    ]);
                }
            }

            $session->update([
                'status'       => 'valide',
                'validated_at' => now(),
                'validated_by' => Auth::id(),
            ]);

            // [FIX-STOCK-01] Post GL entries for inventory variances
            $this->accountingService->postInventoryVariances($session->fresh());

            return $session->fresh();
        });
    }

    /**
     * List sessions with filters.
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->repository->search($filters, $perPage);
    }
}
