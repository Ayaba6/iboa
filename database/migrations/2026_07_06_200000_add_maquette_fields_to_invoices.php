<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Facture de vente : Création] Champs complémentaires :
 * contact, dépôt, mode de prix, projet, liste de prix, représentant/régime fiscal,
 * mode de paiement, banque bénéficiaire, type d'échéance, commercial
 * et informations de livraison (date, transporteur, véhicule, lieu, poids).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()
                  ->constrained('client_contacts')->nullOnDelete();               // contact client
            $table->foreignId('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();                    // entrepôt / dépôt
            $table->string('price_mode', 5)->nullable()->default('ttc');          // ttc | ht
            $table->boolean('net_prices')->default(false);                        // prix nets
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008
            $table->string('price_list', 60)->nullable();                         // Tarif standard 2026
            $table->string('fiscal_representative', 100)->nullable();             // OA METAL INDUSTRIE
            $table->string('payment_method', 30)->nullable();                     // virement | cheque | especes | mobile_money
            $table->string('fiscal_regime', 40)->nullable();                      // Régime réel normal
            $table->string('default_tax_label', 20)->nullable()->default('TVA 18%');
            $table->string('beneficiary_bank', 100)->nullable();                  // Coris Bank International
            $table->string('due_type', 30)->nullable();                           // 30_jours_fin_de_mois
            $table->foreignId('sales_rep_id')->nullable()
                  ->constrained('users')->nullOnDelete();                         // commercial

            // Informations complémentaires livraison
            $table->date('delivery_date')->nullable();
            $table->string('carrier', 80)->nullable();                            // TRANSPORT PLUS
            $table->string('vehicle_number', 30)->nullable();                     // 11 BF 2567
            $table->string('delivery_location', 100)->nullable();                 // Chantier – Kossodo
            $table->decimal('total_weight_kg', 12, 2)->nullable();                // 4 820,000
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropColumn([
                'price_mode', 'net_prices', 'project_reference', 'price_list',
                'fiscal_representative', 'payment_method', 'fiscal_regime', 'default_tax_label',
                'beneficiary_bank', 'due_type',
                'delivery_date', 'carrier', 'vehicle_number', 'delivery_location', 'total_weight_kg',
            ]);
        });
    }
};
