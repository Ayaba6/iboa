<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Parité SAGE X3 — Clôture de caisse] Métadonnées d'entête : site, caissier,
 * responsable caisse, devise. Le billetage (denominations JSON) existait déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_closures', function (Blueprint $t) {
            if (! Schema::hasColumn('cash_closures', 'site'))          $t->string('site', 40)->nullable()->after('number');
            if (! Schema::hasColumn('cash_closures', 'cashier_name'))  $t->string('cashier_name', 100)->nullable()->after('site');
            if (! Schema::hasColumn('cash_closures', 'supervisor_name')) $t->string('supervisor_name', 100)->nullable()->after('cashier_name');
            if (! Schema::hasColumn('cash_closures', 'currency_code')) $t->string('currency_code', 3)->nullable()->default('XOF')->after('supervisor_name');
        });
    }

    public function down(): void
    {
        Schema::table('cash_closures', function (Blueprint $t) {
            foreach (['site', 'cashier_name', 'supervisor_name', 'currency_code'] as $col) {
                if (Schema::hasColumn('cash_closures', $col)) $t->dropColumn($col);
            }
        });
    }
};
