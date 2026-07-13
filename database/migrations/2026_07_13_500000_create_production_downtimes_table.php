<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PRO Temps d'arrêt] Capture des arrêts machine / production hors maintenance.
 *
 * Complète machine_maintenances.downtime_minutes (arrêts liés aux interventions)
 * en traçant les arrêts de production : pannes, changements d'outil, ruptures
 * matière, réglages, attentes… avec cause, catégorie et durée → alimente la
 * disponibilité machine et le plan de charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('production_machines')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->string('category', 20)->default('non_planifie'); // planifie | non_planifie
            $table->string('reason', 30)->default('autre');          // panne | changement_outil | rupture_matiere | reglage | attente | nettoyage | autre
            $table->string('description', 255)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'machine_id', 'started_at']);
            $table->index(['company_id', 'production_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_downtimes');
    }
};
