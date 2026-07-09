<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Parité SAGE X3 — Suivi de fabrication] Chaque saisie de suivi (opérations,
 * déclaration production, matière) est tracée avec un numéro dédié, comme
 * l'écran X3 « Suivi de fabrication STD : Suivi complet ». Les effets réels
 * (pointage opérations, entrée stock PF, consommation bobine/composants)
 * restent portés par les tables existantes — cette table est le journal des
 * suivis, pas une duplication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_trackings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('number', 30)->unique();
            $t->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $t->date('tracking_date');
            $t->boolean('track_operations')->default(false);
            $t->boolean('track_production')->default(false);
            $t->boolean('track_materials')->default(false);
            $t->string('site', 40)->nullable();
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'tracking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_trackings');
    }
};
