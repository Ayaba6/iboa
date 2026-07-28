<?php

/**
 * [Ventes §3] Courses MySQL RÉELLES sur le contrôle de crédit.
 *
 * Chaque scénario lance de VRAIS processus PHP concurrents, avec leurs propres
 * connexions PDO et un départ synchronisé. Un test mono-processus ne prouverait
 * rien du verrouillage : il rejouerait la logique applicative en série.
 *
 * Invariants vérifiés partout :
 *   - aucune exception SQL brute remontée à l'utilisateur (pas de « SQLSTATE ») ;
 *   - aucune validation partielle : une commande est soumise ou ne l'est pas ;
 *   - l'encours retenu par le contrôle est celui réellement engagé en base.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerCreditExposureService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;

/** Le décor doit être COMMITÉ : les workers ont leurs propres connexions. */
function raceCommitFixture(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/** @return array{company:Company,user:User,fy:FiscalYear} */
function raceCompany(string $suffix): array
{
    $fy = FiscalYear::create([
        'label' => "RACE-{$suffix}-2026", 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::create([
        'name' => "OA METAL RACE {$suffix}", 'email' => "race-{$suffix}@oa-metal.test",
        'current_fiscal_year_id' => $fy->id,
    ]);

    $permission = Permission::firstOrCreate(['name' => 'sales.submit', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->givePermissionTo($permission);

    return ['company' => $company, 'user' => $user, 'fy' => $fy];
}

function raceOrder(array $ctx, string $number, int $amount): Order
{
    return Order::create([
        'company_id' => $ctx['company']->id, 'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $ctx['client']->id, 'number' => $number, 'status' => 'brouillon',
        'issued_at' => now(), 'subtotal_ht' => $amount, 'total_ttc' => $amount,
        'invoiced_amount' => 0, 'created_by' => $ctx['user']->id,
    ]);
}

function raceInvoice(array $ctx, string $number, int $amount, string $status = 'emise'): Invoice
{
    return Invoice::create([
        'company_id' => $ctx['company']->id, 'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $ctx['client']->id, 'number' => $number, 'type' => 'facture',
        'status' => $status, 'issued_at' => now(), 'subtotal_ht' => $amount,
        'total_ttc' => $amount, 'remaining_amount' => $status === 'brouillon' ? 0 : $amount,
    ]);
}

/**
 * Lance les workers en parallèle, départ synchronisé.
 *
 * @param  array<int,array{0:string,1:int,2:int}>  $jobs  [action, id, userId]
 * @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string}
 */
function raceRun(array $jobs, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/credit_race_worker.php');

    $processes = [];
    foreach ($jobs as $job) {
        [$action, $id, $userId] = $job;
        $processes[] = new Process(
            [PHP_BINARY, $worker, $action, (string) $id, (string) $userId, (string) $startAt],
            base_path(), null, null, 60
        );
    }
    foreach ($processes as $process) {
        $process->start();
    }
    foreach ($processes as $process) {
        $process->wait();
    }

    return [
        'codes' => array_map(fn (Process $p) => (int) $p->getExitCode(), $processes),
        'outputs' => array_map(fn (Process $p) => $p->getOutput(), $processes),
        'stderr' => trim(implode("\n", array_map(fn (Process $p) => $p->getErrorOutput(), $processes))),
    ];
}

/**
 * EXCLUSION DÉCLARÉE — ces quatre tests ne peuvent pas s'exécuter sur SQLite.
 *
 * Raison technique, pas de commodité : la course exige de VRAIS processus
 * concurrents. Sous SQLite `:memory:`, chaque processus ouvre sa propre base
 * vide — les workers ne verraient ni le client, ni les commandes, et le test
 * ne prouverait rien. SQLite ne fournit pas non plus `SELECT ... FOR UPDATE`,
 * qui est précisément le mécanisme évalué ici.
 *
 * Les identifiants restent donc IDENTIQUES entre les deux moteurs : sur SQLite
 * ils apparaissent en « skipped » avec ce motif, jamais absents de la collecte.
 */
beforeEach(function () {
    if (config('database.default') !== 'mysql') {
        test()->markTestSkipped(
            'Course multi-processus réservée à MySQL : SQLite :memory: n\'est pas '
            .'partageable entre processus et n\'implémente pas SELECT ... FOR UPDATE.'
        );
    }

    expect((string) config('database.connections.mysql.database'))->toContain('test');
});

/**
 * ISOLATION — obligatoire, pas facultatif.
 *
 * Une course multi-processus impose de COMMITER le décor : des connexions PDO
 * distinctes ne peuvent pas voir les données d'une transaction ouverte. Ce
 * commit annule le mécanisme d'isolation habituel (transaction par test annulée
 * en fin de test) : tout ce que ce fichier écrit SURVIT dans la base de test
 * partagée et contamine les tests suivants du même processus.
 *
 * Constaté : une exécution de la suite MySQL complète est passée de 0 à
 * 158 échecs en aval, dont des « Duplicate entry » sur `payroll_settings`.
 *
 * Remède : forcer une reconstruction complète de la base après ce fichier.
 * Coût assumé — un `migrate:fresh` supplémentaire — contre une suite dont les
 * résultats ne veulent plus rien dire.
 */
afterAll(function () {
    RefreshDatabaseState::$migrated = false;
});

// ---------------------------------------------------------------------------
// Scénario A — deux commandes se disputent le même reliquat de plafond
// ---------------------------------------------------------------------------
it('scénario A : sérialise deux commandes concurrentes sur le même reliquat de plafond', function () {
    $ctx = raceCompany('A');
    app()->instance('current_company', $ctx['company']);
    $ctx['client'] = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 10_000_000, 'balance' => 8_000_000,
    ]);

    raceInvoice($ctx, 'FAC-RACE-A-001', 8_000_000);
    $orderA = raceOrder($ctx, 'CMD-RACE-A1', 1_500_000);
    $orderB = raceOrder($ctx, 'CMD-RACE-A2', 1_500_000);

    raceCommitFixture();
    $race = raceRun([
        ['submit_order', $orderA->id, $ctx['user']->id],
        ['submit_order', $orderB->id, $ctx['user']->id],
    ]);
    DB::reconnect();

    $codes = $race['codes'];
    sort($codes);
    $statuses = Order::whereIn('id', [$orderA->id, $orderB->id])->pluck('status')->sort()->values()->all();
    $engaged = 8_000_000 + (int) Order::where('client_id', $ctx['client']->id)
        ->whereIn('status', CustomerCreditExposureService::ORDER_STATUSES)
        ->sum('total_ttc');

    // Une seule commande passe : 8 000 000 + 1 500 000 = 9 500 000 ≤ 10 000 000.
    // Les deux passeraient à 11 000 000, au-dessus du plafond.
    expect($codes)->toBe([0, 2])
        ->and($statuses)->toBe(['brouillon', 'en_attente_validation'])
        ->and($engaged)->toBe(9_500_000)
        ->and($engaged)->toBeLessThanOrEqual(10_000_000)
        ->and($race['stderr'])->toBe('')
        ->and(implode(' ', $race['outputs']))
        ->toContain('encours prévisionnel 11 000 000 FCFA supérieur au plafond 10 000 000 FCFA')
        ->not->toContain('SQLSTATE');
});

// ---------------------------------------------------------------------------
// Scénario B — une commande et une facture augmentent l'encours ensemble
// ---------------------------------------------------------------------------
it('scénario B : une facture émise en concurrence ne corrompt pas le contrôle de crédit', function () {
    $ctx = raceCompany('B');
    app()->instance('current_company', $ctx['company']);
    $ctx['client'] = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 10_000_000, 'balance' => 0,
    ]);

    raceInvoice($ctx, 'FAC-RACE-B-001', 6_000_000);
    // Facture encore en brouillon : elle ne compte pas tant qu'elle n'est pas émise.
    $pending = raceInvoice($ctx, 'FAC-RACE-B-002', 3_000_000, 'brouillon');
    $order = raceOrder($ctx, 'CMD-RACE-B1', 3_000_000);

    raceCommitFixture();
    $race = raceRun([
        ['submit_order', $order->id, $ctx['user']->id],
        ['issue_invoice', $pending->id, $ctx['user']->id],
    ]);
    DB::reconnect();

    $service = app(CustomerCreditExposureService::class);
    $orderStatus = Order::find($order->id)->status;
    $outputs = implode(' ', $race['outputs']);

    // L'émission de la facture aboutit toujours : facturer n'est pas un point de
    // contrôle du crédit. La commande, elle, dépend de l'ordre réel :
    //   - facture émise avant la lecture : 6M + 3M + 3M = 12M > 10M → bloquée ;
    //   - facture émise après            : 6M + 3M      =  9M ≤ 10M → soumise.
    // Les deux issues sont saines. Ce qui doit être vrai dans TOUS les cas :
    // aucune erreur SQL, aucun état partiel, et aucune commande soumise sur un
    // encours déjà au-dessus du plafond.
    expect($race['stderr'])->toBe('')
        ->and($outputs)->not->toContain('SQLSTATE')
        ->and(Invoice::find($pending->id)->status)->toBe('emise');

    if ($orderStatus === 'en_attente_validation') {
        // Soumise : au moment du contrôle, la facture n'était pas encore émise.
        // L'engagement pris par la commande reste sous le plafond.
        $withoutConcurrentInvoice = $service->compute(
            companyId: (int) $ctx['company']->id,
            clientId: (int) $ctx['client']->id,
            limit: 10_000_000,
            isCredit: true,
        )['projected'] - 3_000_000;
        expect($withoutConcurrentInvoice)->toBeLessThanOrEqual(10_000_000);
    } else {
        // Bloquée : la lecture verrouillante a bien vu la facture concurrente —
        // c'est exactement la garantie apportée par le correctif de course A.
        expect($orderStatus)->toBe('brouillon')
            ->and($outputs)->toContain('encours prévisionnel 12 000 000 FCFA');
    }

    // Encours final, quelle que soit l'issue : entièrement expliqué par les
    // écritures réellement committées, à l'unité près.
    $exposure = $service->assessClient($ctx['client']->fresh(), (int) $ctx['company']->id);
    $expectedOpenOrders = $orderStatus === 'en_attente_validation' ? 3_000_000 : 0;
    expect($exposure['outstanding'])->toBe(9_000_000)
        ->and($exposure['open_orders'])->toBe($expectedOpenOrders)
        ->and($exposure['projected'])->toBe(9_000_000 + $expectedOpenOrders);
});

// ---------------------------------------------------------------------------
// Scénario C — un acompte est confirmé pendant le contrôle de crédit
// ---------------------------------------------------------------------------
it('scénario C : seuls les acomptes réellement confirmés réduisent l encours', function () {
    $ctx = raceCompany('C');
    app()->instance('current_company', $ctx['company']);
    $ctx['client'] = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 10_000_000, 'balance' => 0,
    ]);

    raceInvoice($ctx, 'FAC-RACE-C-001', 9_000_000);
    $order = raceOrder($ctx, 'CMD-RACE-C1', 2_000_000);

    // Acompte encore EN ATTENTE : il ne doit rien réduire tant qu'il n'est pas
    // confirmé. Il est confirmé en parallèle de la soumission de la commande.
    $deposit = ClientPayment::create([
        'company_id' => $ctx['company']->id, 'client_id' => $ctx['client']->id,
        'number' => 'ACO-RACE-C-001', 'amount' => 4_000_000, 'payment_date' => now()->toDateString(),
        'status' => 'en_attente', 'is_acompte' => true,
        'allocated_amount' => 0, 'unallocated_amount' => 0,
    ]);

    raceCommitFixture();
    $race = raceRun([
        ['submit_order', $order->id, $ctx['user']->id],
        ['confirm_deposit', $deposit->id, $ctx['user']->id],
    ]);
    DB::reconnect();

    $orderStatus = Order::find($order->id)->status;
    $outputs = implode(' ', $race['outputs']);
    $confirmed = ClientPayment::find($deposit->id);

    expect($race['stderr'])->toBe('')
        ->and($outputs)->not->toContain('SQLSTATE')
        ->and($confirmed->status)->toBe('confirme')
        ->and((int) $confirmed->unallocated_amount)->toBe(4_000_000);

    // Deux issues sont légitimes selon l'ordre réel d'exécution, et AUCUNE des
    // deux ne doit produire d'état partiel :
    //   - acompte confirmé avant la lecture : 9M + 2M - 4M = 7M ≤ 10M → soumise ;
    //   - acompte encore en attente        : 9M + 2M       = 11M > 10M → bloquée.
    if ($orderStatus === 'en_attente_validation') {
        expect($race['codes'])->toBe([0, 0]);
    } else {
        expect($orderStatus)->toBe('brouillon')
            ->and($outputs)->toContain('encours prévisionnel 11 000 000 FCFA');
    }

    // Dans les deux cas, l'acompte n'a JAMAIS été compté avant sa confirmation :
    // aucune sortie ne peut mentionner un encours réduit par un acompte à 0.
    expect($outputs)->not->toContain('acomptes 4 000 000)');
});

