<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PIL-04] Alertes par seuil : règles configurables (indicateur, opérateur,
 * seuil, destinataires) évaluées périodiquement → notification interne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric');                   // clé de l'indicateur
            $table->string('operator')->default('gt');  // gt, gte, lt, lte, eq
            $table->decimal('threshold', 15, 2)->default(0);
            $table->json('target_roles')->nullable();   // rôles notifiés
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->decimal('last_value', 15, 2)->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
