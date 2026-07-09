<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Commande client] Champs complémentaires alignés sur les fiches
 * Facture de vente / Nouveau devis : contact, commercial, mode de prix, liste de prix,
 * paiement, fiscal, projet et informations de livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()
                  ->constrained('client_contacts')->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()
                  ->constrained('users')->nullOnDelete();                         // commercial
            $table->string('price_mode', 5)->nullable()->default('ttc');          // ttc | ht
            $table->boolean('net_prices')->default(false);
            $table->string('price_list', 60)->nullable();                         // Tarif standard 2026
            $table->string('payment_terms', 100)->nullable();                     // 30 jours
            $table->string('payment_method', 30)->nullable();                     // virement | cheque | especes | mobile_money
            $table->string('fiscal_representative', 100)->nullable();
            $table->string('fiscal_regime', 40)->nullable();
            $table->string('default_tax_label', 20)->nullable()->default('TVA 18%');
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008

            // Livraison
            $table->string('carrier', 80)->nullable();                            // TRANSPORT PLUS
            $table->string('vehicle_number', 30)->nullable();                     // 11 BF 2567
            $table->string('delivery_location', 100)->nullable();                 // Chantier – Kossodo
            $table->string('incoterm', 15)->nullable();                           // EXW…
            $table->string('priority', 15)->nullable()->default('normale');
            $table->decimal('total_weight_kg', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropColumn([
                'price_mode', 'net_prices', 'price_list', 'payment_terms', 'payment_method',
                'fiscal_representative', 'fiscal_regime', 'default_tax_label', 'project_reference',
                'carrier', 'vehicle_number', 'delivery_location', 'incoterm', 'priority', 'total_weight_kg',
            ]);
        });
    }
};
