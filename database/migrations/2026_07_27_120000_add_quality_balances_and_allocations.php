<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Qualité #1/#2/#5] Soldes QUANTITATIFS par disposition + allocations ciblées.
 *
 * Un statut seul (`libere_partiel`) ne dit pas COMBIEN est libéré, en quarantaine,
 * refusé, retourné ni consommé : la consommation ne peut donc pas être autorisée
 * sur la seule foi du statut. On ajoute des soldes par bobine et par lot.
 *
 * Invariant (données CERTIFIED, statut qualité renseigné) :
 *   reçu = libéré + quarantaine + refusé + retourné
 * Consommable :
 *   dispo_libéré = libéré − consommé − réservé − retourné_depuis_libéré
 *
 * NULL = inconnu (bobines/lots historiques) : aucun solde inventé.
 *
 * `purchase_quality_decision_allocations` : une décision qualité CIBLE des
 * lots/bobines précis avec une quantité par cible — jamais une mise à jour en
 * bloc de toutes les bobines d'une réception.
 *
 * IDEMPOTENTE : le DDL MySQL n'étant pas transactionnel, un échec partiel
 * laisserait des colonnes créées et la migration non enregistrée, donc non
 * rejouable. Chaque ajout est donc gardé par un test d'existence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            foreach ([
                'qty_released'       => 'quality_status',
                'qty_quarantine'     => 'qty_released',
                'qty_rejected'       => 'qty_quarantine',
                'qty_return_pending' => 'qty_rejected',
                'qty_returned'       => 'qty_return_pending',
            ] as $col => $after) {
                if (! Schema::hasColumn('coils', $col)) {
                    $table->decimal($col, 12, 3)->nullable()->after($after);
                }
            }
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            foreach ([
                'qty_released'   => 'quality_status',
                'qty_quarantine' => 'qty_released',
                'qty_rejected'   => 'qty_quarantine',
            ] as $col => $after) {
                if (! Schema::hasColumn('stock_lots', $col)) {
                    $table->decimal($col, 12, 3)->nullable()->after($after);
                }
            }
        });

        if (! Schema::hasTable('purchase_quality_decision_allocations')) {
            Schema::create('purchase_quality_decision_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quality_decision_id');
                $table->unsignedBigInteger('reception_item_id');
                $table->foreignId('stock_lot_id')->nullable();
                $table->foreignId('coil_id')->nullable();
                $table->decimal('quantity', 12, 3);
                $table->string('unit', 10)->nullable();
                $table->string('disposition_before', 20)->nullable();
                $table->string('disposition_after', 20)->nullable();
                $table->timestamps();

                // Noms de contraintes/index EXPLICITES et courts : le nom généré
                // par défaut dépasserait la limite MySQL de 64 caractères
                // (« purchase_quality_decision_allocations_quality_decision_id_foreign »
                // = 65) — accepté par SQLite, refusé par MySQL.
                $table->foreign('quality_decision_id', 'fk_pqda_decision')
                    ->references('id')->on('purchase_quality_decisions')->cascadeOnDelete();
                $table->foreign('reception_item_id', 'fk_pqda_reception_item')
                    ->references('id')->on('reception_items')->restrictOnDelete();

                $table->index(['quality_decision_id'], 'ix_pqda_decision');
                $table->index(['coil_id'], 'ix_pqda_coil');
                $table->index(['stock_lot_id'], 'ix_pqda_lot');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_quality_decision_allocations');

        Schema::table('coils', function (Blueprint $table) {
            foreach (['qty_released', 'qty_quarantine', 'qty_rejected', 'qty_return_pending', 'qty_returned'] as $col) {
                if (Schema::hasColumn('coils', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            foreach (['qty_released', 'qty_quarantine', 'qty_rejected'] as $col) {
                if (Schema::hasColumn('stock_lots', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
