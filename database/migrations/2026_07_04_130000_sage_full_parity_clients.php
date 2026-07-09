<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parité complète avec la fiche SAGE X3 « Clients : Création complète » :
 * identification étendue, coordonnées, adresse principale détaillée,
 * paramètres commerciaux, livraison et comptabilité/finance.
 * (Les contacts et adresses de livraison utilisent les tables existantes
 *  client_contacts / client_addresses.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // 1. Identification
            $table->foreignId('site_id')->nullable()->after('id')->constrained('warehouses')->nullOnDelete();
            $table->string('numero_contribuable', 30)->nullable()->after('ifu');
            $table->string('groupe_client', 60)->nullable()->after('category');
            $table->string('secteur_activite', 100)->nullable()->after('groupe_client');
            $table->string('currency', 3)->default('XOF')->after('secteur_activite');
            $table->string('language', 5)->default('FR')->after('currency');
            $table->string('quartier', 100)->nullable()->after('city');
            $table->boolean('is_livrable')->default(true)->after('is_active');
            $table->boolean('is_facturable')->default(true)->after('is_livrable');
            $table->boolean('soumis_tva')->default(true)->after('is_facturable');
            $table->boolean('blocage_commande')->default(false)->after('soumis_tva');

            // 2. Coordonnées
            $table->string('boite_postale', 60)->nullable()->after('website');

            // 3. Adresse principale détaillée
            $table->string('address_line2', 200)->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('address_line2');
            $table->string('region', 100)->nullable()->after('quartier');
            $table->decimal('gps_lat', 10, 6)->nullable()->after('region');
            $table->decimal('gps_lng', 10, 6)->nullable()->after('gps_lat');

            // 4. Paramètres commerciaux
            $table->string('canal', 60)->nullable()->after('sales_rep_id');
            $table->string('zone_commerciale', 60)->nullable()->after('canal');
            $table->string('famille_tarifaire', 60)->nullable()->after('zone_commerciale');
            $table->decimal('encours_autorise', 16, 2)->nullable()->after('credit_limit');
            $table->string('compte_collectif', 30)->nullable()->after('encours_autorise');

            // 5. Livraison
            $table->foreignId('depot_livraison_id')->nullable()->after('compte_collectif')->constrained('warehouses')->nullOnDelete();
            $table->string('mode_livraison', 60)->nullable()->after('depot_livraison_id');
            $table->string('transporteur', 100)->nullable()->after('mode_livraison');
            $table->unsignedSmallInteger('delai_livraison')->nullable()->after('transporteur');
            $table->string('adresse_livraison_defaut', 60)->nullable()->after('delai_livraison');

            // 6. Comptabilité / Finance
            $table->string('compte_tiers', 30)->nullable()->after('adresse_livraison_defaut');
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
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('depot_livraison_id');
            $table->dropColumn([
                'numero_contribuable', 'groupe_client', 'secteur_activite', 'currency', 'language',
                'quartier', 'is_livrable', 'is_facturable', 'soumis_tva', 'blocage_commande',
                'boite_postale', 'address_line2', 'postal_code', 'region', 'gps_lat', 'gps_lng',
                'canal', 'zone_commerciale', 'famille_tarifaire', 'encours_autorise', 'compte_collectif',
                'mode_livraison', 'transporteur', 'delai_livraison', 'adresse_livraison_defaut',
                'compte_tiers', 'condition_paiement', 'echeance', 'banque', 'rib_iban', 'numero_compte', 'swift',
            ]);
        });
    }
};
