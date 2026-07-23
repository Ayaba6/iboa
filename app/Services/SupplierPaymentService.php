<?php

namespace App\Services;

use App\Events\SupplierPaymentCreated;
use App\Models\CashAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierInvoice;
use App\Repositories\SupplierPaymentRepository;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        public readonly SupplierPaymentRepository $repository,
        protected DocumentSequenceService $sequenceService,
        protected CashAccountService $cashService,
        protected AccountingService $accountingService,
        protected TreasuryApprovalService $approvalService,
    ) {}

    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $allocations = $data['allocations'] ?? [];
            unset($data['allocations']);

            $data['created_by'] = Auth::id();

            // [DOUBLE-PAYMENT-GUARD] Anti-doublon décaissement (cf. ClientPaymentService).
            $this->assertNoDuplicateRecent($data);

            // Generate payment number
            $company = Auth::user()->company;
            if ($company) {
                $data['company_id'] = $company->id;
                $data['number'] = $this->sequenceService->nextNumber($company, 'decaissement');
            } else {
                $data['number'] = 'DEC-' . date('YmdHis');
            }

            // Calculate unallocated amount initially = full amount
            $data['unallocated_amount'] = $data['amount'];
            $data['allocated_amount']   = 0;

            // [TRESO-WORKFLOW] Déterminer si une validation par seuil est requise.
            // Si le créateur possède déjà l'autorité du seuil, le décaissement est
            // auto-validé (pas de double étape inutile). Sinon il est différé.
            // _pre_authorized : déjà validé en amont (ex. demande de paiement) → pas de re-contrôle.
            $preAuthorized = (bool) ($data['_pre_authorized'] ?? false);
            unset($data['_pre_authorized']);
            $rule       = $this->approvalService->findRequiredRule((int) $data['company_id'], (int) $data['amount']);
            $needsOther = $rule && ! $preAuthorized && ! $this->approvalService->userCanApprove(Auth::user(), $rule);

            if ($needsOther) {
                // Différé : aucun effet (GL/caisse/allocation) avant validation.
                $data['status']              = 'en_attente';
                $data['validation_status']   = 'en_attente_validation';
                $data['required_role']       = $rule->required_role;
                $data['pending_allocations'] = array_values($allocations);
                $data['submitted_by']        = Auth::id();
                $data['submitted_at']        = now();

                return $this->repository->create($data);
            }

            // Pas d'approbation requise (ou créateur habilité) : application directe.
            $data['validation_status'] = 'valide';
            if ($rule) {
                $data['validated_by'] = Auth::id();
                $data['validated_at'] = now();
            }
            $payment = $this->repository->create($data);
            $this->applySideEffects($payment, $allocations);

            return $payment->fresh();
        });
    }

    /**
     * [TRESO-WORKFLOW] Applique les effets d'un décaissement : allocation aux
     * factures, mise à jour des soldes, écriture GL, sortie de caisse, événement.
     * Appelé soit à la création (sans approbation), soit à la validation.
     */
    private function applySideEffects(SupplierPayment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations) {
            $totalAllocated = 0;

            // Pre-validate: total allocations must not exceed payment amount
            $requestedTotal = collect($allocations)->sum(fn($a) => (int) ($a['allocated_amount'] ?? $a['amount'] ?? 0));
            if ($requestedTotal > (int) $payment->amount) {
                throw new \RuntimeException(
                    'Le total des allocations (' . number_format($requestedTotal, 0, ',', ' ') . ' FCFA) '
                    . 'dépasse le montant du paiement (' . number_format($payment->amount, 0, ',', ' ') . ' FCFA).'
                );
            }

            foreach ($allocations as $alloc) {
                // [GARDE format] Une allocation qui référence une facture mais dont le
                // montant est absent/mal nommé ne doit pas être ignorée en silence :
                // l'écriture GL partirait sans jamais solder la facture.
                if (! empty($alloc['supplier_invoice_id'])
                    && ! isset($alloc['allocated_amount']) && ! isset($alloc['amount'])) {
                    throw new \RuntimeException(
                        'Allocation invalide : montant manquant (clé allocated_amount) pour la facture #' . $alloc['supplier_invoice_id'] . '.'
                    );
                }
                if (empty($alloc['supplier_invoice_id'])) {
                    continue;
                }
                $amount = (int) ($alloc['allocated_amount'] ?? $alloc['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                // Lock invoice row to prevent concurrent double-allocation
                $invoice = SupplierInvoice::lockForUpdate()->find($alloc['supplier_invoice_id']);
                if (!$invoice) {
                    continue;
                }

                // [SÉCURITÉ] La facture doit appartenir au même fournisseur que le paiement
                if ((int) $invoice->supplier_id !== (int) $payment->supplier_id) {
                    throw new \RuntimeException(
                        'La facture ' . $invoice->number . ' n\'appartient pas au fournisseur sélectionné.'
                    );
                }

                // [INVOICE-LOCKED-GUARD] Refus explicite d'allouer un décaissement à une FF
                // entièrement payée (status=payee) ou annulée.
                if (in_array($invoice->status, ['payee', 'annulee'], true)) {
                    throw new \RuntimeException(sprintf(
                        "La facture fournisseur %s est %s — aucune nouvelle allocation n'est autorisée.",
                        $invoice->number, $invoice->status === 'payee' ? 'déjà entièrement payée' : 'annulée'
                    ));
                }
                if ((int) $invoice->remaining_amount <= 0) {
                    throw new \RuntimeException(sprintf(
                        "La facture fournisseur %s a un reste à payer nul — elle est techniquement soldée.",
                        $invoice->number
                    ));
                }

                // Cap allocation to actual remaining amount
                $amount = min($amount, (int) $invoice->remaining_amount);
                if ($amount <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'supplier_payment_id' => $payment->id,
                    'supplier_invoice_id' => $invoice->id,
                    'amount'              => $amount,
                    'allocated_at'        => now(),
                    'created_by'          => Auth::id(),
                ]);

                $totalAllocated += $amount;

                // Update supplier invoice paid/remaining/status
                $newPaid      = $invoice->paid_amount + $amount;
                $newRemaining = max(0, $invoice->total_ttc - $newPaid);
                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newRemaining <= 0 ? 'payee' : 'partiellement_payee',
                ]);

                // [ACHATS-PRO-SCHEDULE] Si un cadencier existe, impute le paiement
                // en ordre chronologique sur les échéances en attente.
                if ($invoice->paymentSchedules()->exists()) {
                    app(\App\Services\PaymentScheduleService::class)->applyPayment($invoice, (float) $amount);
                }
            }

            // Update allocated/unallocated on the payment
            $payment->update([
                'allocated_amount'   => $totalAllocated,
                'unallocated_amount' => max(0, $payment->amount - $totalAllocated),
            ]);

            // Post to GL synchronously — must be in the same transaction
            $paymentEntry = $this->accountingService->postSupplierPayment($payment->fresh(['supplier', 'company']))
                ?? $payment->fresh()->journalEntry;

            // [AUTO-LETTRAGE] Symétrie fournisseur : lettre chaque facture soldée 1:1
            // (401 crédit facture ↔ 401 débit décaissement). Garde montant-exact = sûr.
            if ($paymentEntry) {
                foreach ($allocations as $alloc) {
                    $siId = $alloc['supplier_invoice_id'] ?? null;
                    if (! $siId) {
                        continue;
                    }
                    $si = \App\Models\SupplierInvoice::find($siId);
                    if ($si && $si->status === 'payee' && $si->journal_entry_id) {
                        app(\App\Services\LettrageService::class)->lettrerPaiementFacture($si->journalEntry, $paymentEntry);
                    }
                }
            }

            // Enregistrer la transaction de caisse si un compte est lié
            if (!empty($payment->cash_account_id)) {
                $cashAccount = CashAccount::find($payment->cash_account_id);
                if ($cashAccount) {
                    $this->cashService->recordTransaction($cashAccount, [
                        'type'             => 'debit',
                        'reference_type'   => 'SupplierPayment',
                        'reference_id'     => $payment->id,
                        'amount'           => $payment->amount,
                        'label'            => 'Décaissement '.$payment->number.' — '.$payment->supplier?->name,
                        'transaction_date' => $payment->payment_date ?? today(),
                    ]);
                }
            }

            // Fire event — queued listeners update supplier balance + log after commit
            event(new SupplierPaymentCreated($payment));
        });
    }

    /**
     * [TRESO-WORKFLOW] Approuve un décaissement en attente → applique les effets.
     * @throws \RuntimeException
     */
    public function approve(SupplierPayment $payment): SupplierPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->validation_status !== 'en_attente_validation') {
                throw new \RuntimeException('Ce décaissement n\'est pas en attente de validation.');
            }

            // [SEC-PHASE2 §2] Maker-checker : l'auteur du décaissement ne l'approuve pas lui-même
            app(MakerCheckerService::class)->assert($payment->created_by, 'decaissement.approve', "le décaissement {$payment->number}", $payment);

            $user = Auth::user();
            $rule = $this->approvalService->findRequiredRule((int) $payment->company_id, (int) $payment->amount);
            if (! $this->approvalService->userCanApprove($user, $rule)) {
                throw new \RuntimeException(
                    "Vous n'avez pas le niveau requis pour valider ce décaissement"
                    . ($rule?->required_role ? " (rôle « {$rule->required_role} » requis)." : '.')
                );
            }

            $allocations = $payment->pending_allocations ?? [];
            $this->applySideEffects($payment, $allocations);

            $payment->update([
                'status'              => 'confirme',
                'validation_status'   => 'valide',
                'pending_allocations' => null,
                'validated_by'        => $user->id,
                'validated_at'        => now(),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * [TRESO-WORKFLOW] Rejette un décaissement en attente (motif obligatoire).
     * Aucun effet n'avait été appliqué → rien à contre-passer.
     * @throws \RuntimeException
     */
    public function reject(SupplierPayment $payment, string $motif): SupplierPayment
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw new \RuntimeException('Le motif de rejet est obligatoire.');
        }

        return DB::transaction(function () use ($payment, $motif) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->validation_status !== 'en_attente_validation') {
                throw new \RuntimeException('Ce décaissement n\'est pas en attente de validation.');
            }

            $payment->update([
                'status'            => 'rejete',
                'validation_status' => 'rejete',
                'rejected_by'       => Auth::id(),
                'rejected_at'       => now(),
                'rejection_reason'  => $motif,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Annule un décaissement fournisseur déjà confirmé.
     *
     * Procédure SAFE :
     *   1. Lock du payment + check statut (refuse si déjà 'annule')
     *   2. Pour chaque allocation : remettre la facture parent en état non-payée
     *   3. Contre-passation comptable (via AccountingService::reverseEntry)
     *   4. Restitution du solde caisse si une cash_transaction existait
     *   5. Marquage du payment en 'annule' (preserve historique, pas de hard delete)
     *
     * @param  string|null  $reason  Motif obligatoire pour audit comptable
     * @throws \RuntimeException si déjà annulé ou état invalide
     */
    public function cancel(SupplierPayment $payment, ?string $reason = null): SupplierPayment
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new \RuntimeException('Motif d\'annulation obligatoire (traçabilité comptable).');
        }

        return DB::transaction(function () use ($payment, $reason) {
            // Lock du payment pour éviter double annulation
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'annule') {
                throw new \RuntimeException('Ce décaissement est déjà annulé.');
            }

            $payment->load('allocations', 'cashAccount');

            // 1. Restaurer chaque facture allouée
            foreach ($payment->allocations as $alloc) {
                $invoice = \App\Models\SupplierInvoice::lockForUpdate()->find($alloc->supplier_invoice_id);
                if (!$invoice) continue;

                $newPaid      = max(0, (int) $invoice->paid_amount - (int) $alloc->amount);
                $newRemaining = max(0, (int) $invoice->total_ttc - $newPaid);

                $newStatus = $invoice->status;
                if ($newRemaining === (int) $invoice->total_ttc) {
                    $newStatus = 'validee';  // entièrement restaurée
                } elseif ($newRemaining > 0) {
                    $newStatus = 'partiellement_payee';
                }

                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newStatus,
                ]);
            }

            // Supprimer les allocations (cascade pas garantie)
            $payment->allocations()->delete();

            // 2. Contre-passation de l'écriture comptable
            $entry = \App\Models\JournalEntry::where('reference', $payment->number)
                ->where('company_id', $payment->company_id)
                ->where('status', 'valide')
                ->first();
            if ($entry) {
                $this->accountingService->reverseEntry(
                    $entry,
                    'Annulation décaissement ' . $payment->number . ' — ' . $reason
                );
            }

            // 3. Restitution caisse si une cash_transaction existait
            //    (Si elle n'existait pas — cas des paiements historiques pré-sync — on saute.)
            $cashTx = \App\Models\CashTransaction::where('reference_type', 'App\\Models\\SupplierPayment')
                ->where('reference_id', $payment->id)
                ->orWhere(function ($q) use ($payment) {
                    $q->where('reference_type', 'SupplierPayment')->where('reference_id', $payment->id);
                })
                ->first();
            if ($cashTx && $payment->cashAccount) {
                // On crée un mouvement inverse (credit pour restituer ce qu'on avait débité)
                $this->cashService->recordTransaction($payment->cashAccount, [
                    'type'             => 'credit',
                    'reference_type'   => 'SupplierPayment',
                    'reference_id'     => $payment->id,
                    'amount'           => $payment->amount,
                    'label'            => 'Annulation décaissement ' . $payment->number,
                    'transaction_date' => today(),
                ]);
            }

            // 4. Marquer le payment annulé + trace motif
            $cancelNote = sprintf(
                "[ANNULATION %s par %s] %s",
                now()->format('d/m/Y H:i'),
                Auth::user()?->name ?? 'système',
                $reason
            );
            $payment->update([
                'status' => 'annule',
                'notes'  => trim(($payment->notes ?? '') . "\n\n" . $cancelNote),
            ]);

            // [SEC-PHASE2] Journal d'audit : annulation financière tracée
            app(AuditService::class)->log('decaissement.annulation', $payment, [], ['motif' => $reason, 'montant' => $payment->amount]);

            return $payment->fresh();
        });
    }

    /**
     * Return unpaid supplier invoices for a given supplier.
     */
    public function getSupplierUnpaidInvoices(int $supplierId): Collection
    {
        return SupplierInvoice::where('supplier_id', $supplierId)
            ->whereIn('status', ['validee', 'partiellement_payee'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_at')
            ->get(['id', 'number', 'supplier_invoice_number', 'received_at', 'due_at', 'total_ttc', 'remaining_amount', 'status']);
    }

    /**
     * [DOUBLE-PAYMENT-GUARD] Lève une exception si un décaissement strictement
     * identique a été créé dans les 60 dernières secondes (double-clic, soumission
     * concurrente, replay réseau), OU si la référence saisie est déjà utilisée
     * pour ce fournisseur.
     */
    private function assertNoDuplicateRecent(array $data): void
    {
        $supplierId      = (int) ($data['supplier_id'] ?? 0);
        $amount          = (int) ($data['amount']      ?? 0);
        $paymentMethodId = $data['payment_method_id']  ?? null;
        $cashAccountId   = $data['cash_account_id']    ?? null;
        $reference       = trim((string) ($data['reference'] ?? ''));
        $forceDuplicate  = (bool) ($data['force_duplicate'] ?? false);

        if ($supplierId <= 0 || $amount <= 0) return;

        $exists = SupplierPayment::where('supplier_id', $supplierId)
            ->where('amount', $amount)
            ->where('payment_method_id', $paymentMethodId)
            ->where('cash_account_id', $cashAccountId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($exists) {
            throw new \RuntimeException(sprintf(
                "Un décaissement identique vient d'être enregistré il y a quelques secondes : %s (%s FCFA). "
                . "Attendez 1 minute ou modifiez un attribut (méthode, référence…) pour confirmer un second décaissement légitime.",
                $exists->number,
                number_format($exists->amount, 0, ',', ' ')
            ));
        }

        // [DUP-24H-GUARD] Doublon métier sur 24h pour même fournisseur + même montant
        if (!$forceDuplicate) {
            $sameAmountToday = SupplierPayment::where('supplier_id', $supplierId)
                ->where('amount', $amount)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
            if ($sameAmountToday > 0) {
                $unpaidSum = (int) SupplierInvoice::where('supplier_id', $supplierId)
                    ->whereIn('status', ['validee','partiellement_payee','en_retard'])
                    ->whereNull('deleted_at')->sum('remaining_amount');
                throw new \RuntimeException(sprintf(
                    "⚠ Doublon probable : ce fournisseur a déjà reçu %d décaissement(s) de %s FCFA dans les 24h. "
                    . "Total restant à payer au fournisseur : %s FCFA. "
                    . "Pour confirmer ce décaissement supplémentaire, cochez « Forcer le paiement (doublon confirmé) ».",
                    $sameAmountToday,
                    number_format($amount, 0, ',', ' '),
                    number_format($unpaidSum, 0, ',', ' ')
                ));
            }
        }

        // [OVERPAYMENT-GUARD] Refus si le fournisseur a déjà assez de décaissements
        // non imputés pour couvrir tout son passif → empêche les saisies répétées.
        if (!$forceDuplicate) {
            $unallocatedSum = (int) SupplierPayment::where('supplier_id', $supplierId)
                ->whereNull('deleted_at')->sum('unallocated_amount');
            $outstandingSum = (int) SupplierInvoice::where('supplier_id', $supplierId)
                ->whereIn('status', ['validee','partiellement_payee','en_retard'])
                ->whereNull('deleted_at')->sum('remaining_amount');

            if ($unallocatedSum >= $outstandingSum && $outstandingSum >= 0) {
                throw new \RuntimeException(sprintf(
                    "⚠ Doublon structurel : %s FCFA de décaissements non imputés sont déjà disponibles "
                    . "pour ce fournisseur alors qu'il ne reste que %s FCFA à régler. "
                    . "Imputez d'abord les décaissements existants sur ses factures avant d'en saisir un nouveau. "
                    . "Si c'est un véritable nouveau paiement (avance), cochez « Forcer le paiement ».",
                    number_format($unallocatedSum, 0, ',', ' '),
                    number_format($outstandingSum, 0, ',', ' ')
                ));
            }
        }

        if ($reference !== '') {
            $dup = SupplierPayment::where('supplier_id', $supplierId)
                ->where('reference', $reference)
                ->whereNull('deleted_at')
                ->first();
            if ($dup) {
                throw new \RuntimeException(sprintf(
                    "La référence « %s » est déjà utilisée pour ce fournisseur (décaissement %s). "
                    . "Vérifiez qu'il ne s'agit pas d'un doublon — ou ajoutez un suffixe à la référence.",
                    $reference, $dup->number
                ));
            }
        }
    }
}
