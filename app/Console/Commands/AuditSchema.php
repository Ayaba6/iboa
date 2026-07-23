<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * [SEC-PHASE2 §9] a3:audit-schema — audit de dérive de schéma, lecture seule,
 * exit 1 si anomalie. Né de la découverte que personal_access_tokens n'avait
 * jamais été migrée alors que l'API en dépendait.
 *
 * Contrôles :
 *  1. Tables d'infrastructure exigées par la CONFIGURATION active
 *     (queue database → jobs/failed_jobs ; cache database → cache ;
 *     session database → sessions ; Sanctum → personal_access_tokens…).
 *  2. Chaque modèle Eloquent concret → sa table existe.
 *  3. Migrations enregistrées sans fichier / fichiers jamais exécutés.
 *  4. Colonnes $fillable des modèles critiques absentes de leur table.
 */
class AuditSchema extends Command
{
    protected $signature = 'a3:audit-schema';
    protected $description = 'Audit de dérive de schéma : config vs tables, modèles vs tables, migrations vs base (lecture seule)';

    private int $anomalies = 0;

    public function handle(): int
    {
        // ── 1. Tables exigées par la configuration active
        $this->info('1. Tables d\'infrastructure selon la configuration');
        $required = [
            ['personal_access_tokens', 'API Sanctum (routes api + tokens)'],
            ['password_reset_tokens', 'réinitialisation de mot de passe'],
            ['audit_logs', 'journal d\'audit'],
        ];
        if (config('queue.default') === 'database') {
            $required[] = ['jobs', 'queue database'];
            $required[] = ['failed_jobs', 'jobs échoués'];
            $required[] = ['job_batches', 'lots de jobs'];
        }
        if (config('cache.default') === 'database') {
            $required[] = ['cache', 'cache database'];
            $required[] = ['cache_locks', 'verrous de cache'];
        }
        if (config('session.driver') === 'database') {
            $required[] = ['sessions', 'sessions database'];
        }
        foreach ($required as [$table, $why]) {
            if (! Schema::hasTable($table)) {
                $this->fail_("table « $table » ABSENTE alors que la configuration l'exige ($why)");
            }
        }

        // ── 2. Chaque modèle Eloquent → table existante
        $this->info('2. Modèles Eloquent vs tables');
        foreach ($this->concreteModels() as $class) {
            try {
                $model = new $class();
                $table = $model->getTable();
                if (! Schema::hasTable($table)) {
                    $this->fail_("modèle $class → table « $table » ABSENTE");
                }
            } catch (\Throwable $e) {
                // modèle non instanciable sans contexte : ignoré
            }
        }

        // ── 3. Migrations : enregistrées sans fichier / jamais exécutées
        $this->info('3. Migrations vs table migrations');
        $files = collect(File::files(database_path('migrations')))
            ->map(fn ($f) => str_replace('.php', '', $f->getFilename()));
        $ran = collect(DB::table('migrations')->pluck('migration'));
        foreach ($ran->diff($files) as $ghost) {
            $this->fail_("migration enregistrée SANS fichier : $ghost");
        }
        foreach ($files->diff($ran) as $pending) {
            $this->fail_("migration jamais exécutée : $pending");
        }

        // ── 4. Colonnes fillable des modèles critiques
        $this->info('4. Colonnes $fillable vs colonnes réelles (modèles financiers)');
        $critical = [
            \App\Models\Invoice::class, \App\Models\Order::class, \App\Models\Quote::class,
            \App\Models\ClientPayment::class, \App\Models\SupplierPayment::class,
            \App\Models\CreditNote::class, \App\Models\PurchaseOrder::class,
            \App\Models\Reception::class, \App\Models\JournalEntry::class,
            \App\Models\StockMovement::class, \App\Models\PayrollRun::class,
        ];
        foreach ($critical as $class) {
            $model = new $class();
            $cols = Schema::getColumnListing($model->getTable());
            foreach ($model->getFillable() as $f) {
                if (! in_array($f, $cols, true)) {
                    $this->fail_("$class : fillable « $f » absent de la table {$model->getTable()}");
                }
            }
        }

        $this->newLine();
        if ($this->anomalies === 0) {
            $this->info('AUDIT SCHÉMA PROPRE — aucune dérive détectée.');

            return self::SUCCESS;
        }
        $this->error("{$this->anomalies} dérive(s) de schéma — voir ci-dessus. Aucune modification effectuée.");

        return self::FAILURE;
    }

    private function fail_(string $msg): void
    {
        $this->anomalies++;
        $this->warn('  ✗ ' . $msg);
    }

    /** @return array<class-string> */
    private function concreteModels(): array
    {
        $out = [];
        foreach ([app_path('Models'), app_path('Modules')] as $base) {
            if (! is_dir($base)) {
                continue;
            }
            foreach (File::allFiles($base) as $file) {
                if (! str_contains($file->getPathname(), 'Models')) {
                    continue;
                }
                $class = str_replace([app_path(), '/', '\\\\', '.php'], ['App', '\\', '\\', ''], $file->getPathname());
                $class = str_replace('/', '\\', $class);
                if (class_exists($class)
                    && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)
                    && ! (new \ReflectionClass($class))->isAbstract()) {
                    $out[] = $class;
                }
            }
        }

        return $out;
    }
}
