<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Division #3/#4] Proposition de division PERSISTÉE — le maker-checker ne peut
 * pas reposer sur un simple contrôle « exécutant ≠ approbateur » au moment de
 * l'appel : la décision doit exister comme document, avec sa machine d'états.
 *
 *   BROUILLON → SOUMISE → APPROUVEE → EXECUTEE
 *   SOUMISE → REFUSEE ; APPROUVEE → INVALIDEE (payload modifié) | EXPIREE
 *
 * Les SEUILS applicables sont FIGÉS sur la proposition (une modification
 * ultérieure du paramétrage ne change pas rétroactivement une proposition déjà
 * soumise), ainsi que le hash du payload économique : toute modification
 * invalide les approbations et impose une nouvelle soumission.
 *
 * Idempotente ; noms d'index courts (limite MySQL 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coil_split_proposals')) {
            return;
        }

        Schema::create('coil_split_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable();
            $table->unsignedBigInteger('coil_id');
            $table->string('number', 80);
            // Payload économique figé + empreinte (invalidation si modifié).
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->decimal('divisible_qty', 12, 3);
            $table->decimal('scrap_qty', 12, 3)->default(0);
            $table->decimal('loss_qty', 12, 3)->default(0);
            $table->integer('loss_value')->default(0);
            $table->integer('residual_cost')->default(0);
            // Seuils FIGÉS au moment de la soumission (+ version de paramétrage).
            $table->integer('threshold_loss_value');
            $table->decimal('threshold_loss_qty', 12, 3);
            $table->string('threshold_version', 30)->default('config-v1');
            $table->boolean('requires_loss_approval')->default(false);
            // Machine d'états.
            $table->string('status', 20)->default('brouillon');
            $table->unsignedBigInteger('proposed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedBigInteger('split_operation_id')->nullable();
            $table->string('refusal_reason', 500)->nullable();
            $table->timestamps();

            $table->index('coil_id', 'ix_csp_coil');
            $table->index('status', 'ix_csp_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coil_split_proposals');
    }
};
