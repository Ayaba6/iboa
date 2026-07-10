<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §13.3] « Opérateur : déclare production — Chef équipe : valide déclarations ».
 * Chaque sortie PF déclarée doit recevoir le visa du chef d'équipe avant que
 * l'OF puisse être clôturé. L'entrée en stock reste immédiate (la conformité
 * produit est gérée par le contrôle qualité §13.6) ; le visa porte sur la
 * déclaration elle-même (quantités, longueurs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->string('status', 20)->default('declaree')->after('produced_at');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('validated_at')->nullable()->after('validated_by');
        });

        // Les déclarations antérieures au visa chef d'équipe sont réputées validées.
        DB::table('production_outputs')->update(['status' => 'validee']);
    }

    public function down(): void
    {
        Schema::table('production_outputs', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['status', 'validated_by', 'validated_at']);
        });
    }
};
