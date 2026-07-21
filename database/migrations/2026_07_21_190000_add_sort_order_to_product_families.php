<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [X3] Le formulaire famille propose un « Ordre d'affichage » depuis la
 * séparation catégorie/famille, mais la colonne n'existait pas : la saisie
 * était silencieusement perdue (hors $fillable, hors schéma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
