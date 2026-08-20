<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-A3-SALES-LINE-IMMUTABLE-012] Les lignes de commande doivent survivre à
 * la modification de leur commande.
 *
 * `OrderService::update()` faisait `$order->items()->delete()` puis recréait
 * tout. La destruction est PHYSIQUE : les identifiants changent à chaque
 * édition, y compris sur une commande `confirme` ou `en_preparation`. Rien de
 * ce qui pointe une ligne ne peut donc survivre — ni un ordre de fabrication,
 * ni une affectation, ni une réservation, ni une livraison.
 *
 * C'est le préalable à toute traçabilité MTO : une table d'affectation qui
 * référence `order_item_id` serait vidée de son sens dès la première correction
 * de quantité. `SET NULL` n'y changerait rien — la ligne disparaît de toute
 * façon.
 *
 * `quote_items` souffre du même défaut — `QuoteService::update()` applique le
 * même remplacement intégral — mais il est traité à part, sous
 * BUG-A3-SALES-QUOTE-LINE-013. Poser `SoftDeletes` sur les lignes de devis
 * sans leur synchroniseur ne donnerait qu'une moitié de solution : les
 * anciennes lignes seraient conservées, mais leurs identifiants changeraient
 * quand même à chaque édition.
 *
 * Additive et nullable. Aucune ligne existante n'est modifiée ; toutes restent
 * actives, `deleted_at` étant NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'deleted_at')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $t) {
            $t->softDeletes();
            // Les écrans et agrégats filtrent désormais sur `deleted_at` :
            // sans index, chaque total de commande scanne les lignes mortes.
            $t->index('deleted_at');
        });
    }

    public function down(): void
    {
        // Retirer la colonne RESSUSCITERAIT les lignes déjà retirées : elles
        // redeviendraient actives, avec leurs montants, dans des commandes que
        // quelqu'un a explicitement corrigées. Le rollback n'est donc sûr que
        // tant que la suppression logique n'a jamais servi.
        if (DB::table('order_items')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException(
                'Rollback refusé : des lignes de commande sont supprimées logiquement. '
                .'Les retirer les rendrait actives à nouveau.'
            );
        }

        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex(['deleted_at']);
            $t->dropSoftDeletes();
        });
    }
};
