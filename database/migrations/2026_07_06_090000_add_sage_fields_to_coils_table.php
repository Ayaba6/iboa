<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Bobine : Création] Champs étendus de la fiche bobine :
 * réception (réf. fournisseur, dépôt, site, BL, origine, devise),
 * caractéristiques (nuance, diamètres, poids brut, revêtement, finition,
 * tolérance, code-barres, marque, n° série) et propriétés de gestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            // Réception / identification commerciale
            $table->string('supplier_reference', 60)->nullable()->after('supplier_id');
            $table->foreignId('warehouse_id')->nullable()->after('supplier_reference')->constrained()->nullOnDelete();
            $table->string('site', 20)->nullable()->after('warehouse_id');
            $table->string('bl_number', 60)->nullable()->after('site');
            $table->string('origine', 30)->nullable()->after('bl_number');          // importation | local
            $table->string('devise', 10)->nullable()->default('XOF')->after('origine');

            // Caractéristiques produit
            $table->string('nuance', 30)->nullable()->after('color');               // ex. DX51D
            $table->decimal('gross_weight', 12, 3)->nullable()->after('initial_weight');
            $table->decimal('inner_diameter', 8, 2)->nullable()->after('width');    // mm
            $table->decimal('outer_diameter', 8, 2)->nullable()->after('inner_diameter');
            $table->string('coating', 30)->nullable()->after('outer_diameter');     // ex. Z275
            $table->string('surface_finish', 30)->nullable()->after('coating');     // brillante | mate…
            $table->decimal('tolerance_thickness', 6, 3)->nullable()->after('surface_finish'); // +/- mm
            $table->string('barcode', 60)->nullable()->after('tolerance_thickness');
            $table->string('brand', 60)->nullable()->after('barcode');
            $table->string('serial_number', 60)->nullable()->after('brand');

            // Propriétés de gestion
            $table->string('valuation_method', 20)->nullable()->default('cump')->after('cost_per_kg'); // cump | fifo | pmp
            $table->boolean('is_stock_managed')->default(true)->after('valuation_method');
            $table->boolean('lot_tracking')->default(true)->after('is_stock_managed');
            $table->boolean('allow_negative_stock')->default(false)->after('lot_tracking');
        });
    }

    public function down(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn([
                'supplier_reference', 'site', 'bl_number', 'origine', 'devise',
                'nuance', 'gross_weight', 'inner_diameter', 'outer_diameter',
                'coating', 'surface_finish', 'tolerance_thickness', 'barcode',
                'brand', 'serial_number',
                'valuation_method', 'is_stock_managed', 'lot_tracking', 'allow_negative_stock',
            ]);
        });
    }
};
