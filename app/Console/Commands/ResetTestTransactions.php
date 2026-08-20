<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remise à zéro du transactionnel de test, paramétrage conservé.
 *
 * Cette commande remplace `db:reset-transactional`, qui procédait par
 * `SET FOREIGN_KEY_CHECKS = 0` puis `TRUNCATE`. Cette approche est refusée ici
 * pour trois raisons, chacune suffisante :
 *
 *   - désactiver les contraintes revient à demander au moteur de ne plus
 *     vérifier ce qu'on est précisément en train de risquer ; une table oubliée
 *     dans la liste produit alors des orphelins SILENCIEUX au lieu d'une erreur ;
 *   - `TRUNCATE` est du DDL : il valide implicitement la transaction en cours,
 *     donc aucun `ROLLBACK` n'est possible après coup ;
 *   - la liste de `db:reset-transactional` est incomplète — ni les bons de
 *     préparation, ni aucune des quatorze tables de production, ni les
 *     réservations, ni les validations commerciales n'y figurent.
 *
 * Ici : suppressions ordonnées, `DELETE` avec clause, contraintes ACTIVES d'un
 * bout à l'autre, le tout dans une transaction unique. Si l'ordre est faux, la
 * base refuse et la transaction est annulée. C'est le comportement voulu : une
 * erreur bruyante vaut mieux qu'un orphelin muet.
 *
 * PRINCIPE DE CLASSEMENT — une table non classée n'est JAMAIS supprimée. Elle
 * est signalée au rapport. Le défaut sûr est de conserver, pas de purger.
 */
class ResetTestTransactions extends Command
{
    protected $signature = 'a3:reset-test-transactions
                            {--audit : Produit le rapport sans rien modifier (défaut)}
                            {--json= : Chemin du rapport JSON}
                            {--csv= : Chemin du rapport CSV}
                            {--execute : Exécute réellement la remise à zéro}
                            {--confirmation= : Jeton obtenu en mode audit}
                            {--full : Active tout le périmètre et toutes les options}
                            {--include-sales : Ventes — actif par défaut}
                            {--include-purchases : Achats — actif par défaut}
                            {--include-production : Production et qualité — actif par défaut}
                            {--include-stock : Stock — actif par défaut}
                            {--include-accounting : Comptabilité — actif par défaut}
                            {--include-treasury : Trésorerie — actif par défaut}
                            {--include-payroll : Paie et RH transactionnel}
                            {--include-fixed-assets : Immobilisations et amortissements}
                            {--include-crm : CRM}
                            {--include-commercial-contracts : Contrats commerciaux}
                            {--delete-orphans : Supprime les orphelins de paramétrage}
                            {--delete-test-masters : Supprime les tiers de test}
                            {--delete-test-articles : Supprime les articles exclusivement de test}
                            {--delete-test-fiscal-year : Supprime l’exercice de test s’il est libre}
                            {--reset-sequences : Repositionne les séquences documentaires}
                            {--reset-stock : Recalcule les stocks depuis les mouvements restants}
                            {--backup-ref= : Empreinte SHA-256 de la sauvegarde préalable, consignée au rapport}';

    protected $description = 'Remise à zéro du transactionnel de test — audit par défaut, exécution sur jeton.';

    /**
     * Étapes de suppression, dans l'ordre imposé par les clés étrangères RÉELLES
     * relevées dans `information_schema`. L'ordre n'est pas une convention : il
     * est dicté par les contraintes `RESTRICT` et `NO ACTION`, qui bloquent la
     * suppression du parent tant que l'enfant existe.
     *
     * Les dépendances les plus contraignantes, celles qui ont dicté cet ordre :
     *
     *   stock_valuation_adjustments  RESTRICT → stock_movements, production_orders
     *   purchase_quality_decisions   RESTRICT → receptions
     *   credit_note_item_lot_returns RESTRICT → stock_lots
     *   delivery_note_item_lot_alloc RESTRICT → stock_lots
     *   sales_pickings               NO ACTION → orders
     *   client_payment_allocations   NO ACTION → invoices, credit_notes
     *   supplier_payment_allocations NO ACTION → supplier_invoices
     *   cash_closures, cash_transfers NO ACTION → journal_entries
     */
    private const ETAPES = [
        'Paie et RH transactionnel' => [
            'payroll_payments',
            'payroll_declarations',
            'payroll_items',
            'payroll_numberings',
            'payroll_variables',
            'payroll_runs',
            'payroll_periods',
            'attendances',
            'leave_balances',
            'leave_requests',
            'employee_allowances',
            'employee_loan_payments',
            'employee_loans',
            'employee_departures',
            'employee_documents',
            'employee_contracts',
        ],

        'Immobilisations' => [
            'fixed_asset_depreciations',
            'fixed_assets',
        ],

        'CRM' => [
            'crm_activities',
            'crm_opportunities',
            'crm_contacts',
        ],

        'Contrats commerciaux' => [
            'commercial_contract_items',
            'commercial_contracts',
        ],

        'Qualité et production aval' => [
            // RESTRICT vers production_orders ET stock_movements : part en premier.
            'stock_valuation_adjustments',
            'quality_inspection_characteristics',
            'quality_inspections',
            'non_conformity_characteristics',
            'non_conformities',
            'quality_certificates',
            'quality_releases',
            'production_quality_controls',
            'production_outputs',
            'production_consumptions',
            'production_order_operations',
            'production_order_lines',
            'production_batches',
            'production_costs',
            'production_time_logs',
            'production_trackings',
            'production_wastes',
            'production_downtimes',
            'production_orders',
        ],

        'Trésorerie' => [
            'client_payment_allocations',
            'supplier_payment_allocations',
            'bank_deposit_items',
            'bank_deposits',
            'bank_statement_lines',
            'bank_reconciliations',
            'commercial_effects',
            'payment_requests',
            'payment_promises',
            'payment_schedules',
            'client_payment_schedules',
            'cash_flow_forecast_lines',
            'cash_flow_forecasts',
            'cash_closures',
            'cash_operations',
            'cash_transfers',
            'cash_transactions',
            'client_payments',
            'supplier_payments',
        ],

        'Comptabilité' => [
            'analytic_lines',
            'accounting_period_locks',
            'vat_declaration_items',
            'vat_declarations',
            'journal_entry_lines',
            'journal_entries',
        ],

        'Ventes' => [
            'sales_picking_allocations',
            'sales_pickings',
            'sales_validation_logs',
            'commercial_validations',
            'litigation_cases',
            'credit_note_item_lot_returns',
            'credit_note_items',
            'credit_notes',
            'invoice_items',
            'invoices',
            'delivery_note_item_lot_allocations',
            'delivery_note_items',
            'delivery_notes',
            'bon_preparations',
            'stock_reservations',
            'order_items',
            'orders',
            'quote_items',
            'quotes',
        ],

        'Achats' => [
            'supplier_return_items',
            'supplier_returns',
            'supplier_invoice_items',
            'supplier_invoices',
            'purchase_quality_decision_allocations',
            'purchase_quality_decisions',
            'reception_items',
            'receptions',
            'rfq_quote_items',
            'rfq_quotes',
            'rfq_suppliers',
            'rfq_items',
            'rfqs',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
        ],

        'Stock' => [
            'cutting_optimizations',
            'manual_stock_movements',
            'stock_losses',
            'inventory_items',
            'inventory_sessions',
            'stock_transfer_items',
            'stock_transfers',
            'maintenance_parts',
            'coils',
            'stock_movements',
            'stock_lots',
        ],

        'Historique métier' => [
            'document_archives',
            'document_sequence_audits',
            'edit_locks',
        ],
    ];

