<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS #9] Idempotence DURABLE (au-delà du cache HTTP 60s existant) pour les
 * créations rejouables : UI double-clic, API, import, job relancé, retry réseau,
 * intégration externe. Une clé + un fingerprint de payload identifient une
 * requête logique unique ; le rejeu renvoie le document déjà créé.
 *
 * Aucune donnée sensible n'est stockée ici (ni secret, ni pièce jointe brute) :
 * seulement une empreinte du contenu économique et un lien vers le document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('scope', 60);                 // ex. 'supplier_invoice.create'
            $table->string('idempotency_key', 128);
            $table->char('payload_hash', 64);            // sha256 du contenu économique
            $table->string('source', 20)->nullable();    // ui|api|import|job
            $table->string('external_reference', 100)->nullable();
            $table->nullableMorphs('subject');           // document créé (SupplierInvoice…)
            $table->enum('status', ['completed', 'failed'])->default('completed');
            $table->timestamps();

            // Barrière d'unicité : une clé logique par périmètre société+scope.
            $table->unique(['company_id', 'scope', 'idempotency_key'], 'uq_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
