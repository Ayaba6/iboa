<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Gestion] Un seul plafond de crédit, clients ET fournisseurs : `credit_limit`.
 *
 * `clients.encours_autorise` faisait doublon avec `credit_limit` et n'était lu
 * par AUCUN service. Il portait pourtant le nom métier le plus naturel — en
 * comptabilité francophone, « encours autorisé » désigne précisément le plafond.
 * Un gestionnaire qui saisissait un montant là croyait poser une limite ; le
 * contrôle de crédit, qui ne lit que `credit_limit`, la laissait à 0 et
 * n'appliquait donc AUCUN blocage.
 *
 * REPRISE DES VALEURS AVANT SUPPRESSION — aucune donnée n'est perdue en silence.
 * La valeur n'est reportée que lorsque `credit_limit` vaut 0, c'est-à-dire
 * lorsqu'aucun plafond réel n'était posé : dans ce cas seul, `encours_autorise`
 * était bien l'intention de l'utilisateur. Quand les deux sont renseignés, le
 * champ appliqué (`credit_limit`) fait foi et rien n'est écrasé — on ne devine
 * pas laquelle des deux valeurs l'emporte.
 *
 * `suppliers.encours_autorise` subit le même sort, sur décision explicite :
 * le fournisseur en base portait deux valeurs divergentes (plafond 10 000 000,
 * encours autorisé 6 000 000) et aucun contrôle n'appliquait ni l'une ni
 * l'autre. `credit_limit` est retenu, `encours_autorise` supprimé.
 */
return new class extends Migration
{
    /** Tables portant le doublon, dans l'ordre de traitement. */
    private const TABLES = ['clients', 'suppliers'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'encours_autorise')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('encours_autorise')
                ->where('encours_autorise', '>', 0)
                ->where('credit_limit', 0)
                ->update(['credit_limit' => DB::raw('ROUND(encours_autorise)')]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('encours_autorise');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'encours_autorise')) {
                continue;
            }

            // La colonne revient VIDE : les valeurs reprises ci-dessus vivent
            // désormais dans `credit_limit` et ne doivent pas être dupliquées.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('encours_autorise', 16, 2)->nullable()->after('credit_limit');
            });
        }
    }
};
