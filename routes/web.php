<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

// [UI] La racine redirige toujours vers la zone de travail.
// Pas de page d'accueil publique — l'ERP est un outil interne.
Route::get('/', \App\Http\Controllers\RootRedirectController::class);

/*
 * ── Vérification publique de facture (URL signée, aucune auth requise) ──────
 * Accessible via le QR code imprimé sur la facture PDF.
 */
// Rate limited: 30 req/min par IP — prévient l'énumération des numéros de documents
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/verifier/facture/{number}', \App\Http\Controllers\Sales\InvoiceVerifyController::class)
        ->name('invoice.verify');

    Route::get('/verifier/bon-livraison/{number}', \App\Http\Controllers\Sales\DeliveryNoteVerifyController::class)
        ->name('delivery-note.verify');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Changement de société actif (topbar switcher) ───────────────────────────
    Route::post('/company/{company}/switch', CompanySwitchController::class)->name('company.switch');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Endpoint JSON polling — actualisation automatique KPIs (60s cache)
    Route::get('/dashboard/kpis', [DashboardController::class, 'kpisJson'])->name('dashboard.kpis');

    // [CDC §Workflow] « Mes validations » — tout ce qui attend l'action de
    // l'utilisateur selon ses habilitations (le contrôleur filtre par permission).
    Route::get('/mes-validations', [\App\Http\Controllers\MyValidationsController::class, 'index'])->name('validations.index');

    // ── Users (users.manage) ────────────────────────────────────────────────────
    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['destroy']);
        Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
    });

    // ── Roles & Permissions (roles.manage) ──────────────────────────────────────
    Route::middleware('permission:roles.manage')->group(function () {
        Route::resource('roles', RoleController::class)->only(['index', 'show', 'edit', 'update']);
    });

    // ── Company settings (settings.manage) ──────────────────────────────────────
    Route::middleware('permission:settings.manage')->group(function () {
        // [Sync ERP] Administration des synchronisations inter-modules
        Route::get('/admin/synchronisations', [\App\Http\Controllers\SyncLogController::class, 'index'])->name('sync-logs.index');
        Route::post('/admin/synchronisations/{syncLog}/retry', [\App\Http\Controllers\SyncLogController::class, 'retry'])->name('sync-logs.retry');

        Route::get('/parametrage', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/parametrage/general', [CompanyController::class, 'updateGeneral'])->name('company.update.general');
        Route::put('/parametrage/legal', [CompanyController::class, 'updateLegal'])->name('company.update.legal');
        Route::put('/parametrage/documents', [CompanyController::class, 'updateDocuments'])->name('company.update.documents');
        Route::post('/parametrage/banque', [CompanyController::class, 'storeBankAccount'])->name('company.bank.store');
        Route::put('/parametrage/banque/{account}', [CompanyController::class, 'updateBankAccount'])->name('company.bank.update');
        Route::delete('/parametrage/banque/{account}', [CompanyController::class, 'destroyBankAccount'])->name('company.bank.destroy');

        // ── Paramètres généraux ──────────────────────────────────────────────
        Route::prefix('parametres')->name('settings.')->group(function () {
            // [Parametrage Vente X3] hub + parametres generaux + remises + modes reglement
            Route::get('ventes',                          [\App\Http\Controllers\SalesConfigController::class, 'hub'])->name('sales.hub');
            Route::get('ventes/general',                  [\App\Http\Controllers\SalesConfigController::class, 'settings'])->name('sales.settings');
            Route::put('ventes/general',                  [\App\Http\Controllers\SalesConfigController::class, 'updateSettings'])->name('sales.settings.update');
            Route::get('ventes/remises',                  [\App\Http\Controllers\SalesConfigController::class, 'discounts'])->name('sales.discounts');
            Route::post('ventes/remises',                 [\App\Http\Controllers\SalesConfigController::class, 'storeDiscount'])->name('sales.discounts.store');
            Route::put('ventes/remises/{discount}',       [\App\Http\Controllers\SalesConfigController::class, 'updateDiscount'])->name('sales.discounts.update');
            Route::delete('ventes/remises/{discount}',    [\App\Http\Controllers\SalesConfigController::class, 'destroyDiscount'])->name('sales.discounts.destroy');
            Route::get('modes-reglement',                 [\App\Http\Controllers\SalesConfigController::class, 'methods'])->name('payment-methods.index');
            Route::post('modes-reglement',                [\App\Http\Controllers\SalesConfigController::class, 'storeMethod'])->name('payment-methods.store');
            Route::put('modes-reglement/{method}',        [\App\Http\Controllers\SalesConfigController::class, 'updateMethod'])->name('payment-methods.update');

            // Exercices fiscaux
            Route::get('exercices',                                 [\App\Http\Controllers\FiscalYearController::class, 'index'])->name('fiscal-years.index');
            Route::get('exercices/creer',                           [\App\Http\Controllers\FiscalYearController::class, 'create'])->name('fiscal-years.create');
            Route::get('exercices/{fiscalYear}/modifier',           [\App\Http\Controllers\FiscalYearController::class, 'edit'])->name('fiscal-years.edit');
            Route::post('exercices',                                [\App\Http\Controllers\FiscalYearController::class, 'store'])->name('fiscal-years.store');
            Route::put('exercices/{fiscalYear}',                    [\App\Http\Controllers\FiscalYearController::class, 'update'])->name('fiscal-years.update');
            Route::post('exercices/{fiscalYear}/set-current',       [\App\Http\Controllers\FiscalYearController::class, 'setCurrent'])->name('fiscal-years.set-current');
            Route::post('exercices/{fiscalYear}/close',             [\App\Http\Controllers\FiscalYearController::class, 'close'])->name('fiscal-years.close');
            Route::post('exercices/{fiscalYear}/archive',           [\App\Http\Controllers\FiscalYearController::class, 'archive'])->name('fiscal-years.archive');
            Route::post('exercices/{fiscalYear}/report-a-nouveau',  [\App\Http\Controllers\FiscalYearController::class, 'reportANouveau'])->name('fiscal-years.report-a-nouveau');

            // Devises
            Route::get('devises',                                   [\App\Http\Controllers\CurrencyController::class, 'index'])->name('currencies.index');
            Route::post('devises',                                  [\App\Http\Controllers\CurrencyController::class, 'store'])->name('currencies.store');
            Route::put('devises/{currency}',                        [\App\Http\Controllers\CurrencyController::class, 'update'])->name('currencies.update');
            Route::post('devises/{currency}/set-default',           [\App\Http\Controllers\CurrencyController::class, 'setDefault'])->name('currencies.set-default');
            Route::delete('devises/{currency}',                     [\App\Http\Controllers\CurrencyController::class, 'destroy'])->name('currencies.destroy');

            // Taux TVA
            Route::get('taux-tva',                                  [\App\Http\Controllers\TaxRateController::class, 'index'])->name('tax-rates.index');
            Route::post('taux-tva',                                 [\App\Http\Controllers\TaxRateController::class, 'store'])->name('tax-rates.store');
            Route::put('taux-tva/{taxRate}',                        [\App\Http\Controllers\TaxRateController::class, 'update'])->name('tax-rates.update');
            Route::post('taux-tva/{taxRate}/set-default',           [\App\Http\Controllers\TaxRateController::class, 'setDefault'])->name('tax-rates.set-default');
            Route::delete('taux-tva/{taxRate}',                     [\App\Http\Controllers\TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

            // Conditions de paiement
            Route::get('conditions-paiement',                       [\App\Http\Controllers\PaymentTermController::class, 'index'])->name('payment-terms.index');
            Route::post('conditions-paiement',                      [\App\Http\Controllers\PaymentTermController::class, 'store'])->name('payment-terms.store');
            Route::put('conditions-paiement/{paymentTerm}',         [\App\Http\Controllers\PaymentTermController::class, 'update'])->name('payment-terms.update');
            Route::delete('conditions-paiement/{paymentTerm}',      [\App\Http\Controllers\PaymentTermController::class, 'destroy'])->name('payment-terms.destroy');

            // Numérotation automatique
            Route::get('numerotation',                              [\App\Http\Controllers\DocumentSequenceController::class, 'index'])->name('sequences.index');
            Route::put('numerotation-parametres',                   [\App\Http\Controllers\DocumentSequenceController::class, 'saveSettings'])->name('sequences.settings');
            Route::get('numerotation/{sequence}/modifier',          [\App\Http\Controllers\DocumentSequenceController::class, 'edit'])->name('sequences.edit');
            Route::put('numerotation/{sequence}',                   [\App\Http\Controllers\DocumentSequenceController::class, 'update'])->name('sequences.update');
            Route::post('numerotation/{sequence}/reset',            [\App\Http\Controllers\DocumentSequenceController::class, 'reset'])->name('sequences.reset');
            Route::post('numerotation/{sequence}/set-counter',      [\App\Http\Controllers\DocumentSequenceController::class, 'setCounter'])->name('sequences.set-counter');
            Route::post('numerotation/{sequence}/mode',             [\App\Http\Controllers\DocumentSequenceController::class, 'setMode'])->name('sequences.set-mode');
            Route::post('numerotation/{sequence}/lock',             [\App\Http\Controllers\DocumentSequenceController::class, 'toggleLock'])->name('sequences.toggle-lock');
            Route::get('numerotation/{sequence}/historique',        [\App\Http\Controllers\DocumentSequenceController::class, 'audit'])->name('sequences.audit');
            Route::get('numerotation/preview',                      [\App\Http\Controllers\DocumentSequenceController::class, 'preview'])->name('sequences.preview');
        });
    });

    // ── Audit trail (audit.view) ────────────────────────────────────────────────
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // ── Products (products.view minimum) ────────────────────────────────────────
    Route::middleware('permission:products.view')->group(function () {
        Route::resource('products', ProductController::class);
        Route::resource('brands', \App\Http\Controllers\BrandController::class)->except(['show']);
        // 'show' exclu — la méthode n'est pas implémentée (gestion via index + edit suffit pour les familles).
        Route::resource('product-families', \App\Http\Controllers\ProductFamilyController::class)
            ->except(['show'])
            ->parameters(['product-families' => 'family']);
        Route::resource('units', \App\Http\Controllers\UnitController::class)->except(['show']);
        Route::resource('promotions', \App\Http\Controllers\ProductPromotionController::class)->except(['show']);
        Route::post('product-price-tiers', [\App\Http\Controllers\ProductPriceTierController::class, 'store'])->name('product-price-tiers.store');
        Route::delete('product-price-tiers/{tier}', [\App\Http\Controllers\ProductPriceTierController::class, 'destroy'])->name('product-price-tiers.destroy');

        // Quick-create AJAX endpoints (retournent JSON)
        Route::post('quick/brands',   [\App\Http\Controllers\BrandController::class, 'quickStore'])->name('quick.brands.store');
        Route::post('quick/families', [\App\Http\Controllers\ProductFamilyController::class, 'quickStore'])->name('quick.families.store');
        Route::post('quick/units',    [\App\Http\Controllers\UnitController::class, 'quickStore'])->name('quick.units.store');

        // ── Rapports & BI ────────────────────────────────────────────────────────
        // [SECU] Données financières (CA, marges, perf) → réservées à reports.view
        // (le groupe parent products.view ne suffit pas : un magasinier ne doit pas
        //  voir les revenus/marges).
        Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
            Route::get('/ca',                    [\App\Http\Controllers\ReportController::class, 'ca'])->name('ca');
            Route::get('/ca/pdf',                [\App\Http\Controllers\ReportController::class, 'caPdf'])->name('ca-pdf');
            Route::get('/margins',               [\App\Http\Controllers\ReportController::class, 'margins'])->name('margins');
            Route::get('/margins/pdf',           [\App\Http\Controllers\ReportController::class, 'marginsPdf'])->name('margins-pdf');
            Route::get('/sales-performance',     [\App\Http\Controllers\ReportController::class, 'salesPerformance'])->name('sales-performance');
            Route::get('/sales-performance/pdf', [\App\Http\Controllers\ReportController::class, 'salesPerformancePdf'])->name('sales-performance-pdf');
            Route::get('/achats',                [\App\Http\Controllers\ReportController::class, 'achats'])->name('achats');
            Route::get('/aging-receivables',     [\App\Http\Controllers\ReportController::class, 'agingReceivables'])->name('aging-receivables');

            // ── États opérationnels ─────────────────────────────────────────
            Route::get('/journal-ventes',        [\App\Http\Controllers\EtatController::class, 'journalVentes'])->name('journal-ventes');
            Route::get('/etat-stocks',           [\App\Http\Controllers\EtatController::class, 'etatStocks'])->name('etat-stocks');
            Route::get('/mouvements-stock',      [\App\Http\Controllers\EtatController::class, 'mouvementsStock'])->name('mouvements-stock');
            Route::get('/impayes',               [\App\Http\Controllers\EtatController::class, 'impayes'])->name('impayes');
            Route::get('/etat-tva',              [\App\Http\Controllers\EtatController::class, 'etatTva'])->name('etat-tva');
            Route::get('/liste-factures',        [\App\Http\Controllers\EtatController::class, 'listeFactures'])->name('liste-factures');
            Route::get('/liste-devis',           [\App\Http\Controllers\EtatController::class, 'listeDevis'])->name('liste-devis');
            Route::get('/liste-commandes',       [\App\Http\Controllers\EtatController::class, 'listeCommandes'])->name('liste-commandes');
        });
    });

    // ── Gestion (Clients + Fournisseurs) ────────────────────────────────────────
    Route::prefix('gestion')->group(function () {

        // Clients
        Route::middleware('permission:clients.view')->group(function () {
            // Rapports clients — avant la resource pour éviter le conflit {client}
            Route::get('clients/releve',              [\App\Http\Controllers\ClientReportController::class, 'releve'])->name('clients.releve');
            Route::get('clients/releve/export-excel', [\App\Http\Controllers\ClientReportController::class, 'releveExportExcel'])->name('clients.releve.export-excel');
            Route::get('clients/releve/export-pdf',   [\App\Http\Controllers\ClientReportController::class, 'releveExportPdf'])->name('clients.releve.export-pdf');

            Route::get('clients/balance-agee',              [\App\Http\Controllers\ClientReportController::class, 'balanceAgee'])->name('clients.balance-agee');
            Route::get('clients/balance-agee/export-excel', [\App\Http\Controllers\ClientReportController::class, 'balanceAgeeExportExcel'])->name('clients.balance-agee.export-excel');
            Route::get('clients/balance-agee/export-pdf',   [\App\Http\Controllers\ClientReportController::class, 'balanceAgeeExportPdf'])->name('clients.balance-agee.export-pdf');

            Route::get('clients/grand-livre',              [\App\Http\Controllers\ClientReportController::class, 'grandLivreClient'])->name('clients.grand-livre');
            Route::get('clients/grand-livre/export-excel', [\App\Http\Controllers\ClientReportController::class, 'grandLivreExportExcel'])->name('clients.grand-livre.export-excel');
            Route::get('clients/grand-livre/export-pdf',   [\App\Http\Controllers\ClientReportController::class, 'grandLivreExportPdf'])->name('clients.grand-livre.export-pdf');

            Route::resource('clients', ClientController::class);
            Route::post('clients/{client}/interactions', [ClientController::class, 'storeInteraction'])->name('clients.interactions.store');
            Route::get('relances', [\App\Http\Controllers\ClientRelanceController::class, 'index'])->name('relances.index');
            Route::post('relances/send', [\App\Http\Controllers\ClientRelanceController::class, 'send'])->name('relances.send');
            Route::get('relances/lettre', [\App\Http\Controllers\ClientRelanceController::class, 'letter'])->name('relances.letter');

            Route::get('promesses', [\App\Http\Controllers\PaymentPromiseController::class, 'index'])->name('promesses.index');
            Route::post('promesses', [\App\Http\Controllers\PaymentPromiseController::class, 'store'])->name('promesses.store');
            Route::patch('promesses/{promise}/status', [\App\Http\Controllers\PaymentPromiseController::class, 'updateStatus'])->name('promesses.status');
            Route::delete('promesses/{promise}', [\App\Http\Controllers\PaymentPromiseController::class, 'destroy'])->name('promesses.destroy');

            Route::get('contentieux', [\App\Http\Controllers\LitigationCaseController::class, 'index'])->name('contentieux.index');
            Route::post('contentieux', [\App\Http\Controllers\LitigationCaseController::class, 'store'])->name('contentieux.store');
            Route::patch('contentieux/{case}', [\App\Http\Controllers\LitigationCaseController::class, 'update'])->name('contentieux.update');
            Route::delete('contentieux/{case}', [\App\Http\Controllers\LitigationCaseController::class, 'destroy'])->name('contentieux.destroy');

            // ── Représentants commerciaux ──────────────────────────────────────
            Route::resource('representants', \App\Http\Controllers\SalesRepController::class)
                 ->parameters(['representants' => 'representant']);
            Route::patch('commissions/{commission}/status',
                [\App\Http\Controllers\SalesRepController::class, 'updateCommissionStatus']
            )->name('representants.commissions.status');
        });

        // Fournisseurs — Rapports
        Route::middleware('permission:suppliers.view')->group(function () {
            Route::get('fournisseurs/releve',               [\App\Http\Controllers\SupplierReportController::class, 'releve'])->name('suppliers.releve');
            Route::get('fournisseurs/releve/export-excel',  [\App\Http\Controllers\SupplierReportController::class, 'releveExportExcel'])->name('suppliers.releve.export-excel');
            Route::get('fournisseurs/releve/export-pdf',    [\App\Http\Controllers\SupplierReportController::class, 'releveExportPdf'])->name('suppliers.releve.export-pdf');

            Route::get('fournisseurs/balance',              [\App\Http\Controllers\SupplierReportController::class, 'balance'])->name('suppliers.balance');
            Route::get('fournisseurs/balance/export-excel', [\App\Http\Controllers\SupplierReportController::class, 'balanceExportExcel'])->name('suppliers.balance.export-excel');
            Route::get('fournisseurs/balance/export-pdf',   [\App\Http\Controllers\SupplierReportController::class, 'balanceExportPdf'])->name('suppliers.balance.export-pdf');

            Route::get('fournisseurs/balance-agee',               [\App\Http\Controllers\SupplierReportController::class, 'balanceAgee'])->name('suppliers.balance-agee');
            Route::get('fournisseurs/balance-agee/export-excel',  [\App\Http\Controllers\SupplierReportController::class, 'balanceAgeeExportExcel'])->name('suppliers.balance-agee.export-excel');
            Route::get('fournisseurs/balance-agee/export-pdf',    [\App\Http\Controllers\SupplierReportController::class, 'balanceAgeeExportPdf'])->name('suppliers.balance-agee.export-pdf');

            Route::get('fournisseurs/factures-impayees',               [\App\Http\Controllers\SupplierReportController::class, 'facturesImpayees'])->name('suppliers.factures-impayees');
            Route::get('fournisseurs/factures-impayees/export-excel',  [\App\Http\Controllers\SupplierReportController::class, 'facturesImpayeesExportExcel'])->name('suppliers.factures-impayees.export-excel');
            Route::get('fournisseurs/factures-impayees/export-pdf',    [\App\Http\Controllers\SupplierReportController::class, 'facturesImpayeesExportPdf'])->name('suppliers.factures-impayees.export-pdf');

            Route::get('fournisseurs/journal-achats',               [\App\Http\Controllers\SupplierReportController::class, 'journalAchats'])->name('suppliers.journal-achats');
            Route::get('fournisseurs/journal-achats/export-excel',  [\App\Http\Controllers\SupplierReportController::class, 'journalAchatsExportExcel'])->name('suppliers.journal-achats.export-excel');
            Route::get('fournisseurs/journal-achats/export-pdf',    [\App\Http\Controllers\SupplierReportController::class, 'journalAchatsExportPdf'])->name('suppliers.journal-achats.export-pdf');

            Route::get('fournisseurs/grand-livre',               [\App\Http\Controllers\SupplierReportController::class, 'grandLivre'])->name('suppliers.grand-livre');
            Route::get('fournisseurs/grand-livre/export-excel',  [\App\Http\Controllers\SupplierReportController::class, 'grandLivreExportExcel'])->name('suppliers.grand-livre.export-excel');
            Route::get('fournisseurs/grand-livre/export-pdf',    [\App\Http\Controllers\SupplierReportController::class, 'grandLivreExportPdf'])->name('suppliers.grand-livre.export-pdf');
        });

        // Fournisseurs
        Route::middleware('permission:suppliers.view')->group(function () {
            Route::resource('fournisseurs', SupplierController::class)
                ->parameters(['fournisseurs' => 'supplier'])
                ->names([
                    'index'   => 'suppliers.index',
                    'create'  => 'suppliers.create',
                    'store'   => 'suppliers.store',
                    'show'    => 'suppliers.show',
                    'edit'    => 'suppliers.edit',
                    'update'  => 'suppliers.update',
                    'destroy' => 'suppliers.destroy',
                ]);
        });

    });

    // ── Ventes / Module 5 ───────────────────────────────────────────────────────
    Route::prefix('ventes')->name('ventes.')->group(function () {

        // [Parametrage Vente] prix conseille (client + article + unite + qte)
        Route::get('api/prix', function (\Illuminate\Http\Request $r) {
            $r->validate(['product_id' => 'required|exists:products,id']);
            $client  = $r->client_id ? \App\Models\Client::find($r->client_id) : null;
            $product = \App\Models\Product::findOrFail($r->product_id);
            return response()->json(app(\App\Services\Sales\SalesPricingService::class)->resolve(
                $client, $product, $r->integer('unit_id') ?: null, (float) ($r->qty ?? 1)
            ));
        })->name('api.prix');

        // [Maquette X3] Contrats commerciaux
        Route::get('contrats',                    [\App\Http\Controllers\Sales\CommercialContractController::class, 'index'])->name('contrats.index');
        Route::get('contrats/nouveau',            [\App\Http\Controllers\Sales\CommercialContractController::class, 'create'])->name('contrats.create');
        Route::get('contrats/{contrat}',          [\App\Http\Controllers\Sales\CommercialContractController::class, 'show'])->whereNumber('contrat')->name('contrats.show');
        Route::get('contrats/{contrat}/pdf',      [\App\Http\Controllers\Sales\CommercialContractController::class, 'pdf'])->whereNumber('contrat')->name('contrats.pdf');
        Route::post('contrats',                   [\App\Http\Controllers\Sales\CommercialContractController::class, 'store'])->name('contrats.store');
        Route::get('contrats/{contrat}/modifier', [\App\Http\Controllers\Sales\CommercialContractController::class, 'edit'])->name('contrats.edit');
        Route::put('contrats/{contrat}',          [\App\Http\Controllers\Sales\CommercialContractController::class, 'update'])->name('contrats.update');
        Route::delete('contrats/{contrat}',       [\App\Http\Controllers\Sales\CommercialContractController::class, 'destroy'])->name('contrats.destroy');

        // [VENTES-PRO] Tableau de bord ventes (KPIs, top clients, pipeline)
        Route::middleware('permission:invoices.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\Sales\SalesDashboardController::class, 'index'])->name('dashboard');
        });

        Route::middleware('permission:quotes.view')->group(function () {
            Route::get('devis/export', [\App\Http\Controllers\Sales\QuoteController::class, 'export'])->name('devis.export');
            // [Maquette Nouveau devis] import lignes depuis commande (JSON)
            Route::get('devis/order-items', [\App\Http\Controllers\Sales\QuoteController::class, 'orderItems'])->name('devis.order-items');
            Route::resource('devis', \App\Http\Controllers\Sales\QuoteController::class)
                ->parameters(['devis' => 'devis']);
            Route::get('devis/{devis}/pdf',  [\App\Http\Controllers\Sales\QuoteController::class, 'pdf'])->name('devis.pdf');
        });
        Route::middleware('permission:quotes.create')->group(function () {
            // [VENTES-PRO] Action Duplicate (équivalent Odoo)
            Route::post('devis/{devis}/duplicate', [\App\Http\Controllers\Sales\QuoteController::class, 'duplicate'])->name('devis.duplicate');
        });
        // [WORKFLOW-V2] Transformer un devis validé en commande (sales.transform)
        Route::middleware('permission:sales.transform')->group(function () {
            Route::post('devis/{devis}/convert', [\App\Http\Controllers\Sales\QuoteController::class, 'convert'])->name('devis.convert');
        });
        // ── Workflow de validation interne — devis ──────────────────────────────
        Route::middleware('permission:sales.submit')->group(function () {
            Route::post('devis/{devis}/submit',          [\App\Http\Controllers\Sales\QuoteController::class, 'submit'])->name('devis.submit');
        });
        Route::middleware('permission:sales.validate')->group(function () {
            Route::post('devis/{devis}/validate-internal', [\App\Http\Controllers\Sales\QuoteController::class, 'validateInternal'])->name('devis.validate-internal');
        });
        Route::middleware('permission:sales.reject')->group(function () {
            Route::post('devis/{devis}/reject-internal',  [\App\Http\Controllers\Sales\QuoteController::class, 'rejectInternal'])->name('devis.reject-internal');
        });
        Route::middleware('permission:sales.cancel')->group(function () {
            Route::post('devis/{devis}/cancel-internal',  [\App\Http\Controllers\Sales\QuoteController::class, 'cancelInternal'])->name('devis.cancel-internal');
        });

        Route::middleware('permission:orders.view')->group(function () {
            // [Maquette Commande client] import lignes depuis devis (JSON) — avant le resource
            Route::get('commandes/quote-items', [\App\Http\Controllers\Sales\OrderController::class, 'quoteItems'])->name('commandes.quote-items');
            Route::resource('commandes', \App\Http\Controllers\Sales\OrderController::class);
        });
        // [CDC §13.1] Transformer une commande en BL/facture = sales.transform
        // (le commercial transforme ; il ne détient plus orders.validate).
        Route::middleware('permission:sales.transform')->group(function () {
            Route::post('commandes/{commande}/invoice',       [\App\Http\Controllers\Sales\OrderController::class, 'createInvoice'])->name('commandes.invoice');
            Route::post('commandes/{commande}/delivery-note', [\App\Http\Controllers\Sales\OrderController::class, 'createDeliveryNote'])->name('commandes.delivery-note');
        });
        Route::middleware('permission:orders.validate')->group(function () {
            Route::post('commandes/{commande}/confirm',       [\App\Http\Controllers\Sales\OrderController::class, 'confirm'])->name('commandes.confirm');
            Route::post('commandes/{commande}/cancel',        [\App\Http\Controllers\Sales\OrderController::class, 'cancel'])->name('commandes.cancel');
        });
        // ── Workflow de validation interne — commandes ──────────────────────────
        Route::middleware('permission:sales.submit')->group(function () {
            Route::post('commandes/{commande}/submit',           [\App\Http\Controllers\Sales\OrderController::class, 'submit'])->name('commandes.submit');
        });
        Route::middleware('permission:sales.validate')->group(function () {
            Route::post('commandes/{commande}/validate-internal', [\App\Http\Controllers\Sales\OrderController::class, 'validateInternal'])->name('commandes.validate-internal');
        });
        Route::middleware('permission:sales.reject')->group(function () {
            Route::post('commandes/{commande}/reject-internal',  [\App\Http\Controllers\Sales\OrderController::class, 'rejectInternal'])->name('commandes.reject-internal');
        });
        Route::middleware('permission:sales.cancel')->group(function () {
            Route::post('commandes/{commande}/cancel-internal',  [\App\Http\Controllers\Sales\OrderController::class, 'cancelInternal'])->name('commandes.cancel-internal');
        });
        // [CDC §réouverture] Réouvrir une commande annulée — responsable hiérarchique uniquement
        Route::middleware('permission:orders.reopen')->group(function () {
            Route::post('commandes/{commande}/reopen', [\App\Http\Controllers\Sales\OrderController::class, 'reopen'])->name('commandes.reopen');
        });
        // [CDC §cash] Enregistrement paiement comptant par caissier → crée bon de préparation
        Route::middleware('permission:payments.create')->group(function () {
            Route::post('commandes/{commande}/register-payment', [\App\Http\Controllers\Sales\OrderController::class, 'registerPayment'])->name('commandes.register-payment');
        });
        // [CDC §bon-préparation] Module bon de préparation
        Route::middleware('permission:bon_preparations.view')->group(function () {
            Route::resource('bons-preparation', \App\Http\Controllers\Sales\BonPreparationController::class)
                ->parameters(['bons-preparation' => 'bonPreparation'])
                ->only(['index', 'show']);
        });
        Route::middleware('permission:bon_preparations.update')->group(function () {
            Route::post('bons-preparation/{bonPreparation}/start-loading',  [\App\Http\Controllers\Sales\BonPreparationController::class, 'startLoading'])->name('bons-preparation.start-loading');
            Route::post('bons-preparation/{bonPreparation}/finish-loading', [\App\Http\Controllers\Sales\BonPreparationController::class, 'finishLoading'])->name('bons-preparation.finish-loading');
        });

        Route::middleware('permission:deliveries.view')->group(function () {
            Route::resource('bons-livraison', \App\Http\Controllers\Sales\DeliveryNoteController::class)
                ->parameters(['bons-livraison' => 'bonsLivraison'])
                ->only(['index', 'show']);
            Route::get('bons-livraison/{bonsLivraison}/pdf', [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'pdf'])->name('bons-livraison.pdf');
        });
        Route::middleware('permission:deliveries.validate')->group(function () {
            Route::get('bons-livraison/{bonsLivraison}/edit',     [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'edit'])->name('bons-livraison.edit');
            Route::put('bons-livraison/{bonsLivraison}',          [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'update'])->name('bons-livraison.update');
            Route::post('bons-livraison/{bonsLivraison}/validate', [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'validateNote'])->name('bons-livraison.validate');
            Route::post('bons-livraison/{bonsLivraison}/invoice',  [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'createInvoice'])->name('bons-livraison.invoice');
            Route::post('bons-livraison/{bonsLivraison}/cancel',   [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'cancel'])->name('bons-livraison.cancel');
        });
        // ── Workflow de validation interne — bons de livraison ──────────────────
        Route::middleware('permission:sales.submit')->group(function () {
            Route::post('bons-livraison/{bonsLivraison}/submit',           [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'submit'])->name('bons-livraison.submit');
        });
        Route::middleware('permission:sales.validate')->group(function () {
            Route::post('bons-livraison/{bonsLivraison}/validate-internal', [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'validateInternal'])->name('bons-livraison.validate-internal');
        });
        Route::middleware('permission:sales.reject')->group(function () {
            Route::post('bons-livraison/{bonsLivraison}/reject-internal',  [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'rejectInternal'])->name('bons-livraison.reject-internal');
        });
        Route::middleware('permission:sales.cancel')->group(function () {
            Route::post('bons-livraison/{bonsLivraison}/cancel-internal',  [\App\Http\Controllers\Sales\DeliveryNoteController::class, 'cancelInternal'])->name('bons-livraison.cancel-internal');
        });

        Route::middleware('permission:invoices.view')->group(function () {
            Route::get('factures/export-pdf', [\App\Http\Controllers\Sales\InvoiceController::class, 'exportPdf'])->name('factures.export-pdf');
            // [Maquette Facture] import lignes depuis commande / BL (JSON)
            Route::get('factures/order-items', [\App\Http\Controllers\Sales\InvoiceController::class, 'orderItems'])->name('factures.order-items');
            Route::get('factures/dn-items', [\App\Http\Controllers\Sales\InvoiceController::class, 'deliveryNoteItems'])->name('factures.dn-items');
            // [INVOICE-LOCK] Verrouille PUT/PATCH/DELETE quand status=payee ou annulee — renvoie 403
            // [CONCURRENCE] Anti-double-soumission intégré via x-form-guard (idempotency middleware)
            Route::resource('factures', \App\Http\Controllers\Sales\InvoiceController::class)
                ->middleware('invoice.locked');
            Route::get('factures/{facture}/pdf', [\App\Http\Controllers\Sales\InvoiceController::class, 'pdf'])->name('factures.pdf');
            // Échéancier client sur facture
            Route::post('factures/{facture}/schedules',        [\App\Http\Controllers\Treasury\ClientPaymentScheduleController::class, 'store'])->name('factures.schedules.store');
            Route::post('factures/{facture}/schedules-custom', [\App\Http\Controllers\Treasury\ClientPaymentScheduleController::class, 'storeCustom'])->name('factures.schedules.store-custom');
            Route::delete('factures/{facture}/schedules',      [\App\Http\Controllers\Treasury\ClientPaymentScheduleController::class, 'destroyAll'])->name('factures.schedules.destroy-all');
        });
        Route::middleware(['permission:invoices.validate', 'invoice.locked'])->group(function () {
            Route::post('factures/{facture}/validate', [\App\Http\Controllers\Sales\InvoiceController::class, 'validateInvoice'])->name('factures.validate');
            // [BUG-FIX] Exposition de l'annulation : contre-passation comptable automatique
            Route::post('factures/{facture}/cancel',   [\App\Http\Controllers\Sales\InvoiceController::class, 'cancelInvoice'])->name('factures.cancel');
            // [MED-1] Conversion proforma → standard (génère compta + stock)
            Route::post('factures/{facture}/convert-proforma', [\App\Http\Controllers\Sales\InvoiceController::class, 'convertProforma'])->name('factures.convert-proforma');
        });
        // ── Workflow de validation interne — factures ───────────────────────────
        Route::middleware('permission:sales.submit')->group(function () {
            Route::post('factures/{facture}/submit',           [\App\Http\Controllers\Sales\InvoiceController::class, 'submit'])->name('factures.submit');
        });
        Route::middleware('permission:sales.validate')->group(function () {
            Route::post('factures/{facture}/validate-internal', [\App\Http\Controllers\Sales\InvoiceController::class, 'validateInternal'])->name('factures.validate-internal');
        });
        Route::middleware('permission:sales.reject')->group(function () {
            Route::post('factures/{facture}/reject-internal',  [\App\Http\Controllers\Sales\InvoiceController::class, 'rejectInternal'])->name('factures.reject-internal');
        });
        Route::middleware('permission:sales.cancel')->group(function () {
            Route::post('factures/{facture}/cancel-internal',  [\App\Http\Controllers\Sales\InvoiceController::class, 'cancelInternal'])->name('factures.cancel-internal');
        });
        Route::middleware(['permission:invoices.send', 'invoice.locked'])->group(function () {
            Route::post('factures/{facture}/send-email', [\App\Http\Controllers\Sales\InvoiceController::class, 'sendEmail'])->name('factures.send-email');
        });

        Route::middleware('permission:credit_notes.view')->group(function () {
            Route::resource('avoirs', \App\Http\Controllers\Sales\CreditNoteController::class)
                ->parameters(['avoirs' => 'avoir'])
                ->only(['index', 'create', 'store', 'show', 'destroy']);
            Route::get('avoirs/{avoir}/pdf', [\App\Http\Controllers\Sales\CreditNoteController::class, 'pdf'])->name('avoirs.pdf');
        });
        Route::middleware('permission:credit_notes.create')->group(function () {
            Route::post('avoirs/{avoir}/validate', [\App\Http\Controllers\Sales\CreditNoteController::class, 'validateNote'])->name('avoirs.validate');
            Route::post('avoirs/{avoir}/apply', [\App\Http\Controllers\Sales\CreditNoteController::class, 'applyToInvoice'])->name('avoirs.apply');
        });
        // ── Workflow de validation interne — avoirs ─────────────────────────────
        Route::middleware('permission:sales.submit')->group(function () {
            Route::post('avoirs/{avoir}/submit',           [\App\Http\Controllers\Sales\CreditNoteController::class, 'submit'])->name('avoirs.submit');
        });
        Route::middleware('permission:sales.validate')->group(function () {
            Route::post('avoirs/{avoir}/validate-internal', [\App\Http\Controllers\Sales\CreditNoteController::class, 'validateInternal'])->name('avoirs.validate-internal');
        });
        Route::middleware('permission:sales.reject')->group(function () {
            Route::post('avoirs/{avoir}/reject-internal',  [\App\Http\Controllers\Sales\CreditNoteController::class, 'rejectInternal'])->name('avoirs.reject-internal');
        });
        Route::middleware('permission:sales.cancel')->group(function () {
            Route::post('avoirs/{avoir}/cancel-internal',  [\App\Http\Controllers\Sales\CreditNoteController::class, 'cancelInternal'])->name('avoirs.cancel-internal');
        });
    });

    // ── Achats / Module 6 ───────────────────────────────────────────────────────
    Route::prefix('achats')->name('achats.')->group(function () {

        Route::middleware('permission:purchase_orders.view')->group(function () {
            // [ACHATS-PRO] Dashboard + insights — placés AVANT le resource pour ne pas
            // que /commandes/{commande} matche /dashboard, /matching, /suppliers...
            Route::get('/',                  [\App\Http\Controllers\Purchases\PurchaseDashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('matching',           [\App\Http\Controllers\Purchases\PurchaseDashboardController::class, 'matching'])->name('dashboard.matching');
            Route::get('evaluation',         [\App\Http\Controllers\Purchases\PurchaseDashboardController::class, 'suppliersScorecards'])->name('dashboard.suppliers');
        });
        Route::middleware('permission:purchase_orders.create')->group(function () {
            Route::get('restock-po',         [\App\Http\Controllers\Purchases\PurchaseDashboardController::class, 'restockToPo'])->name('dashboard.restock-po');
            Route::post('restock-po',        [\App\Http\Controllers\Purchases\PurchaseDashboardController::class, 'restockToPo'])->name('dashboard.restock-po.post');
        });

        // [ACHATS-PRO-SCHEDULE] Cadenciers de paiement
        Route::middleware('permission:supplier_invoices.view')->group(function () {
            Route::get('echeances', [\App\Http\Controllers\Purchases\PaymentScheduleController::class, 'upcoming'])->name('schedules.upcoming');
        });
        Route::middleware('permission:supplier_invoices.create')->group(function () {
            Route::post('factures-fournisseurs/{facturesFournisseur}/schedules',        [\App\Http\Controllers\Purchases\PaymentScheduleController::class, 'store'])->name('schedules.store');
            Route::post('factures-fournisseurs/{facturesFournisseur}/schedules-custom', [\App\Http\Controllers\Purchases\PaymentScheduleController::class, 'storeCustom'])->name('schedules.store-custom');
            Route::delete('schedules/{schedule}',                                       [\App\Http\Controllers\Purchases\PaymentScheduleController::class, 'destroy'])->name('schedules.destroy');
        });

        // [ACHATS-PRO-APPROVAL] Workflow validation PO par seuil
        Route::middleware('permission:purchase_orders.view')->group(function () {
            Route::get('approbations',           [\App\Http\Controllers\Purchases\PoApprovalController::class, 'pending'])->name('approval.pending');
            Route::post('approbations/{commande}/submit',  [\App\Http\Controllers\Purchases\PoApprovalController::class, 'submit'])->name('approval.submit');
            Route::post('approbations/{commande}/approve', [\App\Http\Controllers\Purchases\PoApprovalController::class, 'approve'])->name('approval.approve');
            Route::post('approbations/{commande}/reject',  [\App\Http\Controllers\Purchases\PoApprovalController::class, 'reject'])->name('approval.reject');
        });
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('approbations/seuils',                       [\App\Http\Controllers\Purchases\PoApprovalController::class, 'thresholdsIndex'])->name('approval.thresholds.index');
            Route::post('approbations/seuils',                      [\App\Http\Controllers\Purchases\PoApprovalController::class, 'thresholdsStore'])->name('approval.thresholds.store');
            Route::delete('approbations/seuils/{threshold}',        [\App\Http\Controllers\Purchases\PoApprovalController::class, 'thresholdsDestroy'])->name('approval.thresholds.destroy');
        });

        // [ACHATS-PRO-RFQ] Demandes de devis (Request For Quotation)
        Route::middleware('permission:purchase_orders.view')->group(function () {
            Route::get('rfq',                          [\App\Http\Controllers\Purchases\RfqController::class, 'index'])->name('rfq.index');
            Route::get('rfq/{rfq}',                    [\App\Http\Controllers\Purchases\RfqController::class, 'show'])->whereNumber('rfq')->name('rfq.show');
            Route::get('rfq/{rfq}/compare',            [\App\Http\Controllers\Purchases\RfqController::class, 'compare'])->whereNumber('rfq')->name('rfq.compare');
        });
        Route::middleware('permission:purchase_orders.create')->group(function () {
            Route::get('rfq/create',                   [\App\Http\Controllers\Purchases\RfqController::class, 'create'])->name('rfq.create');
            Route::post('rfq',                         [\App\Http\Controllers\Purchases\RfqController::class, 'store'])->name('rfq.store');
            Route::delete('rfq/{rfq}',                 [\App\Http\Controllers\Purchases\RfqController::class, 'destroy'])->whereNumber('rfq')->name('rfq.destroy');
            Route::post('rfq/{rfq}/send',              [\App\Http\Controllers\Purchases\RfqController::class, 'send'])->whereNumber('rfq')->name('rfq.send');
            Route::post('rfq/{rfq}/record-quote',      [\App\Http\Controllers\Purchases\RfqController::class, 'recordQuote'])->whereNumber('rfq')->name('rfq.record-quote');
            Route::post('rfq/{rfq}/award/{quote}',     [\App\Http\Controllers\Purchases\RfqController::class, 'award'])->whereNumber('rfq')->whereNumber('quote')->name('rfq.award');
            Route::post('rfq/{rfq}/cancel',            [\App\Http\Controllers\Purchases\RfqController::class, 'cancel'])->whereNumber('rfq')->name('rfq.cancel');
        });

        Route::middleware('permission:purchase_orders.view')->group(function () {
            // [Maquette Commande fournisseur] import lignes depuis DA approuvée (JSON) — avant le resource
            Route::get('commandes/pr-items', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'prItems'])->name('commandes.pr-items');
            Route::resource('commandes', \App\Http\Controllers\Purchases\PurchaseOrderController::class);
            Route::get('commandes/{commande}/pdf', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'pdf'])->name('commandes.pdf');
        });
        Route::middleware('permission:purchase_orders.create')->group(function () {
            Route::post('commandes/{commande}/confirm', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'confirm'])->name('commandes.confirm');
            Route::post('commandes/{commande}/duplicate', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'duplicate'])->name('commandes.duplicate');
        });
        Route::middleware('permission:receptions.create')->group(function () {
            Route::post('commandes/{commande}/reception', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'createReception'])->name('commandes.reception');
        });

        Route::middleware('permission:receptions.view')->group(function () {
            Route::get('receptions', [\App\Http\Controllers\Purchases\ReceptionController::class, 'index'])->name('receptions.index');
            Route::get('receptions/{reception}', [\App\Http\Controllers\Purchases\ReceptionController::class, 'show'])->name('receptions.show');
        });
        Route::middleware('permission:receptions.create')->group(function () {
            Route::post('receptions/{reception}/validate', [\App\Http\Controllers\Purchases\ReceptionController::class, 'validateReception'])->name('receptions.validate');
        });
        Route::middleware('permission:supplier_invoices.create')->group(function () {
            Route::post('commandes/{commande}/facture', [\App\Http\Controllers\Purchases\PurchaseOrderController::class, 'createSupplierInvoice'])->name('commandes.facture');
        });

        Route::middleware('permission:supplier_invoices.view')->group(function () {
            // [Maquette Facture fournisseur] import lignes depuis BC (JSON) — avant le resource
            Route::get('factures-fournisseurs/po-items', [\App\Http\Controllers\Purchases\SupplierInvoiceController::class, 'poItems'])->name('factures-fournisseurs.po-items');
            // [INVOICE-LOCK] Verrouille PUT/PATCH/DELETE sur FF payee/annulee → 403
            Route::resource('factures-fournisseurs', \App\Http\Controllers\Purchases\SupplierInvoiceController::class)
                ->parameters(['factures-fournisseurs' => 'facturesFournisseur'])
                ->middleware('invoice.locked');
        });
        // [FIX-CRITIQUE] validate and payment require create permission, not just view
        Route::middleware(['permission:supplier_invoices.create', 'invoice.locked'])->group(function () {
            Route::post('factures-fournisseurs/{facturesFournisseur}/validate', [\App\Http\Controllers\Purchases\SupplierInvoiceController::class, 'validateInvoice'])->name('factures-fournisseurs.validate');
            Route::post('factures-fournisseurs/{facturesFournisseur}/payment', [\App\Http\Controllers\Purchases\SupplierInvoiceController::class, 'recordPayment'])->name('factures-fournisseurs.payment');
        });

        Route::middleware('permission:supplier_returns.view')->group(function () {
            // [Maquette Retour fournisseur] import lignes depuis réception (JSON) — avant le resource
            Route::get('retours-fournisseurs/reception-items', [\App\Http\Controllers\Purchases\SupplierReturnController::class, 'receptionItems'])->name('retours-fournisseurs.reception-items');
            Route::resource('retours-fournisseurs', \App\Http\Controllers\Purchases\SupplierReturnController::class)
                ->parameters(['retours-fournisseurs' => 'retoursFournisseurs'])
                ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
            Route::get('retours-fournisseurs/{retoursFournisseurs}/pdf', [\App\Http\Controllers\Purchases\SupplierReturnController::class, 'pdf'])->name('retours-fournisseurs.pdf');
        });
        Route::middleware('permission:supplier_returns.validate')->group(function () {
            Route::post('retours-fournisseurs/{retoursFournisseurs}/validate', [\App\Http\Controllers\Purchases\SupplierReturnController::class, 'validateReturn'])->name('retours-fournisseurs.validate');
        });

        Route::middleware('permission:purchase_requests.view')->group(function () {
            Route::resource('demandes-achat', \App\Http\Controllers\Purchases\PurchaseRequestController::class)
                ->parameters(['demandes-achat' => 'demandesAchat'])
                ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        });
        Route::middleware('permission:purchase_requests.submit')->group(function () {
            Route::post('demandes-achat/{demandesAchat}/submit', [\App\Http\Controllers\Purchases\PurchaseRequestController::class, 'submit'])->name('demandes-achat.submit');
        });
        Route::middleware('permission:purchase_requests.approve')->group(function () {
            Route::post('demandes-achat/{demandesAchat}/approve',  [\App\Http\Controllers\Purchases\PurchaseRequestController::class, 'approve'])->name('demandes-achat.approve');
            Route::post('demandes-achat/{demandesAchat}/reject',   [\App\Http\Controllers\Purchases\PurchaseRequestController::class, 'reject'])->name('demandes-achat.reject');
            Route::post('demandes-achat/{demandesAchat}/convert',  [\App\Http\Controllers\Purchases\PurchaseRequestController::class, 'convert'])->name('demandes-achat.convert');
        });
    });

    // ── Stocks / Module 7 ───────────────────────────────────────────────────────
    Route::prefix('stocks')->name('stocks.')->group(function () {

        Route::middleware('permission:stocks.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\Stock\StockController::class, 'index'])->name('index');
            // [STOCK-PRO] Tableau de bord stock + insights avancés
            Route::get('dashboard',          [\App\Http\Controllers\Stock\StockDashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('alertes-reappro',    [\App\Http\Controllers\Stock\StockDashboardController::class, 'restock'])->name('dashboard.restock');
            Route::get('dormants',           [\App\Http\Controllers\Stock\StockDashboardController::class, 'dormant'])->name('dashboard.dormant');
            Route::get('peremption',         [\App\Http\Controllers\Stock\StockDashboardController::class, 'expiry'])->name('dashboard.expiry');
            Route::get('abc',                [\App\Http\Controllers\Stock\StockDashboardController::class, 'abc'])->name('dashboard.abc');

            // [STOCK-PRO] Transferts inter-dépôts (lecture).
            // {transfer} is constrained to digits so the literal /create route in the
            // adjust block isn't shadowed by show.
            Route::get('transferts',                       [\App\Http\Controllers\Stock\StockTransferController::class, 'index'])->name('transfers.index');
            Route::get('transferts/{transfer}',            [\App\Http\Controllers\Stock\StockTransferController::class, 'show'])
                ->whereNumber('transfer')
                ->name('transfers.show');
            Route::get('export', [\App\Http\Controllers\Stock\StockController::class, 'export'])->name('export');
            Route::get('export-pdf', [\App\Http\Controllers\Stock\StockController::class, 'exportPdf'])->name('export-pdf');
            Route::get('mouvements', [\App\Http\Controllers\Stock\StockController::class, 'movements'])->name('movements');
            Route::get('mouvements-pdf', [\App\Http\Controllers\Stock\StockController::class, 'movementsPdf'])->name('movements-pdf');
            Route::get('lots', [\App\Http\Controllers\Stock\StockController::class, 'lots'])->name('lots');
            // §8 CDC — Traçabilité inverse : lot → OF → clients impactés
            Route::get('lots/{lot}/traceabilite', [\App\Http\Controllers\Stock\StockController::class, 'lotTraceability'])->name('lots.traceability');
            Route::get('valorisation', [\App\Http\Controllers\Stock\StockController::class, 'valuation'])->name('valuation');
            Route::get('produit/{product}', [\App\Http\Controllers\Stock\StockController::class, 'show'])->name('show');
        });

        Route::middleware('permission:stocks.adjust')->group(function () {
            // [Maquette X3] formulaire multi-lignes remplace l'ancien mono-article
            Route::get('mouvement/nouveau', [\App\Http\Controllers\Stock\StockController::class, 'createManualMovement'])->name('movement.create');
            Route::post('mouvement', [\App\Http\Controllers\Stock\StockController::class, 'storeManualMovement'])->name('movement.store');
            // [FIX mouvement bloqué] applique les lignes différées au stock
            Route::post('mouvement/{movement}/debloquer', [\App\Http\Controllers\Stock\StockController::class, 'unblockManualMovement'])->name('movement.unblock');
            // Seuils min/max editor (batch)
            Route::get('seuils',  [\App\Http\Controllers\Stock\StockController::class, 'seuils'])->name('seuils');
            Route::post('seuils', [\App\Http\Controllers\Stock\StockController::class, 'seuilsUpdate'])->name('seuils.update');

            // [STOCK-PRO] Transferts inter-dépôts (écriture). Literal /create avant toute capture {transfer}.
            Route::get('transferts/create',                [\App\Http\Controllers\Stock\StockTransferController::class, 'create'])->name('transfers.create');
            Route::post('transferts',                      [\App\Http\Controllers\Stock\StockTransferController::class, 'store'])->name('transfers.store');
            Route::get('transferts/{transfer}/edit',       [\App\Http\Controllers\Stock\StockTransferController::class, 'edit'])->whereNumber('transfer')->name('transfers.edit');
            Route::put('transferts/{transfer}',            [\App\Http\Controllers\Stock\StockTransferController::class, 'update'])->whereNumber('transfer')->name('transfers.update');
            Route::delete('transferts/{transfer}',         [\App\Http\Controllers\Stock\StockTransferController::class, 'destroy'])->whereNumber('transfer')->name('transfers.destroy');
            Route::post('transferts/{transfer}/ship',      [\App\Http\Controllers\Stock\StockTransferController::class, 'ship'])->whereNumber('transfer')->name('transfers.ship');
            Route::post('transferts/{transfer}/receive',   [\App\Http\Controllers\Stock\StockTransferController::class, 'receive'])->whereNumber('transfer')->name('transfers.receive');
            Route::post('transferts/{transfer}/cancel',    [\App\Http\Controllers\Stock\StockTransferController::class, 'cancel'])->whereNumber('transfer')->name('transfers.cancel');
            Route::resource('warehouses', \App\Http\Controllers\Stock\WarehouseController::class);
            // Emplacements (locations within a warehouse)
            Route::get('warehouses/{warehouse}/locations/create',                   [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'create'])->name('warehouses.locations.create');
            Route::post('warehouses/{warehouse}/locations',                         [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'store'])->name('warehouses.locations.store');
            Route::get('warehouses/{warehouse}/locations/{location}/edit',          [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'edit'])->name('warehouses.locations.edit');
            Route::put('warehouses/{warehouse}/locations/{location}',               [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'update'])->name('warehouses.locations.update');
            Route::delete('warehouses/{warehouse}/locations/{location}',            [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'destroy'])->name('warehouses.locations.destroy');
        });
        Route::middleware('permission:stocks.view')->group(function () {
            Route::get('warehouses/{warehouse}/locations',                          [\App\Http\Controllers\Stock\WarehouseLocationController::class, 'index'])->name('warehouses.locations.index');
        });

        Route::middleware('permission:inventory.view')->group(function () {
            Route::resource('inventaires', \App\Http\Controllers\Stock\InventoryController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['inventaires' => 'inventorySession']);
            Route::post('inventaires/{inventorySession}/count', [\App\Http\Controllers\Stock\InventoryController::class, 'saveCount'])
                ->name('inventaires.count');
            Route::get('inventaires/{inventorySession}/export-excel', [\App\Http\Controllers\Stock\InventoryController::class, 'exportExcel'])
                ->name('inventaires.export-excel');
            Route::get('inventaires/{inventorySession}/export-pdf', [\App\Http\Controllers\Stock\InventoryController::class, 'exportPdf'])
                ->name('inventaires.export-pdf');
        });
        Route::middleware('permission:inventory.validate')->group(function () {
            Route::post('inventaires/{inventorySession}/validate', [\App\Http\Controllers\Stock\InventoryController::class, 'validateInventory'])
                ->name('inventaires.validate');
        });
    });

    // ── Comptabilité / Module 9 ─────────────────────────────────────────────
    Route::prefix('comptabilite')->name('comptabilite.')->group(function () {

        // [Maquette X3] Paramètres comptables centralisés
        Route::middleware('permission:accounting.manage')->group(function () {
            Route::get('parametres',  [\App\Http\Controllers\Accounting\AccountingSettingController::class, 'edit'])->name('parametres.edit');
            Route::put('parametres',  [\App\Http\Controllers\Accounting\AccountingSettingController::class, 'update'])->name('parametres.update');
        });

        // [Maquette X3] Annuaire des tiers (clients + fournisseurs unifiés)
        Route::get('tiers', [\App\Http\Controllers\Accounting\TiersController::class, 'index'])->name('tiers.index');
        Route::get('tiers/releve-client', [\App\Http\Controllers\Accounting\ClientStatementController::class, 'show'])->name('tiers.releve-client');
        Route::post('tiers/releve-client/envoyer', [\App\Http\Controllers\Accounting\ClientStatementController::class, 'send'])->name('tiers.releve-client.send');
        Route::post('tiers/releve-client/commentaire', [\App\Http\Controllers\Accounting\ClientStatementController::class, 'comment'])->name('tiers.releve-client.comment');

        // [Maquette X3] Budgets comptables par compte général
        Route::get('budgets', [\App\Http\Controllers\Accounting\BudgetController::class, 'index'])->name('budgets.index');
        Route::middleware('permission:accounting.manage')->group(function () {
            Route::post('budgets',                                  [\App\Http\Controllers\Accounting\BudgetController::class, 'store'])->name('budgets.store');
            Route::post('budgets/{budget}/lignes',                  [\App\Http\Controllers\Accounting\BudgetController::class, 'storeLine'])->name('budgets.lines.store');
            Route::delete('budgets/{budget}/lignes/{line}',         [\App\Http\Controllers\Accounting\BudgetController::class, 'destroyLine'])->name('budgets.lines.destroy');
            Route::post('budgets/{budget}/valider',                 [\App\Http\Controllers\Accounting\BudgetController::class, 'validateBudget'])->name('budgets.validate');
        });

        Route::middleware('permission:accounting.view')->group(function () {
            // Plan comptable — extra routes must come BEFORE the resource to avoid {account} conflict
            Route::get('plan-comptable/export',   [\App\Http\Controllers\Accounting\ChartOfAccountsController::class, 'export'])  ->name('plan-comptable.export');
            Route::get('plan-comptable/export-pdf', [\App\Http\Controllers\Accounting\ChartOfAccountsController::class, 'exportPdf'])->name('plan-comptable.export-pdf');
            Route::post('plan-comptable/import',  [\App\Http\Controllers\Accounting\ChartOfAccountsController::class, 'import'])  ->name('plan-comptable.import');
            Route::get('plan-comptable/template', [\App\Http\Controllers\Accounting\ChartOfAccountsController::class, 'template'])->name('plan-comptable.template');

            Route::resource('plan-comptable', \App\Http\Controllers\Accounting\ChartOfAccountsController::class)
                ->parameters(['plan-comptable' => 'account'])
                ->only(['index', 'create', 'store', 'edit', 'update']);

            // Journaux export (must be before resource to avoid matching {journalEntry})
            Route::get('journaux/export', [\App\Http\Controllers\Accounting\LedgerController::class, 'journauxExport'])->name('journaux.export');
            Route::get('journaux/export-pdf', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'exportPdf'])->name('journaux.export-pdf');

            // [COMPTA-PRO-01] Tableau de bord comptable — page d'accueil module
            Route::get('/', [\App\Http\Controllers\Accounting\DashboardController::class, 'index'])->name('dashboard');

            // [COMPTA-PRO-02] Export FEC (Fichier des Écritures Comptables)
            Route::get('fec',        [\App\Http\Controllers\Accounting\FecExportController::class, 'index'])->name('fec.index');
            Route::get('fec/export', [\App\Http\Controllers\Accounting\FecExportController::class, 'export'])->name('fec.export');

            // [COMPTA-PRO-03] Soldes Intermédiaires de Gestion (SIG)
            Route::get('sig', [\App\Http\Controllers\Accounting\SigController::class, 'index'])->name('sig');

            // [COMPTA-PRO-05] Verrouillage des périodes mensuelles
            Route::get('periodes',                   [\App\Http\Controllers\Accounting\PeriodLockController::class, 'index'])->name('periods.index');
            Route::post('periodes/lock',             [\App\Http\Controllers\Accounting\PeriodLockController::class, 'lock'])->name('periods.lock');
            Route::delete('periodes/{lock}',         [\App\Http\Controllers\Accounting\PeriodLockController::class, 'unlock'])->name('periods.unlock');

            // Journal des écritures
            // [COMPTA-FIX-02] Added edit/update so drafts can be modified without recreating them.
            Route::resource('journaux', \App\Http\Controllers\Accounting\JournalEntryController::class)
                ->parameters(['journaux' => 'journalEntry'])
                ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

            // [COMPTA-JT] CRUD des codes journaux (référentiel)
            Route::resource('codes-journaux', \App\Http\Controllers\Accounting\JournalTypeController::class)
                ->parameters(['codes-journaux' => 'journalType'])
                ->names('journal-types')
                ->except(['show']);

            // Grand livre & balance
            Route::get('grand-livre',        [\App\Http\Controllers\Accounting\LedgerController::class, 'grandLivre'])->name('grand-livre');
            Route::get('grand-livre/export', [\App\Http\Controllers\Accounting\LedgerController::class, 'grandLivreExport'])->name('grand-livre.export');
            Route::get('grand-livre/pdf',    [\App\Http\Controllers\Accounting\LedgerController::class, 'grandLivrePdf'])->name('grand-livre.pdf');
            Route::get('balance',            [\App\Http\Controllers\Accounting\LedgerController::class, 'balance'])->name('balance');
            Route::get('balance/export',     [\App\Http\Controllers\Accounting\LedgerController::class, 'balanceExport'])->name('balance.export');
            Route::get('balance/pdf',        [\App\Http\Controllers\Accounting\LedgerController::class, 'balancePdf'])->name('balance.pdf');

            Route::get('brouillard',          [\App\Http\Controllers\Accounting\LedgerController::class, 'brouillard'])->name('brouillard');
            Route::get('livre-journal',       [\App\Http\Controllers\Accounting\LedgerController::class, 'livreJournal'])->name('livre-journal');
            Route::get('balance-auxiliaire',  [\App\Http\Controllers\Accounting\LedgerController::class, 'balanceAuxiliaire'])->name('balance-auxiliaire');
            Route::get('situation-comptable', [\App\Http\Controllers\Accounting\FinancialReportController::class, 'situationComptable'])->name('situation-comptable');
        });

        Route::middleware('permission:accounting.validate')->group(function () {
            Route::post('journaux/{journalEntry}/validate', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'validateEntry'])->name('journaux.validate');
            // [PRIO-1] Contre-passation : interdit la suppression d'une écriture validée
            Route::post('journaux/{journalEntry}/reverse',  [\App\Http\Controllers\Accounting\JournalEntryController::class, 'reverse'])->name('journaux.reverse');
        });

        // ── Rapprochement bancaire ──────────────────────────────────────────
        // IMPORTANT: literal routes (create, lines/*) MUST come before {rapprochement} wildcard
        Route::middleware('permission:accounting.write')->group(function () {
            Route::get('rapprochement/create',   [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'create'])->name('rapprochement.create');
            Route::post('rapprochement',         [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'store'])->name('rapprochement.store');
            Route::post('rapprochement/lines/{line}/match',   [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'matchLine'])->name('rapprochement.match');
            Route::post('rapprochement/lines/{line}/unmatch', [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'unmatchLine'])->name('rapprochement.unmatch');
            // [TRESO] Comptabiliser une ligne relevé en frais bancaire
            Route::post('rapprochement/lines/{line}/fee',     [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'postAsFee'])->name('rapprochement.fee');
            // [PRIO-5] Import CSV + matching automatique
            Route::post('rapprochement/{rapprochement}/import-csv', [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'importCsv'])->name('rapprochement.import-csv');
            Route::post('rapprochement/{rapprochement}/auto-match', [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'autoMatch'])->name('rapprochement.auto-match');
        });
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('rapprochement',                [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'index'])->name('rapprochement.index');
            Route::get('rapprochement/{rapprochement}',[\App\Http\Controllers\Accounting\BankReconciliationController::class, 'show'])->name('rapprochement.show');
        });
        Route::middleware('permission:accounting.validate')->group(function () {
            Route::post('rapprochement/{rapprochement}/validate', [\App\Http\Controllers\Accounting\BankReconciliationController::class, 'validateReconciliation'])->name('rapprochement.validate');
        });

        // ── TVA & Déclarations fiscales ────────────────────────────────────
        // IMPORTANT: tva/create and tva-calculate MUST come before {tva} wildcard
        Route::middleware('permission:accounting.write')->group(function () {
            Route::get('tva/create',   [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'create'])->name('tva.create');
            Route::post('tva',         [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'store'])->name('tva.store');
        });
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('tva',           [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'index'])->name('tva.index');
            Route::get('tva-calculate', [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'calculate'])->name('tva.calculate');
            Route::get('tva/{tva}',     [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'show'])->name('tva.show');
        });
        Route::middleware('permission:accounting.validate')->group(function () {
            Route::post('tva/{tva}/submit',    [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'submit'])->name('tva.submit');
            Route::post('tva/{tva}/mark-paid', [\App\Http\Controllers\Accounting\VatDeclarationController::class, 'markPaid'])->name('tva.markPaid');
        });

        // ── Lettrage ───────────────────────────────────────────────────────
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('lettrage', [\App\Http\Controllers\Accounting\LettrageController::class, 'index'])->name('lettrage.index');
        });
        Route::middleware('permission:accounting.write')->group(function () {
            Route::post('lettrage/apply',      [\App\Http\Controllers\Accounting\LettrageController::class, 'apply'])->name('lettrage.apply');
            Route::post('lettrage/auto-apply', [\App\Http\Controllers\Accounting\LettrageController::class, 'autoApply'])->name('lettrage.auto-apply');
            Route::post('lettrage/remove',     [\App\Http\Controllers\Accounting\LettrageController::class, 'remove'])->name('lettrage.remove');
        });

        // ── Rapports financiers ────────────────────────────────────────────
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('bilan',                  [\App\Http\Controllers\Accounting\FinancialReportController::class, 'bilan'])->name('bilan');
            Route::get('bilan/pdf',              [\App\Http\Controllers\Accounting\FinancialReportController::class, 'bilanPdf'])->name('bilan.pdf');
            Route::get('compte-de-resultat',     [\App\Http\Controllers\Accounting\FinancialReportController::class, 'compteDeResultat'])->name('compte-de-resultat');
            Route::get('compte-de-resultat/pdf', [\App\Http\Controllers\Accounting\FinancialReportController::class, 'compteDeResultatPdf'])->name('compte-de-resultat.pdf');
            // Affectation du résultat
            Route::get('affectation-resultat',   [\App\Http\Controllers\Accounting\FinancialReportController::class, 'affectationResultat'])->name('affectation-resultat');
        });
        Route::middleware('permission:accounting.validate')->group(function () {
            Route::post('affectation-resultat',  [\App\Http\Controllers\Accounting\FinancialReportController::class, 'storeAffectation'])->name('affectation-resultat.store');
        });

        // ── Immobilisations & Amortissements ──────────────────────────────────
        // IMPORTANT: literal routes (create, depreciation/*/post) MUST come before {immobilisation} wildcard
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('immobilisations',            [\App\Http\Controllers\Accounting\FixedAssetController::class, 'index'])->name('immobilisations.index');
        });
        Route::middleware('permission:accounting.write')->group(function () {
            // create & store BEFORE {immobilisation} wildcard
            Route::get('immobilisations/create',     [\App\Http\Controllers\Accounting\FixedAssetController::class, 'create'])->name('immobilisations.create');
            Route::post('immobilisations',           [\App\Http\Controllers\Accounting\FixedAssetController::class, 'store'])->name('immobilisations.store');
        });
        Route::middleware('permission:accounting.validate')->group(function () {
            // depreciation post BEFORE {immobilisation} wildcard
            Route::post('immobilisations/depreciation/{depreciation}/post', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'postDepreciation'])->name('immobilisations.post-depreciation');
        });
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('immobilisations/{immobilisation}', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'show'])->name('immobilisations.show');
        });
        Route::middleware('permission:accounting.write')->group(function () {
            Route::post('immobilisations/{immobilisation}/regenerate', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'regenerateSchedule'])->name('immobilisations.regenerate');
        });
    });

    // ── Trésorerie / Module 8 ───────────────────────────────────────────────────
    // [RBAC] Accès module réservé au profil trésorerie (treasury.view). Le commercial,
    // qui possède payments.view pour la visibilité des règlements ventes, n'entre PAS
    // dans le module Trésorerie. Gate au niveau du groupe → couvre aussi le dashboard.
    Route::prefix('tresorerie')->name('tresorerie.')->middleware('permission:treasury.view')->group(function () {

        // Dashboard
        Route::get('/', \App\Http\Controllers\Treasury\TresorerieDashboardController::class)->name('dashboard');

        Route::middleware('permission:payments.view')->group(function () {
            Route::get('encaissements/factures',   [\App\Http\Controllers\Treasury\ClientPaymentController::class, 'getInvoices'])->name('encaissements.invoices');
            Route::get('encaissements/export-pdf', [\App\Http\Controllers\Treasury\ClientPaymentController::class, 'exportPdf'])->name('encaissements.export-pdf');
            // Reçu PDF encaissement
            Route::get('encaissements/{encaissement}/recu', [\App\Http\Controllers\Treasury\ClientPaymentController::class, 'recu'])->name('encaissements.recu');
            // Imputation a posteriori (lettrage)
            Route::post('encaissements/{encaissement}/imputer', [\App\Http\Controllers\Treasury\ClientPaymentController::class, 'imputer'])->name('encaissements.imputer');
            Route::resource('encaissements', \App\Http\Controllers\Treasury\ClientPaymentController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['encaissements' => 'encaissement']);

            Route::get('decaissements/factures', [\App\Http\Controllers\Treasury\SupplierPaymentController::class, 'getInvoices'])->name('decaissements.invoices');
            // Reçu PDF décaissement
            Route::get('decaissements/{decaissement}/recu', [\App\Http\Controllers\Treasury\SupplierPaymentController::class, 'recu'])->name('decaissements.recu');
            // [TRESO] Annulation décaissement : contre-passation compta + restauration facture
            Route::post('decaissements/{decaissement}/cancel', [\App\Http\Controllers\Treasury\SupplierPaymentController::class, 'cancel'])->name('decaissements.cancel');
            // [TRESO-WORKFLOW] Validation / rejet par seuil
            Route::post('decaissements/{decaissement}/approve', [\App\Http\Controllers\Treasury\SupplierPaymentController::class, 'approve'])->name('decaissements.approve');
            Route::post('decaissements/{decaissement}/reject',  [\App\Http\Controllers\Treasury\SupplierPaymentController::class, 'reject'])->name('decaissements.reject');
            Route::resource('decaissements', \App\Http\Controllers\Treasury\SupplierPaymentController::class)
                ->only(['index', 'create', 'store', 'show'])
                ->parameters(['decaissements' => 'decaissement']);

            // ── Échéancier clients ──────────────────────────────────────────────
            Route::get('echeancier-clients', [\App\Http\Controllers\Treasury\ClientPaymentScheduleController::class, 'upcoming'])->name('echeancier-clients');
            Route::delete('schedules-clients/{schedule}', [\App\Http\Controllers\Treasury\ClientPaymentScheduleController::class, 'destroy'])->name('schedules-clients.destroy');

            // ── Échéancier fournisseurs ─────────────────────────────────────────
            Route::get('echeancier-fournisseurs', [\App\Http\Controllers\Treasury\SupplierDueController::class, 'upcoming'])->name('echeancier-fournisseurs');

            // ── État de trésorerie ──────────────────────────────────────────────
            Route::get('etat',     [\App\Http\Controllers\Treasury\TresorerieReportController::class, 'etat'])->name('etat');
            Route::get('etat/pdf', [\App\Http\Controllers\Treasury\TresorerieReportController::class, 'etatPdf'])->name('etat.pdf');
            // ── Journal de trésorerie + Alertes ─────────────────────────────────
            Route::get('journal', [\App\Http\Controllers\Treasury\TresorerieReportController::class, 'journal'])->name('journal');
            Route::get('alertes', [\App\Http\Controllers\Treasury\TresorerieReportController::class, 'alertes'])->name('alertes');
        });

        // Routes manage en PREMIER pour éviter que {caisse} intercepte /create
        Route::middleware('permission:cash_accounts.manage')->group(function () {
            Route::get('caisses/create',            [\App\Http\Controllers\Treasury\CashAccountController::class, 'create'])->name('caisses.create');
            Route::post('caisses',                  [\App\Http\Controllers\Treasury\CashAccountController::class, 'store'])->name('caisses.store');
            Route::get('caisses/{caisse}/edit',     [\App\Http\Controllers\Treasury\CashAccountController::class, 'edit'])->name('caisses.edit');
            Route::put('caisses/{caisse}',          [\App\Http\Controllers\Treasury\CashAccountController::class, 'update'])->name('caisses.update');
        });

        Route::middleware('permission:cash_accounts.view')->group(function () {
            Route::resource('caisses', \App\Http\Controllers\Treasury\CashAccountController::class)
                ->only(['index', 'show'])
                ->parameters(['caisses' => 'caisse']);
        });

        // ── Budgets de trésorerie ───────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('budgets/create', [\App\Http\Controllers\Treasury\TreasuryBudgetController::class, 'create'])->name('budgets.create');
            Route::post('budgets',       [\App\Http\Controllers\Treasury\TreasuryBudgetController::class, 'store'])->name('budgets.store');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('budgets',           [\App\Http\Controllers\Treasury\TreasuryBudgetController::class, 'index'])->name('budgets.index');
            Route::get('budgets/{budget}',  [\App\Http\Controllers\Treasury\TreasuryBudgetController::class, 'show'])->name('budgets.show');
        });

        // ── Demandes de paiement ────────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('demandes/create', [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'create'])->name('demandes.create');
            Route::post('demandes',       [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'store'])->name('demandes.store');
            Route::post('demandes/{demande}/submit', [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'submit'])->name('demandes.submit');
        });
        Route::middleware('permission:treasury.validate')->group(function () {
            Route::post('demandes/{demande}/approve', [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'approve'])->name('demandes.approve');
            Route::post('demandes/{demande}/reject',  [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'reject'])->name('demandes.reject');
        });
        Route::middleware('permission:payments.create')->group(function () {
            Route::post('demandes/{demande}/pay', [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'pay'])->name('demandes.pay');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('demandes',           [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'index'])->name('demandes.index');
            Route::get('demandes/{demande}', [\App\Http\Controllers\Treasury\PaymentRequestController::class, 'show'])->name('demandes.show');
        });

        // ── Clôtures de caisse ──────────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('clotures/create', [\App\Http\Controllers\Treasury\CashClosureController::class, 'create'])->name('clotures.create');
            Route::post('clotures',       [\App\Http\Controllers\Treasury\CashClosureController::class, 'store'])->name('clotures.store');
            Route::post('clotures/{cloture}/validate', [\App\Http\Controllers\Treasury\CashClosureController::class, 'validateClosure'])->name('clotures.validate');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('clotures',            [\App\Http\Controllers\Treasury\CashClosureController::class, 'index'])->name('clotures.index');
            Route::get('clotures/{cloture}',  [\App\Http\Controllers\Treasury\CashClosureController::class, 'show'])->name('clotures.show');
        });

        // ── Virements internes ──────────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('virements/create', [\App\Http\Controllers\Treasury\CashTransferController::class, 'create'])->name('virements.create');
            Route::post('virements',       [\App\Http\Controllers\Treasury\CashTransferController::class, 'store'])->name('virements.store');
            Route::post('virements/{virement}/cancel', [\App\Http\Controllers\Treasury\CashTransferController::class, 'cancel'])->name('virements.cancel');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('virements',            [\App\Http\Controllers\Treasury\CashTransferController::class, 'index'])->name('virements.index');
            Route::get('virements/{virement}', [\App\Http\Controllers\Treasury\CashTransferController::class, 'show'])->name('virements.show');
        });

        // ── Opérations diverses de caisse (entrées / sorties) ────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('operations/create', [\App\Http\Controllers\Treasury\CashOperationController::class, 'create'])->name('operations.create');
            Route::post('operations',       [\App\Http\Controllers\Treasury\CashOperationController::class, 'store'])->name('operations.store');
            Route::post('operations/{operation}/cancel', [\App\Http\Controllers\Treasury\CashOperationController::class, 'cancel'])->name('operations.cancel');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('operations', [\App\Http\Controllers\Treasury\CashOperationController::class, 'index'])->name('operations.index');
        });

        // ── Prévisions de trésorerie ────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('previsions/create',         [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'create'])->name('previsions.create');
            Route::post('previsions',               [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'store'])->name('previsions.store');
            Route::post('previsions/{prevision}/validate', [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'validateForecast'])->name('previsions.validate');
            Route::post('previsions/{prevision}/sync-actuals', [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'syncActuals'])->name('previsions.sync-actuals');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('previsions',                [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'index'])->name('previsions.index');
            Route::get('previsions/{prevision}',    [\App\Http\Controllers\Treasury\CashFlowForecastController::class, 'show'])->name('previsions.show');
            Route::get('simulations',               [\App\Http\Controllers\Treasury\TreasurySimulationController::class, 'index'])->name('simulations.index');
        });

        // ── Remises en banque ───────────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('remises/create',            [\App\Http\Controllers\Treasury\BankDepositController::class, 'create'])->name('remises.create');
            Route::post('remises',                  [\App\Http\Controllers\Treasury\BankDepositController::class, 'store'])->name('remises.store');
        });
        Route::middleware('permission:treasury.validate')->group(function () {
            Route::post('remises/{remise}/validate', [\App\Http\Controllers\Treasury\BankDepositController::class, 'validateDeposit'])->name('remises.validate');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('remises',                   [\App\Http\Controllers\Treasury\BankDepositController::class, 'index'])->name('remises.index');
            Route::get('remises/{remise}',          [\App\Http\Controllers\Treasury\BankDepositController::class, 'show'])->name('remises.show');
        });

        // ── Effets de commerce ──────────────────────────────────────────────────
        Route::middleware('permission:treasury.write')->group(function () {
            Route::get('effets/create',             [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'create'])->name('effets.create');
            Route::post('effets',                   [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'store'])->name('effets.store');
            Route::post('effets/{effet}/accept',    [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'accept'])->name('effets.accept');
            Route::post('effets/{effet}/encaisse',  [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'markEncaisse'])->name('effets.encaisse');
            Route::post('effets/{effet}/reject',    [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'reject'])->name('effets.reject');
            Route::post('effets/{effet}/protest',   [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'protest'])->name('effets.protest');
            Route::post('effets/{effet}/cancel',    [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'cancel'])->name('effets.cancel');
        });
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('effets',                    [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'index'])->name('effets.index');
            Route::get('effets/{effet}',            [\App\Http\Controllers\Treasury\CommercialEffectController::class, 'show'])->name('effets.show');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── [MODULE RH / PAIE] ───────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('rh')->name('rh.')->group(function () {

    // ── Dashboard RH ─────────────────────────────────────────────────────────
    Route::middleware('permission:rh.view')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\HR\RhDashboardController::class, 'index'])->name('dashboard');
    });

    // ── Employés — consultation ───────────────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->prefix('employes')->name('employes.')->group(function () {
        Route::get('/',                   [\App\Http\Controllers\HR\EmployeeController::class, 'index'])->name('index');
        Route::get('/{employe}',          [\App\Http\Controllers\HR\EmployeeController::class, 'show'])->whereNumber('employe')->name('show');
        // Photo (accessible à tous ceux qui voient les employés)
        Route::get('/{employe}/photo',    [\App\Http\Controllers\HR\EmployeeDocumentController::class, 'photo'])->whereNumber('employe')->name('photo');
    });

    // ── Employés — gestion ────────────────────────────────────────────────────
    Route::middleware('permission:rh.employees.manage')->prefix('employes')->name('employes.')->group(function () {
        Route::get('/creer',              [\App\Http\Controllers\HR\EmployeeController::class, 'create'])->name('create');
        Route::post('/',                  [\App\Http\Controllers\HR\EmployeeController::class, 'store'])->name('store');
        Route::get('/{employe}/modifier', [\App\Http\Controllers\HR\EmployeeController::class, 'edit'])->whereNumber('employe')->name('edit');
        Route::put('/{employe}',          [\App\Http\Controllers\HR\EmployeeController::class, 'update'])->whereNumber('employe')->name('update');
        Route::delete('/{employe}',       [\App\Http\Controllers\HR\EmployeeController::class, 'destroy'])->whereNumber('employe')->name('destroy');
        // Contrats
        Route::post('/{employe}/contrats', [\App\Http\Controllers\HR\EmployeeController::class, 'storeContract'])->whereNumber('employe')->name('contracts.store');
        // Primes
        Route::post('/{employe}/primes',               [\App\Http\Controllers\HR\EmployeeController::class, 'storeAllowance'])->whereNumber('employe')->name('allowances.store');
        Route::delete('/{employe}/primes/{allowance}', [\App\Http\Controllers\HR\EmployeeController::class, 'destroyAllowance'])->whereNumber('employe')->name('allowances.destroy');
        // Documents
        Route::post('/{employe}/documents',                [\App\Http\Controllers\HR\EmployeeDocumentController::class, 'store'])->whereNumber('employe')->name('documents.store');
        Route::get( '/{employe}/documents/{document}/dl',  [\App\Http\Controllers\HR\EmployeeDocumentController::class, 'download'])->whereNumber('employe')->name('documents.download');
        Route::delete('/{employe}/documents/{document}',   [\App\Http\Controllers\HR\EmployeeDocumentController::class, 'destroy'])->whereNumber('employe')->name('documents.destroy');
        // Photo (mise à jour)
        Route::post('/{employe}/photo',   [\App\Http\Controllers\HR\EmployeeDocumentController::class, 'updatePhoto'])->whereNumber('employe')->name('photo.update');
    });

    // ── Postes / grades / emplois [RH-01] ──────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->prefix('postes')->name('postes.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\HR\JobPositionController::class, 'index'])->name('index');
        Route::get('/creer',   [\App\Http\Controllers\HR\JobPositionController::class, 'create'])->middleware('permission:rh.employees.manage')->name('create');
        Route::post('/',       [\App\Http\Controllers\HR\JobPositionController::class, 'store'])->middleware('permission:rh.employees.manage')->name('store');
        Route::get('/{poste}', [\App\Http\Controllers\HR\JobPositionController::class, 'show'])->whereNumber('poste')->name('show');
        Route::get('/{poste}/modifier', [\App\Http\Controllers\HR\JobPositionController::class, 'edit'])->whereNumber('poste')->middleware('permission:rh.employees.manage')->name('edit');
        Route::put('/{poste}',    [\App\Http\Controllers\HR\JobPositionController::class, 'update'])->whereNumber('poste')->middleware('permission:rh.employees.manage')->name('update');
        Route::delete('/{poste}', [\App\Http\Controllers\HR\JobPositionController::class, 'destroy'])->whereNumber('poste')->middleware('permission:rh.employees.manage')->name('destroy');
    });

    // ── [RH-03] Recrutement & onboarding ──────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->prefix('recrutement')->name('recrutement.')->group(function () {
        Route::get('/',            [\App\Http\Controllers\HR\RecruitmentController::class, 'index'])->name('index');
        Route::get('/creer',       [\App\Http\Controllers\HR\RecruitmentController::class, 'create'])->middleware('permission:rh.employees.manage')->name('create');
        Route::post('/',           [\App\Http\Controllers\HR\RecruitmentController::class, 'store'])->middleware('permission:rh.employees.manage')->name('store');
        Route::get('/{recrutement}', [\App\Http\Controllers\HR\RecruitmentController::class, 'show'])->whereNumber('recrutement')->name('show');
        Route::get('/{recrutement}/modifier', [\App\Http\Controllers\HR\RecruitmentController::class, 'edit'])->whereNumber('recrutement')->middleware('permission:rh.employees.manage')->name('edit');
        Route::put('/{recrutement}',    [\App\Http\Controllers\HR\RecruitmentController::class, 'update'])->whereNumber('recrutement')->middleware('permission:rh.employees.manage')->name('update');
        Route::delete('/{recrutement}', [\App\Http\Controllers\HR\RecruitmentController::class, 'destroy'])->whereNumber('recrutement')->middleware('permission:rh.employees.manage')->name('destroy');
        Route::post('/{recrutement}/candidats', [\App\Http\Controllers\HR\RecruitmentController::class, 'storeCandidate'])->whereNumber('recrutement')->middleware('permission:rh.employees.manage')->name('candidates.store');
        Route::post('/{recrutement}/candidats/{candidate}/statut', [\App\Http\Controllers\HR\RecruitmentController::class, 'advanceCandidate'])->whereNumber('recrutement')->middleware('permission:rh.employees.manage')->name('candidates.advance');
    });

    // ── Départements ──────────────────────────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->prefix('departements')->name('departments.')->group(function () {
        Route::get('/',  [\App\Http\Controllers\HR\EmployeeController::class, 'departments'])->name('index');
    });
    Route::middleware('permission:rh.employees.manage')->prefix('departements')->name('departments.')->group(function () {
        Route::post('/', [\App\Http\Controllers\HR\EmployeeController::class, 'storeDepartment'])->name('store');
    });

    // ── Simulateur de salaire inverse ────────────────────────────────────────
    Route::middleware('permission:rh.payroll.view')->prefix('paie')->name('paie.')->group(function () {
        Route::get('/simulateur',             [\App\Http\Controllers\HR\PayrollSimulatorController::class, 'index'])->name('simulateur.index');
        Route::post('/simulateur/calculer',   [\App\Http\Controllers\HR\PayrollSimulatorController::class, 'calculate'])->name('simulateur.calculate');
        Route::post('/simulateur/export-pdf', [\App\Http\Controllers\HR\PayrollSimulatorController::class, 'exportPdf'])->name('simulateur.pdf');
    });

    // ── Bulletins de paie ─────────────────────────────────────────────────────
    Route::middleware('permission:rh.payroll.view')->prefix('paie')->name('paie.')->group(function () {
        Route::get('/',              [\App\Http\Controllers\HR\PayrollRunController::class, 'index'])->name('index');
        Route::get('/livre-paie',    [\App\Http\Controllers\HR\PayrollRunController::class, 'livrePaiePdf'])->name('livre-paie');
        Route::get('/{run}',         [\App\Http\Controllers\HR\PayrollRunController::class, 'show'])->whereNumber('run')->name('show');
        Route::get('/{run}/variables',              [\App\Http\Controllers\HR\PayrollRunController::class, 'variables'])->whereNumber('run')->name('variables');
        // PDF & Exports (lecture)
        Route::get('/{run}/bulletin/{item}/pdf', [\App\Http\Controllers\HR\PayrollRunController::class, 'bulletinPdf'])->whereNumber('run')->name('bulletin-pdf');
        Route::get('/{run}/recap-pdf',           [\App\Http\Controllers\HR\PayrollRunController::class, 'recapPdf'])->whereNumber('run')->name('recap-pdf');
        Route::get('/{run}/cnss-pdf',            [\App\Http\Controllers\HR\PayrollRunController::class, 'cnssPdf'])->whereNumber('run')->name('cnss-pdf');
        Route::get('/{run}/iuts-pdf',            [\App\Http\Controllers\HR\PayrollRunController::class, 'iutsPdf'])->whereNumber('run')->name('iuts-pdf');
        Route::get('/{run}/virement-csv',        [\App\Http\Controllers\HR\PayrollRunController::class, 'virementCsv'])->whereNumber('run')->name('virement-csv');
        Route::get('/{run}/livre-paie-xlsx',     [\App\Http\Controllers\HR\PayrollRunController::class, 'livreDepaieXlsx'])->whereNumber('run')->name('livre-paie-xlsx');
        Route::get('/{run}/cnss-xlsx',           [\App\Http\Controllers\HR\PayrollRunController::class, 'cnssXlsx'])->whereNumber('run')->name('cnss-xlsx');
        Route::get('/{run}/iuts-xlsx',           [\App\Http\Controllers\HR\PayrollRunController::class, 'iutsXlsx'])->whereNumber('run')->name('iuts-xlsx');
        Route::get('/{run}/avances-pdf',         [\App\Http\Controllers\HR\PayrollRunController::class, 'avancesPdf'])->whereNumber('run')->name('avances-pdf');
        Route::get('/{run}/prets-pdf',           [\App\Http\Controllers\HR\PayrollRunController::class, 'pretsPdf'])->whereNumber('run')->name('prets-pdf');
        // [PAI-07] Virements — suivi ligne par ligne
        Route::get('/{run}/virements',                 [\App\Http\Controllers\HR\PayrollPaymentController::class, 'index'])->whereNumber('run')->name('virements.index');
        Route::get('/{run}/virements/fichier-bancaire',[\App\Http\Controllers\HR\PayrollPaymentController::class, 'bankFile'])->whereNumber('run')->name('virements.bank-file');
        // [PAI-08] Archive des déclarations CNSS/IUTS
        Route::get('/declarations', [\App\Http\Controllers\HR\PayrollDeclarationController::class, 'index'])->name('declarations.index');
    });
    Route::middleware('permission:rh.payroll.manage')->prefix('paie')->name('paie.')->group(function () {
        Route::get('/creer',                         [\App\Http\Controllers\HR\PayrollRunController::class, 'create'])->name('create');
        Route::post('/',                             [\App\Http\Controllers\HR\PayrollRunController::class, 'store'])->name('store');
        Route::post('/{run}/calculer',               [\App\Http\Controllers\HR\PayrollRunController::class, 'calculate'])->whereNumber('run')->name('calculate');
        Route::post('/{run}/variables',              [\App\Http\Controllers\HR\PayrollVariableController::class, 'store'])->whereNumber('run')->name('variables.store');
        Route::delete('/{run}/variables/{variable}', [\App\Http\Controllers\HR\PayrollVariableController::class, 'destroy'])->whereNumber('run')->name('variables.destroy');
        // [PAI-08] Déclarations — figeage + suivi
        Route::post('/{run}/declarations/generer',   [\App\Http\Controllers\HR\PayrollDeclarationController::class, 'generate'])->whereNumber('run')->name('declarations.generate');
        Route::post('/declarations/{declaration}/deposer', [\App\Http\Controllers\HR\PayrollDeclarationController::class, 'markDeposited'])->whereNumber('declaration')->name('declarations.deposit');
        Route::post('/declarations/{declaration}/payer',   [\App\Http\Controllers\HR\PayrollDeclarationController::class, 'markPaid'])->whereNumber('declaration')->name('declarations.pay');
    });
    Route::middleware('permission:rh.payroll.validate')->prefix('paie')->name('paie.')->group(function () {
        Route::post('/{run}/valider',      [\App\Http\Controllers\HR\PayrollRunController::class, 'approuver'])->whereNumber('run')->name('validate');
        Route::post('/{run}/payer',        [\App\Http\Controllers\HR\PayrollRunController::class, 'markPaid'])->whereNumber('run')->name('mark-paid');
        Route::post('/{run}/comptabiliser',[\App\Http\Controllers\HR\PayrollRunController::class, 'journalize'])->whereNumber('run')->name('journalize');
        // [PAI-07] Virements — génération & rapprochement
        Route::post('/{run}/virements/generer',        [\App\Http\Controllers\HR\PayrollPaymentController::class, 'generate'])->whereNumber('run')->name('virements.generate');
        Route::post('/{run}/virements/payer-tout',     [\App\Http\Controllers\HR\PayrollPaymentController::class, 'markRunPaid'])->whereNumber('run')->name('virements.pay-all');
        Route::post('/{run}/virements/{payment}/payer',[\App\Http\Controllers\HR\PayrollPaymentController::class, 'markPaid'])->whereNumber('run')->whereNumber('payment')->name('virements.pay');
    });

    // ── Avances sur salaire ───────────────────────────────────────────────────
    Route::middleware('permission:rh.loans.view')->prefix('avances')->name('avances.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'index'])->name('index');
    });
    Route::middleware('permission:rh.loans.manage')->prefix('avances')->name('avances.')->group(function () {
        Route::post('/',                    [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'store'])->name('store');
        Route::post('/{advance}/approuver', [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'approve'])->name('approve');
        Route::post('/{advance}/annuler',   [\App\Http\Controllers\HR\SalaryAdvanceController::class, 'cancel'])->name('cancel');
    });

    // ── Congés & Absences ─────────────────────────────────────────────────────
    Route::middleware('permission:rh.leaves.view')->prefix('conges')->name('conges.')->group(function () {
        Route::get('/',       [\App\Http\Controllers\HR\LeaveController::class, 'index'])->name('index');
        Route::get('/soldes', [\App\Http\Controllers\HR\LeaveController::class, 'balances'])->name('balances');
        Route::get('/types',  [\App\Http\Controllers\HR\LeaveController::class, 'indexTypes'])->name('types.index');
    });
    Route::middleware('permission:rh.leaves.manage')->prefix('conges')->name('conges.')->group(function () {
        Route::post('/',                  [\App\Http\Controllers\HR\LeaveController::class, 'store'])->name('store');
        Route::post('/{leave}/approuver', [\App\Http\Controllers\HR\LeaveController::class, 'approve'])->name('approve');
        Route::post('/{leave}/refuser',   [\App\Http\Controllers\HR\LeaveController::class, 'refuse'])->name('refuse');
        Route::post('/types',             [\App\Http\Controllers\HR\LeaveController::class, 'storeType'])->name('types.store');
    });

    // ── Prêts salariés ────────────────────────────────────────────────────────
    Route::middleware('permission:rh.loans.view')->prefix('prets')->name('prets.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\HR\EmployeeLoanController::class, 'index'])->name('index');
        Route::get('/{pret}',  [\App\Http\Controllers\HR\EmployeeLoanController::class, 'show'])->whereNumber('pret')->name('show');
    });
    Route::middleware('permission:rh.loans.manage')->prefix('prets')->name('prets.')->group(function () {
        Route::post('/',                     [\App\Http\Controllers\HR\EmployeeLoanController::class, 'store'])->name('store');
        Route::post('/{pret}/approuver',     [\App\Http\Controllers\HR\EmployeeLoanController::class, 'approve'])->name('approve');
        Route::post('/{pret}/annuler',       [\App\Http\Controllers\HR\EmployeeLoanController::class, 'cancel'])->name('cancel');
        Route::post('/{pret}/remboursement', [\App\Http\Controllers\HR\EmployeeLoanController::class, 'recordPayment'])->name('payment');
    });

    // ── Paramétrage & Rubriques ───────────────────────────────────────────────
    Route::middleware('permission:rh.settings')->group(function () {
        Route::prefix('parametrage')->name('parametrage.')->group(function () {
            Route::get('/',  [\App\Http\Controllers\HR\PayrollSettingController::class, 'edit'])->name('edit');
            Route::put('/',  [\App\Http\Controllers\HR\PayrollSettingController::class, 'update'])->name('update');
        });
        Route::prefix('rubriques')->name('rubriques.')->group(function () {
            Route::get('/',            [\App\Http\Controllers\HR\PayRubricController::class, 'index'])->name('index');
            Route::get('/creer',       [\App\Http\Controllers\HR\PayRubricController::class, 'create'])->name('create');
            Route::post('/',           [\App\Http\Controllers\HR\PayRubricController::class, 'store'])->name('store');
            Route::get('/{rubric}',    [\App\Http\Controllers\HR\PayRubricController::class, 'edit'])->name('edit');
            Route::put('/{rubric}',    [\App\Http\Controllers\HR\PayRubricController::class, 'update'])->name('update');
            Route::delete('/{rubric}', [\App\Http\Controllers\HR\PayRubricController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('types-primes')->name('types-primes.')->group(function () {
            Route::get('/',       [\App\Http\Controllers\HR\AllowanceTypeController::class, 'index'])->name('index');
            Route::post('/',      [\App\Http\Controllers\HR\AllowanceTypeController::class, 'store'])->name('store');
            Route::put('/{type}', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'update'])->name('update');
            Route::delete('/{type}', [\App\Http\Controllers\HR\AllowanceTypeController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\HR\PayrollPlanController::class, 'index'])->name('index');
            Route::get('/creer',             [\App\Http\Controllers\HR\PayrollPlanController::class, 'create'])->name('create');
            Route::post('/',                 [\App\Http\Controllers\HR\PayrollPlanController::class, 'store'])->name('store');
            Route::get('/{plan}',            [\App\Http\Controllers\HR\PayrollPlanController::class, 'show'])->name('show');
            Route::get('/{plan}/modifier',   [\App\Http\Controllers\HR\PayrollPlanController::class, 'edit'])->name('edit');
            Route::put('/{plan}',            [\App\Http\Controllers\HR\PayrollPlanController::class, 'update'])->name('update');
            Route::post('/{plan}/dupliquer', [\App\Http\Controllers\HR\PayrollPlanController::class, 'duplicate'])->name('duplicate');
            Route::delete('/{plan}',         [\App\Http\Controllers\HR\PayrollPlanController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('constantes')->name('constantes.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\HR\PayrollConstantController::class, 'index'])->name('index');
            Route::get('/creer',               [\App\Http\Controllers\HR\PayrollConstantController::class, 'create'])->name('create');
            Route::post('/',                   [\App\Http\Controllers\HR\PayrollConstantController::class, 'store'])->name('store');
            Route::get('/{constant}/modifier', [\App\Http\Controllers\HR\PayrollConstantController::class, 'edit'])->name('edit');
            Route::put('/{constant}',          [\App\Http\Controllers\HR\PayrollConstantController::class, 'update'])->name('update');
            Route::delete('/{constant}',       [\App\Http\Controllers\HR\PayrollConstantController::class, 'destroy'])->name('destroy');
            Route::get('/{code}/historique',   [\App\Http\Controllers\HR\PayrollConstantController::class, 'history'])->name('history')->where('code', '[A-Z0-9_]+');
        });
        Route::prefix('baremes')->name('baremes.')->group(function () {
            Route::get('/',             [\App\Http\Controllers\HR\IutsBracketController::class, 'index'])->name('index');
            Route::post('/',            [\App\Http\Controllers\HR\IutsBracketController::class, 'store'])->name('store');
            Route::put('/{bracket}',    [\App\Http\Controllers\HR\IutsBracketController::class, 'update'])->name('update');
            Route::delete('/{bracket}', [\App\Http\Controllers\HR\IutsBracketController::class, 'destroy'])->name('destroy');
            Route::post('/simuler',     [\App\Http\Controllers\HR\IutsBracketController::class, 'simulate'])->name('simulate');
        });
        Route::prefix('cotisations')->name('cotisations.')->group(function () {
            Route::get('/',                        [\App\Http\Controllers\HR\SocialContributionController::class, 'index'])->name('index');
            Route::get('/creer',                   [\App\Http\Controllers\HR\SocialContributionController::class, 'create'])->name('create');
            Route::post('/',                       [\App\Http\Controllers\HR\SocialContributionController::class, 'store'])->name('store');
            Route::get('/{contribution}/modifier', [\App\Http\Controllers\HR\SocialContributionController::class, 'edit'])->name('edit');
            Route::put('/{contribution}',          [\App\Http\Controllers\HR\SocialContributionController::class, 'update'])->name('update');
            Route::delete('/{contribution}',       [\App\Http\Controllers\HR\SocialContributionController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('profils')->name('profils.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\HR\PayrollProfileController::class, 'index'])->name('index');
            Route::get('/creer',                     [\App\Http\Controllers\HR\PayrollProfileController::class, 'create'])->name('create');
            Route::post('/',                         [\App\Http\Controllers\HR\PayrollProfileController::class, 'store'])->name('store');
            Route::get('/{profil}',                  [\App\Http\Controllers\HR\PayrollProfileController::class, 'show'])->name('show');
            Route::get('/{profil}/modifier',         [\App\Http\Controllers\HR\PayrollProfileController::class, 'edit'])->name('edit');
            Route::put('/{profil}',                  [\App\Http\Controllers\HR\PayrollProfileController::class, 'update'])->name('update');
            Route::delete('/{profil}',               [\App\Http\Controllers\HR\PayrollProfileController::class, 'destroy'])->name('destroy');
            // Gestion des rubriques du profil
            Route::post('/{profil}/rubriques',                    [\App\Http\Controllers\HR\PayrollProfileController::class, 'addRubric'])->name('rubrics.add');
            Route::put('/{profil}/rubriques/{rubric}',            [\App\Http\Controllers\HR\PayrollProfileController::class, 'updateRubric'])->name('rubrics.update');
            Route::delete('/{profil}/rubriques/{rubric}',         [\App\Http\Controllers\HR\PayrollProfileController::class, 'removeRubric'])->name('rubrics.remove');
            Route::post('/{profil}/sync-plan',                    [\App\Http\Controllers\HR\PayrollProfileController::class, 'syncFromPlan'])->name('sync-plan');
        });
        // ── Numérotation des bulletins ───────────────────────────────────────────
        Route::prefix('numerotation')->name('numerotation.')->group(function () {
            Route::get('/',                               [\App\Http\Controllers\HR\PayrollNumberingController::class, 'index'])->name('index');
            Route::get('/creer',                          [\App\Http\Controllers\HR\PayrollNumberingController::class, 'create'])->name('create');
            Route::post('/',                              [\App\Http\Controllers\HR\PayrollNumberingController::class, 'store'])->name('store');
            Route::get('/{numerotation}/modifier',        [\App\Http\Controllers\HR\PayrollNumberingController::class, 'edit'])->name('edit');
            Route::put('/{numerotation}',                 [\App\Http\Controllers\HR\PayrollNumberingController::class, 'update'])->name('update');
            Route::delete('/{numerotation}',              [\App\Http\Controllers\HR\PayrollNumberingController::class, 'destroy'])->name('destroy');
            Route::get('/apercu',                         [\App\Http\Controllers\HR\PayrollNumberingController::class, 'preview'])->name('preview');
            Route::post('/{numerotation}/reset-sequence', [\App\Http\Controllers\HR\PayrollNumberingController::class, 'resetSequence'])->name('reset-sequence');
        });
        // ── Modèles de bulletins ─────────────────────────────────────────────────
        Route::prefix('modeles-bulletins')->name('modeles-bulletins.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'index'])->name('index');
            Route::get('/creer',               [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'create'])->name('create');
            Route::post('/',                   [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'store'])->name('store');
            Route::get('/{modele}/modifier',   [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'edit'])->name('edit');
            Route::put('/{modele}',            [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'update'])->name('update');
            Route::delete('/{modele}',         [\App\Http\Controllers\HR\PayrollBulletinTemplateController::class, 'destroy'])->name('destroy');
        });
        // ── Périodes de paie ─────────────────────────────────────────────────────
        Route::prefix('periodes')->name('periodes.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\HR\PayrollPeriodController::class, 'index'])->name('index');
            Route::post('/',                         [\App\Http\Controllers\HR\PayrollPeriodController::class, 'store'])->name('store');
            Route::delete('/{periode}',              [\App\Http\Controllers\HR\PayrollPeriodController::class, 'destroy'])->name('destroy');
            // Transitions de statut
            Route::post('/{periode}/cloturer',       [\App\Http\Controllers\HR\PayrollPeriodController::class, 'close'])->name('close');
            Route::post('/{periode}/rouvrir',        [\App\Http\Controllers\HR\PayrollPeriodController::class, 'reopen'])->name('reopen');
            Route::post('/{periode}/verrouiller',    [\App\Http\Controllers\HR\PayrollPeriodController::class, 'lock'])->name('lock');
            Route::post('/{periode}/deverrouiller',  [\App\Http\Controllers\HR\PayrollPeriodController::class, 'unlock'])->name('unlock');
            Route::post('/{periode}/archiver',       [\App\Http\Controllers\HR\PayrollPeriodController::class, 'archive'])->name('archive');
            // AJAX
            Route::get('/{periode}/statut',          [\App\Http\Controllers\HR\PayrollPeriodController::class, 'status'])->name('status');
        });
    });

    // ── Contrats (liste globale) ──────────────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->group(function () {
        Route::get('/contrats',                    [\App\Http\Controllers\HR\EmployeeController::class, 'contracts'])->name('contrats.index');
        Route::get('/contrats/export',             [\App\Http\Controllers\HR\EmployeeController::class, 'exportContracts'])->name('contrats.export');
        Route::post('/contrats/store',             [\App\Http\Controllers\HR\EmployeeController::class, 'storeContractDirect'])->name('contrats.store');
        Route::get('/contrats/{contract}/pdf',     [\App\Http\Controllers\HR\EmployeeController::class, 'contractPdf'])->name('contrats.pdf');
        Route::patch('/contrats/{contract}/terminate', [\App\Http\Controllers\HR\EmployeeController::class, 'terminateContract'])->name('contrats.terminate');
        Route::patch('/contrats/{contract}/resilier',  [\App\Http\Controllers\HR\EmployeeController::class, 'resilierContract'])->name('contrats.resilier');
        Route::delete('/contrats/{contract}',          [\App\Http\Controllers\HR\EmployeeController::class, 'destroyContract'])->name('contrats.destroy');
    });

    // ── Présences & absences ──────────────────────────────────────────────────
    Route::middleware('permission:rh.employees.view')->group(function () {
        Route::get('/presences', [\App\Http\Controllers\HR\AttendanceController::class, 'index'])->name('presences.index');
        Route::get('/presences/saisie', [\App\Http\Controllers\HR\AttendanceController::class, 'create'])->name('presences.create');
        Route::post('/presences/saisie', [\App\Http\Controllers\HR\AttendanceController::class, 'store'])->name('presences.store');
        Route::get('/presences/export', [\App\Http\Controllers\HR\AttendanceController::class, 'export'])->name('presences.export');
        Route::get('/presences/employe/{employee}', [\App\Http\Controllers\HR\AttendanceController::class, 'employee'])->name('presences.employee');
    });

    // ── Variables mensuelles (index global) ───────────────────────────────────
    Route::middleware('permission:rh.payroll.view')->group(function () {
        Route::get('/variables', [\App\Http\Controllers\HR\PayrollRunController::class, 'variablesIndex'])->name('variables.index');
    });

    // ── États de paie ─────────────────────────────────────────────────────────
    Route::middleware('permission:rh.payroll.view')->group(function () {
        Route::get('/etats', [\App\Http\Controllers\HR\PayrollRunController::class, 'etats'])->name('etats.index');
    });

    // ── Comptabilisation paie ─────────────────────────────────────────────────
    Route::middleware('permission:rh.payroll.view')->group(function () {
        Route::get('/comptabilisation', [\App\Http\Controllers\HR\PayrollRunController::class, 'comptabilisation'])->name('comptabilisation.index');
    });

    // ── Portail self-service employé ──────────────────────────────────────────
    Route::middleware('permission:rh.portail')->prefix('portail')->name('portail.')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\HR\EmployeePortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/bulletins',             [\App\Http\Controllers\HR\EmployeePortalController::class, 'bulletins'])->name('bulletins');
        Route::get('/bulletins/{item}/pdf',  [\App\Http\Controllers\HR\EmployeePortalController::class, 'bulletinPdf'])->name('bulletin-pdf');
        Route::get('/conges',                [\App\Http\Controllers\HR\EmployeePortalController::class, 'conges'])->name('conges');
        Route::post('/conges',               [\App\Http\Controllers\HR\EmployeePortalController::class, 'storeConge'])->name('conges.store');
        Route::get('/documents',             [\App\Http\Controllers\HR\EmployeePortalController::class, 'documents'])->name('documents');
    });
});

// ── [MODULE INTÉGRATIONS EXTERNES] ───────────────────────────────────────────
// Webhooks publics (pas d'auth — reçoivent les callbacks des providers)
// Rate limited: 120 req/min par IP (DOS protection)
Route::middleware(['throttle:webhooks'])->prefix('integrations/webhooks')->name('integrations.webhooks.')->group(function () {
    Route::post('/orange-money', [\App\Http\Controllers\Integrations\WebhookController::class, 'orangeMoney'])->name('orange-money');
    Route::post('/moov-money',   [\App\Http\Controllers\Integrations\WebhookController::class, 'moovMoney'])->name('moov-money');
});

Route::middleware(['auth', 'verified'])->prefix('integrations')->name('integrations.')->group(function () {
    $ctrl  = \App\Http\Controllers\Integrations\IntegrationController::class;
    $fctrl = \App\Http\Controllers\Integrations\FiscalExportController::class;

    // ── Lecture (intégrations, transactions, logs, export fiscal) ──────────────
    Route::middleware('permission:integrations.view')->group(function () use ($ctrl, $fctrl) {
        Route::get('/',              [$ctrl, 'index'])->name('index');
        Route::get('/dashboard',     [$ctrl, 'dashboard'])->name('dashboard');
        Route::get('/transactions',  [$ctrl, 'transactions'])->name('transactions');
        Route::get('/logs',          [$ctrl, 'allLogs'])->name('logs');
        Route::get('/create',        [$ctrl, 'create'])->name('create');
        Route::get('/{integration}',          [$ctrl, 'show'])->name('show');
        Route::get('/{integration}/edit',     [$ctrl, 'edit'])->name('edit');
        Route::get('/{integration}/simulate', [$ctrl, 'simulate'])->name('simulate');
        Route::get('/{integration}/fiscal',   [$fctrl, 'index'])->name('fiscal.index');
    });

    // ── Gestion (clés API, CRUD, actions techniques) ──────────────────────────
    Route::middleware('permission:integrations.manage')->group(function () use ($ctrl) {
        Route::post('/',                  [$ctrl, 'store'])->name('store');
        Route::put('/{integration}',      [$ctrl, 'update'])->name('update');
        Route::delete('/{integration}',   [$ctrl, 'destroy'])->name('destroy');
        Route::post('/{integration}/toggle',   [$ctrl, 'toggle'])->name('toggle');
        Route::post('/{integration}/test',     [$ctrl, 'test'])->name('test');
        Route::post('/{integration}/ping',     [$ctrl, 'ping'])->name('ping');       // AJAX → JSON
        Route::post('/{integration}/simulate', [$ctrl, 'simulateSend'])->name('simulate.send');
        Route::post('/transactions/{transaction}/retry', [$ctrl, 'retryTransaction'])->name('transactions.retry');
    });

    // ── Déclarations fiscales DGI Burkina Faso (envoi à l'administration) ──────
    Route::middleware('permission:integrations.declare')->group(function () use ($fctrl) {
        Route::post('/{integration}/fiscal/tva',      [$fctrl, 'exportTva'])->name('fiscal.tva');
        Route::post('/{integration}/fiscal/factures', [$fctrl, 'exportInvoices'])->name('fiscal.factures');
        Route::post('/{integration}/fiscal/journal',  [$fctrl, 'exportJournal'])->name('fiscal.journal');
        Route::post('/{integration}/fiscal/declarer', [$fctrl, 'declareTva'])->name('fiscal.declarer');
    });
});

// ── [CONCURRENCE-MULTI-USER] Edit Locks ──────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('edit-lock')->name('edit-lock.')->group(function () {
    Route::post('refresh',       [\App\Http\Controllers\EditLockController::class, 'refresh'])->name('refresh');
    Route::post('release',       [\App\Http\Controllers\EditLockController::class, 'release'])->name('release');
    Route::post('force-release', [\App\Http\Controllers\EditLockController::class, 'forceRelease'])->name('force-release');
});

