<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Demande d'achat] Champs complémentaires alignés sur les fiches ventes :
 * priorité, projet, dépôt de destination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('priority', 15)->nullable()->default('normale');
            $table->string('project_reference', 60)->nullable();                  // PROJ-2026-0008
            $table->foreignId('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();                    // dépôt de destination
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['priority', 'project_reference']);
        });
    }
};
