<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\SupplierInvoiceService;
use Illuminate\Console\Command;

/**
 * [ACHATS #5/#9] Worker de course : un processus OS indépendant qui tente de
 * créer une facture fournisseur. Utilisé par scripts/purchase-idem-race.sh pour
 * lancer deux processus concurrents sur le même fournisseur + numéro (+ clé).
 * Émet UNE ligne JSON sur stdout : {worker, outcome, invoice_id|error}.
 *
 * Barrière de synchronisation : attend l'horodatage `barrier_ms` (ms epoch) pour
 * que les deux processus frappent quasi simultanément.
 */
class PurchaseIdemRaceWorker extends Command
{
    protected $signature = 'a3:purchase-idem-race-worker {worker} {supplier} {number} {key} {barrier_ms}';

    protected $description = 'Worker interne de la course concurrente facture fournisseur (tests).';

    public function handle(): int
    {
        $worker    = (int) $this->argument('worker');
        $supplier  = (int) $this->argument('supplier');
        $number    = (string) $this->argument('number');
        $key       = (string) $this->argument('key');
        $barrierMs = (int) $this->argument('barrier_ms');

        app()->instance('current_company', Company::query()->firstOrFail());

        // Barrière : busy-wait jusqu'à l'instant convenu.
        while ((int) (microtime(true) * 1000) < $barrierMs) {
            usleep(200);
        }

        $out = ['worker' => $worker];
        try {
            $inv = app(SupplierInvoiceService::class)->create([
                'supplier_id'             => $supplier,
                'supplier_invoice_number' => $number,
                'currency_code'           => 'XOF',
                'received_at'             => now()->toDateString(),
                'due_at'                  => now()->addDays(30)->toDateString(),
                '_idempotency_key'        => $key !== '-' ? $key : null,
                '_source'                 => 'race',
                'items'                   => [],
            ]);
            $out['outcome']    = 'created';
            $out['invoice_id'] = $inv->id;
        } catch (\Throwable $e) {
            // Erreur métier CONTRÔLÉE attendue (doublon / rejeu), jamais une
            // exception SQL brute non gérée.
            $out['outcome'] = 'rejected';
            $out['error']   = mb_substr($e->getMessage(), 0, 120);
            $out['class']   = class_basename($e);
        }

        $this->line(json_encode($out));

        return self::SUCCESS;
    }
}
