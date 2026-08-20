<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-A3-MTO-TECH-010] Les caractéristiques techniques demandées par le client
 * n'existaient nulle part sur la ligne de vente.
 *
 * `quote_items` et `order_items` ne portaient que `nb_toles` et
 * `metrage_par_tole`. Couleur, épaisseur, profil, nombre d'ondes, largeur,
 * matière, revêtement et tolérances vivaient uniquement sur `production_orders`
 * — où l'OF les hérite de la NOMENCLATURE, pas de ce que le client a demandé.
 *
 * Conséquence : deux commandes du même article dans deux couleurs différentes
 * sont indistinguables au niveau vente. La seule trace de la couleur voulue est
 * la désignation en texte libre — or déduire l'épaisseur de « 27/100 » écrit
 * dans un libellé est exactement ce qu'il faut éviter : rien ne garantit que le
 * libellé et la valeur structurée concordent.
 *
 * Les noms reprennent ceux de `production_orders`, pour que la propagation
 * devis → commande → OF soit une recopie et non une traduction. `nb_ondes` y
 * est ajouté du même geste : il manquait aux trois niveaux alors que le profil
 * « 4 ondes » est une caractéristique commerciale de la tôle bac.
 *
 * Migration additive et nullable : aucune ligne existante n'est modifiée. Les
 * lignes historiques restent sans caractéristiques, ce qui est la vérité — on
 * ne reconstitue pas après coup ce qui n'a jamais été saisi.
 */
return new class extends Migration
{
    /** Caractéristiques portées par une ligne de vente MTO. */
    private const CHAMPS = [
        'sheet_type'          => ['string', 60],   // matière / type de tôle
        'color'               => ['string', 40],
        'couleur_ral'         => ['string', 20],
        'revetement'          => ['string', 40],
        'profil'              => ['string', 40],
        'nb_ondes'            => ['smallint', null],
        'thickness'           => ['decimal', [8, 3]],
        'usable_width'        => ['decimal', [8, 1]],
        'largeur_totale'      => ['decimal', [8, 1]],
        'tolerance_longueur'  => ['decimal', [8, 3]],
        'tolerance_epaisseur' => ['decimal', [8, 3]],
    ];

    public function up(): void
    {
        foreach (['quote_items', 'order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (self::CHAMPS as $nom => [$type, $taille]) {
                    if (Schema::hasColumn($table, $nom)) {
                        continue;
                    }
                    match ($type) {
                        'string'   => $t->string($nom, $taille)->nullable(),
                        'smallint' => $t->unsignedSmallInteger($nom)->nullable(),
                        'decimal'  => $t->decimal($nom, $taille[0], $taille[1])->nullable(),
                    };
                }
            });
        }

        // `nb_ondes` manquait aussi à l'OF : sans lui, la caractéristique
        // recopiée depuis la commande n'aurait nulle part où atterrir.
        Schema::table('production_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('production_orders', 'nb_ondes')) {
                $t->unsignedSmallInteger('nb_ondes')->nullable()->after('profil');
            }
        });
    }

    public function down(): void
    {
        foreach (['quote_items', 'order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(array_keys(self::CHAMPS));
            });
        }

        Schema::table('production_orders', function (Blueprint $t) {
            $t->dropColumn('nb_ondes');
        });
    }
};
