<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [ACHATS Réceptions #14] Audit des dispositions de réception — lecture seule.
 * Détecte les situations ambiguës ou incohérentes. Retourne un code d'échec
 * (exit 1) en présence d'anomalie CRITIQUE.
 */
class AuditReceptions extends Command
{
    protected $signature = 'a3:audit-receptions';

    protected $description = 'Audit des dispositions de réception (accepté/quarantaine/refusé, historique, réconciliation).';

    public function handle(): int
    {
        $critical = 0;
        $this->info('── Audit des réceptions ──');

        // 1. Invariant de ventilation sur les lignes CERTIFIED (décision réelle).
        $badInvariant = DB::table('reception_items')
            ->whereNotNull('accepted_quantity')
            ->whereRaw('ABS(COALESCE(accepted_quantity,0) + COALESCE(quarantine_quantity,0) + COALESCE(rejected_quantity,0) - received_quantity) > 0.0001')
            ->count();
        $this->line("1. Ventilation ≠ reçu (lignes ventilées) : {$badInvariant}");
        $critical += $badInvariant;

        // 2. Quantités négatives.
        $negatives = DB::table('reception_items')
            ->where(fn ($q) => $q->where('accepted_quantity', '<', 0)
                ->orWhere('quarantine_quantity', '<', 0)
                ->orWhere('rejected_quantity', '<', 0)
                ->orWhere('received_quantity', '<', 0))
            ->count();
        $this->line("2. Quantités négatives : {$negatives}");
        $critical += $negatives;

        // 3. Disposition INCONNUE (accepté NULL) ayant généré un stock vendable.
        //    (mouvement d'entrée réception pour un produit dont la ligne est non classée)
        $unknownInStock = DB::table('reception_items as ri')
            ->join('stock_movements as sm', function ($j) {
                $j->on('sm.reference_id', '=', 'ri.reception_id')
                  ->on('sm.product_id', '=', 'ri.product_id')
                  ->where('sm.reference_type', '=', 'reception')
                  ->where('sm.type', '=', 'entree');
            })
            ->whereNull('ri.accepted_quantity')
            ->where('ri.disposition_origin', 'legacy_unclassified')
            ->distinct()->count('ri.id');
        $this->line("3. Lignes non classées (accepté NULL) avec entrée de stock : {$unknownInStock}");
        $critical += $unknownInStock;

        // 4. Informatif : lignes reconstruites / non classées (non bloquant).
        $reconstructed = DB::table('reception_items')->whereNotNull('reconstructed_quantity')->count();
        $legacy = DB::table('reception_items')->where('disposition_origin', 'legacy_unclassified')->count();
        $this->line("4. Lignes reconstruites : {$reconstructed} | non classées (historique) : {$legacy} (informatif)");

        if ($critical > 0) {
            $this->error("{$critical} anomalie(s) CRITIQUE(s) de réception. Aucune modification effectuée.");

            return self::FAILURE;
        }
        $this->info('AUDIT RÉCEPTIONS PROPRE — aucune anomalie critique.');

        return self::SUCCESS;
    }
}
