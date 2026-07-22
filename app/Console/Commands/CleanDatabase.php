<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [QA §6-7] Nettoyage CONTRÔLÉ des anomalies de données auto-corrigeables.
 * Dry-run OBLIGATOIRE par défaut : sans --fix, rien n'est modifié.
 *
 * Périmètre volontairement limité aux corrections sûres du cahier (§7) :
 *  - espaces de début/fin sur les libellés et codes ;
 *  - chaînes vides remplacées par NULL sur les colonnes nullables ;
 *  - codes référentiels remis en MAJUSCULES (convention projet).
 * JAMAIS de fusion de doublons, de suppression, ni de correction métier —
 * ces cas sont détectés par a3:audit-database et tranchés par un humain.
 */
class CleanDatabase extends Command
{
    protected $signature = 'a3:clean-database {--fix : Appliquer réellement les corrections (sinon dry-run)}';

    protected $description = 'Nettoyage contrôlé des données (espaces, vides→NULL, casse des codes) — dry-run par défaut';

    /** [table, colonne] : libellés à débarrasser des espaces parasites. */
    private const TRIM = [
        ['clients', 'name'], ['clients', 'trade_name'], ['clients', 'email'],
        ['suppliers', 'name'], ['suppliers', 'email'],
        ['products', 'name'], ['products', 'code_article'], ['products', 'reference'],
        ['product_families', 'name'], ['product_families', 'code'],
        ['item_categories', 'name'], ['item_categories', 'code'],
        ['units', 'name'], ['warehouses', 'name'], ['warehouses', 'code'],
    ];

    /** [table, colonne] : chaîne vide → NULL (colonnes nullables). */
    private const EMPTY_TO_NULL = [
        ['clients', 'email'], ['clients', 'phone'], ['clients', 'trade_name'],
        ['suppliers', 'email'], ['suppliers', 'phone'],
        ['products', 'barcode'], ['products', 'designation_2'],
        ['product_families', 'code'], ['product_families', 'description'],
    ];

    /** [table, colonne] : codes référentiels en MAJUSCULES. */
    private const UPPERCASE = [
        ['products', 'code_article'],
        ['item_categories', 'code'],
        ['product_families', 'code'],
        ['warehouses', 'code'],
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $this->info($fix ? 'Mode CORRECTION' : 'Mode DRY-RUN (aucune modification)');
        $total = 0;

        $run = function (string $label, string $table, string $col, $whereFn, $fixFn) use ($fix, &$total) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                return;
            }
            $n = $whereFn(DB::table($table))->count();
            if ($n === 0) {
                return;
            }
            $total += $n;
            $this->warn("  ⚠ $table.$col : $n ligne(s) — $label");
            if ($fix) {
                DB::transaction(fn () => $fixFn($whereFn(DB::table($table))));
                $this->line('    → corrigé');
            }
        };

        $this->line('<comment>── Espaces parasites ──</comment>');
        foreach (self::TRIM as [$t, $c]) {
            $run('espaces début/fin', $t, $c,
                fn ($q) => $q->whereNotNull($c)->where(fn ($w) => $w->where($c, 'LIKE', ' %')->orWhere($c, 'LIKE', '% ')),
                fn ($q) => $q->update([$c => DB::raw("TRIM($c)")]));
        }

        $this->line('<comment>── Chaînes vides → NULL ──</comment>');
        foreach (self::EMPTY_TO_NULL as [$t, $c]) {
            $run('vide → NULL', $t, $c,
                fn ($q) => $q->where($c, ''),
                fn ($q) => $q->update([$c => null]));
        }

        $this->line('<comment>── Casse des codes (MAJUSCULES) ──</comment>');
        foreach (self::UPPERCASE as [$t, $c]) {
            $run('code en minuscules', $t, $c,
                fn ($q) => $q->whereNotNull($c)->whereRaw("$c != UPPER($c)"),
                fn ($q) => $q->update([$c => DB::raw("UPPER($c)")]));
        }

        $this->newLine();
        if ($total === 0) {
            $this->info('Rien à nettoyer.');
        } elseif (! $fix) {
            $this->warn("$total ligne(s) corrigeable(s). Relancer avec --fix pour appliquer.");
        } else {
            $this->info("$total ligne(s) corrigée(s). Relancer a3:audit-database pour confirmer.");
        }

        return self::SUCCESS;
    }
}
