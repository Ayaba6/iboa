<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            // Mentions de pénalités de retard / recouvrement (paramétrables, remplacent le texte OHADA en dur du PDF facture)
            $table->text('penalty_mentions')->nullable()->after('terms_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            $table->dropColumn('penalty_mentions');
        });
    }
};
