<?php

namespace App\Modules\Production\Services;
use App\Services\StockService;

use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Models\Warehouse;
use App\Models\Product;
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
            $requestedWarehouseId = $data['warehouse_id'] ?? $this->defaultWarehouseId($order, $productId);
            $requestedWarehouse = $requestedWarehouseId ? Warehouse::find($requestedWarehouseId) : null;
            $requiresQualityRelease = (bool) $order->controle_qualite_obligatoire
                && (bool) ($requestedWarehouse?->quality_warehouse_id || $requestedWarehouse?->requires_quality_control);
            $warehouseId = $requiresQualityRelease
                ? $requestedWarehouse?->quality_warehouse_id
                : $requestedWarehouseId;
            if ($requiresQualityRelease && ! $warehouseId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Aucun dépôt de quarantaine configuré pour isoler le produit fini avant libération qualité.',
                ]);
            }
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
                'release_warehouse_id' => $requiresQualityRelease ? $requestedWarehouseId : null,
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

            $batch = $order->batches()->first();
            if (! $batch) {
                app(BatchService::class)->createForOrder($order->fresh());
            } else {
                $batch->update(['quantity' => $order->fresh()->quantity_produced]);
            }

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
                $this->restoreBomComponents($order, $output);
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
    /**
     * [PRO-08] PMP de la matiere reellement consommee par cet OF.
     *
     * On retient la matiere la PLUS consommee en poids : un OF melangeant deux
     * bobines valorise sa chute au cout de celle qui domine, plutot qu'a une
     * moyenne qui ne correspondrait a aucune matiere reelle.
     *
     * Repli sur le cout au kilo de la bobine quand l'article n'a pas encore de
     * PMP (premiere reception non encore valorisee). Retourne 0 si rien n'est
     * connu : mieux vaut une chute a zero, visible, qu'un cout invente.
     */
    private function materialAverageCost(ProductionOrder $order): float
    {
        $order->loadMissing('consumptions.coil');

        $dominante = $order->consumptions
            ->whereNull('reversed_at')
            ->sortByDesc(fn ($c) => (float) $c->weight_consumed)
            ->first();

        if (! $dominante || ! $dominante->coil) {
            return 0.0;
        }

        $pmp = (float) (\App\Models\Product::whereKey($dominante->coil->product_id)
            ->value('weighted_avg_cost') ?? 0);

        return $pmp > 0 ? $pmp : (float) ($dominante->coil->cost_per_kg ?? 0);
    }

    public function enterByproduct(ProductionOrder $order, int $productId, float $qty, ?int $warehouseId = null, float $unitCost = 0): ?\App\Models\StockMovement
    {
        if ($qty <= 0) {
            return null;
        }
        $warehouseId = $warehouseId ?? $this->defaultWarehouseId($order);
        if (! $warehouseId) {
            throw ValidationException::withMessages(['warehouse_id' => 'Dépôt d\'entrée requis pour le sous-produit.']);
        }

        // [PRO-08] Valorisation au PMP de la MATIÈRE CONSOMMÉE, convention déjà
        // écrite dans CuttingRemnantService : « PMP courant de la matière, pas de
        // profit fictif SYSCOHADA ».
        //
        // Sans cela le sous-produit entrait en stock à ZÉRO — aucun appelant ne
        // passait de coût, le paramètre valant 0 par défaut. La chute récupérée
        // n'avait donc aucune valeur en stock, et le produit fini supportait un
        // coût matière dont une part était revenue en magasin.
        if ($unitCost <= 0) {
            $unitCost = $this->materialAverageCost($order);
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

        $order->load('billOfMaterial.lines.product.itemCategory', 'billOfMaterial.lines.product.family', 'consumptions.coil');
        $bom = $order->billOfMaterial;
        $bomLines = $order->bom_snapshot
            ? collect($order->bom_snapshot['lines'] ?? [])
            : ($bom?->lines ?? collect());
        if ($bomLines->isEmpty()) {
            return; // OF sans nomenclature ou sans composant : rien à consommer
        }
        $snapshotProducts = Product::with(['itemCategory', 'family'])
            ->whereIn('id', $bomLines->map(fn ($line) => data_get($line, 'product_id'))->filter())
            ->get()
            ->keyBy('id');

        // [Sync coils/lots — backflush au RELIQUAT] Le backflush ne consomme que
        // la part du besoin théorique non couverte par les consommations réelles
        // (bobines) déjà journalisées sur l'OF. Cumulatif :
        //   reliquat = besoin(production totale déclarée)
        //            − consommations réelles non annulées
        //            − backflush déjà effectué sur cet OF.
        // Jamais négatif — et le cas historique « tout couvert par les bobines »
        // (anti double comptage MTO #2) reste un reliquat nul → aucun mouvement.
        $totalProducedAfter = (float) $order->quantity_produced + $quantityProduced;

        foreach ($bomLines as $line) {
            $product = $snapshotProducts->get((int) data_get($line, 'product_id'));
            $per     = (float) data_get($line, 'quantity_per_meter');
            if (! $product || $per <= 0) {
                continue;
            }

            $needCumul = round($per * $totalProducedAfter, 4);

            // Consommations réelles (bobines) non annulées de ce composant.
            $consumedReal = (float) $order->consumptions
                ->filter(fn ($c) => $c->coil?->product_id === $product->id && ! $c->reversed_at)
                ->sum('weight_consumed');

            // Once a coil-managed component has a real physical consumption,
            // the bobine remains the source of truth and no residual backflush
            // may decrement only product_stocks.
            if ($consumedReal > 0 && $product->isCoilManaged()) {
                continue;
            }

            // Backflush déjà journalisé pour ce composant sur cet OF
            // (sorties sans production_consumption_id = mouvements backflush).
            $alreadyFlushed = (float) \App\Models\StockMovement::where('product_id', $product->id)
                ->where('type', 'sortie')
                ->whereNull('production_consumption_id')
                ->where(function ($q) use ($order) {
                    $q->where('production_order_id', $order->id)
                      ->orWhere(function ($qq) use ($order) {
                          $qq->where('reference_type', ProductionOutput::class)
                             ->whereIn('reference_id', $order->outputs()->pluck('id'));
                      });
                })
                ->get()
                ->sum(fn ($m) => (float) ($m->quantity_in_stock_uom ?? $m->quantity));

            $need = round(max(0, $needCumul - $consumedReal - $alreadyFlushed), 4);
            if ($need <= 0.0001) {
                continue; // besoin couvert par les consommations réelles
            }

            $warehouseId = $this->componentWarehouseId($line, (int) $product->id, $order);
            if (! $warehouseId) {
                continue; // composant non suivi en product_stocks (bobine…) — ignoré
            }

            // [FIX A4] Libérer la réservation matière de CET OF pour ce composant
            // AVANT la sortie : sinon sa propre réservation réduit le disponible
            // (qté − réservé) et bloque son propre backflush quand le stock est juste.
            app(ReservationService::class)->releaseMaterialReservations($order, (int) $product->id);

            // [Coût de revient] Valoriser la sortie au CMP courant du composant, sinon
            // le mouvement porte total_cost=0 et le coût matière de l'OF est sous-évalué.
            $unitCost = (float) \App\Models\ProductStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)->value('avg_cost');

            // [FIX rapport MTO #2 — message actionnable] Le message générique du
            // module stock (« Stock insuffisant : X dispo, Y demandée ») ne nomme
            // ni le composant ni le dépôt : l'opérateur croit à un bug d'unités.
            // On contextualise : composant, dépôt, et quoi faire.
            try {
                $this->stock->recordMovement([
                    'company_id'          => $order->company_id,
                    'product_id'          => $product->id,
                    'warehouse_id'        => $warehouseId,
                    'type'                => 'sortie',
                    'quantity'            => $need,
                    'uom'                 => 'KG',
                    'conversion_factor'   => 1,
                    'quantity_in_stock_uom' => $need,
                    'stock_uom'           => 'KG',
                    'unit_cost'           => $unitCost,
                    'allow_negative'      => (bool) $product->allow_negative_stock,
                    'production_order_id' => $order->id,
                    'reference_type'      => ProductionOutput::class,
                    'reference_id'        => $output->id,
                    'notes'               => 'Consommation composant OF ' . $order->number . ' (backflush reliquat)',
                    'idempotency_key'     => 'backflush:' . $order->id . ':' . $product->id . ':' . $output->id,
                ]);
            } catch (ValidationException $e) {
                $warehouseName = Warehouse::where('id', $warehouseId)->value('name') ?? ('dépôt #' . $warehouseId);
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Déclaration refusée — consommation automatique du composant « %s » impossible : %s (dépôt de sortie nomenclature : %s). Réapprovisionnez ce dépôt, corrigez le dépôt de sortie de la nomenclature, ou déclarez la consommation bobine avant la déclaration de production.',
                        $product->name,
                        collect($e->errors())->flatten()->first() ?? 'stock insuffisant',
                        $warehouseName
                    ),
                ]);
            }
        }
    }

    /**
     * Ré-entrée des composants consommés (annulation d'une déclaration).
     *
     * [FIX rapport MTO #2] Contre-passe les mouvements de sortie RÉELLEMENT
     * générés par cette déclaration (référence ProductionOutput) plutôt que de
     * recalculer depuis la nomenclature : si le backflush d'un composant a été
     * sauté (matière consommée via bobine), le recalcul créerait du stock fantôme.
     */
    private function restoreBomComponents(ProductionOrder $order, ProductionOutput $output): void
    {
        $moves = \App\Models\StockMovement::where('reference_type', ProductionOutput::class)
            ->where('reference_id', $output->id)
            ->where('type', 'sortie')
            ->get();

        foreach ($moves as $move) {
            $this->stock->recordMovement([
                'company_id'     => $order->company_id,
                'product_id'     => $move->product_id,
                'warehouse_id'   => $move->warehouse_id,
                'type'           => 'entree',
                'quantity'       => (float) $move->quantity,
                'unit_cost'      => (float) $move->unit_cost,
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
    private function componentWarehouseId(mixed $line, int $productId, ProductionOrder $order): ?int
    {
        if ($warehouseId = data_get($line, 'depot_sortie_id')) {
            return (int) $warehouseId;
        }

        return \App\Models\ProductStock::where('product_id', $productId)
            ->orderByDesc('quantity')
            ->value('warehouse_id');
    }

    /**
     * Dépôt d'entrée par défaut d'une déclaration de production.
     *
     * [FIX BUG-2 dépôt PF] Priorité : dépôt de l'article produit (main_warehouse_id —
     * le PF va dans « Produits Finis », l'avarié/la chute dans leur propre dépôt),
     * puis le dépôt produit fini paramétré sur l'OF, enfin le dépôt société par défaut.
     * Évite l'entrée systématique en « Dépôt Central » qui créait un écart avec le
     * dépôt attendu par la commande/le BL (ruptures artificielles).
     */
    private function defaultWarehouseId(ProductionOrder $order, ?int $productId = null): ?int
    {
        $productWarehouse = $productId
            ? Product::where('id', $productId)->value('main_warehouse_id')
            : null;

        return $productWarehouse
            ?? $order->depot_produit_fini_id
            ?? Warehouse::where('company_id', $order->company_id)
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
