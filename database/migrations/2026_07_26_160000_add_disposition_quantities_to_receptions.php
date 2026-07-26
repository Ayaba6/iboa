<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS Réceptions #1/#7] Modèle DÉTAILLÉ des quantités de réception.
 *
 * Une seule `received_quantity` ne peut représenter toutes les réalités. On
 * ventile désormais, par ligne de réception :
 *   received  = accepted + quarantine + refused   (invariant, cf. #7)
 * où :
 *   - accepted  : accepté et libéré (entre en stock utilisable) ;
 *   - quarantine: reçu en attente de décision qualité (entre en DÉPÔT QUAR,
 *     jamais disponible/réservable/consommable) ;
 *   - refused   : refusé à quai (n'entre pas en stock vendable).
 * (`rejected_quantity` préexistant = refusé ; on garde le nom en base.)
 *
 * Cache d'agrégat sur la ligne de commande : `accepted_quantity` — recalculable
 * et réconcilié (audit dédié), jamais seule vérité.
 *
 * Rétro-compatibilité : les lignes existantes sont backfillées en
 * « acceptation reconstruite NON CERTIFIÉE » (accepted = received − refused,
 * quarantine = 0) — cf. #14 : on n'invente pas une décision qualité, on marque
 * l'origine reconstruite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->decimal('accepted_quantity', 10, 4)->default(0)->after('rejected_quantity');
            $table->decimal('quarantine_quantity', 10, 4)->default(0)->after('accepted_quantity');
            // Origine de la ventilation : 'saisie' (décision réelle) vs
            // 'reconstruite' (backfill historique non certifié).
            $table->string('disposition_origin', 20)->default('saisie')->after('quarantine_quantity');
        });

        // Backfill historique : accepted = received − refused, quarantine = 0,
        // marqué 'reconstruite' (non certifié).
        // CASE portable (SQLite n'a pas GREATEST).
        DB::table('reception_items')->update([
            'accepted_quantity'   => DB::raw('CASE WHEN received_quantity - rejected_quantity > 0 THEN received_quantity - rejected_quantity ELSE 0 END'),
            'quarantine_quantity' => 0,
            'disposition_origin'  => 'reconstruite',
        ]);

        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Cache d'agrégat de l'accepté (réconcilié par audit).
            $table->decimal('accepted_quantity', 14, 4)->default(0)->after('received_quantity');
        });

        // Backfill de l'agrégat accepté depuis les lignes de réception.
        $sums = DB::table('reception_items')
            ->select('purchase_order_item_id', DB::raw('SUM(accepted_quantity) as acc'))
            ->whereNotNull('purchase_order_item_id')
            ->groupBy('purchase_order_item_id')
            ->get();
        foreach ($sums as $s) {
            DB::table('purchase_order_items')->where('id', $s->purchase_order_item_id)
                ->update(['accepted_quantity' => $s->acc]);
        }
    }

    public function down(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->dropColumn(['accepted_quantity', 'quarantine_quantity', 'disposition_origin']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('accepted_quantity');
        });
    }
};
