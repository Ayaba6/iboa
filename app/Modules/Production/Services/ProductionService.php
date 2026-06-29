<?php

namespace App\Modules\Production\Services;
use App\Services\DocumentSequenceService;

use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Cycle de vie d'un ordre de fabrication (OF).
 *
 * Workflow : brouillon → lance → en_cours → termine
 *            (annulation possible depuis tout statut non clôturé)
 *
 * La consommation matière, les sorties produits finis et le coût de revient
 * sont gérés en P4/P5 (CoilConsumptionService, ProductionCostService).
 */
class ProductionService
{
    public function __construct(
        private DocumentSequenceService $sequences,
        private ProductionAccountingService $accounting,
    ) {}

    /** Crée un OF en brouillon avec numéro auto + lignes de détail. */
    public function create(array $data, array $lines = []): ProductionOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $company = currentCompany();

            $data['company_id']     = $company->id;
            $data['fiscal_year_id'] = $company->current_fiscal_year_id;
            $data['number']         = $this->sequences->nextNumber($company, 'ordre_fabrication');
            $data['status']         = 'brouillon';

            $order = ProductionOrder::create($data);
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            return $order->fresh('lines');
        });
    }

    /** Met à jour un OF éditable (brouillon/lancé) + ses lignes. */
    public function update(ProductionOrder $order, array $data, array $lines = []): ProductionOrder
    {
        if (! $order->isEditable()) {
            throw ValidationException::withMessages(['status' => 'OF non modifiable dans son statut actuel.']);
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            $order->update($data);
            $order->lines()->delete();
            $this->syncLines($order, $lines);
            $this->recomputeQuantities($order);

            return $order->fresh('lines');
        });
    }

    /** brouillon → matière allouée (allocation matière effectuée). */
    public function allocateMaterial(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'brouillon');
        $order->update(['status' => 'matiere_allouee']);
    }

    /**
     * [§13.2 CDC] Vérifie la condition financière avant lancement.
     * Comptant : 100% encaissé. Acompte : ≥70%. Crédit : validation DAF/DG explicite.
     * Sans commande liée ou si déjà autorisé → passe.
     *
     * @throws ValidationException si condition non remplie
     */
    public function checkFinancialGate(ProductionOrder $order): void
    {
        // Déjà autorisé ou bypassé
        if (in_array($order->financial_authorization, ['approved', 'bypassed'], true)) {
            return;
        }

        // Pas de commande de vente liée → pas de gate financière
        if (! $order->order_id) {
            return;
        }

        $order->loadMissing('order.client');
        $salesOrder = $order->order;
        if (! $salesOrder) {
            return;
        }

        $client   = $salesOrder->client;
        $totalTtc = (float) $salesOrder->total_ttc;
        if ($totalTtc <= 0) {
            return;
        }

        // Encaissements déjà reçus pour cette commande :
        // orders → invoices(order_id) → client_payment_allocations(invoice_id)
        $invoiceIds = \App\Models\Invoice::where('order_id', $salesOrder->id)->pluck('id');
        $paidViaInvoice = $invoiceIds->isNotEmpty()
            ? (float) \App\Models\ClientPaymentAllocation::whereIn('invoice_id', $invoiceIds)
                ->whereHas('clientPayment', fn ($q) => $q->where('status', 'confirme'))
                ->sum('amount')
            : 0.0;

        // Acomptes non alloués reçus directement du client (is_acompte=true)
        $acompteLibre = (float) \App\Models\ClientPayment::where('client_id', $salesOrder->client_id)
            ->where('status', 'confirme')
            ->where('is_acompte', true)
            ->sum('unallocated_amount');

        $paid = $paidViaInvoice + $acompteLibre;
        $rate = $totalTtc > 0 ? round(($paid / $totalTtc) * 100, 1) : 0;

        // Déterminer le mode de paiement du client
        $paymentMode = $client?->payment_mode ?? 'credit'; // défaut = crédit si non défini

        $order->update(['payment_mode' => $paymentMode, 'payment_rate' => $rate]);

        if ($paymentMode === 'comptant' && $rate < 100) {
            throw ValidationException::withMessages([
                'financial' => "Client comptant : paiement intégral requis avant fabrication ({$rate}% encaissé sur {$totalTtc} FCFA). Demandez l'autorisation financière.",
            ]);
        }

        if ($paymentMode === 'acompte' && $rate < 70) {
            throw ValidationException::withMessages([
                'financial' => "Client acompte : acompte ≥ 70% requis ({$rate}% encaissé). Demandez l'autorisation DAF.",
            ]);
        }

        if ($paymentMode === 'credit') {
            throw ValidationException::withMessages([
                'financial' => 'Client crédit : validation DAF/DG obligatoire avant lancement OF. Demandez l\'autorisation financière.',
            ]);
        }

        // Condition remplie — on enregistre l'autorisation automatique
        $order->update([
            'financial_authorization'  => 'approved',
            'financial_authorized_at'  => now(),
            'financial_notes'          => "Autorisé automatiquement : {$paymentMode} / {$rate}%",
        ]);
    }

    /** brouillon | matière allouée → lance */
    public function launch(ProductionOrder $order): void
    {
        $this->assertStatus($order, ['brouillon', 'matiere_allouee']);

        // §13.2 CDC — Gate financière obligatoire
        $this->checkFinancialGate($order);

        $order->update(['status' => 'lance', 'launched_at' => now()]);

        // [§3] Chargement automatique des opérations depuis la gamme opératoire
        // (si une gamme existe et que les opérations n'ont pas déjà été générées).
        // Non bloquant : absence de gamme ou opérations déjà présentes = no-op.
        try {
            $order->loadMissing('billOfMaterial.routing');
            if (! $order->operations()->exists() && $order->billOfMaterial?->routing) {
                app(RoutingService::class)->generateWorkOrders($order);
            }
        } catch (\Throwable $e) {
            // gamme absente / déjà générée — on ne bloque pas le lancement
        }
    }

    /**
     * [§3 — option C] Pénuries de matière (avertissement NON bloquant).
     * Compare le besoin (BOM × quantité) au stock disponible (product_stocks).
     * Ignore les composants non suivis en product_stocks (ex. bobines, gérées
     * dans `coils`) pour éviter les faux positifs. Respecte allow_negative_stock.
     *
     * @return array<int,array{product:string,need:float,available:float}>
     */
    public function materialShortages(ProductionOrder $order): array
    {
        $order->loadMissing('billOfMaterial.lines.product');
        $bom = $order->billOfMaterial;
        $qty = (float) ($order->quantity_requested ?: 0);
        if (! $bom || $qty <= 0) {
            return [];
        }

        $shortages = [];
        foreach ($bom->lines as $line) {
            $product = $line->product;
            if (! $product || $product->allow_negative_stock) {
                continue;
            }
            $rows = \App\Models\ProductStock::where('product_id', $product->id)->get(['quantity', 'reserved_quantity']);
            if ($rows->isEmpty()) {
                continue; // composant non suivi en product_stocks (bobines…) — pas d'alerte
            }
            $available = (float) $rows->sum(fn ($s) => (float) $s->quantity - (float) $s->reserved_quantity);
            $need = (float) $line->quantity_per_meter * $qty;
            if ($need > $available) {
                $shortages[] = ['product' => $product->name, 'need' => round($need, 2), 'available' => round($available, 2)];
            }
        }

        return $shortages;
    }

    /** lance → en_cours */
    public function start(ProductionOrder $order): void
    {
        $this->assertStatus($order, 'lance');
        $order->update(['status' => 'en_cours']);
    }

    /** en_cours → terminé partiellement (production partielle, reste à produire). */
    public function markPartiallyDone(ProductionOrder $order): void
    {
        $this->assertStatus($order, ['en_cours', 'termine_partiellement']);
        $order->update(['status' => 'termine_partiellement']);
    }

    /** en_cours | terminé partiellement → termine */
    public function finish(ProductionOrder $order): void
    {
        $this->assertStatus($order, ['en_cours', 'termine_partiellement']);
        $order->update(['status' => 'termine', 'finished_at' => now()]);

        // Pont comptable SYSCOHADA (no-op si désactivé — OFF par défaut)
        $this->accounting->postForOrder($order);
    }

    /** Annulation depuis tout statut non clôturé. */
    public function cancel(ProductionOrder $order, ?string $reason = null): void
    {
        if (in_array($order->status, ['termine', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'OF déjà clôturé — annulation impossible.']);
        }

        $note = $order->notes;
        if ($reason) {
            $note = trim(($note ? $note . "\n" : '') . 'Annulé : ' . $reason);
        }
        $order->update(['status' => 'annule', 'notes' => $note]);

        // [V4] Libère les réservations de produit fini liées à cet OF.
        app(ReservationService::class)->releaseForProductionOrder($order);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function assertStatus(ProductionOrder $order, array|string $expected): void
    {
        $allowed = (array) $expected;
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition invalide : l'OF doit être au statut « " . implode(' / ', $allowed) . " ».",
            ]);
        }
    }

    /** Recrée les lignes de détail (longueur × quantité → mètres). */
    private function syncLines(ProductionOrder $order, array $lines): void
    {
        foreach ($lines as $i => $row) {
            $length   = (float) ($row['length'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            if ($length <= 0 && $quantity <= 0) {
                continue;
            }
            $order->lines()->create([
                'length'       => $length,
                'quantity'     => $quantity,
                'total_meters' => round($length * $quantity, 2),
                'unit_id'      => $row['unit_id'] ?? null,
                'label'        => $row['label'] ?? null,
                'sort_order'   => $i,
            ]);
        }
    }

    /** Si des lignes existent, la quantité demandée = somme des quantités. */
    private function recomputeQuantities(ProductionOrder $order): void
    {
        $order->load('lines');
        if ($order->lines->isNotEmpty()) {
            $order->update(['quantity_requested' => $order->lines->sum('quantity')]);
        }
    }
}
