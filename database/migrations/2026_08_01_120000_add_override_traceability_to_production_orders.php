<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BUG-A3-MTO-FIN-001] Traçabilité minimale de la dérogation financière d'un OF.
 *
 * `production_orders` savait déjà QUI a autorisé, QUAND et POURQUOI
 * (`financial_authorized_by`, `financial_authorized_at`, `financial_notes`),
 * mais pas JUSQU'À QUAND ni SUR QUEL MONTANT. L'approbation gérant portée par
 * la commande dispose de ces deux informations depuis l'origine
 * (`production_approval_expires_at`, `production_approval_unpaid`) : la
 * dérogation de niveau OF était la seule à ne pas les exiger, alors qu'elle
 * autorise exactement le même acte — produire sans couverture.
 *
 * Sans échéance, une dérogation accordée pour une situation ponctuelle reste
 * valable indéfiniment. Sans montant non couvert, on ne peut plus dire, après
 * coup, quel risque a été accepté au moment de la décision.
 *
 * Migration additive et nullable : aucune ligne existante n'est modifiée, et
 * une dérogation ancienne sans échéance reste valable — l'absence d'échéance
 * signifie « sans limite de validité », pas « expirée ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('production_orders', 'financial_authorization_expires_at')) {
                $table->date('financial_authorization_expires_at')->nullable()->after('financial_authorized_by');
            }
            if (! Schema::hasColumn('production_orders', 'financial_authorization_unpaid')) {
                $table->unsignedBigInteger('financial_authorization_unpaid')->nullable()->after('financial_authorization_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['financial_authorization_expires_at', 'financial_authorization_unpaid']);
        });
    }
};
