<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Sync ERP] journal central des synchronisations inter-modules.
// Clé logique d'idempotence : source_type + source_id + target_module + action.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source_module', 40);                   // achats, ventes, production…
            $table->string('target_module', 40);                   // stock, comptabilite, tresorerie…
            $table->string('event_name', 80);                      // ex: reception.validated
            $table->string('action', 80);                          // ex: create_stock_entry
            $table->string('source_type', 120);                    // FQCN du document source
            $table->unsignedBigInteger('source_id');
            $table->string('status', 20)->default('pending');      // pending|success|failed|skipped|retrying
            $table->string('message', 500)->nullable();
            $table->json('payload')->nullable();
            $table->text('error_trace')->nullable();
            $table->string('handler_class', 150)->nullable();      // relançable si présent
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'created_at']);
            // Idempotence : une action réussie n'est jamais rejouée
            $table->index(['source_type', 'source_id', 'target_module', 'action'], 'sync_logs_logical_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
