<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [CDC §Coût de revient industriel] La catégorie 'machine' (dépréciation
 * équipement) s'ajoute aux natures analytiques. L'enum rigide devient un
 * varchar pour ne plus bloquer les natures futures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE analytic_lines MODIFY COLUMN category VARCHAR(30) NOT NULL DEFAULT 'autre'");
        } else {
            Schema::table('analytic_lines', function (Blueprint $table) {
                $table->string('category', 30)->default('autre')->change();
            });
        }
    }

    public function down(): void
    {
        // Retour à l'enum d'origine impossible sans perte si 'machine' est utilisé — no-op.
    }
};
