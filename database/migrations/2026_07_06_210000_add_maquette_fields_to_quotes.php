<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Nouveau devis] Champs complémentaires :
 * contact, dépôt, commercial, adresse de livraison, durée de validité, projet,
 * liste de prix, mode de prix, paiement, fiscal — et informations complémentaires
 * (source, origine, priorité, livraison souhaitée, lieu, incoterm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()
                  ->constrained('client_contacts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();                    // entrepôt / dépôt
            $table->foreignId('sales_rep_id')->nullable()
                  ->constrained('users')->nullOnDelete();                         // commercial
            $table->text('delivery_address')->nullable();                         // adresse de livraison
            $table->string('validity_duration', 15)->nullable()->default('30');   // durée de validité (jours)
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008
            $table->string('price_list', 60)->nullable();                         // Tarif standard 2026
            $table->string('price_mode', 5)->nullable()->default('ttc');          // ttc | ht
            $table->boolean('net_prices')->default(false);
            $table->string('payment_terms', 100)->nullable();                     // 30 jours
            $table->string('payment_method', 30)->nullable();                     // virement | cheque | especes | mobile_money
            $table->string('fiscal_representative', 100)->nullable();
            $table->string('fiscal_regime', 40)->nullable();
            $table->string('default_tax_label', 20)->nullable()->default('TVA 18%');

            // Informations complémentaires
            $table->string('source', 30)->nullable()->default('prospection');     // prospection | appel_offres | client_existant
            $table->string('origin', 30)->nullable();                             // telephone | email | visite | site_web
            $table->string('priority', 15)->nullable()->default('normale');
            $table->date('desired_delivery_date')->nullable();                    // BL souhaité le
            $table->string('delivery_location', 100)->nullable();                 // Chantier – Kossodo
            $table->string('incoterm', 15)->nullable();                           // EXW, FOB, CIF…
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropColumn([
                'delivery_address', 'validity_duration', 'project_reference', 'price_list',
                'price_mode', 'net_prices', 'payment_terms', 'payment_method',
                'fiscal_representative', 'fiscal_regime', 'default_tax_label',
                'source', 'origin', 'priority', 'desired_delivery_date', 'delivery_location', 'incoterm',
            ]);
        });
    }
};
