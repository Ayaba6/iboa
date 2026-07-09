<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité complète avec la fiche SAGE X3 « Fournisseurs : Création complète » :
 * identification étendue, coordonnées, adresse détaillée, paramètres d'achat,
 * réception et comptabilité/finance (compte tiers 401, banque, RIB…).
 * (Contacts et adresses via supplier_contacts / supplier_addresses.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Identification
            $table->foreignId('site_id')->nullable()->after('id')->constrained('warehouses')->nullOnDelete();
            $table->string('civility', 10)->nullable()->after('type');
            $table->string('trade_name', 100)->nullable()->after('name');
            $table->string('mobile', 20)->nullable()->after('phone2');
            $table->string('category', 60)->nullable()->after('rccm');
            $table->string('numero_contribuable', 30)->nullable()->after('ifu');
            $table->string('groupe_fournisseur', 60)->nullable()->after('category');
            $table->string('secteur_activite', 100)->nullable()->after('groupe_fournisseur');
            $table->string('currency', 3)->default('XOF')->after('secteur_activite');
            $table->string('language', 5)->default('FR')->after('currency');
            $table->boolean('soumis_tva')->default(true)->after('is_active');
            $table->boolean('blocage_achat')->default(false)->after('soumis_tva');

            // Coordonnées / adresse détaillée
            $table->string('boite_postale', 60)->nullable()->after('website');
            $table->string('address_line2', 200)->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('address_line2');
            $table->string('quartier', 100)->nullable()->after('city');
            $table->string('region', 100)->nullable()->after('quartier');
            $table->decimal('gps_lat', 10, 6)->nullable()->after('region');
            $table->decimal('gps_lng', 10, 6)->nullable()->after('gps_lat');

            // Paramètres achat
            $table->string('canal', 60)->nullable()->after('country');
            $table->string('famille_tarifaire', 60)->nullable()->after('canal');
            $table->foreignId('tax_rate_id')->nullable()->after('famille_tarifaire')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('default_discount', 5, 2)->nullable()->after('tax_rate_id');
            $table->string('payment_mode', 20)->nullable()->after('default_discount');
            $table->unsignedSmallInteger('payment_days')->nullable()->after('payment_mode');
            $table->decimal('credit_limit', 16, 2)->nullable()->after('payment_days');
            $table->decimal('encours_autorise', 16, 2)->nullable()->after('credit_limit');
            $table->string('compte_collectif', 30)->nullable()->after('encours_autorise');

            // Réception
            $table->foreignId('depot_reception_id')->nullable()->after('compte_collectif')->constrained('warehouses')->nullOnDelete();
            $table->string('mode_livraison', 60)->nullable()->after('depot_reception_id');
            $table->string('transporteur', 100)->nullable()->after('mode_livraison');
            $table->unsignedSmallInteger('delai_livraison')->nullable()->after('transporteur');

            // Comptabilité / Finance
            $table->string('compte_tiers', 30)->nullable()->after('delai_livraison');
            $table->string('condition_paiement', 60)->nullable()->after('compte_tiers');
            $table->string('echeance', 60)->nullable()->after('condition_paiement');
            $table->string('banque', 100)->nullable()->after('echeance');
            $table->string('rib_iban', 40)->nullable()->after('banque');
            $table->string('numero_compte', 30)->nullable()->after('rib_iban');
            $table->string('swift', 20)->nullable()->after('numero_compte');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropConstrainedForeignId('depot_reception_id');
            $table->dropColumn([
                'civility', 'trade_name', 'mobile', 'category', 'numero_contribuable',
                'groupe_fournisseur', 'secteur_activite', 'currency', 'language',
                'soumis_tva', 'blocage_achat', 'boite_postale', 'address_line2', 'postal_code',
                'quartier', 'region', 'gps_lat', 'gps_lng', 'canal', 'famille_tarifaire',
                'default_discount', 'payment_mode', 'payment_days', 'credit_limit', 'encours_autorise',
                'compte_collectif', 'mode_livraison', 'transporteur', 'delai_livraison',
                'compte_tiers', 'condition_paiement', 'echeance', 'banque', 'rib_iban', 'numero_compte', 'swift',
            ]);
        });
    }
};
