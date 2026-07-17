<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge administrative d'un run de paie DE TEST.
 *
 * Réservée aux données de test identifiées comme telles : un run de paie RÉEL
 * validé ou payé ne doit JAMAIS être supprimé — utiliser un run de
 * régularisation (bulletin rectificatif + écritures d'ajustement).
 *
 * Sécurités :
 *  - motif obligatoire, utilisateur admin obligatoire, confirmation explicite ;
 *  - l'écriture comptable liée n'est extournée/supprimée QUE si sa référence
 *    correspond bien au run (PAIE-{année}-{mois}) — un id recyclé pointant vers
 *    une écriture étrangère est détaché, jamais touché ;
 *  - toute l'opération est transactionnelle et journalisée.
 */
class PurgePayrollRun extends Command
{
    protected $signature = 'paie:purge-run
                            {run : ID du run de paie à purger}
                            {--motif= : Motif de la purge (obligatoire)}
                            {--admin= : ID de l\'utilisateur administrateur qui ordonne la purge (obligatoire)}
                            {--force : Exécuter réellement (sans ce flag : simulation à blanc)}';

    protected $description = 'Purge un run de paie de TEST (paiements, bulletins, écriture) avec journal d\'audit';

    public function handle(): int
    {
        $runId = (int) $this->argument('run');
        $motif = trim((string) $this->option('motif'));
        $adminId = (int) $this->option('admin');
        $force = (bool) $this->option('force');

        if ($motif === '') {
            $this->error('Le motif est obligatoire (--motif="…").');
            return self::FAILURE;
        }

        $admin = User::find($adminId);
        if (! $admin) {
            $this->error('Utilisateur administrateur introuvable (--admin=ID).');
            return self::FAILURE;
        }
        if (method_exists($admin, 'hasRole') && ! $admin->hasRole('super_admin')) {
            $this->error("L'utilisateur #{$adminId} n'a pas le rôle super_admin — purge refusée.");
            return self::FAILURE;
        }

        $run = PayrollRun::withCount('items')->find($runId);
        if (! $run) {
            $this->error("Run de paie #{$runId} introuvable.");
            return self::FAILURE;
        }

        $expectedRef = "PAIE-{$run->period_year}-{$run->period_month}";
        $entry = $run->journal_entry_id ? JournalEntry::find($run->journal_entry_id) : null;
        $entryIsOwn = $entry && $entry->reference === $expectedRef;

        $this->table(['Champ', 'Valeur'], [
            ['Run',            "#{$run->id} — {$run->period_month}/{$run->period_year}"],
            ['Statut',         $run->status],
            ['Bulletins',      $run->items_count],
            ['Paiements',      $run->payments()->count()],
            ['Écriture liée',  $entry ? "#{$entry->id} ({$entry->reference})" : 'aucune'],
            ['Écriture du run', $entry ? ($entryIsOwn ? 'OUI — sera supprimée/extournée' : 'NON — référence étrangère, sera seulement détachée') : '—'],
            ['Motif',          $motif],
            ['Admin',          "#{$admin->id} {$admin->name}"],
        ]);

        if (! $force) {
            $this->warn('Simulation à blanc — relancer avec --force pour exécuter.');
            return self::SUCCESS;
        }

        // En mode non-interactif, --force vaut confirmation (déjà exigé ci-dessus).
        if ($this->input->isInteractive()
            && ! $this->confirm("Purger DÉFINITIVEMENT le run #{$run->id} ? Action irréversible.")) {
            $this->info('Purge annulée.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($run, $entry, $entryIsOwn, $motif, $admin) {
            $deleted = ['payments' => 0, 'items' => 0, 'variables' => 0, 'entry' => null];

            $deleted['payments'] = $run->payments()->delete();
            $deleted['items']    = $run->items()->delete();
            $deleted['variables'] = DB::table('payroll_variables')->where('payroll_run_id', $run->id)->delete();

            if ($entry) {
                // Toujours détacher d'abord : le run disparaît, aucune FK pendante.
                $run->update(['journal_entry_id' => null]);

                if ($entryIsOwn) {
                    if ($entry->status === 'brouillon') {
                        $entry->lines()->delete();
                        $entry->delete();
                        $deleted['entry'] = "écriture #{$entry->id} supprimée (brouillon)";
                    } else {
                        // Écriture validée : extourne (écriture inverse), jamais de delete.
                        $reversal = app(\App\Services\PayrollAccountingService::class);
                        if (method_exists($reversal, 'reverseEntry')) {
                            $reversal->reverseEntry($entry, "Extourne purge paie test — {$motif}");
                            $deleted['entry'] = "écriture #{$entry->id} extournée";
                        } else {
                            $deleted['entry'] = "écriture #{$entry->id} validée — extourne MANUELLE requise";
                        }
                    }
                } else {
                    $deleted['entry'] = "écriture #{$entry->id} étrangère au run (réf. {$entry->reference}) — détachée, non modifiée";
                }
            }

            $run->delete();

            Log::channel('single')->warning('[AUDIT] Purge run de paie de test', [
                'run_id'    => $run->id,
                'periode'   => "{$run->period_month}/{$run->period_year}",
                'statut'    => $run->status,
                'admin_id'  => $admin->id,
                'admin'     => $admin->name,
                'motif'     => $motif,
                'detail'    => $deleted,
                'horodatage'=> now()->toIso8601String(),
            ]);
        });

        $this->info("Run #{$runId} purgé. Détail dans le journal d'audit (storage/logs).");
        return self::SUCCESS;
    }
}
