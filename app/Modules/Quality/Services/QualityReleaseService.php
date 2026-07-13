<?php

namespace App\Modules\Quality\Services;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Quality\Models\QualityRelease;
use Illuminate\Support\Facades\DB;

/**
 * [QUA-07] Workflow de libération qualité des lots de fabrication.
 *  - libere      : lot conforme, disponible (batch → conforme)
 *  - refuse      : lot bloqué en quarantaine (batch reste en_cours)
 *  - derogation  : libéré sous dérogation avec référence (batch → conforme)
 */
class QualityReleaseService
{
    /**
     * Enregistre une décision de libération qualité pour un lot.
     *
     * @param  string  $status  libere|refuse|derogation
     */
    public function decide(ProductionBatch $batch, string $status, ?string $comment = null, ?string $derogationRef = null, ?int $controlPlanId = null): QualityRelease
    {
        return DB::transaction(function () use ($batch, $status, $comment, $derogationRef, $controlPlanId) {
            $release = QualityRelease::updateOrCreate(
                ['production_batch_id' => $batch->id],
                [
                    'company_id'           => $batch->company_id,
                    'control_plan_id'      => $controlPlanId,
                    'reference'            => $this->referenceFor($batch),
                    'quantity'             => $batch->quantity,
                    'status'               => $status,
                    'decision_comment'     => $comment,
                    'derogation_reference' => $status === 'derogation' ? $derogationRef : null,
                    'decided_by'           => auth()->id(),
                    'decided_at'           => now(),
                    'created_by'           => $batch->qualityRelease?->created_by ?? auth()->id(),
                ]
            );

            // Répercussion sur le statut du lot (sémantique existante en_cours→conforme→cloture).
            if (in_array($status, ['libere', 'derogation'], true)) {
                if ($batch->status === 'en_cours') {
                    $batch->update(['status' => 'conforme']);
                }
            } elseif ($status === 'refuse') {
                // Lot bloqué : on annule une éventuelle conformité antérieure.
                if ($batch->status === 'conforme') {
                    $batch->update(['status' => 'en_cours']);
                }
            }

            return $release;
        });
    }

    private function referenceFor(ProductionBatch $batch): string
    {
        return 'LIB-'.($batch->batch_number ?: $batch->id);
    }
}
