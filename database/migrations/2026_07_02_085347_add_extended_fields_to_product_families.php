<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->string('libelle_court', 50)->nullable()->after('name');
            // JSON array: achete | vendu | fabrique | sous_traite | service | livrable
            $table->json('type_flux')->nullable()->after('description');
            $table->boolean('gestion_stock')->default(false)->after('type_flux');
            $table->foreignId('unite_stock_id')->nullable()->constrained('units')->nullOnDelete()->after('gestion_stock');
            $table->foreignId('unite_achat_id')->nullable()->constrained('units')->nullOnDelete()->after('unite_stock_id');
            $table->foreignId('unite_vente_id')->nullable()->constrained('units')->nullOnDelete()->after('unite_achat_id');
            $table->foreignId('site_stockage_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('unite_vente_id');
            $table->boolean('gestion_lot')->default(false)->after('site_stockage_id');
            $table->boolean('gestion_numero_serie')->default(false)->after('gestion_lot');
            $table->boolean('controle_qualite')->default(false)->after('gestion_numero_serie');
        });
    }

    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropForeign(['unite_stock_id']);
            $table->dropForeign(['unite_achat_id']);
            $table->dropForeign(['unite_vente_id']);
            $table->dropForeign(['site_stockage_id']);
            $table->dropColumn([
                'libelle_court', 'type_flux', 'gestion_stock',
                'unite_stock_id', 'unite_achat_id', 'unite_vente_id',
                'site_stockage_id', 'gestion_lot', 'gestion_numero_serie', 'controle_qualite',
            ]);
        });
    }
};
