<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS Réceptions — correction] Sécurise l'historique : NE PAS convertir une
 * absence d'information en acceptation.
 *
 *  - accepted_quantity / quarantine_quantity deviennent NULLABLES : NULL = inconnu,
 *    0 = explicitement nul. On distingue une décision réelle d'une absence de donnée.
 *  - La valeur reconstruite (received − rejected) n'est PLUS écrite dans
 *    accepted_quantity ; elle est isolée dans reconstructed_quantity + métadonnées
 *    (confidence, date), visible en audit et JAMAIS utilisée comme preuve qualité.
 *  - Les lignes backfillées « reconstruite » sont reclassées legacy_unclassified /
 *    RECONSTRUCTED, avec accepted/quarantine remis à NULL (inconnu).
 *  - L'agrégat purchase_order_items.accepted_quantity devient nullable et est remis
 *    à NULL pour l'historique reconstruit (recalculé par le service aux prochaines
 *    réceptions ventilées).
 *
 * Aucune donnée de stock n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->decimal('accepted_quantity', 10, 4)->nullable()->default(null)->change();
            $table->decimal('quarantine_quantity', 10, 4)->nullable()->default(null)->change();
            $table->decimal('reconstructed_quantity', 10, 4)->nullable()->after('quarantine_quantity');
            $table->string('reconstruction_confidence', 20)->nullable()->after('reconstructed_quantity'); // CERTIFIED|RECONSTRUCTED|UNKNOWN
            $table->timestamp('reconstructed_at')->nullable()->after('reconstruction_confidence');
        });

        // Reclasser l'historique backfillé : la valeur calculée passe en
        // reconstructed_quantity, accepted/quarantine repassent à NULL (inconnu).
        DB::table('reception_items')->where('disposition_origin', 'reconstruite')->update([
            'reconstructed_quantity'    => DB::raw('accepted_quantity'),
            'accepted_quantity'         => null,
            'quarantine_quantity'       => null,
            'disposition_origin'        => 'legacy_unclassified',
            'reconstruction_confidence' => 'RECONSTRUCTED',
            'reconstructed_at'          => now(),
        ]);

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('accepted_quantity', 14, 4)->nullable()->default(null)->change();
        });
        // L'agrégat accepté historique provenait du backfill reconstruit → inconnu.
        DB::table('purchase_order_items')->whereNotNull('accepted_quantity')->update(['accepted_quantity' => null]);
    }

    public function down(): void
    {
        Schema::table('reception_items', function (Blueprint $table) {
            $table->dropColumn(['reconstructed_quantity', 'reconstruction_confidence', 'reconstructed_at']);
            $table->decimal('accepted_quantity', 10, 4)->default(0)->change();
            $table->decimal('quarantine_quantity', 10, 4)->default(0)->change();
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('accepted_quantity', 14, 4)->default(0)->change();
        });
    }
};
