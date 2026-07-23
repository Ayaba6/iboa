<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingPeriodLock;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Repositories\JournalEntryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function __construct(
        public readonly JournalEntryRepository $repository,
        private DocumentSequenceService $sequenceService,
    ) {}

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): JournalEntry
    {
        // [COMPTA-PRO-05] Bloquer la création d'un brouillon sur une période verrouillée.
        $this->assertPeriodNotLocked(Auth::user()->company_id, $data['entry_date'] ?? null);

        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $company = Company::findOrFail(Auth::user()->company_id);

            $data['company_id']    = $company->id;
            $data['number']        = $this->sequenceService->nextNumber($company, 'ecriture_comptable');
            $data['created_by']    = Auth::id();
            $data['fiscal_year_id']= $company->current_fiscal_year_id;
            $data['status']        = 'brouillon';

            [$totalDebit, $totalCredit] = $this->calculateTotals($lines);
            $data['total_debit']   = $totalDebit;
            $data['total_credit']  = $totalCredit;

            $entry = JournalEntry::create($data);
            $this->syncLines($entry, $lines);
            $this->recalculate($entry);

            return $entry->fresh();
        });
    }

    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        if (! $entry->isEditable()) {
            throw new \RuntimeException('Cette écriture ne peut plus être modifiée.');
        }

        // [COMPTA-PRO-05] Refuser de modifier une écriture qui retomberait sur un mois verrouillé.
        $this->assertPeriodNotLocked($entry->company_id, $data['entry_date'] ?? $entry->entry_date);

        return DB::transaction(function () use ($entry, $data) {
            $lines = $data['lines'] ?? null;
            unset($data['lines']);

            $entry->update($data);

            if ($lines !== null) {
                $entry->lines()->delete();
                $this->syncLines($entry, $lines);
            }

            $this->recalculate($entry);
            return $entry->fresh();
        });
    }

    /**
     * Validate an entry: set status to 'valide' and update account balances.
     *
     * The status check is performed inside the transaction on a locked row to
     * eliminate the TOCTOU window between the check and the balance updates.
     */
    public function validate(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            // Lock the entry row first — prevents concurrent double-validation
            // which would double-increment account balances.
            $entry = JournalEntry::lockForUpdate()->findOrFail($entry->id);

            // [SEC-PHASE2 §2] Maker-checker : l'auteur d'une écriture manuelle ne la poste pas seul
            app(MakerCheckerService::class)->assert($entry->created_by, 'journal_entry.validate', "l'écriture {$entry->number}", $entry);

            if ($entry->status !== 'brouillon') {
                throw new \RuntimeException('Seules les écritures en brouillon peuvent être validées.');
            }

            // [Maquette X3] Une écriture de simulation n'impacte jamais les soldes.
            if ($entry->is_simulation) {
                throw new \RuntimeException('Écriture de simulation — décochez le flag Simulation avant validation.');
            }

            // [FIX-JOURNAL-01] Refuse to validate against a closed or archived fiscal year.
            if ($entry->fiscal_year_id) {
                $fy = \App\Models\FiscalYear::find($entry->fiscal_year_id);
                if ($fy && $fy->status !== 'ouvert') {
                    throw new \RuntimeException(
                        'Impossible de valider une écriture sur un exercice ' . $fy->status
                        . ' (« ' . $fy->label . ' »). Rouvrez l\'exercice ou déplacez l\'écriture.'
                    );
                }
            }

            // [COMPTA-PRO-05] Refuser de valider sur une période mensuelle verrouillée.
            $this->assertPeriodNotLocked($entry->company_id, $entry->entry_date);

            $entry->load('lines');

            // Une écriture sans ligne (ou à 0) est « équilibrée » au sens 0 = 0 :
            // sans ce guard elle se valide et pollue le journal (pièce vide).
            if ($entry->lines->isEmpty() || ((float) $entry->lines->sum('debit') <= 0 && (float) $entry->lines->sum('credit') <= 0)) {
                throw new \RuntimeException('Écriture sans ligne ou à montant nul — ajoutez des lignes avant de valider.');
            }

            if (! $entry->isBalanced()) {
                throw new \RuntimeException('L\'écriture n\'est pas équilibrée (débit ≠ crédit).');
            }

            // Update account balances
            foreach ($entry->lines as $line) {
                Account::where('id', $line->account_id)->increment('debit_balance', $line->debit);
                Account::where('id', $line->account_id)->increment('credit_balance', $line->credit);
            }

            $entry->update([
                'status'       => 'valide',
                'validated_at' => now(),
                'validated_by' => Auth::id(),
            ]);

            // [PERF] Invalider le cache des rapports financiers (bilan / CDR)
            // Les clés fin_report_* et fin_cumul_* sont taguées par company_id.
            // Comme file-cache ne supporte pas les tags, on flush par pattern.
            $companyId = $entry->company_id;
            $this->flushFinancialReportCache($companyId);

            return $entry->fresh();
        });
    }

    /**
     * Invalide tous les caches de rapports financiers pour une société.
     * Appelé lors de la validation ou annulation d'une écriture.
     */
    public function flushFinancialReportCache(int $companyId): void
    {
        // Les clés utilisées par loadAccountsWithMovements et loadCumulativeAccounts
        $prefixGroups = [
            ['1%', '2%', '3%', '4%', '5%'],
            ['6%', '7%'],
            ['1%', '2%', '3%', '4%', '5%', '6%', '7%', '8%'],
        ];
        foreach ($prefixGroups as $prefixes) {
            $key = 'fin_cumul_' . $companyId . '_' . implode('', $prefixes);
            \Illuminate\Support\Facades\Cache::forget($key);
        }
        // Pour fin_report_*, on ne peut pas flush par pattern avec file cache.
        // En production (Redis + tags), utiliser Cache::tags(['fin_report_' . $companyId])->flush()
        // Pour l'instant, on accepte que le cache expira naturellement en 10min.
    }

    public function delete(JournalEntry $entry): bool
    {
        if (! $entry->isEditable()) {
            throw new \RuntimeException(sprintf(
                'L\'écriture %s est %s — la suppression est interdite par la réglementation comptable. '
                . 'Utilisez une contre-passation pour annuler ses effets.',
                $entry->number,
                $entry->status
            ));
        }
        // [COMPTA-PRO-05] Refuser la suppression sur une période verrouillée.
        $this->assertPeriodNotLocked($entry->company_id, $entry->entry_date);
        return $entry->delete();
    }

    /**
     * [COMPTA-PRO-05] Helper de garde : lève une exception si le mois de la date
     * est verrouillé pour la société. Utilisé par create/update/validate/delete.
     */
    private function assertPeriodNotLocked(int $companyId, $date): void
    {
        if (!$date) return;
        $carbon = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable((string) $date);
        $lock   = AccountingPeriodLock::findForDate($companyId, $carbon);
        if ($lock) {
            throw new \RuntimeException(sprintf(
                "La période « %s » est verrouillée comptablement (par %s le %s). "
                . "Aucune modification ou validation n'est possible sur ce mois. "
                . "Déverrouillez la période ou déplacez l'écriture.",
                $lock->label(),
                $lock->lockedBy?->name ?? 'système',
                $lock->locked_at?->format('d/m/Y') ?? '?'
            ));
        }
    }

    /**
     * Contre-passation : crée une nouvelle écriture qui inverse les débits/crédits.
     * L'écriture d'origine reste, marquée 'annule'. Conforme SYSCOA / Plan Comptable Général.
     *
     * @throws \RuntimeException si l'écriture est déjà annulée ou en brouillon
     */
    public function reverse(JournalEntry $entry, string $reason): JournalEntry
    {
        if ($entry->status !== 'valide') {
            throw new \RuntimeException(
                'Seules les écritures validées peuvent être contre-passées. '
                . 'Pour un brouillon, utilisez la suppression directe.'
            );
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($entry, $reason) {
            return app(\App\Services\AccountingService::class)
                ->reverseEntry($entry, 'Contre-passation : ' . $reason);
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function syncLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $i => $line) {
            if (empty($line['account_id'])) {
                continue;
            }

            $entry->lines()->create([
                'journal_entry_id'   => $entry->id,
                'account_id'         => $line['account_id'],
                'label'              => $line['label'] ?? $entry->description,
                'debit'              => (int) ($line['debit'] ?? 0),
                'credit'             => (int) ($line['credit'] ?? 0),
                'due_date'           => $line['due_date'] ?? null,
                'reconciliation_ref' => $line['reconciliation_ref'] ?? null,
                'partner_name'       => $line['partner_name'] ?? null,
                'cost_center'        => $line['cost_center'] ?? null,
                'tax_code'           => $line['tax_code'] ?? null,
                'sort_order'         => $i,
            ]);
        }
    }

    private function calculateTotals(array $lines): array
    {
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit  += (int) ($line['debit']  ?? 0);
            $totalCredit += (int) ($line['credit'] ?? 0);
        }

        return [$totalDebit, $totalCredit];
    }

    private function recalculate(JournalEntry $entry): void
    {
        $entry->load('lines');
        $entry->update([
            'total_debit'  => (int) $entry->lines->sum('debit'),
            'total_credit' => (int) $entry->lines->sum('credit'),
        ]);
    }
}
