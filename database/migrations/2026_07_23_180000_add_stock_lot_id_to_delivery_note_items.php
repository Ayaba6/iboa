<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [DÉCISION 23/07 — BL par lot] Lien FORMEL ligne de BL → lot de stock
// (lot_number texte reste en fallback déclaratif). Additif.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->foreignId('stock_lot_id')->nullable()->after('lot_number')
                ->constrained('stock_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_lot_id');
        });
    }
};
