<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Gestion] Un seul régime fiscal client : `tax_regime`.
 *
 * `clients.regime_imposition` faisait doublon avec `tax_regime`, et la
 * répartition des rôles était inversée par rapport à l'intuition :
 *
 *   - le FORMULAIRE écrivait `regime_imposition`, que RIEN ne lisait ;
 *   - les DOCUMENTS imprimaient `tax_regime`, que RIEN n'alimentait.
 *
 * Conséquence : le régime fiscal saisi par le gestionnaire n'apparaissait
 * jamais sur la facture, le devis, l'avoir ni le bon de livraison — ces
 * documents affichaient un régime vide alors que la donnée existait en base,
 * dans l'autre colonne.
 *
 * `tax_regime` est retenu : c'est lui qui est lu par les cinq documents PDF et
 * par les écrans devis/commande/fiche client.
 *
 * REPRISE AVANT SUPPRESSION — la valeur n'est reportée que si `tax_regime` est
 * vide, c'est-à-dire quand `regime_imposition` portait seul l'information. Si
 * les deux sont renseignés, le champ effectivement imprimé fait foi et rien
 * n'est écrasé : on ne devine pas laquelle des deux valeurs l'emporte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'regime_imposition')) {
            return;
        }

        // SUBSTR() et non LEFT() : LEFT n'existe pas sur SQLite, et la migration
        // doit se rejouer à l'identique sur les deux moteurs de la suite de tests.
        DB::table('clients')
            ->whereNotNull('regime_imposition')
            ->where('regime_imposition', '!=', '')
            ->where(fn ($q) => $q->whereNull('tax_regime')->orWhere('tax_regime', ''))
            ->update(['tax_regime' => DB::raw('SUBSTR(regime_imposition, 1, 100)')]);

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('regime_imposition');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'regime_imposition')) {
            return;
        }

        // La colonne revient VIDE : les valeurs reprises vivent désormais dans
        // `tax_regime` et ne doivent pas être dupliquées.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('regime_imposition', 80)->nullable()->after('tax_regime');
        });
    }
};
