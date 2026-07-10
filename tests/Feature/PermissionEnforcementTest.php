<?php

/**
 * [CDC §14/§15] Application stricte des rôles : chaque profil ne peut faire
 * que ce à quoi ses permissions lui donnent droit — vérifié en HTTP réel.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\Concerns\RefreshDatabase::class);

function permCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'PERM-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Perm Co'], ['email' => 'perm@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function permUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => permCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

it('interdit à un opérateur les exports de factures et la gestion des rôles', function () {
    $operateur = permUser('operateur_production');

    $this->actingAs($operateur)->get(route('exports.invoices'))->assertForbidden();
    $this->actingAs($operateur)->get('/roles')->assertForbidden();
    $this->actingAs($operateur)->get('/parametrage')->assertForbidden();
});

it('interdit au commercial le paramétrage société et la comptabilité', function () {
    $commercial = permUser('commercial');

    $this->actingAs($commercial)->get('/parametrage')->assertForbidden();
    $this->actingAs($commercial)->get('/roles')->assertForbidden();
});

it('interdit au magasinier de valider une commande (route validate-internal)', function () {
    $magasinier = permUser('magasinier');
    $co = permCompany();
    $order = \App\Models\Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => \App\Models\Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-PERM-1',
        'status'         => 'en_attente_validation',
        'issued_at'      => now(),
        'total_ttc'      => 50_000,
    ]);

    $this->actingAs($magasinier)
        ->post(route('ventes.commandes.validate-internal', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('en_attente_validation');
});

it('API : refuse les clients et factures à un token sans la permission', function () {
    $operateur = permUser('operateur_production'); // ni clients.view ni invoices.view

    $this->actingAs($operateur, 'sanctum')->getJson('/api/clients')->assertForbidden();
    $this->actingAs($operateur, 'sanctum')->getJson('/api/invoices')->assertForbidden();
    // products.view : l'opérateur ne l'a pas non plus
    $this->actingAs($operateur, 'sanctum')->getJson('/api/products')->assertForbidden();
});

it('API : autorise les produits à un profil qui a products.view', function () {
    $magasinier = permUser('magasinier'); // products.view + stocks.view

    $this->actingAs($magasinier, 'sanctum')->getJson('/api/products')->assertOk();
    $this->actingAs($magasinier, 'sanctum')->getJson('/api/stock')->assertOk();
    // mais pas les clients (le magasinier n'a pas clients.view ;
    // il garde invoices.view — contrôle « facturé avant expédition »)
    $this->actingAs($magasinier, 'sanctum')->getJson('/api/clients')->assertForbidden();
});

it('masque les KPI financiers du dashboard aux profils sans reports.view', function () {
    $magasinier = permUser('magasinier'); // pas de reports.view mais accès dashboard

    $resp = $this->actingAs($magasinier)->get('/dashboard');
    if ($resp->status() === 302) {
        // Profil redirigé vers son module principal — pas de fuite non plus.
        expect(true)->toBeTrue();
    } else {
        $resp->assertOk()
             ->assertDontSee('data-kpi', false)
             ->assertSee('Bienvenue');
    }

    // Endpoint JSON de polling : 403 sans reports.view
    $this->actingAs($magasinier)->getJson('/dashboard/kpis')->assertForbidden();

    // Un profil avec reports.view passe le contrôle de permission.
    // (La requête SQL utilise YEAR() MySQL-only → on ne vérifie que
    // l'absence de 403, pas le rendu complet sous SQLite.)
    $daf  = permUser('daf');
    $resp = $this->actingAs($daf)->getJson('/dashboard/kpis');
    expect($resp->status())->not->toBe(403);
});

it('interdit au commercial les référentiels production (machines, nomenclatures, planning, MRP)', function () {
    $commercial = permUser('commercial'); // production.view seulement

    $this->actingAs($commercial)->get('/production/machines')->assertForbidden();
    $this->actingAs($commercial)->get('/production/bom')->assertForbidden();
    $this->actingAs($commercial)->get('/production/planning')->assertForbidden();
    $this->actingAs($commercial)->get('/production/mrp')->assertForbidden();
    $this->actingAs($commercial)->get('/production/coils')->assertForbidden();
    $this->actingAs($commercial)->get('/production/maintenance')->assertForbidden();
    // Mais il garde le suivi des OF (§13.10)
    $this->actingAs($commercial)->get('/production/orders')->assertOk();
});

it('le chef production accède aux référentiels ; le magasinier voit les bobines', function () {
    $chef = permUser('chef_production');
    $this->actingAs($chef)->get('/production/machines')->assertOk();
    $this->actingAs($chef)->get('/production/bom')->assertOk();

    $magasinier = permUser('magasinier'); // stocks.adjust → bobines en lecture
    $this->actingAs($magasinier)->get('/production/coils')->assertOk();
    $this->actingAs($magasinier)->get('/production/machines')->assertForbidden();
});

it('RH : un opérateur (portail seul) ne voit ni employés ni bulletins ; routes 403', function () {
    $operateur = permUser('operateur_production'); // rh.portail éventuel, pas rh.employees/payroll

    // Routes protégées quelle que soit la sidebar
    $this->actingAs($operateur)->get('/rh/employes')->assertForbidden();
    $this->actingAs($operateur)->get('/rh/paie')->assertForbidden();
    $this->actingAs($operateur)->get('/rh/baremes')->assertForbidden();

    // Sidebar : aucun lien vers ces pages (rendu dashboard)
    $resp = $this->actingAs($operateur)->get('/dashboard');
    if ($resp->status() === 200) {
        $resp->assertDontSee('Bulletins de paie')
             ->assertDontSee('Barèmes fiscaux');
    }
});

it('CRM : masqué et interdit sans crm.view ; accessible au commercial', function () {
    $magasinier = permUser('magasinier'); // pas de crm.view
    $this->actingAs($magasinier)->get('/crm/contacts')->assertForbidden();

    $commercial = permUser('commercial'); // crm.view + crm.manage
    $this->actingAs($commercial)->get('/crm/contacts')->assertOk();
});

it('Direction : réservé à la direction — le commercial est bloqué malgré reports.view', function () {
    $commercial = permUser('commercial'); // reports.view mais PAS direction.view
    $this->actingAs($commercial)->get('/direction')->assertForbidden();
    $this->actingAs($commercial)->get('/chaine-valeur')->assertForbidden();

    $daf = permUser('daf');
    $this->actingAs($daf)->get('/direction')->assertOk();

    $directeurUsine = permUser('directeur_usine');
    $this->actingAs($directeurUsine)->get('/chaine-valeur')->assertOk();
});

it('empêche un utilisateur de basculer vers une autre société ; super_admin peut', function () {
    $co    = permCompany();
    $autre = Company::firstOrCreate(['name' => 'Autre Co'], ['email' => 'autre@iboa.test', 'current_fiscal_year_id' => $co->current_fiscal_year_id]);

    $commercial = permUser('commercial');
    $this->actingAs($commercial)
        ->post(route('company.switch', $autre))
        ->assertForbidden();

    $admin = permUser('super_admin');
    $this->actingAs($admin)
        ->from('/dashboard')
        ->post(route('company.switch', $autre))
        ->assertRedirect();
});
