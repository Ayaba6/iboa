<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_settings', function (Blueprint $table) {
            // [CDC §9] Taux d'acompte minimal (%) requis avant lancement production
            // pour un client en mode « acompte ». Défaut 70 = comportement historique.
            if (! Schema::hasColumn('sales_settings', 'deposit_required_rate')) {
                $table->decimal('deposit_required_rate', 5, 2)->default(70)->after('discount_validation_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_settings', function (Blueprint $table) {
            $table->dropColumn('deposit_required_rate');
        });
    }
};
