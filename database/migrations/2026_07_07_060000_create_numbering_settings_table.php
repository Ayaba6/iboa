<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette Numérotation] paramètres globaux de numérotation (singleton par société)
// + inclusion du mois dans les numéros de séquence.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('default_fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('separator', 5)->default('-');
            $table->unsignedTinyInteger('digits')->default(4);
            $table->boolean('reset_on_close')->default(true);
            $table->string('company_prefix', 10)->nullable();
            $table->boolean('include_year')->default(true);
            $table->boolean('include_month')->default(false);
            $table->string('preview_format', 30)->default('annee_mois_numero');
            $table->string('gap_policy', 20)->default('interdite');      // interdite|toleree
            $table->boolean('per_site')->default(false);
            $table->boolean('per_journal')->default(false);
            $table->string('date_format', 12)->default('JJ/MM/AAAA');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::table('document_sequences', function (Blueprint $table) {
            $table->boolean('include_month')->default(false)->after('include_year');
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropColumn('include_month');
        });
        Schema::dropIfExists('numbering_settings');
    }
};
