<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [Maquette X3 Paramètres comptables] configuration comptable centralisée —
// singleton par société : référentiel, comptes par défaut, règles, analytique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 30)->nullable();                          // CPT-2026-0001
            $table->string('referentiel', 30)->default('SYSCOHADA');         // SYSCOHADA | IFRS | autre
            $table->string('regime_fiscal', 40)->nullable();
            $table->string('plan_comptable', 60)->default('Plan SYSCOHADA révisé');
            $table->string('base_currency', 10)->default('XOF');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->date('effective_date')->nullable();
            $table->string('status', 20)->default('brouillon');              // brouillon | actif | archive
            $table->text('comment')->nullable();

            // Comptes par défaut (FK vers accounts, nullable = fallback sur le plan SYSCOHADA)
            foreach ([
                'account_client_collectif', 'account_fournisseur_collectif', 'account_ventes',
                'account_achats', 'account_tva_collectee', 'account_tva_deductible',
                'account_stock_mp', 'account_stock_pf', 'account_variation_stock',
                'account_caisse', 'account_banque',
            ] as $col) {
                $table->foreignId($col)->nullable()->constrained('accounts')->nullOnDelete();
            }

            // Règles de comptabilisation
            $table->boolean('auto_ecriture_vente')->default(true);
            $table->boolean('auto_ecriture_achat')->default(true);
            $table->boolean('auto_comptabilisation_stock')->default(true);
            $table->boolean('validation_obligatoire')->default(true);
            $table->boolean('interdire_periode_cloturee')->default(true);
            $table->boolean('lettrage_auto')->default(true);
            $table->boolean('rapprochement_actif')->default(true);
            $table->boolean('analytique_obligatoire')->default(false);

            // Paramètres analytiques
            $table->boolean('section_analytique_obligatoire')->default(false);
            $table->foreignId('centre_cout_defaut_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('axe_analytique_1', 40)->nullable();
            $table->string('axe_analytique_2', 40)->nullable();
            $table->string('axe_analytique_3', 40)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