// ---------------------------------------------------------------------------
// Scénario D — deux sociétés ne partagent pas l'encours d'un même client
// ---------------------------------------------------------------------------
it('scénario D : deux sociétés ne partagent pas l encours du même client', function () {
    $ctxA = raceCompany('D1');
    $ctxB = raceCompany('D2');

    // MÊME client, présent dans les deux sociétés : la table clients n'a pas de
    // company_id, l'isolation ne peut donc venir que du calcul de l'encours.
    app()->instance('current_company', $ctxA['company']);
    $client = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 10_000_000, 'balance' => 0,
    ]);
    $ctxA['client'] = $client;
    $ctxB['client'] = $client;

    // Société A : déjà 9 000 000 d'encours. Société B : rien.
    raceInvoice($ctxA, 'FAC-RACE-D1-001', 9_000_000);
    $orderA = raceOrder($ctxA, 'CMD-RACE-D1', 2_000_000);
    $orderB = raceOrder($ctxB, 'CMD-RACE-D2', 2_000_000);

    raceCommitFixture();
    $race = raceRun([
        ['submit_order', $orderA->id, $ctxA['user']->id],
        ['submit_order', $orderB->id, $ctxB['user']->id],
    ]);
    DB::reconnect();

    $service = app(CustomerCreditExposureService::class);
    $exposureA = $service->assessClient($client->fresh(), (int) $ctxA['company']->id);
    $exposureB = $service->assessClient($client->fresh(), (int) $ctxB['company']->id);

    expect($race['stderr'])->toBe('')
        ->and(implode(' ', $race['outputs']))->not->toContain('SQLSTATE');

    // Vue depuis la société A : sa commande est visible et bloquée (9M + 2M =
    // 11M > 10M). La commande de B est INVISIBLE — c'est la preuve d'isolation
    // Eloquent, pas un effet de bord du test.
    app()->instance('current_company', $ctxA['company']);
    expect(Order::find($orderA->id)?->status)->toBe('brouillon')
        ->and(Order::find($orderB->id))->toBeNull();

    // Vue depuis la société B : sa commande est passée (aucun encours hérité de
    // A), et la commande de A lui est invisible.
    app()->instance('current_company', $ctxB['company']);
    expect(Order::find($orderB->id)?->status)->toBe('en_attente_validation')
        ->and(Order::find($orderA->id))->toBeNull();

    // Encours : aucune composante n'est partagée entre les deux sociétés.
    expect($exposureA['outstanding'])->toBe(9_000_000)
        ->and($exposureA['open_orders'])->toBe(0)
        ->and($exposureB['outstanding'])->toBe(0)
        ->and($exposureB['open_orders'])->toBe(2_000_000)
        ->and($exposureB['projected'])->toBe(2_000_000);
});
