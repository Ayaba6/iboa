<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette X3 — Nouvelle immobilisation] Champs identification / valeurs /
 * amortissement / affectation-localisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Identification
            $table->string('famille', 30)->nullable()->after('category');
            $table->string('asset_type', 30)->nullable()->after('famille');
            $table->string('serial_number', 60)->nullable()->after('asset_type');
            // Valeurs financières
            $table->bigInteger('accessory_cost')->default(0)->after('acquisition_cost');
            // Amortissement
            $table->string('periodicity', 15)->default('mensuelle')->after('depreciation_method');
            $table->boolean('prorata_temporis')->default(true)->after('periodicity');
            $table->decimal('degressive_rate', 5, 2)->default(0)->after('prorata_temporis');
            // Affectation / localisation
            $table->string('service_code', 30)->nullable()->after('notes');
            $table->string('responsable', 100)->nullable()->after('service_code');
            $table->string('utilisateur', 100)->nullable()->after('responsable');
            $table->string('localisation', 60)->nullable()->after('utilisateur');
            $table->string('batiment', 60)->nullable()->after('localisation');
            $table->string('bureau', 60)->nullable()->after('batiment');
            $table->string('centre_analytique', 30)->nullable()->after('bureau');
            $table->string('projet', 60)->nullable()->after('centre_analytique');
            $table->string('code_activite', 30)->nullable()->after('projet');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn([
                'famille', 'asset_type', 'serial_number', 'accessory_cost',
                'periodicity', 'prorata_temporis', 'degressive_rate',
                'service_code', 'responsable', 'utilisateur', 'localisation',
                'batiment', 'bureau', 'centre_analytique', 'projet', 'code_activite',
            ]);
        });
    }
};
