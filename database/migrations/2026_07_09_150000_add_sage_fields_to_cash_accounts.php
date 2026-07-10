<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Compte de trésorerie] Champs descriptifs complémentaires :
 * classification (groupe/catégorie/compte général), identification bancaire
 * étendue (pays, codes banque/guichet, clé RIB), paramètres financiers
 * (découvert, plafonds, génération des écritures), options de relevé et
 * prévisions de trésorerie. Métadonnées — aucun impact sur les soldes ni sur
 * les transactions de caisse existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $t) {
            if (!Schema::hasColumn('cash_accounts', 'account_group'))        $t->string('account_group', 60)->nullable()->after('type');
            if (!Schema::hasColumn('cash_accounts', 'category'))             $t->string('category', 40)->nullable()->after('account_group');
            if (!Schema::hasColumn('cash_accounts', 'general_account'))      $t->string('general_account', 20)->nullable()->after('category');
            if (!Schema::hasColumn('cash_accounts', 'site'))                 $t->string('site', 40)->nullable()->after('general_account');
            if (!Schema::hasColumn('cash_accounts', 'manager_name'))         $t->string('manager_name', 100)->nullable()->after('site');
            if (!Schema::hasColumn('cash_accounts', 'description'))          $t->string('description', 500)->nullable()->after('manager_name');
            if (!Schema::hasColumn('cash_accounts', 'country_code'))         $t->string('country_code', 2)->nullable()->default('BF')->after('bank_branch');
            if (!Schema::hasColumn('cash_accounts', 'bank_code'))            $t->string('bank_code', 20)->nullable()->after('country_code');
            if (!Schema::hasColumn('cash_accounts', 'branch_code'))          $t->string('branch_code', 20)->nullable()->after('bank_code');
            if (!Schema::hasColumn('cash_accounts', 'rib_key'))              $t->string('rib_key', 4)->nullable()->after('branch_code');
            if (!Schema::hasColumn('cash_accounts', 'overdraft_limit'))      $t->integer('overdraft_limit')->default(0)->after('min_balance');
            if (!Schema::hasColumn('cash_accounts', 'overdraft_currency'))   $t->string('overdraft_currency', 3)->nullable()->default('XOF')->after('overdraft_limit');
            if (!Schema::hasColumn('cash_accounts', 'transaction_ceiling'))  $t->integer('transaction_ceiling')->default(0)->after('overdraft_currency');
            if (!Schema::hasColumn('cash_accounts', 'operation_ceiling'))    $t->integer('operation_ceiling')->default(0)->after('transaction_ceiling');
            if (!Schema::hasColumn('cash_accounts', 'entry_generation'))     $t->string('entry_generation', 20)->nullable()->default('automatique')->after('operation_ceiling');
            if (!Schema::hasColumn('cash_accounts', 'include_in_forecast'))  $t->boolean('include_in_forecast')->default(true)->after('entry_generation');
            if (!Schema::hasColumn('cash_accounts', 'is_regularization'))    $t->boolean('is_regularization')->default(false)->after('include_in_forecast');
            if (!Schema::hasColumn('cash_accounts', 'opened_at'))            $t->date('opened_at')->nullable()->after('is_regularization');
            if (!Schema::hasColumn('cash_accounts', 'closes_at'))            $t->date('closes_at')->nullable()->after('opened_at');
            if (!Schema::hasColumn('cash_accounts', 'statement_format'))     $t->string('statement_format', 20)->nullable()->default('MT940')->after('closes_at');
            if (!Schema::hasColumn('cash_accounts', 'statement_frequency'))  $t->string('statement_frequency', 20)->nullable()->default('quotidienne')->after('statement_format');
            if (!Schema::hasColumn('cash_accounts', 'last_statement_at'))    $t->date('last_statement_at')->nullable()->after('statement_frequency');
            if (!Schema::hasColumn('cash_accounts', 'forecast_horizon_days')) $t->integer('forecast_horizon_days')->default(90)->after('last_statement_at');
            if (!Schema::hasColumn('cash_accounts', 'forecast_currency'))    $t->string('forecast_currency', 3)->nullable()->default('XOF')->after('forecast_horizon_days');
        });
    }

    public function down(): void
    {
        Schema::table('cash_accounts', function (Blueprint $t) {
            foreach ([
                'account_group', 'category', 'general_account', 'site', 'manager_name', 'description',
                'country_code', 'bank_code', 'branch_code', 'rib_key',
                'overdraft_limit', 'overdraft_currency', 'transaction_ceiling', 'operation_ceiling',
                'entry_generation', 'include_in_forecast', 'is_regularization',
                'opened_at', 'closes_at', 'statement_format', 'statement_frequency', 'last_statement_at',
                'forecast_horizon_days', 'forecast_currency',
            ] as $col) {
                if (Schema::hasColumn('cash_accounts', $col)) $t->dropColumn($col);
            }
        });
    }
};
