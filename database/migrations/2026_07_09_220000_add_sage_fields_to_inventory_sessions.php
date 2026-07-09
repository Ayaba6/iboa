<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [PARITÉ SAGE X3 — Inventaire] Paramètres de comptage : site, responsable,
 * type/méthode de comptage, valorisation, options (geler stock, inclure
 * lots/séries et emplacements), périmètre (emplacements / articles), devise,
 * commentaire. Métadonnées de session — n'affectent pas la génération des
 * lignes (InventoryService pré-remplit toujours depuis le stock théorique du
 * dépôt) ni la valorisation des écarts au moment de la validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $t) {
            if (!Schema::hasColumn('inventory_sessions', 'site'))               $t->string('site', 40)->nullable()->after('warehouse_id');
            if (!Schema::hasColumn('inventory_sessions', 'responsible'))        $t->string('responsible', 100)->nullable()->after('site');
            if (!Schema::hasColumn('inventory_sessions', 'counting_type'))      $t->string('counting_type', 20)->nullable()->default('complet')->after('type');
            if (!Schema::hasColumn('inventory_sessions', 'counting_method'))    $t->string('counting_method', 20)->nullable()->default('par_article')->after('counting_type');
            if (!Schema::hasColumn('inventory_sessions', 'valuation_method'))   $t->string('valuation_method', 20)->nullable()->default('cout_standard')->after('counting_method');
            if (!Schema::hasColumn('inventory_sessions', 'valuation_currency')) $t->string('valuation_currency', 3)->nullable()->default('XOF')->after('valuation_method');
            if (!Schema::hasColumn('inventory_sessions', 'currency_code'))      $t->string('currency_code', 3)->nullable()->default('XOF')->after('valuation_currency');
            if (!Schema::hasColumn('inventory_sessions', 'freeze_stock'))       $t->boolean('freeze_stock')->default(true)->after('currency_code');
            if (!Schema::hasColumn('inventory_sessions', 'include_lots'))       $t->boolean('include_lots')->default(true)->after('freeze_stock');
            if (!Schema::hasColumn('inventory_sessions', 'include_locations'))  $t->boolean('include_locations')->default(true)->after('include_lots');
            if (!Schema::hasColumn('inventory_sessions', 'location_scope'))     $t->string('location_scope', 60)->nullable()->after('include_locations');
            if (!Schema::hasColumn('inventory_sessions', 'article_scope'))      $t->string('article_scope', 60)->nullable()->after('location_scope');
            if (!Schema::hasColumn('inventory_sessions', 'comment'))            $t->string('comment', 500)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $t) {
            foreach ([
                'site', 'responsible', 'counting_type', 'counting_method', 'valuation_method',
                'valuation_currency', 'currency_code', 'freeze_stock', 'include_lots',
                'include_locations', 'location_scope', 'article_scope', 'comment',
            ] as $col) {
                if (Schema::hasColumn('inventory_sessions', $col)) $t->dropColumn($col);
            }
        });
    }
};
