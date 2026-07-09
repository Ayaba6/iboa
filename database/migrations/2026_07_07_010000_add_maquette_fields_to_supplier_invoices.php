<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Facture fournisseur] Champs complémentaires alignés sur les fiches ventes/achats :
 * contact fournisseur, acheteur, mode de prix, paiement, fiscal, projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('supplier_contact_id')->nullable()
                  ->constrained('supplier_contacts')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()
                  ->constrained('users')->nullOnDelete();                         // acheteur / valideur métier
            $table->string('price_mode', 5)->nullable()->default('ht');
            $table->boolean('net_prices')->default(false);
            $table->string('payment_terms', 100)->nullable();                     // 30 jours
            $table->string('payment_method', 30)->nullable();                     // virement | cheque | especes
            $table->string('due_type', 30)->nullable();                           // 30_jours_fin_de_mois
            $table->string('beneficiary_bank', 100)->nullable();                  // banque du fournisseur
            $table->string('fiscal_regime', 40)->nullable();
            $table->string('default_tax_label', 20)->nullable()->default('TVA 18%');
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008
            $table->string('priority', 15)->nullable()->default('normale');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_contact_id');
            $table->dropConstrainedForeignId('buyer_id');
            $table->dropColumn([
                'price_mode', 'net_prices', 'payment_terms', 'payment_method', 'due_type',
                'beneficiary_bank', 'fiscal_regime', 'default_tax_label', 'project_reference', 'priority',
            ]);
        });
    }
};
