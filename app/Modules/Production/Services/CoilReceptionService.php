<?php

namespace App\Modules\Production\Services;

use App\Models\Reception;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Modules\Production\Models\Coil;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION ↔ ACHATS] Génère des bobines (matières premières) à partir d'une
 * réception fournisseur validée. Boucle MRP → demande d'achat → commande →
 * réception → bobine en stock.
 *
 * Réutilise le module Achats (Reception/ReceptionItem) — aucun doublon.
 * Idempotent : ne recrée pas de bobines si la réception en a déjà généré.
 *
 * [Sync coils/lots — CDC 17/07/2026] Chaque item de réception crée (ou
 * retrouve) un LOT de stock en kilogrammes, rattache les bobines physiques au
 * lot, et journalise UNE entrée de stock par bobine via le service central
 * (product_stocks incrémenté, idempotence coil-reception:{réception}:{bobine}).
 */
class CoilReceptionService
{
    public function __construct(private StockService $stock) {}

    /**
     * @return array<int, Coil> bobines créées
     */
    /**
     * @param  bool  $onlyTracked  true = ne générer que pour les articles à suivi
     *                             bobine/lot (chemin AUTO à la validation achats) ;
     *                             false = tous les items (bouton manuel du flux
     *                             réception bobines, comportement historique).
     */
    public function createFromReception(Reception $reception, bool $onlyTracked = false): array
    {
        if (! $reception->validated_at) {
            throw ValidationException::withMessages(['status' => 'La réception doit être validée avant de générer les bobines.']);
        }

        if (Coil::where('reception_id', $reception->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Les bobines de cette réception ont déjà été générées.']);
        }

        $reception->loadMissing('items.product');

        return DB::transaction(function () use ($reception, $onlyTracked) {
            $created = [];
            $i = 0;

            foreach ($reception->items as $item) {
                $weight = (float) $item->received_quantity;
                if ($weight <= 0) {
                    continue;
                }

                // [FILTRE bobine] Ne générer que pour les articles à suivi
                // bobine/lot (catégorie coil_managed ou article géré en lot) —
                // un consommable standard reçu sur la même réception n'a pas
                // de bobine.
                $p = $item->product;
                $tracked = $p && $p->isCoilManaged();
                if ($onlyTracked && ! $tracked) {
                    continue;
                }
                $i++;

                $costPerKg = (float) $item->unit_cost;
                $warehouseId = $reception->warehouse_id ?? $item->warehouse_id ?? null;
                if ($tracked && $costPerKg <= 0) {
                    throw ValidationException::withMessages([
                        'cost' => "Réception {$reception->number} : l’article bobine « {$p->name} » n’a aucun coût unitaire. Valorisez la commande/réception avant de générer la bobine.",
                    ]);
                }

                // ── Lot de réception (traçabilité, quantité restante en KG) ──
                $lot = null;
                if ($item->product_id && $warehouseId) {
                    $lot = StockLot::firstOrCreate(
                        [
                            'product_id' => $item->product_id,
                            'warehouse_id' => $warehouseId,
                            'lot_number' => $item->lot_number ?: ('LOT-'.$reception->number.'-'.$i),
                        ],
                        [
                            'quantity' => 0,
                            'initial_quantity' => 0,
                            'reserved_quantity' => 0,
                            'stock_uom' => 'KG',
                            'unit_cost' => round($costPerKg, 2),
                            'received_at' => $reception->received_at ?? now(),
                            'status' => 'disponible',
                            'source_type' => Reception::class,
                            'source_id' => $reception->id,
                            'created_by' => Auth::id(),
                        ]
                    );
                    // initial_quantity cumule les poids reçus sur ce lot.
                    $lot->increment('initial_quantity', $weight);
                }

                // [Qualité #11] Statut qualité INITIAL de la bobine, dérivé de la
                // disposition de la ligne (jamais deviné) :
                //  - quarantaine > 0            → QUARANTINED (non consommable) ;
                //  - accepté = reçu (certifié)  → RELEASED ;
                //  - accepté partiel            → PARTIAL_RELEASE ;
                //  - disposition inconnue (NULL)→ NULL (inconnu, signalé par l'audit).
                $qAccepted   = $item->accepted_quantity;
                $qQuarantine = $item->quarantine_quantity;
                $qualityStatus = null;
                if ($qAccepted !== null || $qQuarantine !== null) {
                    $qualityStatus = ((float) $qQuarantine) > 0
                        ? Coil::QUALITY_QUARANTINED
                        : (((float) $qAccepted) >= $weight - 0.0001
                            ? Coil::QUALITY_RELEASED
                            : Coil::QUALITY_PARTIAL_RELEASE);
                }
                // [Qualité #1] Soldes QUANTITATIFS par disposition (NULL si la
                // disposition est inconnue — aucun solde inventé).
                $balAccepted   = $qualityStatus === null ? null : (float) $qAccepted;
                $balQuarantine = $qualityStatus === null ? null : (float) $qQuarantine;
                $balRejected   = $qualityStatus === null ? null : (float) $item->rejected_quantity;

                if ($lot) {
                    $lot->update([
                        'quality_status' => $qualityStatus,
                        'qty_released'   => $balAccepted,
                        'qty_quarantine' => $balQuarantine,
                        'qty_rejected'   => $balRejected,
                    ]);
                }

                $coil = Coil::create([
                    'quality_status' => $qualityStatus,
                    'qty_released'   => $balAccepted,
                    'qty_quarantine' => $balQuarantine,
                    'qty_rejected'   => $balRejected,
                    'company_id' => $reception->company_id,
                    'product_id' => $item->product_id,
                    'supplier_id' => $reception->supplier_id,
                    'reception_id' => $reception->id,
                    'warehouse_id' => $warehouseId,
                    'stock_lot_id' => $lot?->id,
                    'reference' => 'BOB-'.$reception->number.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'lot_number' => $item->lot_number,
                    'kg_per_linear_meter' => $item->product?->kg_per_linear_meter,
                    'initial_weight' => $weight,
                    'remaining_weight' => $weight,
                    'estimated_length' => 0,
                    'purchase_price' => (int) round($weight * $costPerKg),
                    'cost_per_kg' => round($costPerKg, 2),
                    'received_at' => $reception->received_at ?? now(),
                    'status' => 'disponible',
                    'created_by' => Auth::id(),
                ]);

                // ── Entrée de stock économique UNIQUE (KG) : mouvement + lot +
                //    product_stocks, via le service central. ──
                // [ANTI-DOUBLE] La validation d'une réception ACHATS crée déjà son
                // entrée de stock : dans ce cas, la génération de bobines est de la
                // traçabilité pure — on crédite le lot sans second mouvement
                // (constaté en recette : stock 20 au lieu de 10).
                $alreadyStocked = $item->product_id && $warehouseId
                    && StockMovement::where('product_id', $item->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->where('type', 'entree')
                        ->where(fn ($q) => $q
                            ->where(fn ($w) => $w->where('reference_type', 'reception')->where('reference_id', $reception->id))
                            ->orWhere(fn ($w) => $w->where('reference_type', Reception::class)->where('reference_id', $reception->id)))
                        ->exists();

                if ($alreadyStocked) {
                    $lot?->increment('quantity', $weight);
                } elseif ($item->product_id && $warehouseId) {
                    $movement = $this->stock->recordMovement([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'entree',
                        'quantity' => $weight,
                        'uom' => 'KG',
                        'conversion_factor' => 1,
                        'quantity_in_stock_uom' => $weight,
                        'stock_uom' => 'KG',
                        'unit_cost' => round($costPerKg, 2),
                        'stock_lot_id' => $lot?->id,
                        'coil_id' => $coil->id,
                        'reference_type' => Reception::class,
                        'reference_id' => $reception->id,
                        'notes' => "Réception bobine {$coil->reference} — {$reception->number}",
                        'idempotency_key' => 'coil-reception:'.$reception->id.':'.$coil->id,
                    ]);
                }

                $created[] = $coil;
            }

            return $created;
        });
    }
}
