<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conformité fiscale et sociale Burkina Faso — refonte IUTS + CNSS.
 *
 * 1. IUTS : passage du quotient familial (parts) au régime légal BF :
 *    barème progressif appliqué au revenu imposable TOTAL, puis réduction
 *    d'impôt pour charges de famille (8/10/12/14 %) plafonnée à 4 charges.
 * 2. CNSS : plafond mensuel 800 000 / annuel 9 600 000, ventilation
 *    patronale pension 8,5 % + risques pro 1,5 % + prestations fam. 6 %.
 * 3. Versionnement réglementaire (payroll_parameter_versions + brackets).
 * 4. Snapshot des paramètres au calcul d'un run (aucun recalcul rétroactif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Réduction IUTS pour charges de famille : [[nb_charges, pct], ...]
            $table->json('iuts_family_reductions')->nullable()->after('iuts_abattement_rate');
            $table->unsignedTinyInteger('iuts_max_charges')->default(4)->after('iuts_family_reductions');
            // CNSS BF : plafond annuel + ventilation patronale
            $table->unsignedBigInteger('cnss_annual_ceiling')->default(9_600_000)->after('cnss_ceiling');
            $table->decimal('cnss_employer_pension_rate', 5, 2)->default(8.5)->after('cnss_employer_rate');
            $table->decimal('cnss_employer_rp_rate', 5, 2)->default(1.5)->after('cnss_employer_pension_rate');
            $table->decimal('cnss_employer_pf_rate', 5, 2)->default(6.0)->after('cnss_employer_rp_rate');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->json('calculation_parameters_snapshot')->nullable()->after('notes');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('family_charges')->nullable()->after('nb_parts')
                  ->comment('Charges de famille retenues pour la réduction IUTS');
            $table->json('iuts_detail')->nullable()->after('iuts_amount')
                  ->comment('Détail du calcul IUTS par tranche + réduction charges');
            $table->unsignedBigInteger('cnss_employer_pension')->default(0)->after('cnss_employer');
            $table->unsignedBigInteger('cnss_employer_rp')->default(0)->after('cnss_employer_pension');
            $table->unsignedBigInteger('cnss_employer_pf')->default(0)->after('cnss_employer_rp');
        });

        // ─── Versionnement réglementaire ─────────────────────────────────────
        Schema::create('payroll_parameter_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);                    // ex: IUTS_BAREME, CNSS_PLAFOND, SMIG
            $table->string('libelle');
            $table->string('pays', 2)->default('BF');
            $table->json('valeur');                        // scalaire ou structure selon type
            $table->string('type_valeur', 20)->default('montant'); // montant|taux|bareme|json
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('statut', 20)->default('actif'); // actif|inactif|archive
            $table->unsignedInteger('version')->default(1);
            $table->string('reference_legale')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'pays', 'statut']);
            $table->unique(['code', 'pays', 'version']);
        });

        Schema::create('payroll_bareme_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bareme_id')->constrained('payroll_parameter_versions')->cascadeOnDelete();
            $table->unsignedBigInteger('borne_min');
            $table->unsignedBigInteger('borne_max')->nullable(); // NULL = illimité
            $table->decimal('taux', 5, 2);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();

            $table->unique(['bareme_id', 'ordre']);
        });

        // ─── Données : mise à niveau des sociétés existantes ─────────────────
        $officialBrackets = json_encode([
            [30_000,          0],
            [50_000,       12.1],
            [80_000,       13.9],
            [120_000,      15.7],
            [170_000,      18.4],
            [250_000,      21.7],
            [9_999_999_999,  25],
        ]);
        $officialReductions = json_encode([[0, 0], [1, 8], [2, 10], [3, 12], [4, 14]]);

        DB::table('payroll_settings')->update([
            'iuts_brackets'          => $officialBrackets,
            'iuts_family_reductions' => $officialReductions,
            'iuts_max_charges'       => 4,
            'cnss_ceiling'           => 800_000,
            'cnss_annual_ceiling'    => 9_600_000,
            'cnss_employer_rate'     => 16.0,
            'cnss_employer_pension_rate' => 8.5,
            'cnss_employer_rp_rate'  => 1.5,
            'cnss_employer_pf_rate'  => 6.0,
            'smig'                   => 45_000,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bareme_brackets');
        Schema::dropIfExists('payroll_parameter_versions');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['family_charges', 'iuts_detail', 'cnss_employer_pension', 'cnss_employer_rp', 'cnss_employer_pf']);
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('calculation_parameters_snapshot');
        });
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn([
                'iuts_family_reductions', 'iuts_max_charges', 'cnss_annual_ceiling',
                'cnss_employer_pension_rate', 'cnss_employer_rp_rate', 'cnss_employer_pf_rate',
            ]);
        });
    }
};
