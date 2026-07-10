<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Maquette Paramétrage de la société] Champs complémentaires :
 * identification (code, sigle, CNSS, activité, langue, fuseau, ouverture, statut),
 * adresse/contacts (quartier, BP, contact principal, email comptable),
 * fiscalité (régime, TVA, centre des impôts, retenue, nature contribuable),
 * branding (cachet, signature, en-tête/pied PDF) et options globales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Identification
            $table->string('company_code', 30)->nullable();                        // OMA-SN-001
            $table->string('sigle', 20)->nullable();                               // OAMI
            $table->string('cnss_number', 40)->nullable();                         // CNSS / n° employeur
            $table->string('main_activity', 120)->nullable();                      // Fabrication de structures métalliques
            $table->string('language', 20)->nullable()->default('fr');             // Français (France)
            $table->string('timezone', 60)->nullable()->default('GMT');            // (GMT) Afrique de l'Ouest
            $table->date('opened_at')->nullable();                                 // date d'ouverture
            $table->string('status', 15)->nullable()->default('active');           // active | inactive
            $table->text('notes')->nullable();                                     // commentaire

            // Adresse et contacts
            $table->string('district', 80)->nullable();                            // secteur / quartier
            $table->string('po_box', 20)->nullable();                              // BP
            $table->string('main_contact', 100)->nullable();                       // Ousmane Ndiaye
            $table->string('accounting_email', 120)->nullable();                   // compta@oametal.sn

            // Fiscalité et obligations
            $table->string('fiscal_regime', 40)->nullable()->default('reel_normal');
            $table->string('vat_mode', 20)->nullable()->default('collectee');      // collectee | exoneree
            $table->decimal('default_vat_rate', 5, 2)->nullable()->default(18);    // 18 %
            $table->string('tax_center', 80)->nullable();                          // Dakar - Plateau
            $table->string('withholding_regime', 30)->nullable();                  // à la source / TVA
            $table->string('taxpayer_type', 30)->nullable()->default('personne_morale');

            // Branding & documents
            $table->string('stamp_path')->nullable();                              // cachet
            $table->string('signature_path')->nullable();                          // signature DG
            $table->string('pdf_header_path')->nullable();                         // en-tête PDF
            $table->string('pdf_footer_path')->nullable();                         // pied de page PDF

            // Options globales
            $table->boolean('multi_sites')->default(true);
            $table->boolean('vat_management')->default(true);
            $table->boolean('validation_workflow')->default(true);
            $table->boolean('electronic_signature')->default(true);
            $table->boolean('auto_pdf_print')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('secondary_currency')->default(true);
            $table->boolean('maintenance_mode')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'company_code', 'sigle', 'cnss_number', 'main_activity', 'language', 'timezone',
                'opened_at', 'status', 'notes', 'district', 'po_box', 'main_contact', 'accounting_email',
                'fiscal_regime', 'vat_mode', 'default_vat_rate', 'tax_center', 'withholding_regime', 'taxpayer_type',
                'stamp_path', 'signature_path', 'pdf_header_path', 'pdf_footer_path',
                'multi_sites', 'vat_management', 'validation_workflow', 'electronic_signature',
                'auto_pdf_print', 'email_notifications', 'secondary_currency', 'maintenance_mode',
            ]);
        });
    }
};