// ── Notifications ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/',               [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
    Route::get('/recent',         [\App\Http\Controllers\NotificationController::class, 'recent'])->name('recent');
    Route::post('/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('mark-all-read');
    Route::get('/{id}/read',      [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('read');
    Route::delete('/{id}',        [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
});

// ── Pièces jointes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('attachments')->name('attachments.')->group(function () {
    Route::get('/',                 [\App\Http\Controllers\AttachmentController::class, 'index'])->name('index');
    Route::post('/',                [\App\Http\Controllers\AttachmentController::class, 'store'])->name('store');
    Route::get('/{attachment}/dl',  [\App\Http\Controllers\AttachmentController::class, 'download'])->name('download');
    Route::delete('/{attachment}',  [\App\Http\Controllers\AttachmentController::class, 'destroy'])->name('destroy');
});

// ── Import ────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'permission:settings.manage|products.create|clients.create'])->group(function () {
    Route::get('/import',                [\App\Http\Controllers\ImportController::class, 'index'])->name('import.index');
    Route::get('/import/template/{type}',[\App\Http\Controllers\ImportController::class, 'template'])->name('import.template');
    Route::post('/import',               [\App\Http\Controllers\ImportController::class, 'import'])->name('import.process');
});

// ── Exports ───────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->prefix('exports')->name('exports.')->group(function () {
    Route::get('/factures',           [\App\Http\Controllers\ExportController::class, 'invoices'])->name('invoices');
    Route::get('/produits',           [\App\Http\Controllers\ExportController::class, 'products'])->name('products');
    Route::get('/produits-pdf',       [\App\Http\Controllers\ExportController::class, 'productsPdf'])->name('products-pdf');
    Route::get('/mouvements-stock',   [\App\Http\Controllers\ExportController::class, 'stockMovements'])->name('stock-movements');
    Route::get('/clients',            [\App\Http\Controllers\ExportController::class, 'clients'])->name('clients');
    Route::get('/clients-pdf',        [\App\Http\Controllers\ExportController::class, 'clientsPdf'])->name('clients-pdf');
    Route::get('/fournisseurs',       [\App\Http\Controllers\ExportController::class, 'suppliers'])->name('suppliers');
    Route::get('/fournisseurs-pdf',   [\App\Http\Controllers\ExportController::class, 'suppliersPdf'])->name('suppliers-pdf');
});

