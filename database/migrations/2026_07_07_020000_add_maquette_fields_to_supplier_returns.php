<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Retour fournisseur] Champs complémentaires alignés sur les fiches achats :
 * type de retour, dépôt de sortie, priorité, projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->string('return_type', 30)->nullable()->default('defectueux'); // defectueux | erreur_commande | non_conforme
            $table->foreignId('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();                    // dépôt de sortie
            $table->string('priority', 15)->nullable()->default('normale');
            $table->string('project_reference', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['return_type', 'priority', 'project_reference']);
        });
    }
};
