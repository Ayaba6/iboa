<?php

/**
 * [Ventes §6] Isolation multi-société des indicateurs — preuve sur 11 axes.
 *
 * Le scénario est toujours le même : la société B contient des montants
 * VOLONTAIREMENT distinctifs (900 000, 1 500 000, 7 chiffres uniques). Si une
 * seule de ces valeurs apparaît dans un indicateur de la société A, la fuite est
 * détectée — et pas seulement « le total est un peu trop grand ».
 *
 * Axes couverts : chiffre d'affaires, marge, devis, commandes, livraisons,
 * factures, avoirs, créances, encours, acomptes, taux de transformation.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use App\Services\CustomerCreditExposureService;
use App\Services\SalesInsightsService;
use Illuminate\Support\Collection;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{fy:FiscalYear,a:Company,b:Company,clientA:Client,clientB:Client} */
function kpiIsolationFixture(): array
{
    $fy = FiscalYear::create([
        'label' => 'KPI-2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $a = Company::create(['name' => 'Société A', 'email' => 'kpi-a@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    $b = Company::create(['name' => 'Société B', 'email' => 'kpi-b@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);

    app()->instance('current_company', $a);
    $clientA = Client::factory()->create(['name' => 'Client A', 'payment_mode' => 'credit', 'credit_limit' => 50_000_000, 'balance' => 0]);
    $clientB = Client::factory()->create(['name' => 'Client B', 'payment_mode' => 'credit', 'credit_limit' => 50_000_000, 'balance' => 0]);

    $mkInvoice = function (Company $co, Client $cl, string $number, int $ht, int $ttc, string $type = 'facture', string $status = 'emise') use ($fy) {
        return Invoice::create([
            'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'client_id' => $cl->id,
            'number' => $number, 'type' => $type, 'status' => $status, 'issued_at' => now(),
            'subtotal_ht' => $ht, 'total_ttc' => $ttc, 'remaining_amount' => $ttc, 'currency_code' => 'XOF',
        ]);
    };

    // Société A — montants « ronds » modestes
    $mkInvoice($a, $clientA, 'FA-KPI-001', 100_000, 118_000);
    $mkInvoice($a, $clientA, 'FA-KPI-AV1', 10_000, 11_800, 'avoir');
    // Société B — montants distinctifs, jamais attendus côté A
    $mkInvoice($b, $clientB, 'FB-KPI-001', 900_000, 1_062_000);
    $mkInvoice($b, $clientB, 'FB-KPI-AV1', 90_000, 106_200, 'avoir');

    $mkOrder = fn (Company $co, Client $cl, string $number, int $ttc, string $status) => Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'client_id' => $cl->id,
        'number' => $number, 'status' => $status, 'issued_at' => now(),
        'subtotal_ht' => $ttc, 'total_ttc' => $ttc, 'invoiced_amount' => 0,
    ]);
    $mkOrder($a, $clientA, 'CA-KPI-001', 200_000, 'confirme');
    $mkOrder($b, $clientB, 'CB-KPI-001', 1_500_000, 'confirme');

    $mkQuote = fn (Company $co, Client $cl, string $number, int $ttc, string $status) => Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'client_id' => $cl->id,
        'number' => $number, 'status' => $status, 'issued_at' => now(),
        'subtotal_ht' => $ttc, 'total_ttc' => $ttc,
    ]);
    $mkQuote($a, $clientA, 'DA-KPI-001', 300_000, 'envoye');
    $mkQuote($a, $clientA, 'DA-KPI-002', 400_000, 'accepte');
    $mkQuote($b, $clientB, 'DB-KPI-001', 2_500_000, 'envoye');
    $mkQuote($b, $clientB, 'DB-KPI-002', 3_500_000, 'accepte');

    ClientPayment::create([
        'company_id' => $a->id, 'client_id' => $clientA->id, 'number' => 'ACO-KPI-A',
        'amount' => 50_000, 'payment_date' => now()->toDateString(), 'status' => 'confirme',
        'is_acompte' => true, 'allocated_amount' => 0, 'unallocated_amount' => 50_000, 'currency_code' => 'XOF',
    ]);
    ClientPayment::create([
        'company_id' => $b->id, 'client_id' => $clientB->id, 'number' => 'ACO-KPI-B',
        'amount' => 750_000, 'payment_date' => now()->toDateString(), 'status' => 'confirme',
        'is_acompte' => true, 'allocated_amount' => 0, 'unallocated_amount' => 750_000, 'currency_code' => 'XOF',
    ]);

    return ['fy' => $fy, 'a' => $a, 'b' => $b, 'clientA' => $clientA, 'clientB' => $clientB];
}

/** Sérialise une structure pour y chercher toute trace numérique de la société B. */
function kpiFlatten(mixed $value): string
{
    if ($value instanceof Collection) {
        $value = $value->toArray();
    }
    if (is_object($value)) {
        $value = (array) $value;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
}

// ---------------------------------------------------------------------------

it('axe 1-2 : chiffre d affaires et marge ne contiennent aucune donnée de la société B', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);
    $svc = app(SalesInsightsService::class);

    $kpis = $svc->dashboardKpis();

    // CA de A seul : 100 000 HT. L'avoir de A (10 000) est exclu par type.
    // Ni 900 000 (CA de B) ni 90 000 (avoir de B) ne doivent apparaître.
    expect($kpis['ca_month'])->toBe(100_000)
        ->and($kpis['ca_year'])->toBe(100_000);

    $margin = $svc->grossMargin();
    expect(kpiFlatten($margin))->not->toContain('900000')
        ->and(kpiFlatten($margin))->not->toContain('1062000');
});

