<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette X3] Écriture de simulation : pièce provisoire non validable
 * (n'impacte jamais les soldes tant que le flag n'est pas levé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->boolean('is_simulation')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('is_simulation');
        });
    }
};
