<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [VEN Retour] Retours rebut / remplacement sur avoir.
 *
 *  - credit_note_items.disposition : sort des biens retournés
 *       'restock' (remis en stock vendable, défaut) | 'rebut' (mis au rebut, non remis en stock)
 *  - credit_notes.is_replacement + replacement_delivery_id : avoir avec remplacement (BL lié)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->string('disposition', 20)->default('restock')->after('product_id');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->boolean('is_replacement')->default(false)->after('reason');
            $table->foreignId('replacement_delivery_id')->nullable()->after('is_replacement')
                ->constrained('delivery_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_delivery_id');
            $table->dropColumn('is_replacement');
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropColumn('disposition');
        });
    }
};
