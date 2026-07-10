<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend code_article from varchar(10) to varchar(50) — MySQL only (SQLite TEXT is unlimited)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN code_article VARCHAR(50) NULL");
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('designation_courte', 80)->nullable()->after('name');
            // type_article : nature commerciale/logistique (distinct du champ `type` structurel simple/composé)
            $table->string('type_article', 30)->nullable()->after('type');
            $table->decimal('cout_standard', 15, 4)->default(0)->after('weighted_avg_cost');
            $table->boolean('controle_qualite')->default(false)->after('has_expiry_date');
            // Compte SYSCOHADA 603x — variation de stocks (écriture sortie stock)
            $table->foreignId('variation_stock_account_id')->nullable()->constrained('accounts')->nullOnDelete()->after('stock_account_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['variation_stock_account_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'designation_courte', 'type_article', 'cout_standard',
                'controle_qualite', 'variation_stock_account_id',
                'created_by', 'updated_by',
            ]);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN code_article VARCHAR(10) NULL");
        }
    }
};
