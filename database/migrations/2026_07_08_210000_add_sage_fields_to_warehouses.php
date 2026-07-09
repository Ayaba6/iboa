<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Dépôt] Enrichit la fiche dépôt : désignation longue, dépôt
 * parent, GPS, règles de sortie stock, dépôts qualité/rebut liés, capacité,
 * imputation comptable/analytique, flux autorisés étendus (livraison, transfert)
 * + contrôle qualité requis + paramétrage « validation requise » par flux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $t) {
            if (!Schema::hasColumn('warehouses', 'long_name'))                $t->string('long_name', 255)->nullable()->after('name');
            if (!Schema::hasColumn('warehouses', 'parent_id'))                $t->unsignedBigInteger('parent_id')->nullable()->after('type');
            if (!Schema::hasColumn('warehouses', 'latitude'))                 $t->decimal('latitude', 10, 7)->nullable()->after('country');
            if (!Schema::hasColumn('warehouses', 'longitude'))                $t->decimal('longitude', 10, 7)->nullable()->after('latitude');
            if (!Schema::hasColumn('warehouses', 'default_location'))         $t->string('default_location', 60)->nullable()->after('longitude');
            if (!Schema::hasColumn('warehouses', 'quality_warehouse_id'))     $t->unsignedBigInteger('quality_warehouse_id')->nullable()->after('default_location');
            if (!Schema::hasColumn('warehouses', 'scrap_warehouse_id'))       $t->unsignedBigInteger('scrap_warehouse_id')->nullable()->after('quality_warehouse_id');
            if (!Schema::hasColumn('warehouses', 'max_capacity'))             $t->decimal('max_capacity', 14, 2)->nullable()->after('scrap_warehouse_id');
            if (!Schema::hasColumn('warehouses', 'capacity_unit'))            $t->string('capacity_unit', 10)->nullable()->default('m²')->after('max_capacity');
            if (!Schema::hasColumn('warehouses', 'overload_alert_percent'))   $t->decimal('overload_alert_percent', 5, 2)->nullable()->after('capacity_unit');
            if (!Schema::hasColumn('warehouses', 'issue_method'))             $t->string('issue_method', 10)->nullable()->default('fifo')->after('overload_alert_percent');
            if (!Schema::hasColumn('warehouses', 'issue_priority'))           $t->string('issue_priority', 30)->nullable()->default('oldest')->after('issue_method');
            if (!Schema::hasColumn('warehouses', 'stock_account'))            $t->string('stock_account', 20)->nullable()->after('issue_priority');
            if (!Schema::hasColumn('warehouses', 'stock_journal'))            $t->string('stock_journal', 20)->nullable()->after('stock_account');
            if (!Schema::hasColumn('warehouses', 'cost_center'))              $t->string('cost_center', 30)->nullable()->after('stock_journal');
            if (!Schema::hasColumn('warehouses', 'analytic_section'))         $t->string('analytic_section', 30)->nullable()->after('cost_center');
            if (!Schema::hasColumn('warehouses', 'requires_quality_control')) $t->boolean('requires_quality_control')->default(false)->after('analytic_section');
            if (!Schema::hasColumn('warehouses', 'can_delivery'))             $t->boolean('can_delivery')->default(true)->after('can_sale');
            if (!Schema::hasColumn('warehouses', 'can_transfer'))             $t->boolean('can_transfer')->default(true)->after('can_delivery');
            if (!Schema::hasColumn('warehouses', 'flow_settings'))            $t->json('flow_settings')->nullable()->after('requires_quality_control');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $t) {
            foreach ([
                'long_name', 'parent_id', 'latitude', 'longitude', 'default_location',
                'quality_warehouse_id', 'scrap_warehouse_id', 'max_capacity', 'capacity_unit',
                'overload_alert_percent', 'issue_method', 'issue_priority', 'stock_account',
                'stock_journal', 'cost_center', 'analytic_section', 'requires_quality_control',
                'can_delivery', 'can_transfer', 'flow_settings',
            ] as $col) {
                if (Schema::hasColumn('warehouses', $col)) $t->dropColumn($col);
            }
        });
    }
};
