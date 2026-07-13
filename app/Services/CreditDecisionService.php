<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditDecision;
use Illuminate\Support\Facades\DB;

/**
 * [VEN Crédit client] Enregistrement des décisions de crédit + application au client.
 */
class CreditDecisionService
{
    /**
     * Journalise une décision de crédit et applique son effet au client :
     *  - blocage / deblocage   → is_blocked + blocage_commande
     *  - relevement/reduction  → credit_limit
     *  - derogation            → journal seul (autorisation ponctuelle)
     */
    public function record(Client $client, array $data): CreditDecision
    {
        return DB::transaction(function () use ($client, $data) {
            $type = $data['type'];
            $previousLimit = $client->credit_limit;

            $decision = CreditDecision::create([
                'company_id'     => currentCompany()?->id ?? \App\Models\Company::query()->value('id'),
                'client_id'      => $client->id,
                'type'           => $type,
                'previous_limit' => $previousLimit,
                'new_limit'      => in_array($type, ['relevement_plafond', 'reduction_plafond'], true)
                    ? ($data['new_limit'] ?? null) : null,
                'amount'         => $type === 'derogation' ? ($data['amount'] ?? null) : null,
                'reason'         => $data['reason'] ?? null,
                'decided_by'     => auth()->id(),
            ]);

            match ($type) {
                'blocage'   => $client->update(['is_blocked' => true, 'blocage_commande' => true, 'blocked_reason' => $data['reason'] ?? null]),
                'deblocage' => $client->update(['is_blocked' => false, 'blocage_commande' => false, 'blocked_reason' => null]),
                'relevement_plafond', 'reduction_plafond' => $client->update(['credit_limit' => (int) ($data['new_limit'] ?? $previousLimit)]),
                default => null,
            };

            return $decision;
        });
    }
}
