<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Demande de paiement] Champs descriptifs : site, service
 * demandeur, type de demande, référence interne, ventilation financière
 * informative (HT / TVA / frais / net), compte bancaire bénéficiaire,
 * imputation analytique. `amount` reste la source de vérité monétaire du
 * workflow (submit → approve → décaissement) — les autres montants sont
 * des métadonnées de saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $t) {
            if (!Schema::hasColumn('payment_requests', 'site'))               $t->string('site', 40)->nullable()->after('company_id');
            if (!Schema::hasColumn('payment_requests', 'service'))            $t->string('service', 60)->nullable()->after('site');
            if (!Schema::hasColumn('payment_requests', 'request_type'))       $t->string('request_type', 30)->nullable()->default('paiement_fournisseur')->after('service');
            if (!Schema::hasColumn('payment_requests', 'internal_reference')) $t->string('internal_reference', 100)->nullable()->after('number');
            if (!Schema::hasColumn('payment_requests', 'amount_ht'))          $t->integer('amount_ht')->nullable()->after('amount');
            if (!Schema::hasColumn('payment_requests', 'tax_amount'))         $t->integer('tax_amount')->nullable()->after('amount_ht');
            if (!Schema::hasColumn('payment_requests', 'misc_fees'))          $t->integer('misc_fees')->default(0)->after('tax_amount');
            if (!Schema::hasColumn('payment_requests', 'net_amount'))         $t->integer('net_amount')->nullable()->after('misc_fees');
            if (!Schema::hasColumn('payment_requests', 'bank_account'))       $t->string('bank_account', 50)->nullable()->after('net_amount');
            if (!Schema::hasColumn('payment_requests', 'cost_center'))        $t->string('cost_center', 30)->nullable()->after('bank_account');
            if (!Schema::hasColumn('payment_requests', 'analytic_section'))   $t->string('analytic_section', 30)->nullable()->after('cost_center');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $t) {
            foreach ([
                'site', 'service', 'request_type', 'internal_reference',
                'amount_ht', 'tax_amount', 'misc_fees', 'net_amount',
                'bank_account', 'cost_center', 'analytic_section',
            ] as $col) {
                if (Schema::hasColumn('payment_requests', $col)) $t->dropColumn($col);
            }
        });
    }
};
