<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // [Flux tôle bac] Commande non réglée validée POUR PRODUCTION par le gérant.
            // Rend la commande éligible à la création d'OF sans encaissement préalable.
            if (! Schema::hasColumn('orders', 'production_approved')) {
                $table->boolean('production_approved')->default(false)->after('status');
                $table->timestamp('production_approved_at')->nullable()->after('production_approved');
                $table->foreignId('production_approved_by')->nullable()->after('production_approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_approved_by');
            $table->dropColumn(['production_approved', 'production_approved_at']);
        });
    }
};