// ── Recherche globale ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->get('/search', [\App\Http\Controllers\SearchController::class, 'search'])->name('search');

// ── CRM ───────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'permission:crm.view'])->prefix('crm')->name('crm.')->group(function () {

    // Dashboard
    Route::get('/', [\App\Http\Controllers\Crm\DashboardController::class, 'index'])->name('dashboard');

    // ── Écriture CRM (crm.manage) — déclaré AVANT les reads pour l'ordre create/{id} ──
    Route::middleware('permission:crm.manage')->group(function () {
        Route::post('contacts/{contact}/convert', [\App\Http\Controllers\Crm\ContactController::class, 'convert'])
            ->name('contacts.convert');
        Route::resource('contacts', \App\Http\Controllers\Crm\ContactController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);

        Route::patch('opportunities/{opportunity}/move-stage', [\App\Http\Controllers\Crm\OpportunityController::class, 'moveStage'])
            ->name('opportunities.move-stage');
        Route::resource('opportunities', \App\Http\Controllers\Crm\OpportunityController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);

        Route::patch('activities/{activity}/toggle-done', [\App\Http\Controllers\Crm\ActivityController::class, 'toggleDone'])
            ->name('activities.toggle-done');
        Route::resource('activities', \App\Http\Controllers\Crm\ActivityController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
    });

    // ── Lecture CRM (crm.view) ──
    Route::resource('contacts', \App\Http\Controllers\Crm\ContactController::class)->only(['index', 'show']);
    Route::resource('opportunities', \App\Http\Controllers\Crm\OpportunityController::class)->only(['index', 'show']);
    Route::resource('activities', \App\Http\Controllers\Crm\ActivityController::class)->only(['index']);
});

