<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // [MTO §1.3 cas 2] Approbation exceptionnelle enrichie : motif obligatoire,
            // montant non réglé au moment de l'approbation, durée de validité.
            if (! Schema::hasColumn('orders', 'production_approval_reason')) {
                $table->string('production_approval_reason', 500)->nullable()->after('production_approved_by');
                $table->unsignedBigInteger('production_approval_unpaid')->nullable()->after('production_approval_reason');
                $table->date('production_approval_expires_at')->nullable()->after('production_approval_unpaid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['production_approval_reason', 'production_approval_unpaid', 'production_approval_expires_at']);
        });
    }
};
