<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Audit création OF — parité SAGE X3] Champs entête manquants :
 * type/origine d'OF, atelier, planification (dates/heures début-fin prévues),
 * dépôts matière première & qualité, responsable atelier & opérateur prévus,
 * versions nomenclature/gamme, équipe, temps de réglage, options de clôture,
 * caractéristiques tôle bac étendues (profil, largeurs, poids, RAL,
 * revêtement, tolérances, unité de production).
 * Métadonnées de pilotage — n'altèrent ni les flux stock ni la comptabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $t) {
            $add = fn (string $col) => ! Schema::hasColumn('production_orders', $col);

            // Entête
            if ($add('of_type'))        $t->string('of_type', 30)->nullable()->default('standard');   // standard|reprise|retouche|speciale_client
            if ($add('origin'))         $t->string('origin', 30)->nullable()->default('manuel');      // manuel|commande_client|stock_minimum|mrp
            if ($add('atelier'))        $t->string('atelier', 60)->nullable();
            if ($add('bom_version'))    $t->string('bom_version', 20)->nullable();
            if ($add('routing_version')) $t->string('routing_version', 20)->nullable();
            if ($add('depot_matiere_id')) $t->foreignId('depot_matiere_id')->nullable()->constrained('warehouses')->nullOnDelete();
            if ($add('depot_qualite_id')) $t->foreignId('depot_qualite_id')->nullable()->constrained('warehouses')->nullOnDelete();
            if ($add('responsable_atelier_id')) $t->foreignId('responsable_atelier_id')->nullable()->constrained('users')->nullOnDelete();
            if ($add('operateur_prevu_id'))     $t->foreignId('operateur_prevu_id')->nullable()->constrained('users')->nullOnDelete();
            if ($add('date_debut_prevue'))  $t->date('date_debut_prevue')->nullable();
            if ($add('date_fin_prevue'))    $t->date('date_fin_prevue')->nullable();
            if ($add('heure_debut_prevue')) $t->string('heure_debut_prevue', 8)->nullable();
            if ($add('heure_fin_prevue'))   $t->string('heure_fin_prevue', 8)->nullable();

            // Paramètres production
            if ($add('temps_reglage'))   $t->decimal('temps_reglage', 8, 2)->nullable();  // minutes
            if ($add('equipe_prevue'))   $t->string('equipe_prevue', 60)->nullable();
            if ($add('nb_operateurs'))   $t->unsignedSmallInteger('nb_operateurs')->nullable();
            if ($add('autoriser_cloture_partielle'))  $t->boolean('autoriser_cloture_partielle')->default(true);
            if ($add('autoriser_depassement_qte'))    $t->boolean('autoriser_depassement_qte')->default(false);

            // Caractéristiques tôle bac étendues
            if ($add('profil'))            $t->string('profil', 40)->nullable();          // 5_ondes|6_ondes|7_ondes|bac_alu|bac_galva|bac_prelaque
            if ($add('largeur_totale'))    $t->decimal('largeur_totale', 8, 1)->nullable();
            if ($add('longueur_standard')) $t->decimal('longueur_standard', 8, 2)->nullable();
            if ($add('unite_production'))  $t->string('unite_production', 10)->nullable()->default('ML'); // ML|M2|PIECE
            if ($add('poids_par_metre'))   $t->decimal('poids_par_metre', 8, 3)->nullable();
            if ($add('poids_theorique'))   $t->decimal('poids_theorique', 12, 2)->nullable();
            if ($add('couleur_ral'))       $t->string('couleur_ral', 20)->nullable();
            if ($add('revetement'))        $t->string('revetement', 60)->nullable();
            if ($add('tolerance_longueur')) $t->decimal('tolerance_longueur', 6, 2)->nullable(); // mm
            if ($add('tolerance_epaisseur')) $t->decimal('tolerance_epaisseur', 6, 3)->nullable(); // mm
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $t) {
            foreach (['depot_matiere_id', 'depot_qualite_id', 'responsable_atelier_id', 'operateur_prevu_id'] as $fk) {
                if (Schema::hasColumn('production_orders', $fk)) $t->dropConstrainedForeignId($fk);
            }
            foreach ([
                'of_type', 'origin', 'atelier', 'bom_version', 'routing_version',
                'date_debut_prevue', 'date_fin_prevue', 'heure_debut_prevue', 'heure_fin_prevue',
                'temps_reglage', 'equipe_prevue', 'nb_operateurs',
                'autoriser_cloture_partielle', 'autoriser_depassement_qte',
                'profil', 'largeur_totale', 'longueur_standard', 'unite_production',
                'poids_par_metre', 'poids_theorique', 'couleur_ral', 'revetement',
                'tolerance_longueur', 'tolerance_epaisseur',
            ] as $col) {
                if (Schema::hasColumn('production_orders', $col)) $t->dropColumn($col);
            }
        });
    }
};
