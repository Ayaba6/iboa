<?php

namespace App\Services;

use App\Models\SalesFloorWaiver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesFloorWaiverService
{
    public function request(Model $document, Model $line, string $reason, ?string $justificationPath = null): SalesFloorWaiver
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

            return SalesFloorWaiver::create([
                'company_id' => $document->company_id,
                'document_type' => $document::class, 'document_id' => $document->id,
                'line_type' => $line::class, 'line_id' => $line->id,
                'product_id' => $line->product_id, 'unit_id' => $line->unit_id,
                'quantity' => $line->quantity, 'proposed_price' => $pricing['net_price'],
                'minimum_price' => $pricing['minimum_price'], 'cost_basis' => $pricing['cost_basis'],
                'cost_source' => $pricing['cost_source'], 'margin_rate' => $pricing['margin_rate'],
                'expected_margin' => ($pricing['net_price'] - $pricing['cost_basis']) * (float) $line->quantity,
                'gap' => $pricing['minimum_price'] - $pricing['net_price'],
                'reason' => trim($reason), 'justification_path' => $justificationPath,
                'pricing_signature' => $pricing['signature'], 'status' => 'soumise',
                'requested_by' => Auth::id(), 'submitted_at' => now(),
            ]);
        });
    }

    public function approve(SalesFloorWaiver $waiver, ?string $reason = null, int $validDays = 30): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.approve');
        if ($waiver->status !== 'soumise') {
            throw new \RuntimeException('Seule une demande soumise peut être approuvée.');
        }
        if ((int) $waiver->requested_by === (int) Auth::id()) {
            throw new \RuntimeException('Le demandeur ne peut pas approuver sa propre dérogation.');
        }
        $waiver->update(['status' => 'approuvee', 'decided_by' => Auth::id(), 'decided_at' => now(),
            'expires_at' => now()->addDays($validDays), 'decision_reason' => $reason]);
        app(AuditService::class)->log('sales.below_floor.approved', $waiver, [], ['gap' => $waiver->gap]);

        return $waiver->fresh();
    }

    public function reject(SalesFloorWaiver $waiver, string $reason): SalesFloorWaiver
    {
        $this->authorize('sales_below_floor.reject');
        $waiver->update(['status' => 'refusee', 'decided_by' => Auth::id(), 'decided_at' => now(), 'decision_reason' => $reason]);

        return $waiver->fresh();
    }

    public function assertDocumentMayProceed(Model $document): void
    {
        foreach ($document->items()->with('product')->get() as $line) {
            $pricing = $this->pricing($document, $line);
            if ($pricing['minimum_price'] <= $pricing['net_price'] + 0.005) {
                continue;
            }
            $approved = SalesFloorWaiver::where('line_type', $line::class)->where('line_id', $line->id)
                ->where('status', 'approuvee')->where('pricing_signature', $pricing['signature'])
                ->where('expires_at', '>', now())->exists();
            if (! $approved) {
                throw new \RuntimeException("Prix HT net sous le minimum pour « {$line->product->name} » : dérogation approuvée requise.");
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
        $signature = hash('sha256', implode('|', [$line->product_id, $line->unit_id, $line->quantity,
            $line->unit_price, $lineDiscount, round($globalRatio, 8), $explanation['minimum_price'], $explanation['cost_source']]));

        return ['net_price' => round($net, 2), 'minimum_price' => $explanation['minimum_price'],
            'cost_basis' => $explanation['cost_base'] * $explanation['conversion_factor'],
            'cost_source' => $explanation['cost_source'], 'margin_rate' => $explanation['margin_rate'], 'signature' => $signature];
    }

    private function authorize(string $permission): void
    {
        if (! Auth::user()?->can($permission)) {
            throw new \RuntimeException("Permission {$permission} requise.");
        }
    }
}
