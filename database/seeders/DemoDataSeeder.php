<?php
namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\DocumentSetting;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  SEEDER DE DÉMONSTRATION — données fictives à NE PAS charger en production.
 *
 *  Crée : sociétés de démo, exercices, clients/fournisseurs/produits fictifs,
 *  devis/commandes/factures/réceptions de test, paiements et mouvements de
 *  trésorerie, utilisateurs de démo (admin@iboa.bf…).
 *
 *  Garde-fou intégré : refuse de s'exécuter si APP_ENV=production, même appelé
 *  directement via `--class=DemoDataSeeder`. Définir SEED_DEMO_FORCE=true pour
 *  outrepasser (à n'utiliser que sciemment sur une base jetable).
 * ════════════════════════════════════════════════════════════════════════════
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Garde-fou production ───────────────────────────────────────────────
        if (app()->environment('production') && ! env('SEED_DEMO_FORCE', false)) {
            $this->command->error(
                '⛔ DemoDataSeeder bloqué : APP_ENV=production. '.
                'Les données de démonstration ne doivent jamais être chargées en '.
                'production. (Forcer avec SEED_DEMO_FORCE=true sur une base jetable.)'
            );

            return;
        }

        // ── Devise ────────────────────────────────────────────────────────────
        $xof = Currency::firstOrCreate(['code' => 'XOF'], [
            'name'               => 'Franc CFA BCEAO',
            'symbol'             => 'FCFA',
            'decimal_places'     => 0,
            'thousands_separator'=> ' ',
            'decimal_separator'  => ',',
            'is_default'         => true,
            'is_active'          => true,
        ]);

        // ── Exercice fiscal 2026 ───────────────────────────────────────────────
        $fy = FiscalYear::firstOrCreate(['label' => '2026'], [
            'starts_at'  => '2026-01-01',
            'ends_at'    => '2026-12-31',
            'status'     => 'ouvert',
            'is_current' => true,
        ]);

        // ── Société ───────────────────────────────────────────────────────────
        $company = Company::firstOrCreate(['name' => 'IBOA Commerce SARL'], [
            'trade_name'             => 'IBOA Commerce',
            'slogan'                 => 'Votre partenaire commercial de confiance',
            'address'                => 'Avenue de la Nation, Secteur 4',
            'city'                   => 'Ouagadougou',
            'country'                => 'Burkina Faso',
            'phone'                  => '+226 25 30 00 00',
            'phone2'                 => '+226 70 00 00 00',
            'email'                  => 'contact@iboa-commerce.bf',
            'legal_form'             => 'SARL',
            'rccm'                   => 'BF-OUA-2020-B-12345',
            'ifu'                    => '00123456789',
            'is_vat_subject'         => true,
            'vat_number'             => 18,
            'share_capital'          => 5000000,
            'default_currency_id'    => $xof->id,
            'current_fiscal_year_id' => $fy->id,
        ]);

        // S'assurer que l'exercice courant est bien 2026
        if ($company->current_fiscal_year_id !== $fy->id) {
            $company->update(['current_fiscal_year_id' => $fy->id]);
        }

        // ── Paramètres documents ──────────────────────────────────────────────
        DocumentSetting::firstOrCreate(['company_id' => $company->id], [
            'primary_color'      => '#1e40af',
            'font_family'        => 'DejaVu Sans',
            'page_size'          => 'A4',
            'show_logo'          => true,
            'footer_text'        => 'Merci de votre confiance. IBOA Commerce SARL — RCCM : BF-OUA-2020-B-12345 — IFU : 00123456789',
            'terms_conditions'   => 'Tout règlement doit être effectué à 30 jours de la date de facturation. Tout retard de paiement entraîne des pénalités de 1,5 % par mois.',
        ]);

        // ── Compte bancaire ───────────────────────────────────────────────────
        CompanyBankAccount::firstOrCreate(
            ['company_id' => $company->id, 'account_number' => 'BF001234567890'],
            [
                'bank_name'      => 'Coris Bank International',
                'account_holder' => 'IBOA Commerce SARL',
                'branch'         => 'Agence Centrale Ouagadougou',
                'is_default'     => true,
                'is_active'      => true,
            ]
        );

        // ── Dépôt ─────────────────────────────────────────────────────────────
        $warehouse = Warehouse::firstOrCreate(['code' => 'DEP-CENTRAL'], [
            'company_id' => $company->id,
            'name'       => 'Dépôt Central',
            'address'    => 'Zone Industrielle de Gounghin',
            'city'       => 'Ouagadougou',
            'is_default' => true,
            'is_active'  => true,
        ]);

        // ── Taux de TVA ───────────────────────────────────────────────────────
        $tva18 = TaxRate::firstOrCreate(['short_name' => 'TVA18'], [
            'name'       => 'TVA 18 %',
            'rate'       => 18.00,
            'is_default' => true,
            'is_active'  => true,
        ]);
        TaxRate::firstOrCreate(['short_name' => 'EXO'], [
            'name'       => 'Exonéré',
            'rate'       => 0.00,
            'is_default' => false,
            'is_active'  => true,
        ]);

        // ── Unités ────────────────────────────────────────────────────────────
        $pce = Unit::firstOrCreate(['abbreviation' => 'pcs'], [
            'name' => 'Pièce', 'type' => 'quantite', 'decimal_places' => 0,
        ]);
        Unit::firstOrCreate(['abbreviation' => 'kg'],     ['name' => 'Kilogramme', 'type' => 'poids',    'decimal_places' => 3]);
        Unit::firstOrCreate(['abbreviation' => 'L'],      ['name' => 'Litre',      'type' => 'volume',   'decimal_places' => 2]);
        Unit::firstOrCreate(['abbreviation' => 'carton'], ['name' => 'Carton',     'type' => 'quantite', 'decimal_places' => 0]);

        // ── Familles produits ─────────────────────────────────────────────────
        $informatique = ProductFamily::firstOrCreate(['code' => 'INFO'], [
            'name' => 'Informatique', 'depth' => 0, 'is_active' => true,
        ]);
        $ordis = ProductFamily::firstOrCreate(['code' => 'INFO-ORD'], [
            'parent_id' => $informatique->id, 'name' => 'Ordinateurs', 'depth' => 1, 'is_active' => true,
        ]);
        $peripheriques = ProductFamily::firstOrCreate(['code' => 'INFO-PER'], [
            'parent_id' => $informatique->id, 'name' => 'Périphériques', 'depth' => 1, 'is_active' => true,
        ]);
        $fournitures = ProductFamily::firstOrCreate(['code' => 'FOUR'], [
            'name' => 'Fournitures de bureau', 'depth' => 0, 'is_active' => true,
        ]);

        // ── Marques ───────────────────────────────────────────────────────────
        $hp     = Brand::firstOrCreate(['name' => 'HP'],      ['is_active' => true]);
        $dell   = Brand::firstOrCreate(['name' => 'Dell'],    ['is_active' => true]);
        $samsung= Brand::firstOrCreate(['name' => 'Samsung'], ['is_active' => true]);

        // ── Produits ──────────────────────────────────────────────────────────
        $productDefs = [
            ['reference' => 'ART-00001', 'name' => 'Ordinateur portable HP 15s',      'family_id' => $ordis->id,         'brand_id' => $hp->id,     'purchase_price' => 250000, 'sale_price' => 320000, 'stock_min' => 2],
            ['reference' => 'ART-00002', 'name' => 'Ordinateur portable Dell Inspiron','family_id' => $ordis->id,         'brand_id' => $dell->id,   'purchase_price' => 280000, 'sale_price' => 360000, 'stock_min' => 2],
            ['reference' => 'ART-00003', 'name' => 'Écran Samsung 24"',               'family_id' => $peripheriques->id,  'brand_id' => $samsung->id,'purchase_price' =>  85000, 'sale_price' => 110000, 'stock_min' => 3],
            ['reference' => 'ART-00004', 'name' => 'Clavier USB',                     'family_id' => $peripheriques->id,  'brand_id' => null,        'purchase_price' =>   5000, 'sale_price' =>   8000, 'stock_min' => 5],
            ['reference' => 'ART-00005', 'name' => 'Souris optique USB',              'family_id' => $peripheriques->id,  'brand_id' => null,        'purchase_price' =>   3000, 'sale_price' =>   5000, 'stock_min' => 5],
            ['reference' => 'ART-00006', 'name' => 'Rame de papier A4 80g',           'family_id' => $fournitures->id,    'brand_id' => null,        'purchase_price' =>   3000, 'sale_price' =>   4500, 'stock_min' => 10],
        ];

        foreach ($productDefs as $p) {
            Product::firstOrCreate(['reference' => $p['reference']], array_merge($p, [
                'unit_id'       => $pce->id,
                'tax_rate_id'   => $tva18->id,
                'is_active'     => true,
                'is_stockable'  => true,
            ]));
        }

        // ── Clients ───────────────────────────────────────────────────────────
        $clientDefs = [
            ['code' => 'CLI-00001', 'name' => 'SONABHY',           'type' => 'entreprise',  'phone' => '+226 25 30 61 00', 'email' => 'contact@sonabhy.bf',  'city' => 'Ouagadougou',    'category' => 'gros',      'credit_limit' => 5000000, 'payment_days' => 30],
            ['code' => 'CLI-00002', 'name' => 'ONATEL SA',         'type' => 'entreprise',  'phone' => '+226 25 33 40 00', 'email' => 'info@onatel.bf',      'city' => 'Ouagadougou',    'category' => 'gros',      'credit_limit' => 3000000, 'payment_days' => 30],
            ['code' => 'CLI-00003', 'name' => 'Boubacar Ouédraogo','type' => 'particulier', 'phone' => '+226 70 12 34 56', 'email' => null,                  'city' => 'Ouagadougou',    'category' => 'detail',    'credit_limit' => 0,       'payment_days' => 0],
            ['code' => 'CLI-00004', 'name' => 'Cabinet Conseil BF','type' => 'entreprise',  'phone' => '+226 25 30 22 33', 'email' => null,                  'city' => 'Bobo-Dioulasso', 'category' => 'semi-gros', 'credit_limit' => 1000000, 'payment_days' => 15],
        ];

        foreach ($clientDefs as $c) {
            $existing = Client::withTrashed()->where('code', $c['code'])->first();
            if (!$existing) {
                Client::create(array_merge($c, ['country' => 'Burkina Faso', 'is_active' => true]));
            } elseif ($existing->trashed()) {
                $existing->restore();
                $existing->update(array_merge($c, ['country' => 'Burkina Faso', 'is_active' => true]));
            }
        }

        // ── Fournisseurs ──────────────────────────────────────────────────────
        $supplierDefs = [
            ['code' => 'FOUR-00001', 'name' => 'Tech Afrique Distribution', 'type' => 'entreprise', 'phone' => '+226 25 36 00 11', 'city' => 'Ouagadougou', 'is_active' => true],
            ['code' => 'FOUR-00002', 'name' => 'Bureau Plus SARL',          'type' => 'entreprise', 'phone' => '+226 25 31 45 67', 'city' => 'Ouagadougou', 'is_active' => true],
        ];
        foreach ($supplierDefs as $s) {
            Supplier::firstOrCreate(['code' => $s['code']], array_merge($s, ['country' => 'Burkina Faso']));
        }

        // ── Stocks initiaux ───────────────────────────────────────────────────
        // ART-00006 volontairement sous le stock_min=10 pour déclencher l'alerte
        $stockDefs = [
            'ART-00001' => 5,
            'ART-00002' => 3,
            'ART-00003' => 8,
            'ART-00004' => 20,
            'ART-00005' => 15,
            'ART-00006' => 1,
        ];
        foreach ($stockDefs as $ref => $qty) {
            $product = Product::where('reference', $ref)->first();
            if ($product) {
                ProductStock::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => $qty, 'reserved_quantity' => 0, 'avg_cost' => $product->purchase_price]
                );
            }
        }

        // ── Moyens de paiement ────────────────────────────────────────────────
        $pmDefs = [
            ['code' => 'especes',      'name' => 'Espèces',      'type' => 'especes',      'is_mobile_money' => false, 'requires_reference' => false, 'sort_order' => 1],
            ['code' => 'virement',     'name' => 'Virement',     'type' => 'virement',     'is_mobile_money' => false, 'requires_reference' => true,  'sort_order' => 2],
            ['code' => 'cheque',       'name' => 'Chèque',       'type' => 'cheque',       'is_mobile_money' => false, 'requires_reference' => true,  'sort_order' => 3],
            ['code' => 'orange_money', 'name' => 'Orange Money', 'type' => 'mobile_money', 'is_mobile_money' => true,  'requires_reference' => true,  'provider' => 'Orange Money', 'sort_order' => 4],
            ['code' => 'moov_money',   'name' => 'Moov Money',   'type' => 'mobile_money', 'is_mobile_money' => true,  'requires_reference' => true,  'provider' => 'Moov Money',   'sort_order' => 5],
        ];
        foreach ($pmDefs as $pm) {
            PaymentMethod::firstOrCreate(['code' => $pm['code']], array_merge($pm, ['is_active' => true]));
        }

        $pmEspeces  = PaymentMethod::where('code', 'especes')->first();
        $pmVirement = PaymentMethod::where('code', 'virement')->first();
        $pmOrange   = PaymentMethod::where('code', 'orange_money')->first();

        // ── Caisses / comptes bancaires ───────────────────────────────────────
        $caissePrincipale = CashAccount::firstOrCreate(['code' => 'CAISSE-01'], [
            'company_id'        => $company->id,
            'name'              => 'Caisse Principale',
            'type'              => 'caisse',
            'payment_method_id' => $pmEspeces?->id,
            'currency_code'     => 'XOF',
            'opening_balance'   => 500000,
            'current_balance'   => 500000,
            'is_default'        => true,
            'is_active'         => true,
        ]);

        $compteBanque = CashAccount::firstOrCreate(['code' => 'BQ-CORIS-01'], [
            'company_id'        => $company->id,
            'name'              => 'Compte Coris Bank',
            'type'              => 'banque',
            'payment_method_id' => $pmVirement?->id,
            'currency_code'     => 'XOF',
            'opening_balance'   => 5000000,
            'current_balance'   => 5000000,
            'is_default'        => false,
            'is_active'         => true,
        ]);

        $compteMobile = CashAccount::firstOrCreate(['code' => 'ORANGE-01'], [
            'company_id'        => $company->id,
            'name'              => 'Orange Money',
            'type'              => 'mobile_money',
            'payment_method_id' => $pmOrange?->id,
            'currency_code'     => 'XOF',
            'opening_balance'   => 200000,
            'current_balance'   => 200000,
            'is_default'        => false,
            'is_active'         => true,
        ]);

        // ── Utilisateurs ──────────────────────────────────────────────────────
        $roleAdmin      = Role::where('name', 'super_admin')->first();
        $roleDirecteur  = Role::where('name', 'directeur')->first();
        $roleCommercial = Role::where('name', 'commercial')->first();
        $roleMagasinier = Role::where('name', 'magasinier')->first();
        $roleComptable  = Role::where('name', 'comptable')->first();

        $admin = User::firstOrCreate(['email' => 'admin@iboa.bf'], [
            'name'       => 'Administrateur IBOA',
            'password'   => Hash::make('password'),
            'company_id' => $company->id,
            'job_title'  => 'Super Administrateur',
            'is_active'  => true,
        ]);
        if ($roleAdmin && !$admin->hasRole('super_admin')) $admin->assignRole($roleAdmin);

        $directeur = User::firstOrCreate(['email' => 'directeur@iboa.bf'], [
            'name'       => 'Moussa Kaboré',
            'password'   => Hash::make('password'),
            'company_id' => $company->id,
            'job_title'  => 'Directeur Général',
            'is_active'  => true,
        ]);
        if ($roleDirecteur && !$directeur->hasRole('directeur')) $directeur->assignRole($roleDirecteur);

        $commercial = User::firstOrCreate(['email' => 'commercial@iboa.bf'], [
            'name'       => 'Aminata Sawadogo',
            'password'   => Hash::make('password'),
            'company_id' => $company->id,
            'job_title'  => 'Commerciale',
            'is_active'  => true,
        ]);
        if ($roleCommercial && !$commercial->hasRole('commercial')) $commercial->assignRole($roleCommercial);

        $magasinier = User::firstOrCreate(['email' => 'magasinier@iboa.bf'], [
            'name'       => 'Salif Diallo',
            'password'   => Hash::make('password'),
            'company_id' => $company->id,
            'job_title'  => 'Magasinier',
            'is_active'  => true,
        ]);
        if ($roleMagasinier && !$magasinier->hasRole('magasinier')) $magasinier->assignRole($roleMagasinier);

        $comptable = User::firstOrCreate(['email' => 'comptable@iboa.bf'], [
            'name'       => 'Patricia Konaté',
            'password'   => Hash::make('password'),
            'company_id' => $company->id,
            'job_title'  => 'Comptable',
            'is_active'  => true,
        ]);
        if ($roleComptable && !$comptable->hasRole('comptable')) $comptable->assignRole($roleComptable);

        // ── Utilisateurs CDC §15 (rôles production / RH / finance) ──────────
        $newUsers = [
            ['email' => 'daf@iboa.bf',                   'name' => 'Fatoumata Compaoré',   'role' => 'daf',                   'job' => 'DAF — Directrice Administrative et Financière'],
            ['email' => 'directeur.usine@iboa.bf',       'name' => 'Ibrahim Ouédraogo',     'role' => 'directeur_usine',       'job' => "Directeur d'Usine"],
            ['email' => 'acheteur@iboa.bf',              'name' => 'Rasmané Traoré',        'role' => 'acheteur',              'job' => 'Acheteur / Approvisionneur'],
            ['email' => 'chef.production@iboa.bf',       'name' => 'Adama Zongo',           'role' => 'chef_production',       'job' => 'Chef de Production'],
            ['email' => 'operateur@iboa.bf',             'name' => 'Kofi Sawadogo',         'role' => 'operateur_production',  'job' => 'Opérateur de Production'],
            ['email' => 'qualite@iboa.bf',               'name' => 'Mariam Kaboré',         'role' => 'responsable_qualite',   'job' => 'Responsable Qualité'],
            ['email' => 'maintenance@iboa.bf',           'name' => 'Noël Ouattara',         'role' => 'technicien_maintenance','job' => 'Technicien Maintenance'],
            ['email' => 'drh@iboa.bf',                   'name' => 'Clarisse Bonkoungou',   'role' => 'drh',                   'job' => 'Directrice RH'],
            ['email' => 'rh.manager@iboa.bf',            'name' => 'Boukary Nana',          'role' => 'rh_manager',            'job' => 'Gestionnaire RH'],
            ['email' => 'rh.agent@iboa.bf',              'name' => 'Aïssata Ouédraogo',     'role' => 'rh_agent',              'job' => 'Agent RH'],
            ['email' => 'employe@iboa.bf',               'name' => 'Jean-Pierre Kinda',     'role' => 'employe',               'job' => 'Employé'],
            ['email' => 'resp.commercial@iboa.bf',       'name' => 'Sylvie Ilboudo',        'role' => 'responsable_commercial','job' => 'Responsable Commercial'],
            ['email' => 'resp.stock@iboa.bf',            'name' => 'Wendyam Bassole',       'role' => 'responsable_stock',     'job' => 'Responsable Stock'],
            ['email' => 'caissier@iboa.bf',              'name' => 'Inès Tapsoba',          'role' => 'caissier',              'job' => 'Caissière'],
            ['email' => 'lecture@iboa.bf',               'name' => 'Auditeur Externe',      'role' => 'lecture_seule',         'job' => 'Lecture seule (audit)'],
        ];

        foreach ($newUsers as $u) {
            $role = Role::where('name', $u['role'])->first();
            if (! $role) continue;
            $user = User::firstOrCreate(['email' => $u['email']], [
                'name'       => $u['name'],
                'password'   => Hash::make('password'),
                'company_id' => $company->id,
                'job_title'  => $u['job'],
                'is_active'  => true,
            ]);
            if (! $user->hasRole($u['role'])) {
                $user->assignRole($role);
            }
        }

        // =====================================================================
        // ACHATS
        // =====================================================================

        $supplier1 = Supplier::where('code', 'FOUR-00001')->first();
        $supplier2 = Supplier::where('code', 'FOUR-00002')->first();
        $p1 = Product::where('reference', 'ART-00001')->first(); // HP 15s        — PA 250 000 / PV 320 000
        $p2 = Product::where('reference', 'ART-00002')->first(); // Dell Inspiron  — PA 280 000 / PV 360 000
        $p3 = Product::where('reference', 'ART-00003')->first(); // Écran Samsung  — PA  85 000 / PV 110 000
        $p4 = Product::where('reference', 'ART-00004')->first(); // Clavier USB    — PA   5 000 / PV   8 000
        $p5 = Product::where('reference', 'ART-00005')->first(); // Souris USB     — PA   3 000 / PV   5 000

        // ── BC-2026-001 : 3× HP 15s (fournisseur Tech Afrique) ───────────────
        // Calcul : 3 × 250 000 × (1–0 %) = 750 000 HT ; TVA 18 % = 135 000 ; TTC = 885 000
        if ($supplier1 && $p1) {
            $po1 = PurchaseOrder::firstOrCreate(['number' => 'BC-2026-001'], [
                'supplier_id'  => $supplier1->id,
                'company_id'   => $company->id,
                'status'       => 'confirme',
                'ordered_at'   => now()->subDays(30),
                'expected_at'  => now()->subDays(15),
                'created_by'   => $admin->id,
                'subtotal_ht'  => 750000,
                'total_tax'    => 135000,
                'total_ttc'    => 885000,
            ]);

            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po1->id, 'product_id' => $p1->id],
                [
                    'description'     => $p1->name,
                    'quantity'        => 3,
                    'unit_price'      => 250000,
                    'discount_percent'=> 0,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 750000,   // 3 × 250 000
                    'line_tax'        => 135000,   // 750 000 × 18 %
                    'line_total_ttc'  => 885000,
                ]
            );

            $reception1 = Reception::firstOrCreate(['number' => 'REC-2026-001'], [
                'purchase_order_id' => $po1->id,
                'warehouse_id'      => $warehouse->id,
                'company_id'        => $company->id,
                'status'            => 'valide',
                'received_at'       => now()->subDays(20),
                'created_by'        => $magasinier->id,
                'validated_by'      => $magasinier->id,
                'validated_at'      => now()->subDays(20),
            ]);

            $poItem1 = PurchaseOrderItem::where(['purchase_order_id' => $po1->id, 'product_id' => $p1->id])->first();
            ReceptionItem::firstOrCreate(
                ['reception_id' => $reception1->id, 'product_id' => $p1->id],
                [
                    'purchase_order_item_id' => $poItem1?->id,
                    'description'            => $p1->name,
                    'expected_quantity'      => 3,
                    'received_quantity'      => 3,
                    'rejected_quantity'      => 0,
                    'unit_cost'              => 250000,
                ]
            );

            // Facture fournisseur validée
            $sinv1 = SupplierInvoice::firstOrCreate(['number' => 'FINV-2026-001'], [
                'supplier_id'            => $supplier1->id,
                'company_id'             => $company->id,
                'purchase_order_id'      => $po1->id,
                'reception_id'           => $reception1->id,
                'supplier_invoice_number'=> 'TAD-2026-0042',
                'status'                 => 'validee',
                'received_at'            => now()->subDays(18),
                'due_at'                 => now()->addDays(12),   // 30 j nets
                'created_by'             => $comptable->id,
                'validated_at'           => now()->subDays(17),
                'validated_by'           => $comptable->id,
                'subtotal_ht'            => 750000,
                'total_tax'              => 135000,
                'total_ttc'              => 885000,
                'paid_amount'            => 0,
                'remaining_amount'       => 885000,
            ]);

            SupplierInvoiceItem::firstOrCreate(
                ['supplier_invoice_id' => $sinv1->id, 'product_id' => $p1->id],
                [
                    'description'    => $p1->name,
                    'quantity'       => 3,
                    'unit_price'     => 250000,
                    'tax_rate_id'    => $tva18->id,
                    'tax_rate_value' => 18,
                    'line_total_ht'  => 750000,
                    'line_tax'       => 135000,
                    'line_total_ttc' => 885000,
                ]
            );
        }

        // ── BC-2026-002 : écrans + claviers (fournisseur Bureau Plus) ─────────
        // p3 : 5 × 85 000 × (1–5 %) = 5 × 80 750 = 403 750 HT ; TVA = 72 675 ; TTC = 476 425
        // p4 : 100 × 5 000 × (1–10%) = 100 × 4 500 = 450 000 HT ; TVA = 81 000 ; TTC = 531 000
        // Total              : HT = 853 750 ; TVA = 153 675 ; TTC = 1 007 425
        if ($supplier2 && $p3 && $p4) {
            $po2 = PurchaseOrder::firstOrCreate(['number' => 'BC-2026-002'], [
                'supplier_id'  => $supplier2->id,
                'company_id'   => $company->id,
                'status'       => 'recu',
                'ordered_at'   => now()->subDays(25),
                'expected_at'  => now()->subDays(10),
                'created_by'   => $commercial->id,
                'subtotal_ht'  => 853750,
                'total_tax'    => 153675,
                'total_ttc'    => 1007425,
            ]);

            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po2->id, 'product_id' => $p3->id],
                [
                    'description'     => $p3->name,
                    'quantity'        => 5,
                    'unit_price'      => 85000,
                    'discount_percent'=> 5,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 403750,   // 5 × 85 000 × 0,95
                    'line_tax'        => 72675,    // 403 750 × 18 %
                    'line_total_ttc'  => 476425,
                ]
            );

            PurchaseOrderItem::firstOrCreate(
                ['purchase_order_id' => $po2->id, 'product_id' => $p4->id],
                [
                    'description'     => $p4->name,
                    'quantity'        => 100,
                    'unit_price'      => 5000,
                    'discount_percent'=> 10,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 450000,   // 100 × 5 000 × 0,90
                    'line_tax'        => 81000,    // 450 000 × 18 %
                    'line_total_ttc'  => 531000,
                ]
            );
        }

        // =====================================================================
        // VENTES — Cycle complet  DEV → COM → FAC → BL → Paiement
        // =====================================================================

        $client1 = Client::where('code', 'CLI-00001')->first(); // SONABHY
        $client2 = Client::where('code', 'CLI-00002')->first(); // ONATEL

        // ── Devis DEV-2026-001 (SONABHY) ─────────────────────────────────────
        // p1 : 2 × 320 000 × (1–0 %) = 640 000 HT ; TVA = 115 200 ; TTC = 755 200
        // p3 : 3 × 110 000 × (1–5 %) = 3 × 104 500 = 313 500 HT ; TVA = 56 430 ; TTC = 369 930
        // Total  : HT = 953 500 ; TVA = 171 630 ; TTC = 1 125 130
        if ($client1 && $p1 && $p3) {
            $quote1 = Quote::firstOrCreate(['number' => 'DEV-2026-001'], [
                'client_id'      => $client1->id,
                'company_id'     => $company->id,
                'fiscal_year_id' => $fy->id,
                'status'         => 'accepte',
                'issued_at'      => now()->subDays(20),
                'expires_at'     => now()->addDays(40),
                'created_by'     => $commercial->id,
                'subtotal_ht'    => 953500,
                'total_discount' => 0,
                'total_tax'      => 171630,
                'total_ttc'      => 1125130,
            ]);

            QuoteItem::firstOrCreate(
                ['quote_id' => $quote1->id, 'product_id' => $p1->id],
                [
                    'description'     => $p1->name,
                    'quantity'        => 2,
                    'unit_price'      => 320000,
                    'discount_percent'=> 0,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 640000,   // 2 × 320 000
                    'line_tax'        => 115200,   // 640 000 × 18 %
                    'line_total_ttc'  => 755200,
                ]
            );

            QuoteItem::firstOrCreate(
                ['quote_id' => $quote1->id, 'product_id' => $p3->id],
                [
                    'description'     => $p3->name,
                    'quantity'        => 3,
                    'unit_price'      => 110000,
                    'discount_percent'=> 5,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 313500,   // 3 × 110 000 × 0,95
                    'line_tax'        => 56430,    // 313 500 × 18 %
                    'line_total_ttc'  => 369930,
                ]
            );

            // Commande COM-2026-001
            $order1 = Order::firstOrCreate(['number' => 'COM-2026-001'], [
                'quote_id'        => $quote1->id,
                'client_id'       => $client1->id,
                'company_id'      => $company->id,
                'fiscal_year_id'  => $fy->id,
                'status'          => 'partiellement_livre',
                'issued_at'       => now()->subDays(18),
                'delivery_date'   => now()->addDays(7),
                'created_by'      => $commercial->id,
                'validated_by'    => $commercial->id,
                'validated_at'    => now()->subDays(18),
                'subtotal_ht'     => 953500,
                'total_discount'  => 0,
                'total_tax'       => 171630,
                'total_ttc'       => 1125130,
            ]);
            if (!$quote1->converted_to_order_id) {
                $quote1->update(['converted_to_order_id' => $order1->id, 'status' => 'accepte']);
            }

            $oi1a = OrderItem::firstOrCreate(
                ['order_id' => $order1->id, 'product_id' => $p1->id],
                [
                    'description'        => $p1->name,
                    'quantity'           => 2,
                    'delivered_quantity' => 2,   // livré en totalité par BL-2026-001
                    'unit_price'         => 320000,
                    'discount_percent'   => 0,
                    'tax_rate_id'        => $tva18->id,
                    'tax_rate_value'     => 18,
                    'line_total_ht'      => 640000,
                    'line_tax'           => 115200,
                    'line_total_ttc'     => 755200,
                ]
            );

            $oi1b = OrderItem::firstOrCreate(
                ['order_id' => $order1->id, 'product_id' => $p3->id],
                [
                    'description'        => $p3->name,
                    'quantity'           => 3,
                    'delivered_quantity' => 0,   // reliquat : encore à livrer
                    'unit_price'         => 110000,
                    'discount_percent'   => 5,
                    'tax_rate_id'        => $tva18->id,
                    'tax_rate_value'     => 18,
                    'line_total_ht'      => 313500,
                    'line_tax'           => 56430,
                    'line_total_ttc'     => 369930,
                ]
            );

            // Facture FAC-2026-001 (émise, non payée)
            $invoice1 = Invoice::firstOrCreate(['number' => 'FAC-2026-001'], [
                'order_id'        => $order1->id,
                'client_id'       => $client1->id,
                'company_id'      => $company->id,
                'fiscal_year_id'  => $fy->id,
                'status'          => 'emise',
                'issued_at'       => now()->subDays(15),
                'due_at'          => now()->addDays(15),   // 30 j nets
                'payment_terms'   => '30 jours nets',
                'created_by'      => $commercial->id,
                'validated_by'    => $commercial->id,
                'validated_at'    => now()->subDays(15),
                'subtotal_ht'     => 953500,
                'total_discount'  => 0,
                'total_tax'       => 171630,
                'total_ttc'       => 1125130,
                'paid_amount'     => 0,
                'remaining_amount'=> 1125130,
            ]);

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice1->id, 'product_id' => $p1->id],
                [
                    'description'     => $p1->name,
                    'quantity'        => 2,
                    'unit_price'      => 320000,
                    'discount_percent'=> 0,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 640000,
                    'line_tax'        => 115200,
                    'line_total_ttc'  => 755200,
                ]
            );

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice1->id, 'product_id' => $p3->id],
                [
                    'description'     => $p3->name,
                    'quantity'        => 3,
                    'unit_price'      => 110000,
                    'discount_percent'=> 5,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 313500,
                    'line_tax'        => 56430,
                    'line_total_ttc'  => 369930,
                ]
            );

            // BL-2026-001 : livraison partielle (p1 uniquement — p3 en reliquat)
            $bl1 = DeliveryNote::firstOrCreate(['number' => 'BL-2026-001'], [
                'order_id'     => $order1->id,
                'client_id'    => $client1->id,
                'company_id'   => $company->id,
                'warehouse_id' => $warehouse->id,
                'status'       => 'valide',
                'issued_at'    => now()->subDays(12),
                'created_by'   => $magasinier->id,
                'validated_by' => $magasinier->id,
                'validated_at' => now()->subDays(12),
                'total_quantity'=> 2,
            ]);

            DeliveryNoteItem::firstOrCreate(
                ['delivery_note_id' => $bl1->id, 'product_id' => $p1->id],
                [
                    'order_item_id' => $oi1a->id,
                    'description'   => $p1->name,
                    'quantity'      => 2,
                    'unit_price'    => 320000,
                ]
            );

            // Paiement partiel PAYCLI-2026-001 (virement, 60 % de la facture)
            // 1 125 130 × 60 % ≈ 675 078 → arrondi à 675 000
            $payment1 = ClientPayment::firstOrCreate(['number' => 'PAYCLI-2026-001'], [
                'client_id'        => $client1->id,
                'company_id'       => $company->id,
                'amount'           => 675000,
                'payment_method_id'=> $pmVirement?->id,
                'payment_date'     => now()->subDays(5),
                'reference'        => 'VIR-BQ-2026-001',
                'notes'            => 'Acompte 60 % sur FAC-2026-001',
                'status'           => 'confirme',
                'created_by'       => $comptable->id,
            ]);
            // Allocation : lie le paiement à la facture (sinon paid_amount incohérent)
            \App\Models\ClientPaymentAllocation::firstOrCreate(
                ['client_payment_id' => $payment1->id, 'invoice_id' => $invoice1->id],
                ['amount' => 675000, 'allocated_at' => now()->subDays(5)]
            );
            // Comptabilisation GL (DR trésorerie / CR clients) — idempotent.
            if (! $payment1->journal_entry_id) {
                app(\App\Services\AccountingService::class)->postClientPayment($payment1->fresh(['client', 'company']));
            }
        }

        // ── Commande COM-2026-002 (ONATEL) — facture réglée ───────────────────
        // p2 : 2 × 360 000 × (1–0 %) = 720 000 HT ; TVA = 129 600 ; TTC = 849 600
        // p5 : 40 × 5 000 × (1–10%) = 40 × 4 500 = 180 000 HT ; TVA = 32 400 ; TTC = 212 400
        // Total  : HT = 900 000 ; TVA = 162 000 ; TTC = 1 062 000
        if ($client2 && $p2 && $p5) {
            $order2 = Order::firstOrCreate(['number' => 'COM-2026-002'], [
                'client_id'      => $client2->id,
                'company_id'     => $company->id,
                'fiscal_year_id' => $fy->id,
                'status'         => 'livre',
                'issued_at'      => now()->subDays(12),
                'delivery_date'  => now()->subDays(5),
                'created_by'     => $commercial->id,
                'validated_by'   => $commercial->id,
                'validated_at'   => now()->subDays(12),
                'subtotal_ht'    => 900000,
                'total_discount' => 0,
                'total_tax'      => 162000,
                'total_ttc'      => 1062000,
            ]);

            $oi2a = OrderItem::firstOrCreate(
                ['order_id' => $order2->id, 'product_id' => $p2->id],
                [
                    'description'        => $p2->name,
                    'quantity'           => 2,
                    'delivered_quantity' => 2,
                    'unit_price'         => 360000,
                    'discount_percent'   => 0,
                    'tax_rate_id'        => $tva18->id,
                    'tax_rate_value'     => 18,
                    'line_total_ht'      => 720000,   // 2 × 360 000
                    'line_tax'           => 129600,   // 720 000 × 18 %
                    'line_total_ttc'     => 849600,
                ]
            );

            $oi2b = OrderItem::firstOrCreate(
                ['order_id' => $order2->id, 'product_id' => $p5->id],
                [
                    'description'        => $p5->name,
                    'quantity'           => 40,
                    'delivered_quantity' => 40,
                    'unit_price'         => 5000,
                    'discount_percent'   => 10,
                    'tax_rate_id'        => $tva18->id,
                    'tax_rate_value'     => 18,
                    'line_total_ht'      => 180000,   // 40 × 5 000 × 0,90
                    'line_tax'           => 32400,    // 180 000 × 18 %
                    'line_total_ttc'     => 212400,
                ]
            );

            // Facture FAC-2026-002 (entièrement réglée)
            $invoice2 = Invoice::firstOrCreate(['number' => 'FAC-2026-002'], [
                'order_id'        => $order2->id,
                'client_id'       => $client2->id,
                'company_id'      => $company->id,
                'fiscal_year_id'  => $fy->id,
                'status'          => 'payee',
                'issued_at'       => now()->subDays(10),
                'due_at'          => now()->addDays(20),
                'payment_terms'   => '30 jours nets',
                'created_by'      => $commercial->id,
                'validated_by'    => $commercial->id,
                'validated_at'    => now()->subDays(10),
                'subtotal_ht'     => 900000,
                'total_discount'  => 0,
                'total_tax'       => 162000,
                'total_ttc'       => 1062000,
                'paid_amount'     => 1062000,
                'remaining_amount'=> 0,
            ]);

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice2->id, 'product_id' => $p2->id],
                [
                    'description'     => $p2->name,
                    'quantity'        => 2,
                    'unit_price'      => 360000,
                    'discount_percent'=> 0,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 720000,
                    'line_tax'        => 129600,
                    'line_total_ttc'  => 849600,
                ]
            );

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice2->id, 'product_id' => $p5->id],
                [
                    'description'     => $p5->name,
                    'quantity'        => 40,
                    'unit_price'      => 5000,
                    'discount_percent'=> 10,
                    'tax_rate_id'     => $tva18->id,
                    'tax_rate_value'  => 18,
                    'line_total_ht'   => 180000,
                    'line_tax'        => 32400,
                    'line_total_ttc'  => 212400,
                ]
            );

            // BL-2026-002 : livraison complète
            $bl2 = DeliveryNote::firstOrCreate(['number' => 'BL-2026-002'], [
                'order_id'      => $order2->id,
                'client_id'     => $client2->id,
                'company_id'    => $company->id,
                'warehouse_id'  => $warehouse->id,
                'status'        => 'valide',
                'issued_at'     => now()->subDays(7),
                'created_by'    => $magasinier->id,
                'validated_by'  => $magasinier->id,
                'validated_at'  => now()->subDays(7),
                'total_quantity'=> 42,   // 2 + 40
            ]);

            DeliveryNoteItem::firstOrCreate(
                ['delivery_note_id' => $bl2->id, 'product_id' => $p2->id],
                ['order_item_id' => $oi2a->id, 'description' => $p2->name, 'quantity' => 2,  'unit_price' => 360000]
            );
            DeliveryNoteItem::firstOrCreate(
                ['delivery_note_id' => $bl2->id, 'product_id' => $p5->id],
                ['order_item_id' => $oi2b->id, 'description' => $p5->name, 'quantity' => 40, 'unit_price' => 5000]
            );

            // Paiement complet PAYCLI-2026-002
            $payment2 = ClientPayment::firstOrCreate(['number' => 'PAYCLI-2026-002'], [
                'client_id'        => $client2->id,
                'company_id'       => $company->id,
                'amount'           => 1062000,
                'payment_method_id'=> $pmVirement?->id,
                'payment_date'     => now()->subDays(3),
                'reference'        => 'VIR-BQ-2026-002',
                'notes'            => 'Règlement intégral FAC-2026-002',
                'status'           => 'confirme',
                'created_by'       => $comptable->id,
            ]);
            \App\Models\ClientPaymentAllocation::firstOrCreate(
                ['client_payment_id' => $payment2->id, 'invoice_id' => $invoice2->id],
                ['amount' => 1062000, 'allocated_at' => now()->subDays(3)]
            );
            // Comptabilisation GL (DR trésorerie / CR clients) — idempotent.
            if (! $payment2->journal_entry_id) {
                app(\App\Services\AccountingService::class)->postClientPayment($payment2->fresh(['client', 'company']));
            }
        }

        // ── Transactions de trésorerie demo ───────────────────────────────────
        $cashTxData = [
            // Caisse Principale
            ['account' => $caissePrincipale, 'type' => 'credit', 'amount' => 150000, 'days' => 25, 'label' => 'Vente comptoir — divers clients'],
            ['account' => $caissePrincipale, 'type' => 'debit',  'amount' =>  35000, 'days' => 20, 'label' => 'Achat fournitures de bureau'],
            ['account' => $caissePrincipale, 'type' => 'credit', 'amount' =>  85000, 'days' => 14, 'label' => 'Encaissement espèces — client Ouédraogo'],
            ['account' => $caissePrincipale, 'type' => 'debit',  'amount' =>  20000, 'days' =>  9, 'label' => 'Frais de transport — livraison BL-2026-001'],
            ['account' => $caissePrincipale, 'type' => 'credit', 'amount' =>  60000, 'days' =>  4, 'label' => 'Règlement partiel client divers'],
            // Compte Banque
            ['account' => $compteBanque,     'type' => 'credit', 'amount' => 675000,  'days' =>  5, 'label' => 'Virement reçu — PAYCLI-2026-001 (SONABHY)'],
            ['account' => $compteBanque,     'type' => 'credit', 'amount' => 1062000, 'days' =>  3, 'label' => 'Virement reçu — PAYCLI-2026-002 (ONATEL)'],
            ['account' => $compteBanque,     'type' => 'debit',  'amount' =>  250000, 'days' => 17, 'label' => 'Paiement fournisseur Tech Afrique — acompte BC-2026-001'],
            ['account' => $compteBanque,     'type' => 'debit',  'amount' =>  120000, 'days' =>  8, 'label' => 'Charges locatives — loyer bureau avril 2026'],
            // Orange Money
            ['account' => $compteMobile,     'type' => 'credit', 'amount' =>  75000, 'days' => 18, 'label' => 'Paiement mobile reçu — client Koné B.'],
            ['account' => $compteMobile,     'type' => 'credit', 'amount' =>  45000, 'days' => 10, 'label' => 'Paiement mobile reçu — client Traoré S.'],
            ['account' => $compteMobile,     'type' => 'debit',  'amount' =>  30000, 'days' =>  5, 'label' => 'Transfert vers caisse principale'],
        ];

        $grouped = collect($cashTxData)->groupBy(fn ($r) => $r['account']->id);
        foreach ($grouped as $accountId => $rows) {
            $account = $rows->first()['account'];
            if (CashTransaction::where('cash_account_id', $accountId)->exists()) {
                continue;
            }
            $runningBalance = (int) $account->opening_balance;
            foreach ($rows->sortByDesc('days') as $row) {
                $amount = (int) $row['amount'];
                $runningBalance += $row['type'] === 'credit' ? $amount : -$amount;
                CashTransaction::create([
                    'cash_account_id'  => $accountId,
                    'type'             => $row['type'],
                    'amount'           => $amount,
                    'balance_after'    => $runningBalance,
                    'label'            => $row['label'],
                    'transaction_date' => now()->subDays($row['days'])->toDateString(),
                    'created_by'       => null,
                ]);
            }
            $account->update(['current_balance' => $runningBalance]);
        }

        // ── Réconciliation : écritures comptables, mouvements de stock, soldes ──
        // Le seeder insère les documents en direct (sans passer par les services).
        // Cette passe genere les donnees derivees pour que les audits metier
        // (audit:business / audit:sync) soient verts.
        $this->reconcileDemoData();

        // ── Résumé console ────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✅ Données de démonstration créées avec succès.');
        $this->command->info('');
        $this->command->info('Utilisateurs (mot de passe : password)');
        $this->command->info('  admin@iboa.bf       → super_admin');
        $this->command->info('  directeur@iboa.bf   → directeur');
        $this->command->info('  commercial@iboa.bf  → commercial');
        $this->command->info('  comptable@iboa.bf   → comptable');
        $this->command->info('  magasinier@iboa.bf  → magasinier');
        $this->command->info('');
        $this->command->info('Documents créés (exercice 2026) :');
        $this->command->info('  BC-2026-001/002 + REC-2026-001 + FINV-2026-001');
        $this->command->info('  DEV-2026-001 → COM-2026-001 → FAC-2026-001 (partiel) + BL-2026-001');
        $this->command->info('  COM-2026-002 → FAC-2026-002 (payée) + BL-2026-002');
        $this->command->info('  PAYCLI-2026-001 (675 000 FCFA) + PAYCLI-2026-002 (1 062 000 FCFA)');
    }

    /**
     * Réconcilie les données dérivées que le seeder n'a pas générées en insérant
     * les documents en direct : écritures comptables, mouvements de stock initiaux,
     * et soldes clients/fournisseurs. Rend les audits métier verts.
     */
    private function reconcileDemoData(): void
    {
        $accounting = app(\App\Services\AccountingService::class);

        // 1. Écritures comptables des factures clients validées sans écriture
        Invoice::whereNull('journal_entry_id')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'])
            ->has('items') // seulement les factures avec lignes (GL équilibré)
            ->get()
            ->each(function (Invoice $inv) use ($accounting) {
                try { $accounting->postClientInvoice($inv); } catch (\Throwable $e) {}
            });

        // 2. Écritures comptables des factures fournisseurs validées sans écriture
        \App\Models\SupplierInvoice::whereNull('journal_entry_id')
            ->whereIn('status', ['validee', 'partiellement_payee', 'payee', 'en_retard'])
            ->get()
            ->each(function ($inv) use ($accounting) {
                try { $accounting->postSupplierInvoice($inv); } catch (\Throwable $e) {}
            });

        // 3. Mouvements de stock initiaux pour que Σ mouvements = product_stocks.quantity
        \App\Models\ProductStock::where('quantity', '>', 0)->get()->each(function ($ps) {
            // Somme signée des mouvements existants (entree +, sortie −)
            $signed = (float) \App\Models\StockMovement::where('product_id', $ps->product_id)
                ->where('warehouse_id', $ps->warehouse_id)
                ->selectRaw("COALESCE(SUM(CASE
                    WHEN type IN ('entree','retour_client') THEN quantity
                    WHEN type IN ('sortie','retour_fournisseur') THEN -quantity
                    ELSE quantity END), 0) AS s")
                ->value('s');
            $delta = (float) $ps->quantity - $signed;
            if (abs($delta) > 0.0001) {
                \App\Models\StockMovement::create([
                    'product_id'   => $ps->product_id,
                    'warehouse_id' => $ps->warehouse_id,
                    'type'         => 'entree',
                    'quantity'     => abs($delta),
                    'unit_cost'    => 0,
                    'total_cost'   => 0,
                    'occurred_at'  => now()->subDays(30),
                    'notes'        => 'Stock initial (réconciliation seeder demo)',
                ]);
            }
        });

        // 4. Recalage paid_amount / remaining_amount / status des factures depuis
        //    les allocations réelles (le seeder a pu fixer paid_amount sans allocation).
        Invoice::whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'])
            ->get()
            ->each(function (Invoice $inv) {
                $paidFromPayments = (int) \App\Models\ClientPaymentAllocation::where('invoice_id', $inv->id)->sum('amount');
                $paidFromCredits  = (int) \App\Models\CreditNote::where('invoice_id', $inv->id)
                    ->where('status', 'valide')->sum('total_ttc');
                $paid    = $paidFromPayments + $paidFromCredits;
                $netDue  = (int) ($inv->net_to_pay ?: $inv->total_ttc);
                $remain  = max(0, $netDue - $paid);
                $status  = $paid <= 0 ? $inv->status
                        : ($remain <= 0 ? 'payee' : 'partiellement_payee');
                $inv->updateQuietly([
                    'paid_amount'      => $paid,
                    'remaining_amount' => $remain,
                    'status'           => $status,
                ]);
            });

        // 5. Recalcul des soldes clients et fournisseurs
        \App\Models\Client::all()->each->recalculateBalance();
        \App\Models\Supplier::all()->each->recalculateBalance();

        $this->command->info('  ↳ Réconciliation : écritures GL, mouvements stock, paiements, soldes recalculés');
    }
}
