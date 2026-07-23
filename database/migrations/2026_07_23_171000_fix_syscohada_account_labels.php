<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [SYSCOHADA — Phase 2.2, complément] Le plan seedé contenait déjà 431 et
 * 4452 — la migration de renommage précédente a donc correctement laissé les
 * comptes en place. Reste : le libellé de 4452 était erroné
 * (« TVA due intracommunautaire » — hors contexte Burkina/UEMOA ; SYSCOHADA :
 * 4452 = TVA récupérable sur achats). Le compte 4432 conserve son unique
 * ligne de recette (aucune écriture modifiée) : la base de recette sera
 * purgée par erp:pre-production-clean avant production.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->where('code', '4452')
            ->update(['name' => 'État — TVA récupérable sur achats']);
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '4452')
            ->update(['name' => 'TVA due intracommunautaire']);
    }
};
