<?php

namespace App\Services\Sync\Handlers;

use App\Models\CashAccount;
use App\Models\ClientPayment;
use App\Services\CashAccountService;
use Illuminate\Support\Facades\DB;

/**
 * [Sync ERP] Encaissement client → transaction de trésorerie.
 * IDEMPOTENT : refuse de créer une deuxième transaction référencée sur
 * le même encaissement.
 */
class ReplayClientPaymentTreasurySync
{
    public function __construct(private CashAccountService $cashService)
    {
    }

    public function __invoke(ClientPayment $payment, array $payload = []): void
    {
        $cashAccount = CashAccount::find($payload['cash_account_id'] ?? $payment->cash_account_id);
        if (!$cashAccount) {
            throw new \RuntimeException("Encaissement {$payment->number} : compte de trésorerie introuvable.");
        }

        // Idempotence : transaction déjà enregistrée pour cet encaissement ?
        $exists = DB::table('cash_transactions')
            ->where('reference_type', 'ClientPayment')
            ->where('reference_id', $payment->id)
            ->exists();
        if ($exists) {
            return;
        }

        $this->cashService->recordTransaction($cashAccount, [
            'type'             => 'credit',
            'reference_type'   => 'ClientPayment',
            'reference_id'     => $payment->id,
            'amount'           => $payment->amount,
            'label'            => 'Encaissement '.$payment->number.' — '.$payment->client?->displayName(),
            'transaction_date' => $payment->payment_date ?? today(),
        ]);
    }
}
