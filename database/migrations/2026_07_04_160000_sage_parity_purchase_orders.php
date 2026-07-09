<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Bon de commande fournisseur : Création complète » :
 * référence, conditions de règlement, pied de page et dépôt de réception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('reference', 50)->nullable()->after('number');
            $table->text('terms')->nullable()->after('notes');
            $table->text('footer_note')->nullable()->after('terms');
            $table->foreignId('depot_reception_id')->nullable()->after('delivery_address')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_reception_id');
            $table->dropColumn(['reference', 'terms', 'footer_note']);
        });
    }
};
