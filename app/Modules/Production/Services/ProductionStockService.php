<?php

namespace App\Modules\Production\Services;
use App\Services\StockService;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Sorties de production (produits finis) et pertes/chutes.
 *
 * Les produits finis entrent dans le stock existant (product_stocks) via
 * StockService — réutilisation du module stock, pas de table parallèle.
 * Les chutes/rebuts sont tracés dans production_wastes (valorisation indicative).
 */
class ProductionStockService
{
    public function __construct(private StockService $stock) {}

    /** Enregistre une sortie de produit fini + entrée en stock. */
    public function recordOutput(ProductionOrder $order, array $data): ProductionOutput
    {
        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'La production n\'est possible que sur un OF « en cours ».']);
        }

        $length   = (float) ($data['length'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité produite doit être positive.']);
        }

        // [Audit OF — métrage] Longueur non saisie → repli sur la longueur de
        // l'en-tête OF (tôle bac / profilés) pour ne pas produire des sorties à
        // 0 mètre qui faussent le total métrage et le coût/mètre.
        if ($length <= 0 && (float) $order->length > 0) {
            $length = (float) $order->length;
        }

        // [Paramétrage OF] « Autoriser dépassement qté » = Non → la production
        // déclarée ne peut pas excéder la quantité demandée (sur-production
        // interdite : consommerait de la matière non budgétée).
        $requested = (float) $order->quantity_requested;
        if ($order->autoriser_depassement_qte !== true && $requested > 0
            && (float) $order->quantity_produced + $quantity > $requested + 0.001) {
            $reste = max(0, $requested - (float) $order->quantity_produced);
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Dépassement de quantité non autorisé sur cet OF : reste à produire %s (demandé %s, déjà produit %s). Activez « Autoriser dépassement qté » ou ajustez la déclaration.',
                    rtrim(rtrim(number_format($reste, 2, ',', ' '), '0'), ','),
                    rtrim(rtrim(number_format($requested, 2, ',', ' '), '0'), ','),
                    rtrim(rtrim(number_format((float) $order->quantity_produced, 2, ',', ' '), '0'), ',')
                ),
            ]);
        }

        return DB::transaction(function () use ($order, $data, $length, $quantity) {
            $totalMeters = round($length * $quantity, 2);
            $productId   = $data['product_id'] ?? $order->product_id;
            $warehouseId = $data['warehouse_id'] ?? $this->defaultWarehouseId($order);
            $unitCost    = (float) ($data['unit_cost'] ?? 0);

            // [Sync ERP] L'output est créé d'abord ; l'entrée stock est ensuite
            // journalisée + idempotente + relançable au grain de la déclaration
            // (un OF reçoit plusieurs déclarations — la clé logique est l'output).
            $output = $order->outputs()->create([
                'company_id'        => $order->company_id,
                'product_id'        => $productId,
                'length'            => $length,
                'color'             => $data['color'] ?? $order->color,
                'thickness'         => $data['thickness'] ?? $order->thickness,
                'quantity'          => $quantity,
                'total_meters'      => $totalMeters,
                'unit_id'           => $data['unit_id'] ?? null,
                'warehouse_id'      => $warehouseId,
                'lot_number'        => $data['lot_number'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'stock_movement_id' => null,
                'produced_at'       => $data['produced_at'] ?? now(),
                // [CDC §13.3] Déclaration opérateur — en attente du visa chef d'équipe.
                'status'            => 'declaree',
                'created_by'        => Auth::id(),
            ]);

            // Entrée en stock du produit fini (si produit + entrepôt connus)
            if ($productId && $warehouseId) {
                app(\App\Services\Sync\SyncOrchestrator::class)->run(
                    sourceModule: 'production',
                    targetModule: 'stock',
                    eventName: 'production.declared',
                    action: 'create_finished_goods_entry',
                    source: $output,
                    callback: fn () => app(\App\Services\Sync\Handlers\ReplayProductionOutputStockSync::class)($output, ['unit_cost' => $unitCost]),
                    payload: ['unit_cost' => $unitCost],
                    handlerClass: \App\Services\Sync\Handlers\ReplayProductionOutputStockSync::class,
                );
            }

            // [CDC §3 — cohérence stock] Consommation automatique des composants de
            // la nomenclature (matière → produit fini). Proportionnelle à la quantité
            // déclarée. Les composants suivis en coils (bobines) restent gérés
            // manuellement via CoilConsumptionService → non consommés ici.
            $this->consumeBomComponents($order, $quantity, $output);

            $order->increment('quantity_produced', $quantity);

            // [Sync ERP] event domaine apres commit — point d'extension decouple
            DB::afterCommit(fn () => event(new \App\Events\ProductionDeclared($output)));

            return $output->refresh();
        });
    }

    /** Annule une sortie : retire le produit fini du stock. */
    public function reverseOutput(ProductionOutput $output): void
    {
        $order = $output->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }

        DB::transaction(function () use ($output) {
            $order = $output->productionOrder;

            if ($output->stock_movement_id && $output->product_id && $output->warehouse_id) {
                $this->stock->recordMovement([
                    'product_id'     => $output->product_id,
                    'warehouse_id'   => $output->warehouse_id,
                    'type'           => 'sortie',
                    'quantity'       => $output->quantity,
                    'reference_type' => ProductionOrder::class,
                    'reference_id'   => $order?->id,
                    'notes'          => 'Annulation sortie production',
                ]);
            }

            // [Cohérence stock] Ré-entrée des composants consommés lors de la
            // déclaration annulée (symétrique de consumeBomComponents).
            if ($order) {
                $this->restoreBomComponents($order, (float) $output->quantity);
                $order->decrement('quantity_produced', $output->quantity);
            }
            $output->delete();
        });
    }

    /** Enregistre une perte / chute. */
    public function recordWaste(ProductionOrder $order, array $data): ProductionWaste
    {
        $weight   = (float) ($data['weight'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 0);
        if ($weight <= 0 && $quantity <= 0) {
            throw ValidationException::withMessages(['weight' => 'Renseignez un poids ou une quantité de chute.']);
        }

        $unitCost = (float) ($data['unit_cost'] ?? $this->averageConsumedCostPerKg($order));
        $value    = (int) round($weight * $unitCost);

        return $order->wastes()->create([
            'company_id'  => $order->company_id,
            'machine_id'  => $data['machine_id'] ?? null,
            'operator_id' => $data['operator_id'] ?? null,
            'type'        => $data['type'] ?? 'non_reutilisable',
            'quantity'    => $quantity,
            'weight'      => $weight,
            'value'       => $value,
            'reason'      => $data['reason'] ?? null,
            'created_by'  => Auth::id(),
        ]);
    }

    /**
     * [PHASE B] Entrée en stock d'un sous-produit (chute ou avarié) issu de la
     * fabrication, lié à la nomenclature (scrap_product_id / defect_product_id).
     * Renseigné au suivi : la chute entre au poids (kg), l'avarié à la quantité.
     */
    public function enterByproduct(ProductionOrder $order, int $productId, float $qty, ?int $warehouseId = null, float $unitCost = 0): ?\App\Models\StockMovement
    {
        if ($qty <= 0) {
            return null;
        }
        $warehouseId = $warehouseId ?? $this->defaultWarehouseId($order);
        if (! $warehouseId) {
            throw ValidationException::withMessages(['warehouse_id' => 'Dépôt d\'entrée requis pour le sous-produit.']);
        }

        // [Sync ERP] entrée sous-produit journalisée (non idempotente : plusieurs
        // entrées de chutes légitimes par OF au fil des déclarations).
        return app(\App\Services\Sync\SyncOrchestrator::class)->run(
            sourceModule: 'production',
            targetModule: 'stock',
            eventName: 'production.byproduct',
            action: 'create_byproduct_entry',
            source: $order,
            callback: fn () => $this->stock->recordMovement([
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'type'           => 'entree',
                'quantity'       => $qty,
                'unit_cost'      => $unitCost,
                'reference_type' => ProductionOrder::class,
                'reference_id'   => $order->id,
                'notes'          => 'Sous-produit fabrication OF ' . $order->number,
            ]),
            payload: ['product_id' => $productId, 'quantity' => $qty],
            idempotent: false,
        );
    }

    public function reverseWaste(ProductionWaste $waste): void
    {
        $order = $waste->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }
        $waste->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * [CDC §3] Consomme les composants de la nomenclature pour la quantité produite.
     * Sortie de stock `sortie` par composant = quantity_per_meter × quantité déclarée.
     *
     * Ne consomme QUE les composants suivis dans product_stocks (identique à
     * ProductionService::materialShortages). Les composants sans ligne product_stocks
     * (bobines gérées dans `coils`) sont ignorés — consommés manuellement via
     * CoilConsumptionService lors de la déclaration de consommation bobine.
     */
    private function consumeBomComponents(ProductionOrder $order, float $quantityProduced, ProductionOutput $output): void
    {
        if ($quantityProduced <= 0) {
            return;
        }

        $order->loadMissing('billOfMaterial.lines.product');
        $bom = $order->billOfMaterial;
        if (! $bom) {
            return; // OF sans nomenclature : rien à consommer
        }

        foreach ($bom->lines as $line) {
            $product = $line->product;
            $per     = (float) $line->quantity_per_meter;
            if (! $product || $per <= 0) {
                continue;
            }

            $need = round($per * $quantityProduced, 4);
            if ($need <= 0) {
                continue;
            }

            $warehouseId = $this->componentWarehouseId($line, (int) $product->id, $order);
            if (! $warehouseId) {
                continue; // composant non suivi en product_stocks (bobine…) — ignoré
            }

            // [Coût de revient] Valoriser la sortie au CMP courant du composant, sinon
            // le mouvement porte total_cost=0 et le coût matière de l'OF est sous-évalué.
            $unitCost = (float) \App\Models\ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)->value('avg_cost');

            $this->stock->recordMovement([
                'company_id'     => $order->company_id,
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouseId,
                'type'           => 'sortie',
                'quantity'       => $need,
                'unit_cost'      => $unitCost,
                'allow_negative' => (bool) $product->allow_negative_stock,
                'reference_type' => ProductionOutput::class,
                'reference_id'   => $output->id,
                'notes'          => 'Consommation composant OF ' . $order->number,
            ]);
        }
    }

    /**
     * Ré-entrée des composants consommés (annulation d'une déclaration).
     * Symétrique de consumeBomComponents : entrée `entree` au coût CMP courant.
     */
    private function restoreBomComponents(ProductionOrder $order, float $quantityProduced): void
    {
        if ($quantityProduced <= 0) {
            return;
        }

        $order->loadMissing('billOfMaterial.lines.product');
        $bom = $order->billOfMaterial;
        if (! $bom) {
            return;
        }

        foreach ($bom->lines as $line) {
            $product = $line->product;
            $per     = (float) $line->quantity_per_meter;
            if (! $product || $per <= 0) {
                continue;
            }

            $need = round($per * $quantityProduced, 4);
            if ($need <= 0) {
                continue;
            }

            $warehouseId = $this->componentWarehouseId($line, (int) $product->id, $order);
            if (! $warehouseId) {
                continue;
            }

            $avgCost = (float) \App\Models\ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)->value('avg_cost');

            $this->stock->recordMovement([
                'company_id'     => $order->company_id,
                'product_id'     => $product->id,
                'warehouse_id'   => $warehouseId,
                'type'           => 'entree',
                'quantity'       => $need,
                'unit_cost'      => $avgCost,
                'reference_type' => ProductionOrder::class,
                'reference_id'   => $order->id,
                'notes'          => 'Ré-entrée composant (annulation déclaration) OF ' . $order->number,
            ]);
        }
    }

    /**
     * Dépôt de sortie du composant : depot_sortie_id (nomenclature) sinon le dépôt
     * product_stocks le mieux approvisionné. Retourne null si le composant n'est
     * suivi dans aucun product_stocks (→ non consommé automatiquement).
     */
    private function componentWarehouseId(\App\Modules\Production\Models\BomLine $line, int $productId, ProductionOrder $order): ?int
    {
        if ($line->depot_sortie_id) {
            return (int) $line->depot_sortie_id;
        }

        return \App\Models\ProductStock::where('product_id', $productId)
            ->orderByDesc('quantity')
            ->value('warehouse_id');
    }

    private function defaultWarehouseId(ProductionOrder $order): ?int
    {
        return Warehouse::where('company_id', $order->company_id)
            ->orderByDesc('is_default')->orderBy('id')->value('id');
    }

    /** Coût moyen au kg réellement consommé sur l'OF (sinon 0). */
    private function averageConsumedCostPerKg(ProductionOrder $order): float
    {
        $totalWeight = (float) $order->consumptions()->sum('weight_consumed');
        if ($totalWeight <= 0) {
            return 0.0;
        }

        return round((float) $order->consumptions()->sum('cost') / $totalWeight, 2);
    }
}
