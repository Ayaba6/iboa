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

// ═══════════════════════ [PHASE 2.6 — volumes] ═══════════════════════

// Grand livre + balance sur 1 000 lignes d'écritures : temps borné et
// requêtes STABLES entre 100 et 1 000 lignes (pas de N+1, pas de
// chargement par écriture).
it('balance comptable : requêtes stables entre 100 et 1 000 lignes d\'écritures', function () {
    $this->actingAs(perfUser());
    $co = Company::first();
    $accounts = [];
    foreach (['6011', '7011', '411', '401', '521', '571'] as $i => $code) {
        $accounts[] = \App\Models\Account::create([
            'company_id' => $co->id, 'code' => $code . '-PERF', 'name' => 'Compte ' . $code,
            'type' => 'actif', 'class_number' => (int) $code[0], 'is_active' => true,
            'account_class_id' => \App\Models\AccountClass::firstOrCreate(['company_id' => $co->id, 'number' => (int) $code[0]], ['name' => 'Classe ' . $code[0]])->id,
            'debit_balance' => 0, 'credit_balance' => 0,
        ]);
    }
    $seed = function (int $n) use ($co, $accounts) {
        foreach (range(1, $n) as $i) {
            $e = \App\Models\JournalEntry::create([
                'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
                'number' => 'EC-PERF-' . uniqid() . $i, 'entry_date' => now()->subDays($i % 300),
                'journal_type_id' => \App\Models\JournalType::firstOrCreate(['company_id' => $co->id, 'code' => 'OD-P'], ['name' => 'OD Perf', 'type' => 'operations_diverses'])->id,
                'description' => 'Perf', 'status' => 'valide', 'total_debit' => 1000, 'total_credit' => 1000,
            ]);
            $a = $accounts[$i % 3]; $b = $accounts[3 + ($i % 3)];
            $e->lines()->create(['account_id' => $a->id, 'label' => 'D', 'debit' => 1000, 'credit' => 0]);
            $e->lines()->create(['account_id' => $b->id, 'label' => 'C', 'debit' => 0, 'credit' => 1000]);
            $a->increment('debit_balance', 1000); $b->increment('credit_balance', 1000);
        }
    };

    $seed(100);
    $q100 = perfQueryCount(fn () => $this->get('/comptabilite/balance')->assertOk());

    $seed(900);
    $t0 = microtime(true);
    $q1000 = perfQueryCount(fn () => $this->get('/comptabilite/balance')->assertOk());
    $elapsed = microtime(true) - $t0;

    expect($q1000)->toBeLessThanOrEqual($q100 + 5)     // stable = pas de N+1
        ->and($elapsed)->toBeLessThan(5.0);            // borne large (CI variable)
});

// Balance âgée clients sur 300 factures : requêtes indépendantes du volume.
it('balance âgée clients : requêtes stables entre 30 et 300 factures', function () {
    $this->actingAs(perfUser());
    $co = Company::first();
    $seed = function (int $n) use ($co) {
        $client = Client::factory()->create();
        foreach (range(1, $n) as $i) {
            \App\Models\Invoice::create([
                'company_id' => $co->id, 'client_id' => $client->id,
                'fiscal_year_id' => $co->current_fiscal_year_id,
                'number' => 'FA-PERF-' . uniqid() . $i, 'status' => 'emise',
                'issued_at' => now()->subDays($i % 200), 'due_at' => now()->subDays(($i % 200) - 30),
                'subtotal_ht' => 10000, 'total_tax' => 0, 'total_ttc' => 10000, 'remaining_amount' => 10000,
            ]);
        }
    };

    $seed(30);
    $q30 = perfQueryCount(fn () => $this->get('/gestion/clients/balance-agee')->assertOk());

    $seed(270);
    $q300 = perfQueryCount(fn () => $this->get('/gestion/clients/balance-agee')->assertOk());

    expect($q300)->toBeLessThanOrEqual($q30 + 10);
});

// Paie 100 salariés : temps borné et requêtes sous-linéaires (le calcul
// par salarié ne doit pas exploser en requêtes).
it('calcul de paie : 100 salariés en moins de 30 s et sans explosion de requêtes', function () {
    $company = bfCompany();
    bfSettings($company);
    bfAccountClasses($company);
    $this->actingAs(bfAdmin());
    foreach (range(1, 100) as $i) {
        bfEmployee($company, 100_000 + ($i * 1000));
    }

    $svc = app(\App\Services\PayrollService::class);
    $run = $svc->createRun(['period_month' => 7, 'period_year' => 2026, 'notes' => '']);

    $t0 = microtime(true);
    $queries = perfQueryCount(fn () => $svc->calculate($run->fresh()));
    $elapsed = microtime(true) - $t0;

    expect($run->fresh()->employee_count)->toBe(100)
        ->and($elapsed)->toBeLessThan(30.0)
        ->and($queries)->toBeLessThan(100 * 12); // < 12 requêtes/salarié en moyenne
});
