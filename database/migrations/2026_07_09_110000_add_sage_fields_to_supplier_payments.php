<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Décaissement fournisseur] Champs descriptifs complémentaires
 * (n° pièce, référence/date valeur bancaire, journal, condition de paiement,
 * imputation analytique, frais bancaires informatifs, objet du paiement).
 *
 * IMPORTANT : métadonnées uniquement. L'allocation aux factures fournisseurs
 * reste calculée sur `amount` et l'écriture comptable (401/521-571) est
 * inchangée. `bank_fees` / `net_amount` sont informatifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $t) {
            if (!Schema::hasColumn('supplier_payments', 'bank_fees'))         $t->integer('bank_fees')->default(0)->after('amount');
            if (!Schema::hasColumn('supplier_payments', 'net_amount'))        $t->integer('net_amount')->nullable()->after('bank_fees');
            if (!Schema::hasColumn('supplier_payments', 'piece_number'))      $t->string('piece_number', 60)->nullable()->after('reference');
            if (!Schema::hasColumn('supplier_payments', 'bank_reference'))    $t->string('bank_reference', 100)->nullable()->after('piece_number');
            if (!Schema::hasColumn('supplier_payments', 'value_date'))        $t->date('value_date')->nullable()->after('payment_date');
            if (!Schema::hasColumn('supplier_payments', 'treasury_journal'))  $t->string('treasury_journal', 20)->nullable()->after('bank_reference');
            if (!Schema::hasColumn('supplier_payments', 'payment_condition')) $t->string('payment_condition', 60)->nullable()->after('treasury_journal');
            if (!Schema::hasColumn('supplier_payments', 'payment_object'))    $t->string('payment_object', 150)->nullable()->after('payment_condition');
            if (!Schema::hasColumn('supplier_payments', 'cost_center'))       $t->string('cost_center', 30)->nullable()->after('payment_object');
            if (!Schema::hasColumn('supplier_payments', 'analytic_section'))  $t->string('analytic_section', 30)->nullable()->after('cost_center');
            if (!Schema::hasColumn('supplier_payments', 'project'))           $t->string('project', 60)->nullable()->after('analytic_section');
            if (!Schema::hasColumn('supplier_payments', 'site'))              $t->string('site', 40)->nullable()->after('project');
            if (!Schema::hasColumn('supplier_payments', 'observations'))      $t->string('observations', 1000)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $t) {
            foreach ([
                'bank_fees', 'net_amount', 'piece_number', 'bank_reference', 'value_date',
                'treasury_journal', 'payment_condition', 'payment_object', 'cost_center',
                'analytic_section', 'project', 'site', 'observations',
            ] as $col) {
                if (Schema::hasColumn('supplier_payments', $col)) $t->dropColumn($col);
            }
        });
    }
};
