<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS Qualité — segment 3] Décisions qualité HISTORISÉES sur les réceptions.
 *
 * La vérité d'une décision (libération, refus après contrôle, dérogation,
 * contre-décision) ne vit pas dans un statut final écrasé sur reception_items :
 * chaque décision est un DOCUMENT immuable, chaîné à la ligne, au lot/bobine,
 * avec quantités avant/après, contrôleur, approbateur, critères et pièces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_quality_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('reception_id')->constrained('receptions')->restrictOnDelete();
            $table->foreignId('reception_item_id')->constrained('reception_items')->restrictOnDelete();
            $table->foreignId('coil_id')->nullable();          // bobine concernée le cas échéant
            $table->string('lot_number', 100)->nullable();

            // release | reject_after_control | derogation_acceptance | counter_decision
            $table->string('type', 30);
            $table->decimal('quantity', 10, 4);
            $table->decimal('quarantine_before', 10, 4);
            $table->decimal('quarantine_after', 10, 4);
            $table->decimal('accepted_before', 10, 4);
            $table->decimal('accepted_after', 10, 4);

            $table->json('criteria')->nullable();              // critères contrôlés + résultats + défauts + gravité
            $table->string('reason', 500)->nullable();         // motif (obligatoire pour refus/dérogation)
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('controlled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('appliquee'); // appliquee | annulee (par contre-décision)
            $table->foreignId('replaces_decision_id')->nullable(); // contre-décision → décision remplacée
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();

            $table->index(['reception_item_id', 'type']);
            // Une clé d'idempotence unique par ligne (rejeu → même décision).
            $table->unique(['reception_item_id', 'idempotency_key'], 'uq_pqd_idem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_quality_decisions');
    }
};
