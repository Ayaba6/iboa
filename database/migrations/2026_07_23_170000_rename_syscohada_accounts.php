<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [SYSCOHADA — Phase 2.2] Alignement du plan avant production :
 *  - 4432 → 4452 : la TVA récupérable est une subdivision de 445 (État, TVA
 *    récupérable), pas de 443 (TVA facturée). 1 ligne d'écriture existante
 *    suit le compte (renommage, aucune écriture modifiée).
 *  - 451 → 431 : la CNSS relève de 43 (Organismes sociaux), pas de 45
 *    (Opérations groupe). 0 ligne existante.
 * Ne renomme que si le compte source existe et que la cible n'existe pas.
 */
return new class extends Migration
{
    private const RENAMES = [
        ['4432', '4452', 'État — TVA récupérable sur achats'],
        ['451',  '431',  'Caisse nationale de sécurité sociale'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as [$from, $to, $name]) {
            $exists = DB::table('accounts')->where('code', $to)->exists();
            if ($exists) {
                continue;
            }
            DB::table('accounts')->where('code', $from)->update(['code' => $to, 'name' => $name]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as [$from, $to]) {
            DB::table('accounts')->where('code', $to)->update(['code' => $from]);
        }
    }
};
