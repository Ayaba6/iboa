<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [SEC-PHASE2 webhooks] Deux livraisons simultanées d'un même webhook ne
// doivent pas créer deux transactions : unicité (provider, external_reference)
// garantie en base (NULL multiples autorisés — transactions initiées côté ERP
// sans référence opérateur encore connue).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_transactions', function (Blueprint $table) {
            $table->unique(['provider', 'external_reference'], 'ext_txn_provider_extref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('external_transactions', function (Blueprint $table) {
            $table->dropUnique('ext_txn_provider_extref_unique');
        });
    }
};
