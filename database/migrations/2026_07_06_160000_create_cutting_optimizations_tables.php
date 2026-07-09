<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Optimisation de découpe : Création complète]
 * Persistance des plans d'optimisation : entête (bobine, produit, profil, méthode…),
 * demandes (lignes de commandes à couper), paramètres/contraintes et résultats calculés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutting_optimizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->nullable();                               // OPT-2026-00014
            $table->string('site', 20)->nullable();                               // SITE01
            $table->string('atelier', 60)->nullable();                            // Atelier Tôle Bac
            $table->foreignId('production_line_id')->nullable()
                  ->constrained('production_lines')->nullOnDelete();
            $table->string('type_optimisation', 30)->nullable()->default('decoupe_bobines');
            $table->foreignId('coil_id')->nullable()
                  ->constrained('coils')->nullOnDelete();                         // bobine / matière
            $table->foreignId('product_id')->nullable()
                  ->constrained('products')->nullOnDelete();                      // produit à fabriquer
            $table->string('profil', 30)->nullable();                             // 5 ondes
            $table->decimal('thickness', 6, 2)->nullable();                       // épaisseur (mm)
            $table->decimal('coil_width', 8, 2)->nullable();                      // largeur bobine (mm)
            $table->decimal('useful_width', 8, 2)->nullable();                    // largeur utile (mm)
            $table->decimal('standard_length', 10, 2)->nullable();                // longueur standard (m)
            $table->string('method', 30)->nullable()->default('automatique');     // optimisation automatique | manuelle
            $table->date('execution_date')->nullable();
            $table->string('priorite', 15)->nullable()->default('normale');
            $table->string('status', 20)->nullable()->default('brouillon');       // brouillon | optimisee | validee
            $table->text('notes')->nullable();

            // Paramètres et contraintes
            $table->boolean('allow_order_mixing')->default(true);                 // autoriser mélange de commandes
            $table->decimal('min_reusable_offcut', 8, 2)->nullable()->default(1); // longueur mini chute réutilisable (m)
            $table->decimal('cut_tolerance_mm', 6, 2)->nullable()->default(5);    // tolérance coupe (mm)
            $table->boolean('respect_client_lot')->default(false);                // respect lot client
            $table->boolean('group_by_color')->default(true);                     // grouper par couleur
            $table->boolean('optimize_by_delivery_date')->default(true);          // optimiser par date livraison
            $table->boolean('valorize_offcuts')->default(true);                   // valoriser chutes

            // Résultats calculés (après lancement)
            $table->decimal('total_requested_m', 12, 2)->nullable();
            $table->decimal('optimized_m', 12, 2)->nullable();
            $table->decimal('material_yield', 5, 2)->nullable();                  // rendement matière %
            $table->decimal('estimated_waste_m', 12, 2)->nullable();              // chute estimée
            $table->unsignedInteger('cuts_count')->nullable();                    // nombre de coupes
            $table->unsignedInteger('coils_used')->nullable();                    // bobines utilisées
            $table->json('plan')->nullable();                                     // schéma de coupe (barres/coupes)

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cutting_optimization_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_optimization_id')->constrained()->cascadeOnDelete();
            $table->string('order_reference', 40)->nullable();                    // CMD-2026-01587
            $table->string('client', 100)->nullable();                            // Boubacar Ouédraogo
            $table->foreignId('product_id')->nullable()
                  ->constrained('products')->nullOnDelete();                      // article
            $table->decimal('requested_length_m', 10, 2)->nullable();             // longueur demandée (m)
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('total_m', 12, 2)->nullable();                        // total métrage (m)
            $table->string('priorite', 15)->nullable()->default('normale');
            $table->date('delivery_date')->nullable();
            $table->string('status', 20)->nullable()->default('planifiee');       // planifiee | confirmee
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutting_optimization_lines');
        Schema::dropIfExists('cutting_optimizations');
    }
};
