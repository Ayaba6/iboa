<?php

namespace App\Services;

use App\Models\Appraisal;

/**
 * [RH-11] Calcul des notes d'évaluation et finalisation.
 */
class AppraisalService
{
    /**
     * Note pondérée /5 à partir d'une clé de notation des critères.
     * @param 'self_rating'|'manager_rating' $field
     */
    public function weightedScore(Appraisal $appraisal, string $field): ?float
    {
        $criteria = $appraisal->criteria()->get();
        $totalWeight = 0;
        $sum = 0.0;
        foreach ($criteria as $c) {
            if ($c->$field === null) {
                continue;
            }
            $w = max(1, (int) $c->weight);
            $sum += (float) $c->$field * $w;
            $totalWeight += $w;
        }

        return $totalWeight > 0 ? round($sum / $totalWeight, 2) : null;
    }

    /** Convertit une note /5 en libellé d'appréciation. */
    public function ratingFor(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score < 2   => 'insuffisant',
            $score < 3   => 'a_ameliorer',
            $score < 4   => 'satisfaisant',
            $score < 4.5 => 'bon',
            default      => 'excellent',
        };
    }

    /** Recalcule les notes auto/manager/globale + l'appréciation. */
    public function recompute(Appraisal $appraisal): void
    {
        $self    = $this->weightedScore($appraisal, 'self_rating');
        $manager = $this->weightedScore($appraisal, 'manager_rating');
        $overall = $manager ?? $self;

        $appraisal->update([
            'self_score'    => $self,
            'manager_score' => $manager,
            'overall_score' => $overall,
            'rating'        => $this->ratingFor($overall),
        ]);
    }

    /** Finalise l'évaluation (fige la note globale et la date). */
    public function finalize(Appraisal $appraisal): void
    {
        $this->recompute($appraisal);
        $appraisal->update(['status' => 'finalisee', 'finalized_at' => now()->toDateString()]);
    }
}
