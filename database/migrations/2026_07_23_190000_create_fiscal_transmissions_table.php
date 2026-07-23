<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Phase 2.3 — ancrages fiscaux] Socle de la future facture normalisée
 * (administration fiscale burkinabè) — AUCUN flux branché à ce jour, mais le
 * modèle est prêt : statut fiscal DISTINCT du statut commercial, référence
 * externe, idempotence, payloads conservés, rejets/relances tracés.
 * Un document avec transmission ACCEPTÉE devient immuable (garde service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->morphs('document'); // invoices, credit_notes…
            $table->string('status')->default('en_attente'); // en_attente|transmis|accepte|rejete|annule
            $table->string('external_reference')->nullable()->index(); // n° attribué par l'administration
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('transmitted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->json('request_payload')->nullable();   // charge utile envoyée (conservée)
            $table->json('response_payload')->nullable();  // réponse de l'administration (conservée)
            $table->string('rejection_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_transmissions');
    }
};