// ═══ Production / Fabrication tôles bac ═══
Route::middleware(['auth', 'verified', 'permission:production.view'])->prefix('production')->name('production.')->group(function () {
    // Tableau de bord & rapports
    Route::get('dashboard', [\App\Modules\Production\Controllers\ProductionDashboardController::class, 'index'])->name('dashboard');
    Route::get('reports', [\App\Modules\Production\Controllers\ProductionReportController::class, 'index'])->name('reports');

    // [Parité SAGE X3] Suivi de fabrication — saisie unique opérations / production / matière
    Route::get('suivis', [\App\Modules\Production\Controllers\ProductionTrackingController::class, 'index'])->name('trackings.index');
    Route::middleware('permission:production.update')->group(function () {
        Route::get('suivis/create', [\App\Modules\Production\Controllers\ProductionTrackingController::class, 'create'])->name('trackings.create');
        Route::post('suivis', [\App\Modules\Production\Controllers\ProductionTrackingController::class, 'store'])->name('trackings.store');
    });

    // [BUG-002] Articles fabriqués sans nomenclature
    Route::get('articles-sans-nomenclature', [\App\Modules\Production\Controllers\MissingBomController::class, 'index'])->name('articles-sans-nomenclature');

    // Planification — plan de charge + optimisation découpe : pilotage
    // production uniquement (chef production / directeur usine).
    Route::middleware('permission:production.update')->group(function () {
        Route::get('planning', [\App\Modules\Production\Controllers\ProductionPlanningController::class, 'index'])->name('planning');
        // [X3 §19] Déplacer OF / réaffecter ligne depuis le plan de charge
        Route::post('planning/replan/{order}', [\App\Modules\Production\Controllers\ProductionPlanningController::class, 'replan'])->name('planning.replan');
        Route::get('cutting', [\App\Modules\Production\Controllers\CuttingController::class, 'index'])->name('cutting');
        Route::post('cutting', [\App\Modules\Production\Controllers\CuttingController::class, 'optimize'])->name('cutting.optimize');
        // [Maquette Optimisation de découpe] fiches persistées
        Route::get('cutting/create', [\App\Modules\Production\Controllers\CuttingController::class, 'create'])->name('cutting.create');
        Route::get('cutting/import-lines', [\App\Modules\Production\Controllers\CuttingController::class, 'importLines'])->name('cutting.import-lines');
        Route::post('cutting/store', [\App\Modules\Production\Controllers\CuttingController::class, 'store'])->name('cutting.store');
        Route::get('cutting/{optimization}/edit', [\App\Modules\Production\Controllers\CuttingController::class, 'edit'])->name('cutting.edit');
        Route::put('cutting/{optimization}', [\App\Modules\Production\Controllers\CuttingController::class, 'update'])->name('cutting.update');
        Route::delete('cutting/{optimization}', [\App\Modules\Production\Controllers\CuttingController::class, 'destroyOptimization'])->name('cutting.destroy');
        Route::post('cutting/{optimization}/run', [\App\Modules\Production\Controllers\CuttingController::class, 'run'])->name('cutting.run');
    });

    // Maintenance machines
    Route::resource('maintenance', \App\Modules\Production\Controllers\MaintenanceController::class)->except('show')->parameters(['maintenance' => 'maintenance']);
    Route::post('maintenance/{maintenance}/start', [\App\Modules\Production\Controllers\MaintenanceController::class, 'start'])->name('maintenance.start');
    Route::post('maintenance/{maintenance}/finish', [\App\Modules\Production\Controllers\MaintenanceController::class, 'finish'])->name('maintenance.finish');
    // [CDC §13.8] Pièces de rechange consommées
    Route::post('maintenance/{maintenance}/parts', [\App\Modules\Production\Controllers\MaintenanceController::class, 'storePart'])->name('maintenance.parts.store');
    Route::delete('maintenance/parts/{part}', [\App\Modules\Production\Controllers\MaintenanceController::class, 'destroyPart'])->name('maintenance.parts.destroy');
    // [CDC §13.8] Plans de maintenance préventive
    Route::resource('maintenance-plans', \App\Modules\Production\Controllers\MaintenancePlanController::class)->except('show')->parameters(['maintenance-plans' => 'plan']);
    Route::post('maintenance-plans/generate', [\App\Modules\Production\Controllers\MaintenancePlanController::class, 'generate'])->name('maintenance-plans.generate');

    // MRP — réapprovisionnement bobines : pilotage production uniquement
    Route::middleware('permission:production.update')->group(function () {
        Route::get('mrp', [\App\Modules\Production\Controllers\MrpController::class, 'index'])->name('mrp');
        Route::post('mrp/generate', [\App\Modules\Production\Controllers\MrpController::class, 'generate'])->name('mrp.generate');
    });

    // Réception bobine ← Achats : génère des Coil depuis une réception validée
    Route::post('receptions/{reception}/coils', [\App\Modules\Production\Controllers\CoilReceptionController::class, 'generate'])->name('receptions.coils');

    // Prévision trésorerie production
    Route::get('treasury-forecast', [\App\Modules\Production\Controllers\ProductionTreasuryController::class, 'index'])->name('treasury');

    // Pointage temps (main-d'œuvre ← RH)
    Route::post('orders/{order}/time', [\App\Modules\Production\Controllers\ProductionTimeController::class, 'store'])->name('orders.time');
    Route::delete('time-logs/{timeLog}', [\App\Modules\Production\Controllers\ProductionTimeController::class, 'destroy'])->name('time-logs.destroy');

    // Lots de fabrication (batch/traçabilité)
    Route::post('orders/{order}/batches', [\App\Modules\Production\Controllers\BatchController::class, 'store'])->name('orders.batches');
    Route::post('batches/{batch}/close', [\App\Modules\Production\Controllers\BatchController::class, 'close'])->name('batches.close');

    // Réservation produit fini ← Ventes
    Route::post('orders/{order}/reserve', [\App\Modules\Production\Controllers\ProductionReservationController::class, 'reserve'])->name('orders.reserve');
    Route::post('reservations/{reservation}/release', [\App\Modules\Production\Controllers\ProductionReservationController::class, 'release'])->name('reservations.release');
    Route::post('sales/{commande}/reserve-stock', [\App\Modules\Production\Controllers\ProductionReservationController::class, 'reserveStock'])->name('sales.reserve-stock');

    // Ordres de fabrication — workflow
    Route::get('orders/{order}/pdf', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'pdf'])->name('orders.pdf');
    Route::post('orders/{order}/allocate', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'allocateMaterial'])->name('orders.allocate');
    Route::post('orders/{order}/launch', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'launch'])->name('orders.launch');
    Route::post('orders/{order}/start',  [\App\Modules\Production\Controllers\ProductionOrderController::class, 'start'])->name('orders.start');
    Route::post('orders/{order}/partial', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'partial'])->name('orders.partial');
    Route::post('orders/{order}/finish', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'finish'])->name('orders.finish');
    Route::post('orders/{order}/cancel', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'cancel'])->name('orders.cancel');
    // [X3] Suspension / reprise d'OF
    Route::post('orders/{order}/suspend', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'suspend'])->name('orders.suspend');
    Route::post('orders/{order}/resume',  [\App\Modules\Production\Controllers\ProductionOrderController::class, 'resume'])->name('orders.resume');
    // §13.2 CDC — Validation financière DAF/DG avant lancement OF
    Route::post('orders/{order}/authorize-finance', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'authorizeFinance'])->name('orders.authorize-finance');
    // §13.3 CDC — Validation 2-niveaux avant lancement (Chef Atelier → Responsable Production)
    Route::post('orders/{order}/submit-validation', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'submitForValidation'])->name('orders.submit-validation');
    Route::post('orders/{order}/validate-chef', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'validateChefAtelier'])->name('orders.validate-chef');
    Route::post('orders/{order}/validate-responsable', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'validateResponsable'])->name('orders.validate-responsable');
    // §13.10 CDC — Modification OF exceptionnelle : 4 étapes séquentielles
    // Chef Production → Commercial → Finance → DG
    Route::post('orders/{order}/request-modification', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'requestModification'])->name('orders.request-modification');
    Route::post('orders/{order}/modification-avis-chef', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'modificationChefAvis'])->name('orders.modification-avis-chef');
    Route::post('orders/{order}/modification-avis-commercial', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'modificationCommercialAvis'])->name('orders.modification-avis-commercial');
    Route::post('orders/{order}/modification-avis-finance', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'modificationFinanceAvis'])->name('orders.modification-avis-finance');
    Route::post('orders/{order}/modification-approve-dg', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'modificationDgApprove'])->name('orders.modification-approve-dg');
    Route::post('orders/{order}/modification-reject', [\App\Modules\Production\Controllers\ProductionOrderController::class, 'modificationReject'])->name('orders.modification-reject');

    // Exécution : consommation matière, sorties PF, chutes
    Route::post('orders/{order}/consume', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'consume'])->name('orders.consume');
    Route::delete('consumptions/{consumption}', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'destroyConsumption'])->name('consumptions.destroy');
    Route::post('orders/{order}/output', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'output'])->name('orders.output');
    Route::delete('outputs/{output}', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'destroyOutput'])->name('outputs.destroy');
    // [CDC §13.3] Visa chef d'équipe sur déclaration de production
    Route::post('outputs/{output}/validate', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'validateOutput'])->name('outputs.validate');
    Route::post('orders/{order}/waste', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'waste'])->name('orders.waste');
    Route::post('orders/{order}/byproduct', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'byproduct'])->name('orders.byproduct');
    Route::delete('wastes/{waste}', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'destroyWaste'])->name('wastes.destroy');
    // [§13.9 CDC] Validation rebuts : Chef Atelier → Responsable Qualité → GL
    Route::post('wastes/{waste}/validate-chef',    [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'validateChef'])->middleware('permission:production.declare')->name('wastes.validate-chef');
    Route::post('wastes/{waste}/validate-quality', [\App\Modules\Production\Controllers\ProductionExecutionController::class, 'validateQuality'])->middleware('permission:quality.manage')->name('wastes.validate-quality');

    // Coût de revient + contrôle qualité
    Route::post('orders/{order}/cost', [\App\Modules\Production\Controllers\ProductionCostController::class, 'compute'])->name('orders.cost');
    Route::post('orders/{order}/quality', [\App\Modules\Production\Controllers\ProductionQualityController::class, 'store'])->name('orders.quality');
    Route::delete('quality/{qualityControl}', [\App\Modules\Production\Controllers\ProductionQualityController::class, 'destroy'])->name('quality.destroy');

    Route::resource('orders', \App\Modules\Production\Controllers\ProductionOrderController::class);

    // Référentiel
    Route::resource('machines', \App\Modules\Production\Controllers\ProductionMachineController::class)->except('show');
    Route::resource('lines', \App\Modules\Production\Controllers\ProductionLineController::class)->except('show');
    Route::resource('work-centers', \App\Modules\Production\Controllers\WorkCenterController::class)->except('show')->parameters(['work-centers' => 'workCenter']);
    Route::resource('routings', \App\Modules\Production\Controllers\RoutingController::class)->except('show');

    // Work Orders — opérations d'un OF (gammes)
    Route::post('orders/{order}/operations', [\App\Modules\Production\Controllers\WorkOrderController::class, 'generate'])->name('orders.operations');
    Route::post('operations/{operation}/start', [\App\Modules\Production\Controllers\WorkOrderController::class, 'start'])->name('operations.start');
    Route::post('operations/{operation}/finish', [\App\Modules\Production\Controllers\WorkOrderController::class, 'finish'])->name('operations.finish');
    Route::resource('coils', \App\Modules\Production\Controllers\CoilController::class);
    Route::resource('bom', \App\Modules\Production\Controllers\BillOfMaterialController::class)->parameter('bom', 'bom');
});

