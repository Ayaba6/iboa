<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashClosure;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [TRESO] Clôtures journalières de caisse.
 * create() enregistre le comptage (brouillon). validate() ajuste le solde à la
 * réalité physique + génère l'écriture d'écart le cas échéant.
 */
class CashClosureService
{
    public function __construct(
        private DocumentSequenceService $seq,
        private CashAccountService $cashAccountService,
        private AccountingService $accountingService,
    ) {}

    public function create(array $data): CashClosure
    {
        return DB::transaction(function () use ($data) {
            $company = Company::findOrFail(Auth::user()->company_id);
            $account = CashAccount::findOrFail((int) $data['cash_account_id']);

            $theoretical = (int) $account->current_balance;
            $counted     = (int) $data['counted_balance'];

            $closure = CashClosure::create([
                'company_id'          => $company->id,
                'cash_account_id'     => $account->id,
                'number'              => $this->seq->nextNumber($company, 'cloture_caisse'),
                'closure_date'        => $data['closure_date'] ?? today(),
                'theoretical_balance' => $theoretical,
                'counted_balance'     => $counted,
                'difference'          => $counted - $theoretical,
                'denominations'       => $data['denominations'] ?? null,
                // [Parité SAGE X3]
                'site'                => $data['site'] ?? null,
                'cashier_name'        => $data['cashier_name'] ?? Auth::user()?->name,
                'supervisor_name'     => $data['supervisor_name'] ?? null,
                'currency_code'       => $data['currency_code'] ?? 'XOF',
                'difference_reason'   => $data['difference_reason'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'brouillon',
                'created_by'          => Auth::id(),
            ]);

            return $closure;
        });
    }

    /**
     * Valide la clôture : ajuste le solde caisse au compté + écriture d'écart.
     * @throws \RuntimeException
     */
    public function validateClosure(CashClosure $closure): CashClosure
    {
        return DB::transaction(function () use ($closure) {
            $closure = CashClosure::lockForUpdate()->findOrFail($closure->id);

            if (! $closure->isValidatable()) {
                throw new \RuntimeException('Cette clôture est déjà validée.');
            }
            if ($closure->hasDifference() && trim((string) $closure->difference_reason) === '') {
                throw new \RuntimeException("Un écart est constaté : le motif est obligatoire.");
            }

            $diff = (int) $closure->difference; // compté - théorique

            if ($diff !== 0) {
                $account = CashAccount::findOrFail($closure->cash_account_id);

                // Ajuster le solde opérationnel à la réalité comptée
                $this->cashAccountService->recordTransaction($account, [
                    'type'             => $diff > 0 ? 'credit' : 'debit',
                    'amount'           => abs($diff),
                    'reference_type'   => 'cash_closure',
                    'reference_id'     => $closure->id,
                    'label'            => 'Écart clôture ' . $closure->number,
                    'transaction_date' => $closure->closure_date,
                ]);

                // Écriture comptable d'écart
                $this->accountingService->postCashClosureDifference($closure->fresh('cashAccount'));
            }

            $closure->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            return $closure->fresh(['cashAccount', 'journalEntry', 'validatedBy']);
        });
    }
}
