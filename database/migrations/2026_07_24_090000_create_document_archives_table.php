<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Phase 2.8 — reproductibilité documentaire] Le figement des DONNÉES ne fige
 * pas le RENDU (référentiels vivants : logo, adresses, taxes, modèle). À la
 * validation/émission d'un document, on archive le PDF réellement produit +
 * son empreinte SHA-256 : l'exemplaire archivé fait foi, la régénération reste
 * possible mais n'écrase jamais l'original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->morphs('document'); // invoices, credit_notes, delivery_notes…
            $table->string('number')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('byte_size');
            $table->timestamp('archived_at');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un document n'est archivé qu'une fois (l'original ne se réécrit pas)
            $table->unique(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_archives');
    }
};
