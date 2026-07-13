<?php

namespace App\Modules\Quality\Services;

use App\Modules\Quality\Models\CorrectiveAction;
use App\Modules\Quality\Models\NonConformity;
use Illuminate\Support\Facades\DB;

/**
 * [QUA-05] Cycle de vie des actions correctives/préventives (CAPA).
 * a_faire → en_cours → faite → verifiee (efficace/inefficace).
 */
class CorrectiveActionService
{
    /** Génère la référence CAPA d'une NC : NC-REF/A01, /A02… */
    public function nextReference(NonConformity $nc): string
    {
        $n = $nc->correctiveActions()->count() + 1;

        return ($nc->reference ?: 'NC-'.$nc->id).'/A'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /** Passe une action à en_cours ou faite (avec date de réalisation). */
    public function changeStatus(CorrectiveAction $action, string $status): void
    {
        $attrs = ['status' => $status];
        if ($status === 'faite') {
            $attrs['completed_at'] = now()->toDateString();
        } elseif ($status === 'en_cours') {
            $attrs['completed_at'] = null;
        }
        $action->update($attrs);
    }

    /**
     * Vérifie l'efficacité d'une action réalisée. Passe en 'verifiee'.
     * Si inefficace, l'action retourne en 'en_cours' pour être retravaillée.
     */
    public function verify(CorrectiveAction $action, bool $effective, ?string $comment, ?int $verifierId): void
    {
        DB::transaction(function () use ($action, $effective, $comment, $verifierId) {
            $action->update([
                'is_effective'          => $effective,
                'effectiveness_comment' => $comment,
                'verified_by_id'        => $verifierId,
                'verified_at'           => now()->toDateString(),
                'status'                => $effective ? 'verifiee' : 'en_cours',
                'completed_at'          => $effective ? $action->completed_at : null,
            ]);

            // CAPA soldée → proposer la clôture de la NC (statut en_cours si ouverte).
            $nc = $action->nonConformity;
            if ($nc && $nc->capaComplete() && $nc->status !== 'cloturee') {
                $nc->update(['status' => 'cloturee', 'closed_at' => now()->toDateString()]);
            }
        });
    }
}