    /**
     * Option qui commande chaque étape, et si elle est active SANS option.
     *
     * Les six domaines du cœur transactionnel sont actifs par défaut : c'est le
     * sens même de `reset-test-transactions`, et leur option existe pour que la
     * ligne de commande puisse rester explicite. Les quatre domaines annexes
     * sont en opt-in, parce qu'ils touchent des données dont le caractère de
     * test ne se lit pas dans le document lui-même.
     */
    private const OPTION_PAR_ETAPE = [
        'Paie et RH transactionnel'  => ['include-payroll', false],
        'Immobilisations'            => ['include-fixed-assets', false],
        'CRM'                        => ['include-crm', false],
        'Contrats commerciaux'       => ['include-commercial-contracts', false],
        'Qualité et production aval' => ['include-production', true],
        'Trésorerie'                 => ['include-treasury', true],
        'Comptabilité'               => ['include-accounting', true],
        'Ventes'                     => ['include-sales', true],
        'Achats'                     => ['include-purchases', true],
        'Stock'                      => ['include-stock', true],
        'Historique métier'          => [null, true],
    ];

    /** Options activées en bloc par `--full`. */
    private const OPTIONS_FULL = [
        'include-sales', 'include-purchases', 'include-production', 'include-stock',
        'include-accounting', 'include-treasury', 'include-payroll',
        'include-fixed-assets', 'include-crm', 'include-commercial-contracts',
        'delete-orphans', 'delete-test-masters', 'delete-test-articles',
        'delete-test-fiscal-year', 'reset-sequences', 'reset-stock',
    ];

    /**
     * Orphelins de PARAMÉTRAGE : lignes dont le parent n'existe pas, et que la
     * purge transactionnelle ne fait donc pas disparaître. Traités à part, sur
     * `--delete-orphans`, et jamais confondus avec des relations valides.
     */
    private const ORPHELINS_PARAMETRAGE = [
        ['category_warehouse', 'product_family_id', 'product_families', 'id'],
        ['payroll_items', 'payroll_run_id', 'payroll_runs', 'id'],
    ];

    /**
     * Stocks calculés : recalculés depuis les mouvements restants, jamais mis à
     * zéro par un `UPDATE` aveugle. Sans mouvement, le recalcul DONNE zéro — la
     * nuance compte, parce qu'un recalcul se vérifie et qu'une mise à zéro se
     * croit.
     *
     * `product_warehouse` et `category_warehouse` ne figurent PAS ici malgré
     * leur nom : elles ne portent aucune quantité, seulement les autorisations
     * `can_production` / `can_sale` / `can_purchase` / `can_stock`. Ce sont des
     * tables de paramétrage, et elles sont protégées à ce titre.
     */
    private const AGREGATS = ['product_stocks'];

    /** Marqueurs de test. Un marqueur n'est qu'un CANDIDAT, jamais une preuve. */
    private const MARQUEURS = [
        'TEST', 'TEST-GUIDE', 'CLIENT TEST', 'CLIENT TEST GUIDE',
        'CLT-TEST', 'EMP-TEST', 'DEMO', 'QA', 'MTO-TEST', 'A3-SALES-TEST',
    ];

    /**
     * Marqueurs propres aux EXERCICES. Les marqueurs généraux ne conviennent
     * pas : un exercice de mise au point s'appelle « DBG », pas « TEST », et
     * inversement « DBG » n'a rien à faire dans la détection des tiers.
     */
    private const MARQUEURS_EXERCICE = ['TEST', 'DEMO', 'QA', 'DBG', 'DEBUG'];

    /** Référentiels de test : table => colonnes portant un marqueur. */
    private const MASTERS = [
        'clients'   => ['code', 'name'],
        'suppliers' => ['code', 'name'],
        'employees' => ['matricule', 'last_name', 'first_name'],
        'products'  => ['reference', 'name'],
    ];

    /**
     * Paramétrage : jamais touché. Cette liste ne sert pas à la suppression —
     * elle sert à la GARDE : si une de ces tables perdait des lignes, la
     * commande échouerait.
     */
    private const PROTEGEES = [
        'companies', 'users', 'roles', 'permissions', 'role_has_permissions',
        'model_has_roles', 'model_has_permissions', 'accounts', 'account_classes',
        'journal_types', 'tax_rates', 'payment_methods', 'payment_terms',
        'currencies', 'units', 'brands', 'product_families', 'item_categories',
        'warehouses', 'warehouse_locations', 'cash_accounts',
        'company_bank_accounts', 'bills_of_materials', 'bom_lines', 'routings',
        'routing_operations', 'production_machines', 'work_centers',
        'production_lines', 'cost_centers', 'document_sequences',
        'migrations',
        // Autorisations article/dépôt et famille/dépôt — aucune quantité.
        'product_warehouse', 'category_warehouse',
    ];

    private array $rapport = [];

    /**
     * Lignes retirées d'une table PROTÉGÉE, avec leur justification.
     *
     * La garde finale refuse toute perte non déclarée sur le paramétrage. Or
     * deux traitements légitimes en retirent des lignes : la suppression des
     * orphelins, et celle des articles de test avec leurs autorisations de
     * dépôt. Plutôt que d'assouplir la garde — ce qui la rendrait aveugle au
     * reste — chaque perte est DÉCLARÉE ici et déduite du décompte attendu.
     */
    private array $pertesDeclarees = [];

    public function handle(): int
    {
        if (! $this->garderLaBase()) {
            return self::FAILURE;
        }

        $this->rapport = $this->construireRapport();
        $hash = $this->hachage($this->rapport);

        $this->afficherRapport($this->rapport, $hash);
        $this->exporter($this->rapport, $hash);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->line('  <fg=gray>Mode audit — aucune donnée modifiée.</>');
            $this->line("  Jeton d'exécution : <fg=yellow>{$this->prefixeJeton()}{$hash}</>");

            return self::SUCCESS;
        }

