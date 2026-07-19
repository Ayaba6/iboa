<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Fiche client — précompte BIC] Inclusion/exclusion du client au précompte
 * BIC (Bénéfices Industriels et Commerciaux) burkinabè. Par défaut le client
 * y est soumis ; les entités exonérées (DGE, administrations, exonérés BIC)
 * sont exclues avec un motif. Migration idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'soumis_bic')) {
                $table->boolean('soumis_bic')->default(true)->after('soumis_tva');
            }
            if (! Schema::hasColumn('clients', 'bic_exemption_reason')) {
                $table->string('bic_exemption_reason', 150)->nullable()->after('soumis_bic');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['soumis_bic', 'bic_exemption_reason'],
                fn ($c) => Schema::hasColumn('clients', $c)
            ));
            if ($cols) $table->dropColumn($cols);
        });
    }
};
