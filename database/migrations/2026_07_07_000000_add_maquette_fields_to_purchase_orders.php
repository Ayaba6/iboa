<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Commande fournisseur] Champs complémentaires alignés sur les fiches ventes :
 * contact fournisseur, acheteur, mode de prix, liste de prix, paiement, projet
 * et informations de livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('supplier_contact_id')->nullable()
                  ->constrained('supplier_contacts')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()
                  ->constrained('users')->nullOnDelete();                         // acheteur
            $table->string('price_mode', 5)->nullable()->default('ht');           // achats : HT par défaut
            $table->boolean('net_prices')->default(false);
            $table->string('price_list', 60)->nullable();
            $table->string('payment_terms', 100)->nullable();                     // 30 jours
            $table->string('payment_method', 30)->nullable();                     // virement | cheque | especes
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008

            // Livraison
            $table->string('carrier', 80)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('delivery_location', 100)->nullable();
            $table->string('incoterm', 15)->nullable();
            $table->string('priority', 15)->nullable()->default('normale');
            $table->decimal('total_weight_kg', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_contact_id');
            $table->dropConstrainedForeignId('buyer_id');
            $table->dropColumn([
                'price_mode', 'net_prices', 'price_list', 'payment_terms', 'payment_method',
                'project_reference', 'carrier', 'vehicle_number', 'delivery_location',
                'incoterm', 'priority', 'total_weight_kg',
            ]);
        });
    }
};
