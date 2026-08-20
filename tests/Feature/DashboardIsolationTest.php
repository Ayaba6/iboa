<?php

/**
 * [SEC] Isolation multi-société du tableau de bord principal.
 *
 * `DashboardController::index()` calcule ses KPI en DB::table / DB::selectOne,
 * ce qui contourne HasCompanyScope. Onze requêtes n'y portaient aucun filtre
 * société, et la clé de cache elle-même ignorait la société — alors que
 * `kpisJson()`, le point d'entrée du rafraîchissement automatique, filtrait
 * correctement. La page affichait donc des totaux toutes sociétés confondues
 * que le polling corrigeait soixante secondes plus tard.
 *
 * `SalesInsightsIsolationTest` couvrait `SalesInsightsService`, un autre chemin :
 * le tableau de bord n'avait aucune couverture d'isolation. D'où l'angle mort.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/** Monte deux sociétés dotées de données comparables et rend un utilisateur de A. */
function isoDashboard(): array
{
    $fy = FiscalYear::create(['label' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $a  = Company::create(['name' => 'ISO A', 'email' => 'isoa@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $b  = Company::create(['name' => 'ISO B', 'email' => 'isob@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    app()->instance('current_company', $a);
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();

    foreach ([[$a, $clientA, 'A', 100000, 118000], [$b, $clientB, 'B', 900000, 1062000]] as [$co, $cl, $tag, $ht, $ttc]) {
        Invoice::create([
            'company_id' => $co->id, 'client_id' => $cl->id, 'number' => "F{$tag}-ISO-DASH",
            'type' => 'facture', 'status' => 'emise', 'issued_at' => now(),
            'subtotal_ht' => $ht, 'total_ttc' => $ttc, 'remaining_amount' => $ttc,
        ]);
        Order::create([
            'company_id' => $co->id, 'client_id' => $cl->id, 'fiscal_year_id' => $fy->id,
            'number' => "C{$tag}-ISO-DASH", 'status' => 'confirme', 'issued_at' => now(),
            'total_ttc' => $ttc,
        ]);
        CashAccount::create([
            'company_id' => $co->id, 'name' => "Caisse {$tag}", 'code' => "CA-{$tag}",
            'type' => 'caisse', 'current_balance' => $ht, 'is_active' => true,
        ]);
        ClientPayment::create([
            'company_id' => $co->id, 'client_id' => $cl->id, 'number' => "P{$tag}-ISO-DASH",
            'amount' => $ht, 'payment_date' => now(), 'status' => 'confirme', 'method' => 'especes',
        ]);
    }

    // `dashboard.view` ouvre la page, `reports.view` en débloque les KPI financiers :
    // sans les deux, l'écran redirige ou se rend en version neutre, et le test ne
    // prouverait rien sur l'isolation.
    $role = Role::firstOrCreate(['name' => 'iso_dash', 'guard_name' => 'web']);
    foreach (['dashboard.view', 'reports.view'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $a->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    Cache::flush();

    return ['a' => $a, 'b' => $b, 'user' => $u];
}

it('n\'expose au tableau de bord que les données de la société courante', function () {
    ['user' => $u] = isoDashboard();

    $vue = $this->actingAs($u)->get(route('dashboard'))->assertOk();

    // Société A vaut 100 000 HT / 118 000 TTC ; B vaut neuf fois plus. Toute
    // contamination ferait exploser ces valeurs, jamais les rendre égales.
    $vue->assertViewHas('revenueMois', 118000);
    $vue->assertViewHas('encaissementsMois', 100000);
    $vue->assertViewHas('soldeTresorerie', 100000);
    $vue->assertViewHas('nbCommandesEnCours', 1);
    $vue->assertViewHas('montantEnRetard', 0);
});

it('fait dire la même chose à la page et à son rafraîchissement automatique', function () {
    ['user' => $u] = isoDashboard();

    $vue = $this->actingAs($u)->get(route('dashboard'))->assertOk();
    $json = $this->actingAs($u)->getJson(route('dashboard.kpis'))->assertOk()->json();

    // Le cœur du défaut : deux calculs du même KPI, l'un filtré et l'autre non.
    // La page et le polling doivent tomber sur le même nombre.
    expect($json['rev_mois'])->toBe($vue->viewData('revenueMois'));
    expect($json['enc_mois'])->toBe($vue->viewData('encaissementsMois'));
    expect($json['solde_tresorerie'])->toBe($vue->viewData('soldeTresorerie'));
    expect($json['nb_commandes'])->toBe($vue->viewData('nbCommandesEnCours'));
    expect($json['rupture_stock'])->toBe($vue->viewData('ruptureStock'));
    expect($json['factures_retard'])->toBe($vue->viewData('facturesEnRetard'));
    expect($json['montant_retard'])->toBe($vue->viewData('montantEnRetard'));
});

it('annonce la même rupture que les écrans stock', function () {
    ['user' => $u] = isoDashboard();

    // Un article à zéro, sans aucune ligne de stock : invisible pour l'ancienne
    // définition du tableau de bord, qui comptait des lignes de product_stocks.
    \App\Models\Product::factory()->create([
        'is_active' => true, 'is_stockable' => true,
        'stock_min' => 100, 'reorder_point' => 150,
    ]);

    $attendu = app(\App\Services\StockInsightsService::class);
    $attendu = $attendu->compter($attendu->ruptureQuery());

    $vue = $this->actingAs($u)->get(route('dashboard'))->assertOk();
    $json = $this->actingAs($u)->getJson(route('dashboard.kpis'))->assertOk()->json();

    // Trois écrans, une seule définition : /dashboard, son rafraîchissement JSON
    // et les écrans stock doivent tomber sur le même nombre.
    expect($attendu)->toBeGreaterThan(0);
    expect($vue->viewData('ruptureStock'))->toBe($attendu);
    expect($json['rupture_stock'])->toBe($attendu);
});

it('ne resserre pas le cache d\'une société sur une autre', function () {
    ['a' => $a, 'b' => $b, 'user' => $userA] = isoDashboard();

    $role  = Role::where('name', 'iso_dash')->firstOrFail();
    $userB = User::factory()->create(['company_id' => $b->id, 'email_verified_at' => now()]);
    $userB->assignRole($role);

    // A d'abord : c'est lui qui remplit le cache. Si la clé ignorait la société,
    // B recevrait ensuite les chiffres de A — le bloc étant mis en cache 5 min.
    $vueA = $this->actingAs($userA)->get(route('dashboard'))->assertOk();

    app()->instance('current_company', $b);
    $vueB = $this->actingAs($userB)->get(route('dashboard'))->assertOk();

    expect($vueA->viewData('revenueMois'))->toBe(118000);
    expect($vueB->viewData('revenueMois'))->toBe(1062000);
});
