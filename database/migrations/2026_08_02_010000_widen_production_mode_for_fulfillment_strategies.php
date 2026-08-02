<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [D2] `products.production_mode` doit pouvoir porter toute stratégie
 * d'approvisionnement, pas seulement les deux modes de fabrication.
 *
 * La colonne était un `varchar(3)` — dimensionnée pour 'mto' et 'mts', et
 * incapable de contenir 'achat_revente' (13 caractères), 'service' ou
 * 'conso_interne'. Le vocabulaire complet n'existait donc que sur
 * `item_categories.strategy`, et `CategoryDefaultsService` écrasait à NULL tout
 * ce que la colonne ne pouvait pas stocker : d'où 19 articles sans mode.
 *
 * La catégorie ne peut pas servir de véhicule de remplacement : elle porte
 * d'autres règles — `is_manufactured` en particulier, qui conditionne la
 * création d'un ordre de fabrication. Rattacher un article à une catégorie
 * « marchandise » pour lui donner une stratégie lui interdit du même geste
 * d'être fabriqué. Les deux notions doivent rester distinctes.
 *
 * 20 caractères couvrent la plus longue valeur de l'énumération existante avec
 * de la marge. Élargissement pur : aucune valeur n'est tronquée, aucune ligne
 * modifiée, et la contrainte reste `nullable` pour les articles non vendables.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY production_mode VARCHAR(20) NULL');
    }

    public function down(): void
    {
        // Rétrécir tronquerait les stratégies longues : on les remet à NULL
        // d'abord, sinon MySQL refuse ou mutile la donnée en silence.
        DB::table('products')->whereNotIn('production_mode', ['mto', 'mts'])->update(['production_mode' => null]);
        DB::statement('ALTER TABLE products MODIFY production_mode VARCHAR(3) NULL');
    }
};
