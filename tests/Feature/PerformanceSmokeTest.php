<?php
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function perfUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PERF'], ['email' => 'perf@co.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

/**
 * [QA 1-4 §23] Budget de requêtes SQL par page à volume : le nombre de requêtes
 * ne doit PAS croître avec le nombre de lignes affichées (détection N+1).
 * Données générées en éphémère (SQLite :memory:), jamais en base réelle.
 */
function perfSeedOrders(int $n): void
{
    $co = Company::first();
    $client = Client::factory()->create();
    $p = Product::factory()->create();
    foreach (range(1, $n) as $i) {
        $o = Order::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'client_id' => $client->id, 'number' => 'CMD-PERF-' . uniqid() . '-' . $i,
            'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100000,
        ]);
        $o->items()->create([
            'product_id' => $p->id, 'description' => 'L', 'quantity' => 1,
            'unit_price' => 100000, 'discount_percent' => 0, 'tax_rate_value' => 0,
            'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
        ]);
    }
}

function perfQueryCount(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('liste des commandes : requêtes stables entre 10 et 100 lignes (pas de N+1)', function () {
    $this->actingAs(perfUser());

    perfSeedOrders(10);
    $q10 = perfQueryCount(fn () => $this->get('/ventes/commandes?per_page=100')->assertOk());

    perfSeedOrders(90);
    $q100 = perfQueryCount(fn () => $this->get('/ventes/commandes?per_page=100')->assertOk());

    // Tolérance faible : quelques requêtes de plus (pagination/compteurs), pas ~90.
    expect($q100 - $q10)->toBeLessThanOrEqual(10);
});

it('liste des articles : requêtes stables à 100 articles', function () {
    $this->actingAs(perfUser());

    Product::factory()->count(10)->create();
    $q10 = perfQueryCount(fn () => $this->get('/products')->assertOk());

    Product::factory()->count(90)->create();
    $q100 = perfQueryCount(fn () => $this->get('/products')->assertOk());

    expect($q100 - $q10)->toBeLessThanOrEqual(10);
});

it('liste des articles : tient le palier de 1 000 articles (requêtes et durée bornées)', function () {
    $this->actingAs(perfUser());

    Product::factory()->count(100)->create();
    $q100 = perfQueryCount(fn () => $this->get('/products')->assertOk());

    Product::factory()->count(900)->create();
    $start = microtime(true);
    $q1000 = perfQueryCount(fn () => $this->get('/products')->assertOk());
    $ms = (microtime(true) - $start) * 1000;

    // Pagination serveur : le nombre de requêtes ne doit pas croître avec le
    // volume (×10), et la page reste sous 5 s même en environnement de test.
    expect($q1000 - $q100)->toBeLessThanOrEqual(10)
        ->and($ms)->toBeLessThan(5000);
});

it('dashboard : budget de requêtes borné', function () {
    $this->actingAs(perfUser());
    perfSeedOrders(30);

    $q = perfQueryCount(fn () => $this->get('/dashboard')->assertOk());

    // Le dashboard agrège ~15 widgets : 81 requêtes mesurées, INDÉPENDANT du
    // volume (agrégats SQL + cache KPI 5 min en réel). Budget garde-fou : une
    // dérive au-delà de 90 signale un N+1 introduit.
    expect($q)->toBeLessThanOrEqual(90);
});
