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
 * NULL = inconnu (bobines/lots historiques) : aucun solde inventé, la garde
 * quantitative ne s'applique qu'aux bobines réellement qualifiées.
 *
 * `purchase_quality_decision_allocations` : une décision qualité CIBLE des
 * lots/bobines précis avec une quantité par cible — jamais une mise à jour en
 * bloc de toutes les bobines d'une réception.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coils', function (Blueprint $table) {
            $table->decimal('qty_released', 12, 3)->nullable()->after('quality_status');
            $table->decimal('qty_quarantine', 12, 3)->nullable()->after('qty_released');
            $table->decimal('qty_rejected', 12, 3)->nullable()->after('qty_quarantine');
            $table->decimal('qty_return_pending', 12, 3)->nullable()->after('qty_rejected');
            $table->decimal('qty_returned', 12, 3)->nullable()->after('qty_return_pending');
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->decimal('qty_released', 12, 3)->nullable()->after('quality_status');
            $table->decimal('qty_quarantine', 12, 3)->nullable()->after('qty_released');
            $table->decimal('qty_rejected', 12, 3)->nullable()->after('qty_quarantine');
        });

        Schema::create('purchase_quality_decision_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_decision_id')->constrained('purchase_quality_decisions')->cascadeOnDelete();
            $table->foreignId('reception_item_id')->constrained('reception_items')->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable();
            $table->foreignId('coil_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 10)->nullable();
            $table->string('disposition_before', 20)->nullable();
            $table->string('disposition_after', 20)->nullable();
            $table->timestamps();

            $table->index(['quality_decision_id']);
            $table->index(['coil_id']);
            $table->index(['stock_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_quality_decision_allocations');
        Schema::table('coils', function (Blueprint $table) {
            $table->dropColumn(['qty_released', 'qty_quarantine', 'qty_rejected', 'qty_return_pending', 'qty_returned']);
        });
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropColumn(['qty_released', 'qty_quarantine', 'qty_rejected']);
        });
    }
};
