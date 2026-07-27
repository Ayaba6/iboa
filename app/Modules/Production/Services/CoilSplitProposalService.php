<?php

namespace App\Modules\Production\Services;

use App\Models\CoilSplitProposal;
use App\Modules\Production\Models\Coil;
use App\Services\AuditService;
use App\Services\ExecutionContext;
use App\Services\MakerCheckerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [Division #3/#4] Circuit d'approbation PERSISTÉ de la division de bobine.
 *
 *   BROUILLON → SOUMISE → APPROUVEE → EXECUTEE
 *   SOUMISE → REFUSEE ; APPROUVEE → INVALIDEE (payload modifié)
 *
 * Les enfants et mouvements ne sont créés qu'à l'exécution d'une proposition
 * APPROUVÉE et INCHANGÉE : l'exécution recalcule le hash du payload et refuse
 * s'il diffère de celui approuvé.
 *
 * Les seuils sont FIGÉS à la soumission — une modification ultérieure du
 * paramétrage ne change pas rétroactivement une proposition déjà soumise.
 */
class CoilSplitProposalService
{
    /** Soumet une proposition (permission `coils.split.propose`). */
    public function submit(Coil $mother, array $children, array $opts = []): CoilSplitProposal
    {
        ExecutionContext::assertCan('coils.split.propose', 'proposer une division de bobine');

        return DB::transaction(function () use ($mother, $children, $opts) {
            $mother   = Coil::lockForUpdate()->findOrFail($mother->id);
            $divisible = (float) $mother->remaining_weight;
            $scrap    = (float) ($opts['scrap'] ?? 0);
            $loss     = (float) ($opts['loss'] ?? 0);
            $costPerKg = (float) $mother->cost_per_kg;
            $lossValue = (int) round($loss * $costPerKg);

            // [#2] Seuils FIGÉS sur la proposition.
            $thresholdValue = (int) config('security.maker_checker.coil_split.loss_value_threshold', 50000);
            $thresholdQty   = (float) config('security.maker_checker.coil_split.loss_qty_threshold', 50);
            $requiresLossApproval = $loss > 0 && ($lossValue > $thresholdValue || $loss > $thresholdQty);

            $payload = $this->economicPayload($mother, $children, $scrap, $loss, $divisible);

            $proposal = CoilSplitProposal::create([
                'company_id'             => $mother->company_id,
                'coil_id'                => $mother->id,
                'number'                 => 'PROP-' . mb_substr((string) $mother->reference, 0, 40) . '-' . now()->format('YmdHis'),
                'payload'                => $payload,
                'payload_hash'           => self::payloadHash($payload),
                'divisible_qty'          => $divisible,
                'scrap_qty'              => $scrap,
                'loss_qty'               => $loss,
                'loss_value'             => $lossValue,
                'residual_cost'          => (int) round($divisible * $costPerKg),
                'threshold_loss_value'   => $thresholdValue,
                'threshold_loss_qty'     => $thresholdQty,
                'requires_loss_approval' => $requiresLossApproval,
                'status'                 => CoilSplitProposal::STATUS_SUBMITTED,
                'proposed_by'            => Auth::id(),
                'submitted_at'           => now(),
            ]);

            app(AuditService::class)->log('bobine.division.proposition', $proposal, [], [
                'bobine' => $mother->reference, 'divisible' => $divisible,
                'perte' => $loss, 'valeur_perte' => $lossValue,
                'approbation_perte_requise' => $requiresLossApproval,
                'acteur' => ExecutionContext::describe(),
            ]);

            return $proposal;
        });
    }

    /**
     * Approuve une proposition SOUMISE. Maker-checker : l'approbateur ne peut pas
     * être le proposeur lorsque l'approbation de perte est requise.
     */
    public function approve(CoilSplitProposal $proposal): CoilSplitProposal
    {
        return DB::transaction(function () use ($proposal) {
            $proposal = CoilSplitProposal::lockForUpdate()->findOrFail($proposal->id);

            if ($proposal->status !== CoilSplitProposal::STATUS_SUBMITTED) {
                throw new \RuntimeException(sprintf(
                    'Proposition %s : seul l\'état « soumise » peut être approuvé (état actuel : %s).',
                    $proposal->number, $proposal->status
                ));
            }

            if ($proposal->requires_loss_approval) {
                ExecutionContext::assertCan('coils.split.approve_loss', sprintf(
                    'approuver la perte de %s kg (%s FCFA) de la proposition %s',
                    $proposal->loss_qty, $proposal->loss_value, $proposal->number
                ));
                app(MakerCheckerService::class)->assert(
                    $proposal->proposed_by, 'coil_split.approve_loss',
                    "la proposition de division {$proposal->number}", $proposal
                );
            } else {
                ExecutionContext::assertCan('coils.split.execute', 'approuver une division sans perte significative');
            }

            $proposal->update([
                'status'      => CoilSplitProposal::STATUS_APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            app(AuditService::class)->log('bobine.division.approbation', $proposal, [], [
                'approbateur' => Auth::id(), 'proposeur' => $proposal->proposed_by,
                'acteur' => ExecutionContext::describe(),
            ]);

            return $proposal->fresh();
        });
    }

    /**
     * Exécute une proposition APPROUVÉE et INCHANGÉE. Toute modification
     * économique du payload invalide l'approbation (#4).
     *
     * @return array<int,Coil> bobines filles
     */
    public function execute(CoilSplitProposal $proposal, array $children, array $opts = []): array
    {
        ExecutionContext::assertCan('coils.split.execute', 'exécuter une division de bobine');

        $proposal = CoilSplitProposal::lockForUpdate()->findOrFail($proposal->id);
        if ($proposal->status !== CoilSplitProposal::STATUS_APPROVED) {
            throw new \RuntimeException(sprintf(
                'Proposition %s : exécution impossible sans approbation (état : %s).',
                $proposal->number, $proposal->status
            ));
        }

        $mother = Coil::findOrFail($proposal->coil_id);
        $scrap  = (float) ($opts['scrap'] ?? $proposal->scrap_qty);
        $loss   = (float) ($opts['loss'] ?? $proposal->loss_qty);

        // [#4] Le payload exécuté doit être IDENTIQUE à celui approuvé.
        $hash = self::payloadHash($this->economicPayload(
            $mother, $children, $scrap, $loss, (float) $proposal->divisible_qty
        ));
        if ($hash !== $proposal->payload_hash) {
            $proposal->update(['status' => CoilSplitProposal::STATUS_INVALIDATED]);
            app(AuditService::class)->log('bobine.division.invalidation', $proposal, [], [
                'motif' => 'payload modifié après approbation', 'acteur' => ExecutionContext::describe(),
            ]);

            throw new \RuntimeException(sprintf(
                'Proposition %s INVALIDÉE : le contenu économique a été modifié après approbation — '
                . 'une nouvelle soumission et une nouvelle approbation sont requises.',
                $proposal->number
            ));
        }

        $children_ = app(CoilSplitService::class)->split($mother, $children, $scrap, $opts['reason'] ?? null, [
            'loss'            => $loss,
            'tolerance'       => $opts['tolerance'] ?? null,
            'idempotency_key' => $opts['idempotency_key'] ?? ('prop-' . $proposal->id),
            'proposed_by'     => $proposal->proposed_by,
            'approved'        => true, // approbation déjà tracée sur la proposition
            'requires_post_split_qc' => $opts['requires_post_split_qc'] ?? false,
        ]);

        $proposal->update([
            'status'      => CoilSplitProposal::STATUS_EXECUTED,
            'executed_by' => Auth::id(),
            'executed_at' => now(),
        ]);

        return $children_;
    }

    /** Payload ÉCONOMIQUE figé (base du hash d'invalidation). */
    private function economicPayload(Coil $mother, array $children, float $scrap, float $loss, float $divisible): array
    {
        $n = fn ($v) => number_format((float) $v, 3, '.', '');

        return [
            'coil_id'    => $mother->id,
            'divisible'  => $n($divisible),
            'cost_per_kg' => $n($mother->cost_per_kg),
            'scrap'      => $n($scrap),
            'loss'       => $n($loss),
            'children'   => array_values(array_map(fn ($c) => [
                'w' => $n($c['weight'] ?? 0),
                'q' => (string) ($c['quality_status'] ?? ''),
                'r' => (string) ($c['reference'] ?? ''),
            ], $children)),
        ];
    }

    /** Empreinte canonique (ordre fixe, décimales normalisées). */
    public static function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
