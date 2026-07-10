<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quality_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('number', 40)->unique();
            $table->enum('type', ['reception_bobine', 'produit_fini', 'matiere_premiere', 'autre'])->default('reception_bobine');
            $table->string('ref_type', 80)->nullable();       // polymorphic: Coil, ProductionOrder, Reception
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('lot_number', 60)->nullable();
            $table->string('fournisseur', 120)->nullable();
            $table->date('date_reception')->nullable();
            $table->date('date_certificat');
            $table->decimal('poids_reel', 12, 3)->nullable();
            $table->decimal('largeur_mm', 8, 2)->nullable();
            $table->decimal('epaisseur_mm', 8, 3)->nullable();
            $table->string('couleur', 40)->nullable();
            $table->string('norme', 60)->nullable();
            $table->enum('resultat', ['conforme', 'non_conforme', 'sous_reserve'])->default('conforme');
            $table->text('observations')->nullable();
            $table->json('controles')->nullable();     // [{libelle, valeur_attendue, valeur_obtenue, conforme}]
            $table->unsignedBigInteger('controleur_id')->nullable();
            $table->unsignedBigInteger('validateur_id')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('controleur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validateur_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'type', 'resultat']);
            $table->index(['lot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_certificates');
    }
};
