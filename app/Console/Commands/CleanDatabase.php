<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [QA §6-7] Controlled cleanup of auto-correctable data anomalies.
 * Dry-run is mandatory by default: without --fix, nothing is changed.
 *
 * Scope is intentionally limited to safe corrections from the QA checklist:
 *  - trim leading/trailing spaces on labels and codes;
 *  - replace empty strings with NULL on nullable columns;
 *  - normalize reference codes to uppercase.
 * Never merge duplicates, delete rows, or apply business corrections.
 */
class CleanDatabase extends Command
{
    protected $signature = 'a3:clean-database {--fix : Apply the corrections for real (otherwise dry-run)}';

    protected $description = 'Controlled data cleanup (spaces, empty strings to NULL, code casing) with dry-run by default';

    /** [table, column] pairs for trimming surrounding spaces. */
    private const TRIM = [
        ['clients', 'name'], ['clients', 'trade_name'], ['clients', 'email'],
        ['suppliers', 'name'], ['suppliers', 'email'],
        ['products', 'name'], ['products', 'code_article'], ['products', 'reference'],
        ['product_families', 'name'], ['product_families', 'code'],
        ['item_categories', 'name'], ['item_categories', 'code'],
        ['units', 'name'], ['warehouses', 'name'], ['warehouses', 'code'],
    ];

    /** [table, column] pairs for empty string to NULL normalization. */
    private const EMPTY_TO_NULL = [
        ['clients', 'email'], ['clients', 'phone'], ['clients', 'trade_name'],
        ['suppliers', 'email'], ['suppliers', 'phone'],
        ['products', 'barcode'], ['products', 'designation_2'],
        ['product_families', 'code'], ['product_families', 'description'],
    ];

    /** [table, column] pairs for uppercase code normalization. */
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

        $run = function (string $label, string $table, string $column, $whereFn, $fixFn) use ($fix, &$total) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }

            $count = $whereFn(DB::table($table))->count();
            if ($count === 0) {
                return;
            }

            $total += $count;
            $this->warn("  ! {$table}.{$column} : {$count} ligne(s) - {$label}");

            if ($fix) {
                DB::transaction(fn () => $fixFn($whereFn(DB::table($table))));
                $this->line('    -> corrige');
            }
        };

        $this->line('<comment>-- Espaces parasites --</comment>');
        foreach (self::TRIM as [$table, $column]) {
            $run(
                'espaces debut/fin',
                $table,
                $column,
                fn (Builder $query) => $query->whereNotNull($column)->where(fn (Builder $nested) => $nested
                    ->where($column, 'LIKE', ' %')
                    ->orWhere($column, 'LIKE', '% ')),
                fn (Builder $query) => $query->update([$column => DB::raw('TRIM('.$this->wrapColumn($column).')')])
            );
        }

        $this->line('<comment>-- Chaines vides vers NULL --</comment>');
        foreach (self::EMPTY_TO_NULL as [$table, $column]) {
            $run(
                'vide vers NULL',
                $table,
                $column,
                fn (Builder $query) => $query->where($column, ''),
                fn (Builder $query) => $query->update([$column => null])
            );
        }

        $this->line('<comment>-- Casse des codes (MAJUSCULES) --</comment>');
        foreach (self::UPPERCASE as [$table, $column]) {
            $run(
                'code en minuscules',
                $table,
                $column,
                fn (Builder $query) => $this->uppercaseMismatchQuery($query, $column),
                fn (Builder $query) => $query->update([$column => DB::raw('UPPER('.$this->wrapColumn($column).')')])
            );
        }

        $this->newLine();
        if ($total === 0) {
            $this->info('Rien a nettoyer.');
        } elseif (! $fix) {
            $this->warn("{$total} ligne(s) corrigeable(s). Relancer avec --fix pour appliquer.");
        } else {
            $this->info("{$total} ligne(s) corrigee(s). Relancer a3:audit-database pour confirmer.");
        }

        return self::SUCCESS;
    }

    private function uppercaseMismatchQuery(Builder $query, string $column): Builder
    {
        $wrapped = $this->wrapColumn($column);
        $expression = DB::getDriverName() === 'mysql'
            ? "BINARY {$wrapped} <> BINARY UPPER({$wrapped})"
            : "{$wrapped} != UPPER({$wrapped})";

        return $query->whereNotNull($column)->whereRaw($expression);
    }

    private function wrapColumn(string $column): string
    {
        return DB::getQueryGrammar()->wrap($column);
    }
}