// ═══ Direction — tableau de bord exécutif (cross-module) ═══
// [SEC §15] Synthèse exécutive (trésorerie, marges, cross-module) : réservée
// à la direction — reports.view ne suffit pas (le commercial l'a pour SES rapports).
Route::middleware(['auth', 'verified', 'permission:direction.view'])
    ->get('direction', [\App\Http\Controllers\DirectionDashboardController::class, 'index'])->name('direction.dashboard');

// ═══ Chaîne de Valeur Intégrée (§3 CDC) ═══
Route::middleware(['auth', 'verified', 'permission:direction.view'])
    ->get('chaine-valeur', [\App\Http\Controllers\ValueChainController::class, 'index'])->name('chaine-valeur');

// ═══ Qualité — contrôles, non-conformités & certificats (§10 CDC) ═══
Route::middleware(['auth', 'verified', 'permission:quality.view'])->prefix('qualite')->name('qualite.')->group(function () {
    Route::resource('inspections', \App\Modules\Quality\Controllers\QualityInspectionController::class)->except('show');
    Route::resource('non-conformities', \App\Modules\Quality\Controllers\NonConformityController::class)->except('show')->parameters(['non-conformities' => 'nonConformity']);
    // [QUA-05] Actions correctives / préventives (CAPA)
    Route::get('actions-correctives', [\App\Modules\Quality\Controllers\CorrectiveActionController::class, 'index'])->name('corrective-actions.index');
    Route::get('non-conformities/{nonConformity}/actions', [\App\Modules\Quality\Controllers\CorrectiveActionController::class, 'forNc'])->name('corrective-actions.nc');
    Route::post('non-conformities/{nonConformity}/actions', [\App\Modules\Quality\Controllers\CorrectiveActionController::class, 'store'])->name('corrective-actions.store');
    Route::post('actions-correctives/{action}/statut', [\App\Modules\Quality\Controllers\CorrectiveActionController::class, 'changeStatus'])->name('corrective-actions.status');
    Route::post('actions-correctives/{action}/verifier', [\App\Modules\Quality\Controllers\CorrectiveActionController::class, 'verify'])->name('corrective-actions.verify');
    // Certificats qualité (§8 Traçabilité + §10 Qualité)
    Route::resource('certificats', \App\Http\Controllers\Quality\QualityCertificateController::class)->parameters(['certificats' => 'certificat']);
    Route::post('certificats/{certificat}/approve', [\App\Http\Controllers\Quality\QualityCertificateController::class, 'approve'])->name('certificats.approve')->middleware('permission:quality.manage');
    Route::get('certificats/{certificat}/pdf', [\App\Http\Controllers\Quality\QualityCertificateController::class, 'pdf'])->name('certificats.pdf');
});

// ═══ Comptabilité analytique — Centres de coûts/profit (§12 CDC) ═══
Route::middleware(['auth', 'verified', 'permission:analytic.view'])->prefix('analytique')->name('analytique.')->group(function () {
    Route::resource('centres-couts', \App\Http\Controllers\Accounting\CostCenterController::class)->except('show')->parameters(['centres-couts' => 'costCenter']);
    Route::get('rapport', [\App\Http\Controllers\Accounting\CostCenterController::class, 'report'])->name('rapport');
    Route::get('centres-couts/{costCenter}', [\App\Http\Controllers\Accounting\CostCenterController::class, 'show'])->name('centres-couts.show');
    Route::post('lignes', [\App\Http\Controllers\Accounting\CostCenterController::class, 'storeLine'])->name('lignes.store');
});

require __DIR__.'/auth.php';
