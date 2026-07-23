<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [CDC §7 — versionnement devis] Lien de révision : une révision référence le
// devis qu'elle remplace. Additif uniquement.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('revision_of_id')->nullable()->after('converted_to_order_id')
                ->constrained('quotes')->nullOnDelete();
            $table->unsignedInteger('revision_number')->default(1)->after('revision_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn('revision_number');
        });
    }
};