it('axe 3 : le pipeline devis ne mélange pas les deux sociétés', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);

    $pipeline = app(SalesInsightsService::class)->quotesPipeline();
    $flat = kpiFlatten($pipeline);

    expect($pipeline['envoye']['count'])->toBe(1)
        ->and($pipeline['envoye']['total'])->toBe(300_000)
        ->and($pipeline['accepte']['total'])->toBe(400_000)
        ->and($flat)->not->toContain('2500000')
        ->and($flat)->not->toContain('3500000');
});

it('axe 4 : les commandes de la société B sont invisibles', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);
    $svc = app(SalesInsightsService::class);

    $byStatus = kpiFlatten($svc->ordersByStatus());

    expect($svc->ordersValueYear())->toBe(200_000.0)
        ->and($byStatus)->not->toContain('1500000');
});

it('axe 5 : les livraisons sont cloisonnées', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);

    // Chaque société a EXACTEMENT une commande à livrer, sans date de livraison
    // (donc classée « plus_tard »). Un total de 2 d'un côté signalerait la fuite.
    $svc = app(SalesInsightsService::class);
    $fromA = $svc->deliveriesByBucket();

    app()->instance('current_company', $f['b']);
    $fromB = $svc->deliveriesByBucket();

    expect(array_sum($fromA))->toBe(1)
        ->and($fromA['plus_tard'])->toBe(1)
        ->and(array_sum($fromB))->toBe(1)
        ->and($fromB['plus_tard'])->toBe(1);
});

it('axe 6-7 : factures et avoirs restent dans leur société', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);
    $svc = app(SalesInsightsService::class);

    $due = kpiFlatten($svc->upcomingDueInvoices(3650));
    $alerts = $svc->salesAlerts();

    // A possède 2 pièces non payées (1 facture + 1 avoir), B en possède 2 aussi.
    // Un compteur à 4 signalerait la fuite.
    expect($alerts['invoices_unpaid'])->toBe(2)
        ->and($due)->not->toContain('1062000')
        ->and($due)->not->toContain('106200');
});

it('axe 8-9 : créances et encours ne sont jamais partagés', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);
    $svc = app(SalesInsightsService::class);
    $exposure = app(CustomerCreditExposureService::class);

    $kpis = $svc->dashboardKpis();

    // Créances de A : facture 118 000 + avoir 11 800 = 129 800.
    expect($kpis['outstanding'])->toBe(129_800);

    // Encours du client A vu depuis A, puis du client B vu depuis B :
    // aucune composante commune.
    $expA = $exposure->assessClient($f['clientA'], (int) $f['a']->id);
    $expB = $exposure->assessClient($f['clientB'], (int) $f['b']->id);

    expect($expA['outstanding'])->toBe(129_800)
        ->and($expB['outstanding'])->toBe(1_168_200)
        ->and($expA['open_orders'])->toBe(200_000)
        ->and($expB['open_orders'])->toBe(1_500_000);

    // Et le client A vu depuis la société B est vide : il n'y a rien à son nom là-bas.
    $expAinB = $exposure->assessClient($f['clientA'], (int) $f['b']->id);
    expect($expAinB['outstanding'])->toBe(0)
        ->and($expAinB['open_orders'])->toBe(0)
        ->and($expAinB['deposits'])->toBe(0);
});

it('axe 10 : les acomptes ne traversent pas la frontière de société', function () {
    $f = kpiIsolationFixture();
    $exposure = app(CustomerCreditExposureService::class);

    $expA = $exposure->assessClient($f['clientA'], (int) $f['a']->id);
    $expB = $exposure->assessClient($f['clientB'], (int) $f['b']->id);

    expect($expA['deposits'])->toBe(50_000)
        ->and($expB['deposits'])->toBe(750_000);
});

it('axe 11 : le taux de transformation est calculé sur la seule société courante', function () {
    $f = kpiIsolationFixture();
    app()->instance('current_company', $f['a']);

    $kpis = app(SalesInsightsService::class)->dashboardKpis();

    // A : 2 devis émis, 1 accepté → 50 %. B en a 2 également ; un calcul global
    // donnerait aussi 50 % — le piège est donc dans les COMPTEURS, pas le ratio.
    expect($kpis['quotes_sent'])->toBe(2)
        ->and($kpis['quotes_accepted'])->toBe(1)
        ->and($kpis['conversion_rate'])->toBe(50.0);
});

it('bascule de société : les mêmes appels rendent des valeurs entièrement différentes', function () {
    $f = kpiIsolationFixture();
    $svc = app(SalesInsightsService::class);

    app()->instance('current_company', $f['a']);
    $fromA = $svc->dashboardKpis();

    app()->instance('current_company', $f['b']);
    $fromB = $svc->dashboardKpis();

    expect($fromA['ca_year'])->toBe(100_000)
        ->and($fromB['ca_year'])->toBe(900_000)
        ->and($fromA['outstanding'])->toBe(129_800)
        ->and($fromB['outstanding'])->toBe(1_168_200)
        ->and($fromA['ca_year'])->not->toBe($fromB['ca_year']);
});

it('le scope Eloquent isole aussi les documents, pas seulement les agrégats', function () {
    $f = kpiIsolationFixture();

    app()->instance('current_company', $f['a']);
    $ordersFromA = Order::pluck('number')->all();
    $invoicesFromA = Invoice::pluck('number')->all();

    app()->instance('current_company', $f['b']);
    $ordersFromB = Order::pluck('number')->all();
    $invoicesFromB = Invoice::pluck('number')->all();

    expect($ordersFromA)->toBe(['CA-KPI-001'])
        ->and($ordersFromB)->toBe(['CB-KPI-001'])
        ->and($invoicesFromA)->toBe(['FA-KPI-001', 'FA-KPI-AV1'])
        ->and($invoicesFromB)->toBe(['FB-KPI-001', 'FB-KPI-AV1']);
});
