<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Models\CashAccount;
use App\Models\ClientPayment;
use App\Models\ClientPaymentSchedule;
use App\Models\Invoice;
use App\Repositories\ClientPaymentRepository;
use App\Services\AccountingService;
use App\Services\LettrageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientPaymentService
{
    public function __construct(
        public readonly ClientPaymentRepository $repository,
        protected DocumentSequenceService $sequenceService,
        protected CashAccountService $cashService,
        protected AccountingService $accountingService,
        protected ClientPaymentScheduleService $scheduleService,
    ) {}

    public function create(array $data): ClientPayment
    {
        return DB::transaction(function () use ($data) {
            $allocations = $data['allocations'] ?? [];
            unset($data['allocations']);

            // [Imputation automatique FIFO] Aucune imputation saisie et paiement non-acompte :
            // imputer d'office sur les factures OUVERTES du client, la plus ancienne d'abord
            // (logique balance âgée), jusqu'à épuisement du montant. Les gardes de la boucle
            // d'allocation (verrou, statuts, plafond au reste dû) s'appliquent inchangées.
            // Un acompte (is_acompte) reste volontairement non imputé.
            if (empty($allocations) && empty($data['is_acompte']) && ! empty($data['client_id'])) {
                $restant = (int) $data['amount'];
                $ouvertes = Invoice::where('client_id', $data['client_id'])
                    ->whereNotIn('status', ['brouillon', 'en_attente_validation', 'annulee', 'payee'])
                    ->where('remaining_amount', '>', 0)
                    ->orderBy('issued_at')->orderBy('id')
                    ->get(['id', 'remaining_amount']);
                foreach ($ouvertes as $inv) {
                    if ($restant <= 0) {
                        break;
                    }
                    $part = min($restant, (int) $inv->remaining_amount);
                    $allocations[] = ['invoice_id' => $inv->id, 'allocated_amount' => $part];
                    $restant -= $part;
                }
            }

            // [GARDE DEVISE] Les agrégats financiers (imputation, balance, éligibilité
            // production) somment les montants bruts : un paiement dans une devise
            // différente de la facture fausserait tout. ERP mono-devise XOF de fait —
            // refus explicite de tout mélange.
            if (! empty($allocations)) {
                $payCur = strtoupper($data['currency_code'] ?? 'XOF');
                $invCurs = Invoice::whereIn('id', collect($allocations)->pluck('invoice_id'))
                    ->pluck('currency_code', 'id');
                foreach ($invCurs as $invId => $cur) {
                    if (strtoupper($cur ?? 'XOF') !== $payCur) {
                        throw new \RuntimeException(
                            "Devise du paiement ({$payCur}) différente de celle de la facture #{$invId} (" . strtoupper($cur ?? 'XOF') . ') — imputation refusée.'
                        );
                    }
                }
            }

            $data['created_by'] = Auth::id();

            // [DOUBLE-PAYMENT-GUARD] Protection anti-doublon AVANT toute écriture :
            // - refuse un encaissement strictement identique (client + montant + méthode + référence)
            //   créé dans les 60 dernières secondes
            // - refuse aussi si une référence saisie (n° chèque, etc.) a déjà été utilisée pour ce client
            $this->assertNoDuplicateRecent($data);

            // Generate payment number
            // [Chemins système] Webhooks/jobs n'ont pas d'utilisateur connecté :
            // retomber sur la société passée en données, puis la société unique.
            $company = Auth::user()?->company
                ?? \App\Models\Company::find($data['company_id'] ?? null)
                ?? \App\Models\Company::first();
            if ($company) {
                $data['company_id'] = $company->id;
                $data['number'] = $this->sequenceService->nextNumber($company, 'encaissement');
            } else {
                $data['number'] = 'ENC-' . date('YmdHis');
            }

            // Calculate unallocated amount initially = full amount
            $data['unallocated_amount'] = $data['amount'];
            $data['allocated_amount']   = 0;

            $payment = $this->repository->create($data);

            $totalAllocated = 0;

            // [FIX-CRITIQUE] Pre-validate: total allocations must not exceed payment amount
            $requestedTotal = collect($allocations)->sum(fn($a) => (int) ($a['allocated_amount'] ?? 0));
            if ($requestedTotal > (int) $data['amount']) {
                throw new \RuntimeException(
                    'Le total des allocations (' . number_format($requestedTotal, 0, ',', ' ') . ' FCFA) '
                    . 'dépasse le montant du paiement (' . number_format($data['amount'], 0, ',', ' ') . ' FCFA).'
                );
            }

            foreach ($allocations as $alloc) {
                if (empty($alloc['invoice_id']) || empty($alloc['allocated_amount'])) {
                    continue;
                }
                $amount = (int) $alloc['allocated_amount'];
                if ($amount <= 0) {
                    continue;
                }

                // [FIX-CRITIQUE] Lock invoice row to prevent concurrent double-allocation
                $invoice = Invoice::lockForUpdate()->find($alloc['invoice_id']);
                if (!$invoice) {
                    continue;
                }

                // [SÉCURITÉ] La facture doit appartenir au même client que le paiement
                if ((int) $invoice->client_id !== (int) $payment->client_id) {
                    throw new \RuntimeException(
                        'La facture ' . $invoice->number . ' n\'appartient pas au client sélectionné.'
                    );
                }

                // [INVOICE-VALIDATION-GUARD] La facture doit être validée avant tout
                // encaissement — refus des factures encore en brouillon / attente de validation.
                if (in_array($invoice->status, ['brouillon', 'en_attente_validation'], true)) {
                    throw new \RuntimeException(sprintf(
                        "La facture %s n'est pas encore validée — validez-la avant d'enregistrer un encaissement.",
                        $invoice->number
                    ));
                }

                // [INVOICE-LOCKED-GUARD] Refus explicite d'allouer un paiement à une facture
                // entièrement payée (status=payee) ou annulée — protection contre les doubles
                // règlements involontaires.
                if (in_array($invoice->status, ['payee', 'annulee'], true)) {
                    throw new \RuntimeException(sprintf(
                        "La facture %s est %s — aucune nouvelle allocation de paiement n'est autorisée. "
                        . "Si vous avez reçu de l'argent en trop, créez un avoir client ou laissez le paiement non alloué (crédit).",
                        $invoice->number, $invoice->status === 'payee' ? 'déjà entièrement payée' : 'annulée'
                    ));
                }
                if ((int) $invoice->remaining_amount <= 0) {
                    throw new \RuntimeException(sprintf(
                        "La facture %s a un reste à payer nul — elle est techniquement soldée. "
                        . "Imputez ce paiement sur une autre facture ou laissez-le en crédit.",
                        $invoice->number
                    ));
                }

                // [FIX-MAJEUR] Cap allocation to actual remaining amount
                $amount = min($amount, (int) $invoice->remaining_amount);
                if ($amount <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'client_payment_id' => $payment->id,
                    'invoice_id'        => $invoice->id,
                    'amount'            => $amount,
                    'allocated_at'      => now(),
                    'created_by'        => Auth::id(),
                ]);

                $totalAllocated += $amount;

                // Update invoice paid/remaining/status
                // [FIX-WITHHOLDING-PAY] Compute remaining against NET_TO_PAY (= total_ttc - withholding),
                // not raw total_ttc — otherwise an invoice with retenue à la source can never reach
                // "payée" because the client only pays the net portion (the State collects the withholding).
                // Use ?: (not ??) because net_to_pay defaults to 0 in the DB schema (NOT NULL),
                // so a null check alone would not catch un-initialized invoices.
                $newPaid       = $invoice->paid_amount + $amount;
                $netToPay      = (int) $invoice->net_to_pay
                                 ?: max(0, (int) $invoice->total_ttc - (int) ($invoice->withholding_amount ?? 0));
                $newRemaining  = max(0, $netToPay - $newPaid);
                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newRemaining <= 0 ? 'payee' : 'partiellement_payee',
                ]);

                // [ECHEANCIER] Appliquer le montant encaissé aux lignes d'échéancier
                // dans l'ordre chronologique (plus ancienne d'abord).
                $this->applyPaymentToSchedule($invoice->id, $amount);
            }

            // Update allocated/unallocated on the payment
            $payment->update([
                'allocated_amount'   => $totalAllocated,
                'unallocated_amount' => max(0, $payment->amount - $totalAllocated),
            ]);

            // Enregistrer la transaction de caisse si un compte est lié.
            // [Sync ERP] journalisée + idempotente (jamais deux transactions de
            // trésorerie pour le même encaissement) + relançable via sync_logs.
            if (!empty($data['cash_account_id'])) {
                $cashAccount = CashAccount::find($data['cash_account_id']);
                if ($cashAccount) {
                    app(\App\Services\Sync\SyncOrchestrator::class)->run(
                        sourceModule: 'ventes',
                        targetModule: 'tresorerie',
                        eventName: 'client_payment.registered',
                        action: 'create_cash_transaction',
                        source: $payment,
                        callback: fn () => $this->cashService->recordTransaction($cashAccount, [
                            'type'             => 'credit',
                            'reference_type'   => 'ClientPayment',
                            'reference_id'     => $payment->id,
                            'amount'           => $payment->amount,
                            'label'            => 'Encaissement '.$payment->number.' — '.$payment->client?->displayName(),
                            'transaction_date' => $payment->payment_date ?? today(),
                        ]),
                        payload: ['cash_account_id' => $cashAccount->id],
                        handlerClass: \App\Services\Sync\Handlers\ReplayClientPaymentTreasurySync::class,
                    );
                }
            }

            // Post to GL synchronously — must be in the same transaction
            $paymentEntry = $this->accountingService->postClientPayment($payment->fresh(['client', 'company']));

            // [AUTO-LETTRAGE] Lettre automatiquement chaque facture soldée 1:1 par ce
            // paiement (411 débit facture = 411 crédit paiement). La garde de montant
            // exact dans lettrerPaiementFacture() neutralise les cas partiels/multiples.
            if ($paymentEntry) {
                foreach ($allocations as $alloc) {
                    $invoiceId = $alloc['invoice_id'] ?? null;
                    if (! $invoiceId) {
                        continue;
                    }
                    $inv = \App\Models\Invoice::find($invoiceId);
                    if ($inv && $inv->status === 'payee' && $inv->journal_entry_id) {
                        app(LettrageService::class)->lettrerPaiementFacture($inv->journalEntry, $paymentEntry);
                    }
                }
            }

            // Fire event — listener recalculates client balance after commit
            event(new PaymentReceived($payment));

            return $payment;
        });
    }

    /**
     * [DOUBLE-PAYMENT-GUARD] Lève une exception si un encaissement strictement
     * identique a été créé dans les 60 dernières secondes (double-clic, soumission
     * concurrente, replay réseau), OU si la référence saisie (numéro de chèque,
     * référence virement, transaction mobile money) est déjà utilisée pour ce client.
     *
     * Strict = même client_id + même amount + même payment_method_id + même cash_account_id
     */
    private function assertNoDuplicateRecent(array $data): void
    {
        $clientId        = (int) ($data['client_id'] ?? 0);
        $amount          = (int) ($data['amount']    ?? 0);
        $paymentMethodId = $data['payment_method_id'] ?? null;
        $cashAccountId   = $data['cash_account_id']   ?? null;
        $reference       = trim((string) ($data['reference'] ?? ''));
        $forceDuplicate  = (bool) ($data['force_duplicate'] ?? false);

        if ($clientId <= 0 || $amount <= 0) return;   // sera bloqué par d'autres validations

        // 1a. Doublon ultra-récent (60 s) sur les 4 attributs structurants — toujours bloqué
        $exists = ClientPayment::where('client_id', $clientId)
            ->where('amount', $amount)
            ->where('payment_method_id', $paymentMethodId)
            ->where('cash_account_id', $cashAccountId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($exists) {
            throw new \RuntimeException(sprintf(
                "Un encaissement identique vient d'être enregistré il y a quelques secondes : %s (%s FCFA). "
                . "Si vous voulez en créer un second pour le même client/montant, attendez 1 minute ou modifiez un attribut "
                . "(méthode, référence…).",
                $exists->number,
                number_format($exists->amount, 0, ',', ' ')
            ));
        }

        // 1b. Doublon "métier" sur 24h pour MÊME CLIENT + MÊME MONTANT (toutes méthodes)
        if (!$forceDuplicate) {
            $sameAmountToday = ClientPayment::where('client_id', $clientId)
                ->where('amount', $amount)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($sameAmountToday > 0) {
                $unpaidSum = (int) Invoice::where('client_id', $clientId)
                    ->whereIn('status', ['emise','envoyee','partiellement_payee','en_retard'])
                    ->whereNull('deleted_at')->sum('remaining_amount');

                throw new \RuntimeException(sprintf(
                    "⚠ Doublon probable : ce client a déjà reçu %d encaissement(s) de %s FCFA dans les 24h. "
                    . "Total impayé actuel du client : %s FCFA. "
                    . "Vérifiez qu'il ne s'agit pas d'une erreur de saisie. Pour confirmer ce paiement supplémentaire, "
                    . "cochez « Forcer le paiement (doublon confirmé) » sur le formulaire.",
                    $sameAmountToday,
                    number_format($amount, 0, ',', ' '),
                    number_format($unpaidSum, 0, ',', ' ')
                ));
            }
        }

        // 1c. [OVERPAYMENT-GUARD] Refuser un encaissement si le client a déjà reçu
        // assez de paiements non imputés pour couvrir TOUT son passif.
        // → empêche les saisies répétées sur un client déjà entièrement payé.
        if (!$forceDuplicate) {
            $unallocatedSum = (int) ClientPayment::where('client_id', $clientId)
                ->whereNull('deleted_at')->sum('unallocated_amount');
            $outstandingSum = (int) Invoice::where('client_id', $clientId)
                ->whereIn('status', ['emise','envoyee','partiellement_payee','en_retard'])
                ->whereNull('deleted_at')->sum('remaining_amount');

            // Si paiements non imputés ≥ ce que doit le client → nouvelle saisie est un doublon structurel
            if ($unallocatedSum >= $outstandingSum && $outstandingSum >= 0) {
                throw new \RuntimeException(sprintf(
                    "⚠ Doublon structurel : ce client a déjà %s FCFA de paiements non imputés disponibles "
                    . "alors qu'il ne doit que %s FCFA au total. Imputez d'abord les paiements existants "
                    . "sur ses factures avant de saisir un nouvel encaissement. "
                    . "Si c'est un véritable nouveau règlement (avance, acompte sur futur achat), "
                    . "cochez « Forcer le paiement ».",
                    number_format($unallocatedSum, 0, ',', ' '),
                    number_format($outstandingSum, 0, ',', ' ')
                ));
            }
        }

        // 2. Référence dupliquée sur le même client (n° chèque, transaction MM…)
        // Ne bloque pas si pas de référence ni reference vide.
        if ($reference !== '') {
            $dup = ClientPayment::where('client_id', $clientId)
                ->where('reference', $reference)
                ->whereNull('deleted_at')
                ->first();
            if ($dup) {
                throw new \RuntimeException(sprintf(
                    "La référence « %s » est déjà utilisée pour ce client (encaissement %s). "
                    . "Vérifiez qu'il ne s'agit pas d'un doublon — si besoin, ajoutez un suffixe à la référence.",
                    $reference, $dup->number
                ));
            }
        }
    }

    /**
     * Return unpaid (validated or partial) invoices for a given client.
     */
    public function getClientUnpaidInvoices(int $clientId): Collection
    {
        return Invoice::where('client_id', $clientId)
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_at')
            ->get(['id', 'number', 'issued_at', 'due_at', 'total_ttc', 'remaining_amount', 'status']);
    }

    /**
     * Ajoute une imputation sur une facture depuis un paiement existant (lettrage a posteriori).
     */
    public function addAllocation(ClientPayment $payment, int $invoiceId, int $amount): void
    {
        DB::transaction(function () use ($payment, $invoiceId, $amount) {
            if ($amount <= 0) {
                throw new \RuntimeException('Le montant à imputer doit être positif.');
            }
            if ($amount > (int) $payment->unallocated_amount) {
                throw new \RuntimeException(sprintf(
                    'Le montant à imputer (%s) dépasse le solde non imputé (%s FCFA).',
                    number_format($amount, 0, ',', ' '),
                    number_format($payment->unallocated_amount, 0, ',', ' ')
                ));
            }

            $invoice = Invoice::lockForUpdate()->find($invoiceId);
            if (!$invoice || (int) $invoice->client_id !== (int) $payment->client_id) {
                throw new \RuntimeException('Facture introuvable ou appartenant à un autre client.');
            }
            if (in_array($invoice->status, ['payee', 'annulee'])) {
                throw new \RuntimeException("La facture {$invoice->number} est déjà {$invoice->status}.");
            }

            $amount = min($amount, (int) $invoice->remaining_amount);
            if ($amount <= 0) {
                throw new \RuntimeException("La facture {$invoice->number} n'a plus de reste à payer.");
            }

            $payment->allocations()->create([
                'client_payment_id' => $payment->id,
                'invoice_id'        => $invoice->id,
                'amount'            => $amount,
                'allocated_at'      => now(),
                'created_by'        => Auth::id(),
            ]);

            // Update invoice
            $newPaid      = (int) $invoice->paid_amount + $amount;
            $netToPay     = (int) $invoice->net_to_pay
                            ?: max(0, (int) $invoice->total_ttc - (int) ($invoice->withholding_amount ?? 0));
            $newRemaining = max(0, $netToPay - $newPaid);
            $invoice->update([
                'paid_amount'      => $newPaid,
                'remaining_amount' => $newRemaining,
                'status'           => $newRemaining <= 0 ? 'payee' : 'partiellement_payee',
            ]);

            // Update payment unallocated
            $payment->update([
                'allocated_amount'   => $payment->allocated_amount + $amount,
                'unallocated_amount' => max(0, $payment->unallocated_amount - $amount),
            ]);

            // [ECHEANCIER] Mettre à jour les lignes d'échéancier
            $this->applyPaymentToSchedule($invoice->id, $amount);
        });
    }

    /**
     * [ECHEANCIER] Applique un montant reçu sur les lignes de l'échéancier de la facture,
     * en commençant par la plus ancienne échéance non réglée (ordre chronologique).
     *
     * Ne lève pas d'exception si la facture n'a pas d'échéancier — dans ce cas, rien
     * ne se passe (la mise à jour paid_amount/remaining_amount sur Invoice suffit).
     */
    private function applyPaymentToSchedule(int $invoiceId, int $allocatedAmount): void
    {
        if ($allocatedAmount <= 0) return;

        $schedules = ClientPaymentSchedule::where('invoice_id', $invoiceId)
            ->whereIn('status', ['en_attente', 'partiel'])
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $remaining = $allocatedAmount;

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) break;

            $scheduleRemaining = (int) $schedule->remainingAmount();
            if ($scheduleRemaining <= 0) continue;

            $toApply = min($remaining, $scheduleRemaining);
            $this->scheduleService->markPayment($schedule, $toApply);
            $remaining -= $toApply;
        }
    }
}
