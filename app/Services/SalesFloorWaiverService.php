<?php

namespace App\Services;

use App\Models\SalesFloorWaiver;
use App\Services\Sales\CommercialLinePriceRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesFloorWaiverService
{
    public function createDraft(Model $document, Model $line, string $reason, ?string $justificationPath = null): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.request');
        if (mb_strlen(trim($reason)) < 10) {
            throw new \RuntimeException('Un motif détaillé est obligatoire.');
        }
        $pricing = $this->pricing($document, $line);
        if ($pricing['net_price'] >= $pricing['minimum_price']) {
            throw new \RuntimeException('Cette ligne n’est pas sous le prix minimum.');
        }

        return DB::transaction(function () use ($document, $line, $reason, $justificationPath, $pricing) {
            SalesFloorWaiver::where('line_type', $line::class)->where('line_id', $line->id)
                ->whereIn('status', ['brouillon', 'soumise', 'approuvee'])->update(['status' => 'revoquee']);
            $waiver = SalesFloorWaiver::create([
                'company_id' => $document->company_id,
                'document_type' => $document::class, 'document_id' => $document->id,
                'line_type' => $line::class, 'line_id' => $line->id,
                'product_id' => $line->product_id, 'unit_id' => $line->unit_id,
                'quantity' => $line->quantity, 'proposed_price' => $pricing['net_price'],
                'minimum_price' => $pricing['minimum_price'], 'cost_basis' => $pricing['cost_basis'],
                'cost_source' => $pricing['cost_source'], 'conversion_factor' => $pricing['conversion_factor'],
                'margin_rate' => $pricing['margin_rate'], 'line_discount' => $pricing['line_discount'],
                'global_discount_ratio' => $pricing['global_discount_ratio'],
                'expected_margin' => ($pricing['net_price'] - $pricing['cost_basis']) * (float) $line->quantity,
                'gap' => $pricing['minimum_price'] - $pricing['net_price'],
                'reason' => trim($reason), 'justification_path' => $justificationPath,
                'pricing_signature' => $pricing['signature'], 'status' => 'brouillon',
                'requested_by' => Auth::id(),
            ]);
            app(AuditService::class)->log('sales.below_floor.drafted', $waiver, [], ['gap' => $waiver->gap]);

            return $waiver;
        });
    }

    public function submit(SalesFloorWaiver $waiver): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.request');
        if ($waiver->status !== 'brouillon' || (int) $waiver->requested_by !== (int) Auth::id()) {
            throw new \RuntimeException('Seul le demandeur peut soumettre une dérogation en brouillon.');
        }
        $waiver->update(['status' => 'soumise', 'submitted_at' => now()]);
        app(AuditService::class)->log('sales.below_floor.submitted', $waiver);

        return $waiver->fresh();
    }

    public function request(Model $document, Model $line, string $reason, ?string $justificationPath = null): SalesFloorWaiver
    {
        return $this->submit($this->createDraft($document, $line, $reason, $justificationPath));
    }

    public function approve(SalesFloorWaiver $waiver, ?string $reason = null, int $validDays = 30): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.approve');

        return DB::transaction(function () use ($waiver, $reason, $validDays) {
            $waiver = SalesFloorWaiver::lockForUpdate()->findOrFail($waiver->id);
            if ($waiver->status !== 'soumise') {
                throw new \RuntimeException('Seule une demande soumise peut être approuvée.');
            }
            if ((int) $waiver->requested_by === (int) Auth::id()) {
                throw new \RuntimeException('Le demandeur ne peut pas approuver sa propre dérogation.');
            }
            $waiver->update([
                'status' => 'approuvee', 'decided_by' => Auth::id(), 'decided_at' => now(),
                'expires_at' => now()->addDays(max(1, $validDays)), 'decision_reason' => $reason,
            ]);
            app(AuditService::class)->log('sales.below_floor.approved', $waiver, [], ['gap' => $waiver->gap]);

            return $waiver->fresh();
        });
    }

    public function reject(SalesFloorWaiver $waiver, string $reason): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.reject');
        if ($waiver->status !== 'soumise' || mb_strlen(trim($reason)) < 5) {
            throw new \RuntimeException('Seule une demande soumise peut être refusée avec un motif.');
        }
        $waiver->update(['status' => 'refusee', 'decided_by' => Auth::id(), 'decided_at' => now(), 'decision_reason' => trim($reason)]);
        app(AuditService::class)->log('sales.below_floor.rejected', $waiver, [], ['reason' => trim($reason)]);

        return $waiver->fresh();
    }

    public function revoke(SalesFloorWaiver $waiver, string $reason): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.cancel');
        if (! in_array($waiver->status, ['soumise', 'approuvee'], true)) {
            throw new \RuntimeException('Cette dérogation ne peut plus être révoquée.');
        }
        $waiver->update(['status' => 'revoquee', 'decided_by' => Auth::id(), 'decided_at' => now(), 'decision_reason' => trim($reason)]);
        app(AuditService::class)->log('sales.below_floor.revoked', $waiver, [], ['reason' => trim($reason)]);

        return $waiver->fresh();
    }

    public function expireApproved(): int
    {
        return SalesFloorWaiver::where('status', 'approuvee')->where('expires_at', '<=', now())
            ->update(['status' => 'expiree']);
    }

    /**
     * [BUG-A3-SALES-ZERO-PRICE-026] GARDE 1 — aucune ligne à titre gratuit.
     *
     * Séparée de `assertDocumentMayProceed()` parce que tous les documents ne
     * méritent pas les deux gardes. Le prix plancher protège la MARGE : il n'a
     * de sens que là où l'on vend. La gratuité, elle, doit être refusée partout
     * où un document engage l'entreprise — y compris sur une facture directe,
     * qui n'a jamais transité par un devis et n'a donc jamais été contrôlée.
     *
     * Le refus est inconditionnel : `sales_below_floor.approve` autorise à
     * remiser, pas à offrir. Tant que [FEATURE-A3-SALES-FREE-LINE-027] n'existe
     * pas, la gratuité n'a aucun chemin d'approbation.
     */
    public function assertNoFreeLine(Model $document): void
    {
        $devise = $document->currency_code ?? null;
        $sousTotal = (float) ($document->subtotal_ht ?? 0);
        $ratioGlobal = $sousTotal > 0
            ? (float) ($document->global_discount_amount ?? 0) / $sousTotal
            : 0.0;

        foreach ($document->items()->with('product')->get() as $ligne) {
            $net = CommercialLinePriceRule::montantNetLigne(
                (float) $ligne->unit_price,
                (float) $ligne->quantity,
                (float) ($ligne->discount_percent ?? 0),
                $ratioGlobal,
                $devise,
            );

            if (CommercialLinePriceRule::estGratuit($net, $devise)) {
                throw new \RuntimeException(
                    CommercialLinePriceRule::messageGratuite($ligne->product?->name)
                );
            }
        }
    }

    public function assertDocumentMayProceed(Model $document): void
    {
        if (in_array($document->status, ['annule', 'annulee'], true)) {
            throw new \RuntimeException('Un document annulé ne peut utiliser aucune dérogation.');
        }
        $this->expireApproved();

        // GARDE 1 — contrôlée AVANT le plancher, et indépendamment de lui : le
        // plancher se déduit du coût, si bien qu'un article sans coût connu le
        // ramène à zéro, et zéro n'est pas inférieur à zéro.
        $this->assertNoFreeLine($document);

        foreach ($document->items()->with('product')->get() as $line) {
            $pricing = $this->pricing($document, $line);

            // GARDE 2 — sous le plancher, dérogeable.
            //
            // [BUG-A3-SALES-MONEY-PRECISION-031] Le `0.005` ci-dessous est une
            // tolérance en dur : elle suppose deux décimales, alors que le franc
            // CFA n'en a aucune. Laissée en l'état À DESSEIN — la corriger
            // change `pricing_signature`, donc invalide les dérogations déjà
            // approuvées, ce qui appelle une décision métier. Voir
            // docs/BUG-A3-SALES-MONEY-PRECISION-031.md.
            if ($pricing['minimum_price'] <= $pricing['net_price'] + 0.005) {
                continue;
            }
            $approved = SalesFloorWaiver::where('document_type', $document::class)->where('document_id', $document->id)
                ->where('line_type', $line::class)->where('line_id', $line->id)
                ->where('status', 'approuvee')->where('pricing_signature', $pricing['signature'])
                ->where('expires_at', '>', now())->exists();
            if (! $approved) {
                throw new \RuntimeException(sprintf(
                    'Prix HT net %.2f sous le minimum %.2f pour « %s » (coût %.2f, source %s, marge %.2f). dérogation approuvée requise.',
                    $pricing['net_price'], $pricing['minimum_price'], $line->product->name,
                    $pricing['cost_basis'], $pricing['cost_source'],
                    ($pricing['net_price'] - $pricing['cost_basis']) * (float) $line->quantity
                ));
            }
        }
    }

    private function pricing(Model $document, Model $line): array
    {
        $explanation = app(SalesPriceGuardService::class)->explain($line->product);
        $lineDiscount = (float) ($line->discount_percent ?? 0);
        $globalRatio = (float) ($document->subtotal_ht ?? 0) > 0
            ? (float) ($document->global_discount_amount ?? 0) / (float) $document->subtotal_ht : 0;
        $net = (float) $line->unit_price * (1 - $lineDiscount / 100) * (1 - $globalRatio);
        $costBasis = $explanation['cost_base'] * $explanation['conversion_factor'];
        $signature = hash('sha256', implode('|', [
            $document::class, $document->id, $line::class, $line->id, $line->product_id, $line->unit_id,
            $line->quantity, $line->unit_price, $lineDiscount, round($globalRatio, 8),
            $costBasis, $explanation['minimum_price'], $explanation['cost_source'], $explanation['margin_rate'],
        ]));

        return [
            'net_price' => round($net, 2), 'minimum_price' => $explanation['minimum_price'],
            'cost_basis' => $costBasis, 'cost_source' => $explanation['cost_source'],
            'conversion_factor' => $explanation['conversion_factor'], 'margin_rate' => $explanation['margin_rate'],
            'line_discount' => $lineDiscount, 'global_discount_ratio' => $globalRatio, 'signature' => $signature,
        ];
    }

    private function authorize(string $permission): void
    {
        if (! Auth::user()?->can($permission)) {
            throw new \RuntimeException("Permission {$permission} requise.");
        }
    }
}
