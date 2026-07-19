<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Fiche client — parité Sage X3] Champs juridiques/fiscaux, risque crédit et
 * tiers comptables absents de la table clients. Tous nullable (aucun backfill),
 * migration idempotente (guards hasColumn) — sûre sur base déployée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // ── Bloc juridique / fiscal ─────────────────────────────────────
            if (! Schema::hasColumn('clients', 'forme_juridique'))     $table->string('forme_juridique', 60)->nullable()->after('rccm');
            if (! Schema::hasColumn('clients', 'regime_imposition'))   $table->string('regime_imposition', 80)->nullable()->after('tax_regime');
            if (! Schema::hasColumn('clients', 'no_agrement'))         $table->string('no_agrement', 60)->nullable()->after('regime_imposition');

            // ── Bloc risque crédit ──────────────────────────────────────────
            if (! Schema::hasColumn('clients', 'code_risque'))         $table->string('code_risque', 30)->nullable()->after('encours_autorise');
            if (! Schema::hasColumn('clients', 'garantie_montant'))    $table->decimal('garantie_montant', 15, 2)->nullable()->after('code_risque');
            if (! Schema::hasColumn('clients', 'nature_garantie'))     $table->string('nature_garantie', 80)->nullable()->after('garantie_montant');
            if (! Schema::hasColumn('clients', 'assurance_credit'))    $table->string('assurance_credit', 120)->nullable()->after('nature_garantie');
            if (! Schema::hasColumn('clients', 'rrr_montant'))         $table->decimal('rrr_montant', 15, 2)->nullable()->after('assurance_credit');
            if (! Schema::hasColumn('clients', 'rrr_taux'))            $table->decimal('rrr_taux', 5, 2)->nullable()->after('rrr_montant');
            if (! Schema::hasColumn('clients', 'reference_cadastrale')) $table->string('reference_cadastrale', 80)->nullable()->after('rrr_taux');

            // ── Bloc tiers comptables (self-références) ──────────────────────
            if (! Schema::hasColumn('clients', 'client_facture_id'))   $table->unsignedBigInteger('client_facture_id')->nullable()->after('compte_tiers');
            if (! Schema::hasColumn('clients', 'client_payeur_id'))    $table->unsignedBigInteger('client_payeur_id')->nullable()->after('client_facture_id');
            if (! Schema::hasColumn('clients', 'client_groupe_id'))    $table->unsignedBigInteger('client_groupe_id')->nullable()->after('client_payeur_id');
            if (! Schema::hasColumn('clients', 'client_risque_id'))    $table->unsignedBigInteger('client_risque_id')->nullable()->after('client_groupe_id');
            if (! Schema::hasColumn('clients', 'factor_id'))           $table->unsignedBigInteger('factor_id')->nullable()->after('client_risque_id');
        });

        // FK self-référentielles (nullOnDelete : supprimer un tiers ne casse pas
        // les clients qui le référencent).
        Schema::table('clients', function (Blueprint $table) {
            foreach (['client_facture_id', 'client_payeur_id', 'client_groupe_id', 'client_risque_id', 'factor_id'] as $col) {
                try { $table->foreign($col)->references('id')->on('clients')->nullOnDelete(); }
                catch (\Throwable $e) { /* FK déjà posée */ }
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['client_facture_id', 'client_payeur_id', 'client_groupe_id', 'client_risque_id', 'factor_id'] as $col) {
                try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
            }
            $cols = ['forme_juridique', 'regime_imposition', 'no_agrement', 'code_risque',
                     'garantie_montant', 'nature_garantie', 'assurance_credit', 'rrr_montant',
                     'rrr_taux', 'reference_cadastrale', 'client_facture_id', 'client_payeur_id',
                     'client_groupe_id', 'client_risque_id', 'factor_id'];
            $table->dropColumn(array_values(array_filter($cols, fn ($c) => Schema::hasColumn('clients', $c))));
        });
    }
};
