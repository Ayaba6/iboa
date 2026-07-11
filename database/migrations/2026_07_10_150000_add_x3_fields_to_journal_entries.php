<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette X3 — Nouvelle écriture comptable]
 * Entête : Tiers. Lignes : Tiers / Centre de coût / Code taxe.
 * (Date document = value_date existant ; Référence ligne = reconciliation_ref existant.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('partner_name', 100)->nullable()->after('reference');
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->string('partner_name', 100)->nullable()->after('label');
            $table->string('cost_center', 30)->nullable()->after('partner_name');
            $table->string('tax_code', 10)->nullable()->after('cost_center');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('partner_name');
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['partner_name', 'cost_center', 'tax_code']);
        });
    }
};