        return $this->executer($hash);
    }

    /**
     * Refus fail-closed si la base n'est pas celle attendue. La mission porte
     * sur `iboa_erp` ; l'exécuter par mégarde ailleurs — une base de test
     * parallèle, une copie de production — n'aurait aucun moyen d'être défait.
     */
    private function garderLaBase(): bool
    {
        $base = DB::connection()->getDatabaseName();

        if (! self::baseAutorisee($base)) {
            $this->error("Refus : base « {$base} » hors périmètre autorisé.");
            $this->line('  Autorisé : iboa_erp, ou toute base portant test/testing/qa/ci.');

            return false;
        }

        $this->line("  Base : <fg=cyan>{$base}</>");

        return true;
    }

    /**
     * Règle de périmètre, isolée en fonction pure pour être éprouvée sans
     * toucher à la connexion du processus de test. Une garde qu'on ne peut
     * vérifier qu'en cassant la connexion est une garde qu'on ne vérifie pas.
     *
     * Les bases de CONTRÔLE DE RESTAURATION sont admises : elles n'existent que
     * pour éprouver un dump et le reset qui suivra, avant de toucher à la base
     * réelle. Les refuser interdirait la seule répétition générale qui vaille.
     *
     * `iboa_erp_profils`, `iboa_erp_prod` ou une copie datée restent refusées :
     * rien n'y indique qu'elles soient jetables.
     */
    public static function baseAutorisee(string $base): bool
    {
        return $base === 'iboa_erp'
            || self::baseDeTest($base)
            || (bool) preg_match('/(^|_)restore_check(_|$)/i', $base);
    }

    /**
     * Base jetable, recréée par les suites : son nom porte un marqueur
     * explicite. Rien n'y est à sauvegarder.
     */
    public static function baseDeTest(string $base): bool
    {
        return (bool) preg_match('/(^|_)(test|testing|qa|ci)(_|$)/i', $base);
    }

    private function baseJetable(): bool
    {
        return self::baseDeTest(DB::connection()->getDatabaseName());
    }

    /**
     * Une base durable exige que l'application soit figée ; une base jetable,
     * non — les suites automatisées ne peuvent pas se mettre elles-mêmes en
     * maintenance, et rien d'applicatif n'écrit dedans pendant leur exécution.
     *
     * Isolée en fonction pure pour être éprouvée sans basculer réellement
     * l'application en maintenance au milieu d'une suite.
     */
    public static function exigeMaintenance(string $base): bool
    {
        return ! self::baseDeTest($base);
    }

    /** `--full` vaut activation de chacune des options qu'il recouvre. */
    private function actif(string $option): bool
    {
        if ($this->option('full') && in_array($option, self::OPTIONS_FULL, true)) {
            return true;
        }

        return (bool) $this->option($option);
    }

    /** Étapes effectivement retenues, selon les options. */
    private function etapes(): array
    {
        $etapes = [];

        foreach (self::ETAPES as $libelle => $tables) {
            [$option, $parDefaut] = self::OPTION_PAR_ETAPE[$libelle] ?? [null, true];

            if ($parDefaut || ($option !== null && $this->actif($option))) {
                $etapes[$libelle] = $tables;
            }
        }

        return $etapes;
    }

    private function construireRapport(): array
    {
        $etapes = [];
        $total = 0;

        foreach ($this->etapes() as $libelle => $tables) {
            $lignes = [];
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $n = (int) DB::table($table)->count();
                if ($n > 0) {
                    $lignes[$table] = $n;
                    $total += $n;
                }
            }
            if ($lignes !== []) {
                $etapes[$libelle] = $lignes;
            }
        }

        return [
            'etapes'      => $etapes,
            'total'       => $total,
            'comptable'   => $this->photoComptable(),
            'masters'     => $this->candidatsMasters(),
            'agregats'    => $this->photoAgregats(),
            'sequences'   => $this->photoSequences(),
            'exercices'   => $this->photoExercices(),
            'non_classes' => $this->tablesNonClassees(),
            'orphelins'   => $this->orphelinsDeParametrage(),
            'articles'    => $this->photoArticlesDeTest(),
            'empreintes'  => $this->empreintesDeFraicheur(),
        ];
    }

    /**
     * Photo comptable AVANT suppression (§10). L'équilibre débit/crédit se
     * constate ici, pas après : une fois les lignes parties, plus rien ne
     * permet de dire si elles étaient équilibrées.
     */
    private function photoComptable(): array
    {
        if (! Schema::hasTable('journal_entry_lines')) {
            return [];
        }

        $t = DB::table('journal_entry_lines')
            ->selectRaw('COUNT(*) n, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
            ->first();

        return [
            'ecritures'  => Schema::hasTable('journal_entries') ? (int) DB::table('journal_entries')->count() : 0,
            'lignes'     => (int) $t->n,
            'debit'      => (string) $t->d,
            'credit'     => (string) $t->c,
            'equilibre'  => bccomp((string) $t->d, (string) $t->c, 2) === 0,
            'validees'   => Schema::hasTable('journal_entries')
                ? (int) DB::table('journal_entries')->where('status', 'valide')->count()
                : 0,
        ];
    }

    /** Candidats référentiels : marqueur ET absence de lien transactionnel. */
    private function candidatsMasters(): array
    {
        $out = [];

        foreach (self::MASTERS as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $q = DB::table($table);
            $q->where(function ($w) use ($colonnes, $table) {
                foreach ($colonnes as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    foreach (self::MARQUEURS as $m) {
                        $w->orWhere($col, 'LIKE', '%'.$m.'%');
                    }
                }
            });

            $lignes = $q->get(['id']);
            if ($lignes->isEmpty()) {
                continue;
            }

            $out[$table] = [
                'candidats' => $lignes->count(),
                'ids'       => $lignes->pluck('id')->all(),
                'total'     => (int) DB::table($table)->count(),
            ];
        }

        return $out;
    }

    private function photoAgregats(): array
    {
        $out = [];
        foreach (self::AGREGATS as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            $col = Schema::hasColumn($t, 'quantity') ? 'quantity'
                : (Schema::hasColumn($t, 'stock') ? 'stock' : null);

            $out[$t] = [
                'lignes'   => (int) DB::table($t)->count(),
                'non_nuls' => $col ? (int) DB::table($t)->where($col, '<>', 0)->count() : null,
            ];
        }

        return $out;
    }

    private function photoSequences(): array
    {
        if (! Schema::hasTable('document_sequences')) {
            return [];
        }

        return DB::table('document_sequences')
            ->where('last_number', '>', 0)
            ->orderBy('document_type')
            ->get(['document_type', 'prefix', 'last_number'])
            ->map(fn ($s) => [
                'type'    => $s->document_type,
                'prefix'  => $s->prefix,
                'actuel'  => (int) $s->last_number,
                'propose' => 0,
            ])->all();
    }

    private function photoExercices(): array
    {
        if (! Schema::hasTable('fiscal_years')) {
            return [];
        }

        return DB::table('fiscal_years')
            ->orderBy('id')
            ->get(['id', 'label', 'status', 'is_current'])
            ->map(fn ($e) => [
                'id'         => (int) $e->id,
                'label'      => $e->label,
                'status'     => $e->status,
                'is_current' => (bool) $e->is_current,
            ])->all();
    }

    /**
     * Tables peuplées qu'AUCUNE liste ne mentionne. Elles ne sont pas
     * supprimées — elles sont montrées. Une purge qui décide seule du sort
     * d'une table qu'on a oublié de classer est une purge qu'on ne relit plus.
     */
    private function tablesNonClassees(): array
    {
        $classees = array_merge(
            ...array_values(self::ETAPES),
            ...[self::AGREGATS, self::PROTEGEES, array_keys(self::MASTERS)]
        );

        $base = DB::connection()->getDatabaseName();
        $toutes = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $base)
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->pluck('TABLE_NAME');

        $out = [];
        foreach ($toutes as $t) {
            if (in_array($t, $classees, true)) {
                continue;
            }
            $n = (int) DB::table($t)->count();
            if ($n > 0) {
                $out[$t] = $n;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Le hachage porte sur ce qui SERA supprimé ET sur les conditions dans
     * lesquelles ce sera supprimé.
     *
     * Un hachage limité aux compteurs laisserait un jeton valide alors même que
     * le code, la base, la sauvegarde de secours ou le périmètre auraient
     * changé — le rapport validé ne décrirait plus l'opération. Y entrent donc
     * aussi le commit exécuté, l'empreinte de la sauvegarde, le nom de la base
     * et les options.
     *
     * Les identifiants exacts comptent autant que les nombres : cinq lignes
     * supprimées ne sont pas cinq AUTRES lignes supprimées.
     */
    private function hachage(array $rapport): string
    {
        return substr(hash('sha256', json_encode([
            'base'       => DB::connection()->getDatabaseName(),
            'code'       => $this->commitExecute(),
            'sauvegarde' => (string) $this->option('backup-ref'),
            'options'    => $this->optionsRetenues(),
            'etapes'     => $rapport['etapes'],
            'masters'    => $rapport['masters'],
            'orphelins'  => $rapport['orphelins'],
            'articles'   => $rapport['articles'],
            'sequences'  => $rapport['sequences'],
            'exercices'  => $rapport['exercices'],
            'empreintes' => $rapport['empreintes'],
            'total'      => $rapport['total'],
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    private function afficherRapport(array $r, string $hash): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>RAPPORT DE REMISE À ZÉRO — TRANSACTIONNEL DE TEST</>');
        $this->newLine();

        foreach ($r['etapes'] as $libelle => $tables) {
            $this->line("  <fg=yellow>{$libelle}</>");
            foreach ($tables as $t => $n) {
                $this->line(sprintf('    %-42s %6d', $t, $n));
            }
            $this->newLine();
        }

        $this->line(sprintf('  <fg=green>Total à supprimer : %d lignes</>', $r['total']));
        $this->newLine();

        if ($r['comptable'] !== []) {
            $c = $r['comptable'];
            $this->line('  <fg=yellow>Comptabilité — photo avant suppression</>');
            $this->line(sprintf('    écritures %d (dont %d validées), lignes %d', $c['ecritures'], $c['validees'], $c['lignes']));
            $this->line(sprintf('    débit %s / crédit %s — %s', $c['debit'], $c['credit'],
                $c['equilibre'] ? 'ÉQUILIBRÉ' : 'DÉSÉQUILIBRÉ'));
            $this->newLine();
        }

        if ($r['masters'] !== []) {
            $this->line('  <fg=yellow>Référentiels de test — candidats par marqueur</>');
            foreach ($r['masters'] as $t => $m) {
                $this->line(sprintf('    %-22s %d candidat(s) sur %d', $t, $m['candidats'], $m['total']));
            }
            $this->line('    <fg=gray>Un marqueur est un candidat. Le lien transactionnel est vérifié à l\'exécution.</>');
            $this->newLine();
        }

        if ($r['articles'] !== []) {
            $this->line('  <fg=yellow>Articles de test — dépendances exclusives</>');
            foreach ($r['articles'] as $a) {
                $this->line(sprintf('    %-20s %d nomenclature(s), %d gamme(s) — %s',
                    $a['reference'], $a['nomenclatures'], $a['gammes'],
                    $a['partagee'] ?? 'dédiées, suppressibles'));
            }
            $this->newLine();
        }

        if ($r['orphelins'] !== []) {
            $this->line('  <fg=yellow>Orphelins de paramétrage — liste exacte</>');
            $this->line(sprintf('    %-24s %-6s %-22s %-22s %s', 'TABLE', 'ID', 'COLONNE', 'PARENT ATTENDU', 'ACTION'));
            foreach ($r['orphelins'] as $o) {
                $this->line(sprintf('    %-24s %-6d %-22s %-22s %s',
                    $o['table'], $o['id'], $o['colonne'].' = '.$o['valeur'], $o['parent'], 'suppression'));
            }
            $this->newLine();
        }

        if ($r['non_classes'] !== []) {
            $this->line('  <fg=red>ZONES GRISES — tables peuplées non classées, NON supprimées</>');
            foreach ($r['non_classes'] as $t => $n) {
                $this->line(sprintf('    %-42s %6d', $t, $n));
            }
            $this->line('    <fg=gray>À arbitrer avant de les inclure. Le défaut est de conserver.</>');
            $this->newLine();
        }

        $this->line('  <fg=yellow>Exercices comptables</>');
        foreach ($r['exercices'] as $e) {
            $this->line(sprintf('    id %-3d %-8s %-10s %s', $e['id'], $e['label'], $e['status'],
                $e['is_current'] ? '← courant' : ''));
        }
        $this->newLine();

        if ($r['sequences'] !== []) {
            $this->line('  <fg=yellow>Séquences documentaires en avance</>');
            foreach ($r['sequences'] as $s) {
                $this->line(sprintf('    %-24s %-8s %d → %d', $s['type'], $s['prefix'], $s['actuel'], $s['propose']));
            }
            $this->newLine();
        }

        $this->line("  Empreinte du rapport : <fg=cyan>{$hash}</>");
    }

    private function exporter(array $r, string $hash): void
    {
        if ($chemin = $this->option('json')) {
            file_put_contents($chemin, json_encode(
                ['hash' => $hash, 'genere_le' => now()->toIso8601String()] + $r,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ));
            $this->line("  JSON : {$chemin}");
        }

        if ($chemin = $this->option('csv')) {
            $fh = fopen($chemin, 'w');
            fputcsv($fh, ['etape', 'table', 'lignes']);
            foreach ($r['etapes'] as $libelle => $tables) {
                foreach ($tables as $t => $n) {
                    fputcsv($fh, [$libelle, $t, $n]);
                }
            }
            foreach ($r['non_classes'] as $t => $n) {
                fputcsv($fh, ['ZONE GRISE (non supprimée)', $t, $n]);
            }
            fclose($fh);
            $this->line("  CSV  : {$chemin}");
        }
    }

    /**
     * Le préfixe du jeton dit le PÉRIMÈTRE, pas seulement l'empreinte. Un jeton
     * complet ne peut donc pas être présenté à une commande partielle, ni
     * l'inverse — même si les deux rapports avaient par accident le même hash.
     */
    private function prefixeJeton(): string
    {
        return $this->option('full') ? 'RESET-A3-FULL-TEST-' : 'RESET-A3-TEST-';
    }

    private function executer(string $hash): int
    {
        $attendu = $this->prefixeJeton().$hash;

        if ($this->option('confirmation') !== $attendu) {
            $this->newLine();
            $this->error('Refus : jeton de confirmation absent ou périmé.');
            $this->line("  Attendu : {$attendu}");
            $this->line('  Un jeton périmé signifie que les données ont changé depuis l\'audit.');

            return self::FAILURE;
        }

        $avant = $this->photoProtegees();

        $this->newLine();
        $this->line('  <fg=yellow>Contrôles préalables à la première suppression</>');

        // Le moteur est INTERROGÉ, et non supposé. Une session héritant d'un
        // `foreign_key_checks = 0` posé ailleurs — un import, un script — ferait
        // passer les suppressions sans vérifier une seule contrainte, et les
        // orphelins n'apparaîtraient qu'après coup.
        $fk = (int) DB::selectOne('SELECT @@SESSION.foreign_key_checks AS c')->c;
        if ($fk !== 1) {
            $this->error("Refus : foreign_key_checks vaut {$fk}. Les contraintes doivent rester actives.");

            return self::FAILURE;
        }
        // L'application doit être figée : entre l'audit et le COMMIT, une
        // requête applicative pourrait créer une pièce que le rapport validé ne
        // mentionne pas, et qui serait supprimée sans avoir été présentée.
        if (self::exigeMaintenance(DB::connection()->getDatabaseName()) && ! app()->isDownForMaintenance()) {
            $this->newLine();
            $this->error('Refus : application non figée. Lancez `php artisan down` avant le reset.');
            $this->line('  Une écriture concurrente créerait une pièce absente du rapport validé.');

            return self::FAILURE;
        }

        $this->line('    foreign_key_checks           = 1');
        $this->line('    base                         = '.DB::connection()->getDatabaseName());
        $this->line('    code exécuté                 = '.$this->commitExecute());
        $this->line('    empreinte du rapport         = '.$hash);
        $this->line('    sauvegarde référencée        = '.($this->option('backup-ref') ?: '<fg=red>AUCUNE</>'));
        $this->line('    application figée            = '.($this->baseJetable() ? 'base jetable, garde levée' : 'oui'));

        // Exigée sur une base durable seulement. Une base jetable — celle des
        // suites, recréée à chaque exécution — n'a rien à restaurer, et
        // réclamer une sauvegarde y serait un rite sans objet.
        if (! $this->option('backup-ref') && ! $this->baseJetable()) {
            $this->newLine();
            $this->error('Refus : aucune sauvegarde référencée (--backup-ref=<sha256>).');
            $this->line('  Une suppression sans sauvegarde identifiée ne peut pas être défaite.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=yellow>Exécution — contraintes de clés étrangères ACTIVES.</>');

        try {
            DB::transaction(function () use ($avant, $hash) {
                // Second calcul, À L'INTÉRIEUR de la transaction. Le premier a
                // été fait hors verrou : une écriture concurrente pouvait se
                // glisser entre les deux. Ici la transaction est ouverte, et le
                // rapport est confronté une dernière fois aux données avant la
                // moindre suppression.
                $verrouille = $this->hachage($this->construireRapport());

                if ($verrouille !== $hash) {
                    throw new \RuntimeException(
                        "RESET REFUSÉ : le rapport a changé entre l'audit et l'ouverture de la "
                        ."transaction ({$hash} → {$verrouille}). Une écriture concurrente a eu lieu ; "
                        .'relancez l’audit.'
                    );
                }

                $this->supprimerTransactionnel();

                if ($this->actif('delete-orphans')) {
                    $this->supprimerOrphelins();
                }

                if ($this->actif('delete-test-articles')) {
                    $this->supprimerArticlesDeTest();
                }

                if ($this->actif('delete-test-masters')) {
                    $this->supprimerMasters();
                }

                if ($this->actif('delete-test-fiscal-year')) {
                    $this->traiterExerciceDeTest();
                }

                // Systématique, et non optionnel : les mouvements de caisse
                // viennent d'être supprimés, et le solde qu'ils justifiaient ne
                // peut pas leur survivre.
                $this->recalculerTresorerie();

                if ($this->actif('reset-stock')) {
                    $this->recalculerStocks();
                }

                if ($this->actif('reset-sequences')) {
                    $this->reinitialiserSequences();
                }

                $this->verifierProtegees($avant);
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('ROLLBACK — aucune donnée supprimée.');
            $this->line('  '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('  Remise à zéro terminée.');
        $this->controlesFinaux();

        return self::SUCCESS;
    }

    private function supprimerTransactionnel(): void
    {
        foreach ($this->etapes() as $libelle => $tables) {
            $this->line("  <fg=yellow>{$libelle}</>");
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                // `whereRaw('1=1')` : un DELETE porte toujours une clause.
                $n = DB::table($table)->whereRaw('1 = 1')->delete();
                if ($n > 0) {
                    $this->line(sprintf('    %-42s %6d supprimée(s)', $table, $n));
                }
            }
        }
    }

    /**
     * Un référentiel n'est supprimé que si PLUS AUCUNE ligne ne le référence.
     * La vérification interroge le schéma réel plutôt qu'une liste écrite à la
     * main, qui vieillirait à la première migration.
     */
    private function supprimerMasters(): void
    {
        $this->line('  <fg=yellow>Référentiels de test</>');
        $base = DB::connection()->getDatabaseName();

        foreach (self::MASTERS as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $candidats = $this->idsCandidats($table, $colonnes);
            if ($candidats === []) {
                continue;
            }

            $referents = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $base)
                ->where('REFERENCED_TABLE_NAME', $table)
                ->get(['TABLE_NAME', 'COLUMN_NAME']);

            foreach ($candidats as $id) {
                // Dépendances propres au tiers — contacts, adresses, conditions
                // particulières. Elles sont en CASCADE : la base les détruirait
                // de toute façon. Les retirer explicitement les rend VISIBLES au
                // rapport au lieu de les faire disparaître en silence.
                $this->supprimerDependancesCascade($table, $id, $base);

                $liens = [];
                foreach ($referents as $r) {
                    if (! Schema::hasTable($r->TABLE_NAME)) {
                        continue;
                    }
                    $n = (int) DB::table($r->TABLE_NAME)->where($r->COLUMN_NAME, $id)->count();
                    if ($n > 0) {
                        $liens[] = "{$r->TABLE_NAME}.{$r->COLUMN_NAME} ({$n})";
                    }
                }

                if ($liens !== []) {
                    // Conservé, et dit. Un article de démonstration encore
                    // référencé se désactive, il ne se supprime pas.
                    $this->line(sprintf('    %-22s id %-5d CONSERVÉ — %s', $table, $id, implode(', ', $liens)));

                    continue;
                }

                DB::table($table)->where('id', $id)->delete();
                $this->line(sprintf('    %-22s id %-5d supprimé', $table, $id));
            }
        }
    }

    private function idsCandidats(string $table, array $colonnes): array
    {
        return DB::table($table)
            ->where(function ($w) use ($colonnes, $table) {
                foreach ($colonnes as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    foreach (self::MARQUEURS as $m) {
                        $w->orWhere($col, 'LIKE', '%'.$m.'%');
                    }
                }
            })
            ->pluck('id')->all();
    }

    /**
     * Commit réellement exécuté. Sans lui, un jeton obtenu avec une version de
     * la commande resterait valide pour une autre — or c'est le CODE qui décide
     * de ce qui est supprimé, pas seulement le rapport.
     *
     * `git` peut être absent d'un environnement d'exécution ; le repli sur
     * l'empreinte du fichier source remplit la même fonction, et ne dépend de
     * rien.
     */
    private function commitExecute(): string
    {
        $chemin = (string) (new \ReflectionClass($this))->getFileName();
        $empreinteSource = is_file($chemin) ? hash_file('sha256', $chemin) : 'source-illisible';

        $tete = @exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD 2>&1', $sortie, $code);

        return ($code === 0 && preg_match('/^[0-9a-f]{40}$/', (string) $tete))
            ? $tete.':'.substr($empreinteSource, 0, 16)
            : 'sans-git:'.substr($empreinteSource, 0, 16);
    }

    /** Options qui changent le périmètre — figées dans l'empreinte. */
    private function optionsRetenues(): array
    {
        $retenues = ['full' => (bool) $this->option('full')];
        foreach (self::OPTIONS_FULL as $option) {
            $retenues[$option] = $this->actif($option);
        }
        ksort($retenues);

        return $retenues;
    }

    /**
     * Marque de fraîcheur par table : dernière modification connue et plus
     * grand identifiant. Une ligne modifiée SANS changement de compte — un
     * montant corrigé, un statut basculé — laisse les compteurs identiques ;
     * `MAX(updated_at)` la trahit.
     */
    private function empreintesDeFraicheur(): array
    {
        $out = [];

        foreach ($this->etapes() as $tables) {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $ligne = ['max_id' => (int) DB::table($table)->max('id')];

                if (Schema::hasColumn($table, 'updated_at')) {
                    $ligne['maj'] = (string) DB::table($table)->max('updated_at');
                }

                if ($ligne['max_id'] > 0) {
                    $out[$table] = $ligne;
                }
            }
        }

        ksort($out);

        return $out;
    }

    /** Articles de test et dépendances qui partiraient avec eux. */
    private function photoArticlesDeTest(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $out = [];
        foreach ($this->idsCandidats('products', self::MASTERS['products']) as $id) {
            $nomenclatures = Schema::hasTable('bills_of_materials')
                ? DB::table('bills_of_materials')->where('product_id', $id)->pluck('id')->all() : [];

            $out[] = [
                'id'             => $id,
                'reference'      => DB::table('products')->where('id', $id)->value('reference'),
                'nomenclatures'  => count($nomenclatures),
                'gammes'         => Schema::hasTable('routings')
                    ? (int) DB::table('routings')->where('product_id', $id)->count() : 0,
                'partagee'       => $this->nomenclaturePartagee($nomenclatures, $id),
            ];
        }

        return $out;
    }

    /** Exercices dont le libellé porte un marqueur de mise au point. */
    private function exercicesDeTest(): array
    {
        if (! Schema::hasTable('fiscal_years')) {
            return [];
        }

        return DB::table('fiscal_years')
            ->where(function ($w) {
                foreach (self::MARQUEURS_EXERCICE as $m) {
                    $w->orWhere('label', 'LIKE', '%'.$m.'%');
                }
            })
            ->pluck('id')->all();
    }

    /** Lignes dont la clé étrangère désigne un parent qui n'existe pas. */
    private function orphelinsDeParametrage(): array
    {
        $out = [];

        foreach (self::ORPHELINS_PARAMETRAGE as [$table, $colonne, $parent, $cle]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parent)) {
                continue;
            }

            $lignes = DB::table($table.' as c')
                ->leftJoin($parent.' as p', "p.{$cle}", '=', "c.{$colonne}")
                ->whereNotNull("c.{$colonne}")
                ->whereNull("p.{$cle}")
                ->get(["c.id", "c.{$colonne} as valeur"]);

            foreach ($lignes as $l) {
                $out[] = [
                    'table'   => $table,
                    'id'      => (int) $l->id,
                    'colonne' => $colonne,
                    'parent'  => $parent,
                    'valeur'  => $l->valeur,
                ];
            }
        }

        return $out;
    }

    /**
     * Suppression ligne à ligne, par identifiant relevé. Un `DELETE` portant
     * sur la condition d'orphelinat emporterait aussi les lignes devenues
     * orphelines entre le relevé et l'exécution — ici, seul ce qui a été
     * inventorié et présenté est supprimé.
     */
    private function supprimerOrphelins(): void
    {
        $orphelins = $this->orphelinsDeParametrage();

        if ($orphelins === []) {
            return;
        }

        $this->line('  <fg=yellow>Orphelins de paramétrage</>');

        foreach ($orphelins as $o) {
            DB::table($o['table'])->where('id', $o['id'])->delete();
            $this->declarerPerte($o['table'], 1);
            $this->line(sprintf('    %-24s id %-5d %s = %s introuvable dans %s',
                $o['table'], $o['id'], $o['colonne'], $o['valeur'], $o['parent']));
        }
    }

    /**
     * Retire les lignes qui dépendent du tiers par une contrainte `CASCADE` :
     * contacts, adresses, conditions particulières. Ce sont des données QUI
     * N'EXISTENT QUE PAR LUI ; la base les détruirait d'elle-même à la
     * suppression du parent. Les retirer explicitement les fait apparaître au
     * rapport, et permet à la garde de lien de ne plus voir qu'elles.
     */
    private function supprimerDependancesCascade(string $table, int $id, string $base): void
    {
        $cascades = DB::table('information_schema.KEY_COLUMN_USAGE as k')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as r', function ($j) {
                $j->on('r.CONSTRAINT_SCHEMA', '=', 'k.CONSTRAINT_SCHEMA')
                    ->on('r.CONSTRAINT_NAME', '=', 'k.CONSTRAINT_NAME');
            })
            ->where('k.TABLE_SCHEMA', $base)
            ->where('k.REFERENCED_TABLE_NAME', $table)
            ->where('r.DELETE_RULE', 'CASCADE')
            ->get(['k.TABLE_NAME', 'k.COLUMN_NAME']);

        foreach ($cascades as $c) {
            if (! Schema::hasTable($c->TABLE_NAME)) {
                continue;
            }

            $n = DB::table($c->TABLE_NAME)->where($c->COLUMN_NAME, $id)->delete();

            if ($n > 0) {
                $this->declarerPerte($c->TABLE_NAME, $n);
                $this->line(sprintf('      dépendance %-30s %d ligne(s)', $c->TABLE_NAME, $n));
            }
        }
    }

    private function declarerPerte(string $table, int $lignes): void
    {
        $this->pertesDeclarees[$table] = ($this->pertesDeclarees[$table] ?? 0) + $lignes;
    }

    /**
     * Articles exclusivement de test, avec leurs dépendances EXCLUSIVES.
     *
     * Une nomenclature ou une gamme n'est retirée que si elle est dédiée à
     * l'article : `product_id` pointe sur lui, et rien d'autre ne s'en sert. Si
     * elle est partagée avec un article réel, elle reste, et l'article ne peut
     * pas partir — la commande le dit plutôt que de trancher.
     */
    private function supprimerArticlesDeTest(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->line('  <fg=yellow>Articles exclusivement de test</>');
        $base = DB::connection()->getDatabaseName();

        foreach ($this->idsCandidats('products', self::MASTERS['products']) as $id) {
            $reference = DB::table('products')->where('id', $id)->value('reference');

            // Dépendances exclusives, retirées d'abord.
            $nomenclatures = Schema::hasTable('bills_of_materials')
                ? DB::table('bills_of_materials')->where('product_id', $id)->pluck('id')->all() : [];
            $gammes = Schema::hasTable('routings')
                ? DB::table('routings')->where('product_id', $id)->pluck('id')->all() : [];

            $partagee = $this->nomenclaturePartagee($nomenclatures, $id);
            if ($partagee !== null) {
                $this->line(sprintf('    %-20s CONSERVÉ — %s', $reference, $partagee));

                continue;
            }

            // Nomenclatures et gammes sont du PARAMÉTRAGE protégé : chaque
            // ligne retirée doit être déclarée, sinon la garde finale annule
            // tout — ce qu'elle a effectivement fait à la première tentative.
            foreach ($nomenclatures as $bom) {
                $this->declarerPerte('bom_lines', DB::table('bom_lines')->where('bill_of_material_id', $bom)->delete());
                $this->declarerPerte('bills_of_materials', DB::table('bills_of_materials')->where('id', $bom)->delete());
            }
            foreach ($gammes as $gamme) {
                $this->declarerPerte('routing_operations', DB::table('routing_operations')->where('routing_id', $gamme)->delete());
                $this->declarerPerte('routings', DB::table('routings')->where('id', $gamme)->delete());
            }

            foreach (['product_stocks', 'product_warehouse', 'product_sites', 'product_attribute_values'] as $t) {
                if (Schema::hasTable($t)) {
                    $this->declarerPerte($t, DB::table($t)->where('product_id', $id)->delete());
                }
            }

            $liens = $this->liensRestants('products', $id, $base);
            if ($liens !== []) {
                throw new \RuntimeException(
                    "Suppression de l'article {$reference} refusée : encore référencé par "
                    .implode(', ', $liens).'. La transaction est annulée.'
                );
            }

            DB::table('products')->where('id', $id)->delete();
            $this->line(sprintf('    %-20s supprimé — %d nomenclature(s), %d gamme(s)',
                $reference, count($nomenclatures), count($gammes)));
        }
    }

    /**
     * Rend la raison du partage, ou `null` si toutes les nomenclatures de
     * l'article lui sont bien dédiées.
     */
    private function nomenclaturePartagee(array $nomenclatures, int $articleId): ?string
    {
        if (! Schema::hasTable('bom_lines')) {
            return null;
        }

        // L'article sert-il de COMPOSANT dans une nomenclature d'autrui ?
        $utilise = DB::table('bom_lines as bl')
            ->join('bills_of_materials as b', 'b.id', '=', 'bl.bill_of_material_id')
            ->where('b.product_id', '<>', $articleId)
            ->where(fn ($w) => $w->where('bl.product_id', $articleId)
                ->orWhere('bl.substitute_product_id', $articleId))
            ->count();

        if ($utilise > 0) {
            return "composant de {$utilise} nomenclature(s) d'un autre article";
        }

        return null;
    }

    /** Tables et colonnes qui référencent encore une ligne donnée. */
    private function liensRestants(string $table, int $id, string $base): array
    {
        $liens = [];

        $referents = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $base)
            ->where('REFERENCED_TABLE_NAME', $table)
            ->get(['TABLE_NAME', 'COLUMN_NAME']);

        foreach ($referents as $r) {
            if (! Schema::hasTable($r->TABLE_NAME)) {
                continue;
            }
            $n = (int) DB::table($r->TABLE_NAME)->where($r->COLUMN_NAME, $id)->count();
            if ($n > 0) {
                $liens[] = "{$r->TABLE_NAME}.{$r->COLUMN_NAME} ({$n})";
            }
        }

        return $liens;
    }

    /**
     * L'exercice de test perd d'abord `is_current`, puis n'est supprimé que
     * s'il est libre de toute référence. L'exercice réel reste courant : il
     * n'est jamais touché.
     */
    private function traiterExerciceDeTest(): void
    {
        if (! Schema::hasTable('fiscal_years')) {
            return;
        }

        $this->line('  <fg=yellow>Exercices comptables</>');
        $base = DB::connection()->getDatabaseName();

        foreach ($this->exercicesDeTest() as $id) {
            DB::table('fiscal_years')->where('id', $id)->update(['is_current' => false]);

            $label = DB::table('fiscal_years')->where('id', $id)->value('label');
            $liens = $this->liensRestants('fiscal_years', $id, $base);

            if ($liens !== []) {
                $this->line(sprintf('    %-10s is_current = 0, CONSERVÉ — %s', $label, implode(', ', $liens)));

                continue;
            }

            DB::table('fiscal_years')->where('id', $id)->delete();
            $this->line(sprintf('    %-10s is_current = 0 puis supprimé', $label));
        }

        $courants = (int) DB::table('fiscal_years')->where('is_current', true)->count();
        if ($courants !== 1) {
            // Deux défauts distincts, deux messages : « ambigu » ne veut rien
            // dire quand il n'en reste aucun.
            throw new \RuntimeException($courants === 0
                ? 'Aucun exercice courant après traitement : les documents à venir n’auraient '
                  .'plus d’exercice de rattachement. Désignez-en un avant de relancer.'
                : "Exercice courant ambigu : {$courants} exercices portent is_current = 1. "
                  .'Un seul est admis.'
            );
        }
    }

    /**
     * Soldes de trésorerie, reconstruits depuis les mouvements subsistants.
     *
     * `cash_accounts.current_balance` est un agrégat dénormalisé : supprimer les
     * mouvements sans le refaire laisse un solde que plus rien ne justifie. Le
     * défaut a été constaté après la première remise à zéro — la caisse
     * principale annonçait 136 280 F pour 35 400 F de mouvements réels, et la
     * banque 5 020 F sans le moindre mouvement. Trois écrans affichaient ces
     * chiffres sans broncher : un solde faux ne se signale pas tout seul.
     *
     * Ce recalcul n'est pas optionnel. Supprimer un mouvement en laissant le
     * solde qu'il justifiait n'est jamais un résultat souhaitable, et le rendre
     * facultatif reviendrait à proposer de laisser la caisse fausse.
     *
     * `balance_after` est réaligné dans la foulée : ce cumul figé sur chaque
     * ligne sert au rapprochement, et il hérite du solde qui régnait à
     * l'écriture — donc du faux, si on ne le refait pas.
     */
    private function recalculerTresorerie(): void
    {
        if (! Schema::hasTable('cash_accounts') || ! Schema::hasTable('cash_transactions')) {
            return;
        }

        $this->line('  <fg=yellow>Trésorerie — recalcul des soldes depuis les mouvements restants</>');

        foreach (DB::table('cash_accounts')->orderBy('id')->get() as $compte) {
            $solde = (float) $compte->opening_balance;
            $lignes = 0;

            // Ordre chronologique, `id` en départage : `balance_after` est un
            // cumul, il n'a de sens que dans la séquence où les mouvements se
            // sont produits.
            foreach (DB::table('cash_transactions')
                ->where('cash_account_id', $compte->id)
                ->orderBy('transaction_date')->orderBy('id')
                ->get(['id', 'type', 'amount']) as $mouvement) {
                $solde += $mouvement->type === 'credit'
                    ? (float) $mouvement->amount
                    : -(float) $mouvement->amount;

                DB::table('cash_transactions')->where('id', $mouvement->id)
                    ->update(['balance_after' => $solde]);
                $lignes++;
            }

            $ancien = (float) $compte->current_balance;

            if (abs($ancien - $solde) >= 0.01) {
                DB::table('cash_accounts')->where('id', $compte->id)
                    ->update(['current_balance' => $solde]);

                $this->line(sprintf('    %-16s %14s → %14s  (%d mouvement(s))',
                    $compte->code,
                    number_format($ancien, 0, ',', ' '),
                    number_format($solde, 0, ',', ' '),
                    $lignes));
            }
        }
    }

    /**
     * Recalcul, pas remise à zéro. La quantité est reconstruite depuis les
     * mouvements SUBSISTANTS : sans mouvement, elle vaut zéro, et c'est un
     * résultat vérifiable plutôt qu'une valeur imposée.
     */
    private function recalculerStocks(): void
    {
        $this->line('  <fg=yellow>Stocks — recalcul depuis les mouvements restants</>');

        if (! Schema::hasTable('stock_movements') || ! Schema::hasTable('product_stocks')) {
            return;
        }

        $restants = (int) DB::table('stock_movements')->count();

        if ($restants === 0) {
            $n = DB::table('product_stocks')->whereRaw('1 = 1')->update([
                'quantity'          => 0,
                'reserved_quantity' => Schema::hasColumn('product_stocks', 'reserved_quantity') ? 0 : DB::raw('reserved_quantity'),
            ]);
            $this->line(sprintf('    product_stocks : %d ligne(s) recalculée(s) à 0 (aucun mouvement subsistant)', $n));

            return;
        }

        // Des mouvements subsistent : la quantité de chaque couple
        // article/dépôt est reconstruite depuis eux, signe compris.
        $recalculees = 0;
        foreach (DB::table('product_stocks')->get(['id', 'product_id', 'warehouse_id']) as $ligne) {
            $q = DB::table('stock_movements')
                ->where('product_id', $ligne->product_id)
                ->where('warehouse_id', $ligne->warehouse_id)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'entree' THEN quantity WHEN type = 'sortie' THEN -quantity ELSE quantity END), 0) q")
                ->value('q');

            $recalculees += DB::table('product_stocks')->where('id', $ligne->id)
                ->update(['quantity' => $q, 'reserved_quantity' => 0]);
        }

        $this->line(sprintf('    product_stocks : %d ligne(s) recalculée(s) depuis %d mouvement(s)', $recalculees, $restants));
    }

    private function reinitialiserSequences(): void
    {
        if (! Schema::hasTable('document_sequences')) {
            return;
        }

        $n = DB::table('document_sequences')->where('last_number', '>', 0)->update(['last_number' => 0]);
        $this->line("  <fg=yellow>Séquences</> : {$n} repositionnée(s) — prochain numéro = 1");
    }

    private function photoProtegees(): array
    {
        $out = [];
        foreach (self::PROTEGEES as $t) {
            if (Schema::hasTable($t)) {
                $out[$t] = (int) DB::table($t)->count();
            }
        }

        return $out;
    }

    /**
     * Garde de dernière ligne : si le paramétrage a perdu ne serait-ce qu'une
     * ligne, la transaction entière est annulée. Une cascade oubliée se
     * manifeste ici, avant le COMMIT.
     */
    private function verifierProtegees(array $avant): void
    {
        foreach ($avant as $table => $initial) {
            $attendu = $initial - ($this->pertesDeclarees[$table] ?? 0);
            $constate = (int) DB::table($table)->count();

            if ($constate !== $attendu) {
                throw new \RuntimeException(
                    "Paramétrage entamé : {$table} est passée de {$initial} à {$constate} ligne(s), "
                    ."alors que {$attendu} était attendu. Une cascade non prévue a atteint une "
                    .'table protégée.'
                );
            }
        }
    }

    private function controlesFinaux(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>Contrôles post-remise à zéro</>');

        $restes = [];
        foreach ($this->etapes() as $tables) {
            foreach ($tables as $t) {
                if (Schema::hasTable($t) && ($n = (int) DB::table($t)->count()) > 0) {
                    $restes[$t] = $n;
                }
            }
        }

        if ($restes === []) {
            $this->line('    Toutes les tables transactionnelles sont vides.');
        } else {
            foreach ($restes as $t => $n) {
                $this->line(sprintf('    <fg=red>RESTE</> %-38s %d', $t, $n));
            }
        }

        foreach (['clients', 'suppliers', 'products', 'users', 'accounts', 'warehouses'] as $t) {
            if (Schema::hasTable($t)) {
                $this->line(sprintf('    %-38s %d conservée(s)', $t, (int) DB::table($t)->count()));
            }
        }
    }
}
