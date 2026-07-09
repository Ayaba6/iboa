<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Opération diverse de caisse] Champs descriptifs : site,
 * type d'opération, référence, demandeur, responsable caisse, devise, taux de
 * change, ventilation (frais / net), imputation comptable/analytique
 * (compte général, contrepartie, centre de coût, section), date de valeur,
 * mode de règlement, commentaire + aperçu des lignes comptables (JSON).
 *
 * IMPORTANT : métadonnées. Le mouvement de trésorerie et l'écriture comptable
 * restent générés par CashOperationService à partir de `amount` / `direction`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_operations', function (Blueprint $t) {
            if (!Schema::hasColumn('cash_operations', 'site'))                $t->string('site', 40)->nullable()->after('company_id');
            if (!Schema::hasColumn('cash_operations', 'operation_type'))      $t->string('operation_type', 40)->nullable()->default('operation_diverse')->after('direction');
            if (!Schema::hasColumn('cash_operations', 'reference'))           $t->string('reference', 100)->nullable()->after('number');
            if (!Schema::hasColumn('cash_operations', 'requester'))           $t->string('requester', 100)->nullable()->after('label');
            if (!Schema::hasColumn('cash_operations', 'cashier_name'))        $t->string('cashier_name', 100)->nullable()->after('requester');
            if (!Schema::hasColumn('cash_operations', 'currency_code'))       $t->string('currency_code', 3)->nullable()->default('XOF')->after('cashier_name');
            if (!Schema::hasColumn('cash_operations', 'exchange_rate'))       $t->decimal('exchange_rate', 12, 6)->nullable()->default(1)->after('currency_code');
            if (!Schema::hasColumn('cash_operations', 'fees'))                $t->integer('fees')->default(0)->after('amount');
            if (!Schema::hasColumn('cash_operations', 'net_amount'))          $t->integer('net_amount')->nullable()->after('fees');
            if (!Schema::hasColumn('cash_operations', 'value_date'))          $t->date('value_date')->nullable()->after('operation_date');
            if (!Schema::hasColumn('cash_operations', 'general_account'))     $t->string('general_account', 20)->nullable()->after('value_date');
            if (!Schema::hasColumn('cash_operations', 'counterpart_account')) $t->string('counterpart_account', 20)->nullable()->after('general_account');
            if (!Schema::hasColumn('cash_operations', 'cost_center'))         $t->string('cost_center', 30)->nullable()->after('counterpart_account');
            if (!Schema::hasColumn('cash_operations', 'analytic_section'))    $t->string('analytic_section', 30)->nullable()->after('cost_center');
            if (!Schema::hasColumn('cash_operations', 'payment_method'))      $t->string('payment_method', 40)->nullable()->after('analytic_section');
            if (!Schema::hasColumn('cash_operations', 'comment'))             $t->string('comment', 500)->nullable()->after('payment_method');
            if (!Schema::hasColumn('cash_operations', 'lines'))              $t->json('lines')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('cash_operations', function (Blueprint $t) {
            foreach ([
                'site', 'operation_type', 'reference', 'requester', 'cashier_name', 'currency_code',
                'exchange_rate', 'fees', 'net_amount', 'value_date', 'general_account',
                'counterpart_account', 'cost_center', 'analytic_section', 'payment_method', 'comment', 'lines',
            ] as $col) {
                if (Schema::hasColumn('cash_operations', $col)) $t->dropColumn($col);
            }
        });
    }
};
