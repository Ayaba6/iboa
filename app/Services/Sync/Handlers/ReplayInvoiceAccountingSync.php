<?php

namespace App\Services\Sync\Handlers;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\AccountingService;

/**
 * [Sync ERP] Facture de vente validée → écriture comptable SYSCOHADA.
 * Relance après échec — IDEMPOTENT : refuse de créer une deuxième écriture
 * si une écriture référencée sur le numéro de facture existe déjà.
 */
class ReplayInvoiceAccountingSync
{
    public function __construct(private AccountingService $accounting)
    {
    }

    public function __invoke(Invoice $invoice, array $payload = []): void
    {
        if (!in_array($invoice->status, ['emise', 'partiellement_payee', 'payee'], true)) {
            throw new \RuntimeException("Facture {$invoice->number} : relance impossible, statut « {$invoice->status} ».");
        }

        // Idempotence : une écriture existe déjà pour cette facture ?
        $exists = JournalEntry::where('company_id', $invoice->company_id)
            ->where('reference', $invoice->number)
            ->exists();
        if ($exists) {
            return; // déjà comptabilisée — rien à faire
        }

        $this->accounting->postClientInvoice($invoice->fresh(['client', 'company', 'items']));
    }
}
