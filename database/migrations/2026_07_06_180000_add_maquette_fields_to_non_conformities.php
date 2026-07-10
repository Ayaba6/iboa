<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Non-conformité : Création] Champs complémentaires :
 * type, origine, catégorie, atelier/ligne/poste/machine, produit, lot, valeurs mesurées,
 * évaluation d'impact, classification et disposition immédiate — plus table
 * non_conformity_characteristics (caractéristiques en défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_conformities', function (Blueprint $table) {
            // Général
            $table->string('nc_type', 30)->nullable()->default('produit');        // produit | process | systeme | fournisseur
            $table->string('origin', 30)->nullable()->default('controle_qualite'); // origine
            $table->string('category', 30)->nullable();                            // dimensionnelle | aspect | fonctionnelle
            $table->string('atelier', 60)->nullable();
            $table->foreignId('production_line_id')->nullable()
                  ->constrained('production_lines')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()
                  ->constrained('work_centers')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()
                  ->constrained('production_machines')->nullOnDelete();
            $table->foreignId('product_id')->nullable()
                  ->constrained('products')->nullOnDelete();
            $table->string('lot_number', 60)->nullable();                          // LOT-2026-0706-01
            $table->string('norm_reference', 60)->nullable();                      // NF EN 10169:2022
            $table->string('requirement', 150)->nullable();                        // Largeur utile : 995 à 1005 mm
            $table->string('measured_value', 30)->nullable();                      // 1008 mm
            $table->string('deviation', 20)->nullable();                           // +3
            $table->string('deviation_unit', 15)->nullable();                      // mm
            $table->decimal('nc_quantity', 12, 2)->nullable();                     // quantité non conforme
            $table->string('nc_quantity_unit', 15)->nullable();                    // MTL
            $table->date('detected_at')->nullable();                               // date de détection
            $table->foreignId('detected_by_id')->nullable()
                  ->constrained('employees')->nullOnDelete();                      // détectée par
            $table->string('comments', 300)->nullable();                           // commentaires courts

            // Évaluation
            $table->string('impact_quality', 15)->nullable()->default('moyen');    // eleve | moyen | faible
            $table->string('impact_cost', 15)->nullable()->default('moyen');
            $table->string('impact_delay', 15)->nullable()->default('moyen');
            $table->string('safety_risk', 15)->nullable()->default('faible');

            // Classification
            $table->string('classification', 20)->nullable()->default('interne');  // interne | externe
            $table->boolean('client_claim')->default(false);                       // réclamation client
            $table->boolean('production_stopped')->default(false);                 // arrêt de production
            $table->boolean('isolation_needed')->default(false);                   // besoin d'isolement
            $table->boolean('product_isolated')->default(false);                   // produit isolé

            // Disposition immédiate
            $table->string('immediate_action', 30)->nullable();                    // isolement_du_lot | tri | retouche | rebut
            $table->decimal('isolated_quantity', 12, 2)->nullable();
            $table->string('isolated_quantity_unit', 15)->nullable();
            $table->string('isolation_location', 60)->nullable();                  // Zone quarantaine
            $table->foreignId('disposition_responsible_id')->nullable()
                  ->constrained('employees')->nullOnDelete();
            $table->date('isolated_at')->nullable();
            $table->string('disposition_comments', 300)->nullable();
        });

        Schema::create('non_conformity_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                                           // Largeur utile
            $table->string('spec_min', 20)->nullable();
            $table->string('spec_max', 20)->nullable();
            $table->string('unit', 15)->nullable();
            $table->string('measured_value', 30)->nullable();                      // 1008
            $table->string('result', 20)->nullable();                              // conforme | non_conforme
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformity_characteristics');

        Schema::table('non_conformities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('work_center_id');
            $table->dropConstrainedForeignId('machine_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('detected_by_id');
            $table->dropConstrainedForeignId('disposition_responsible_id');
            $table->dropColumn([
                'nc_type', 'origin', 'category', 'atelier', 'lot_number', 'norm_reference',
                'requirement', 'measured_value', 'deviation', 'deviation_unit',
                'nc_quantity', 'nc_quantity_unit', 'detected_at', 'comments',
                'impact_quality', 'impact_cost', 'impact_delay', 'safety_risk',
                'classification', 'client_claim', 'production_stopped', 'isolation_needed', 'product_isolated',
                'immediate_action', 'isolated_quantity', 'isolated_quantity_unit',
                'isolation_location', 'isolated_at', 'disposition_comments',
            ]);
        });
    }
};
