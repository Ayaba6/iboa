<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité SAGE X3 « Livraison : Création complète ».
 * Complète le bon de livraison avec les informations de transport :
 * mode d'expédition, incoterm, poids, nombre de colis, date de livraison
 * prévue et contact destinataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_notes', 'shipping_mode')) {
                $table->string('shipping_mode', 40)->nullable()->after('carrier');
            }
            if (! Schema::hasColumn('delivery_notes', 'incoterm')) {
                $table->string('incoterm', 10)->nullable()->after('shipping_mode');
            }
            if (! Schema::hasColumn('delivery_notes', 'weight_kg')) {
                $table->decimal('weight_kg', 12, 3)->nullable()->after('incoterm');
            }
            if (! Schema::hasColumn('delivery_notes', 'packages_count')) {
                $table->unsignedInteger('packages_count')->nullable()->after('weight_kg');
            }
            if (! Schema::hasColumn('delivery_notes', 'expected_delivery_at')) {
                $table->date('expected_delivery_at')->nullable()->after('packages_count');
            }
            if (! Schema::hasColumn('delivery_notes', 'delivery_contact')) {
                $table->string('delivery_contact', 120)->nullable()->after('expected_delivery_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_mode', 'incoterm', 'weight_kg',
                'packages_count', 'expected_delivery_at', 'delivery_contact',
            ]);
        });
    }
};
