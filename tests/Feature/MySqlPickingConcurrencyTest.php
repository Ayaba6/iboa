<?php

/**
 * [Ventes §16] Courses MySQL RÉELLES sur les bons de préparation.
 *
 * Quatre scénarios, tous en processus concurrents avec départ synchronisé :
 *   A · deux préparations se disputent le MÊME reliquat de commande ;
 *   B · deux préparations allouent le MÊME lot de stock ;
 *   C · une modification tente de passer pendant la VALIDATION du bon ;
 *   D · annulation contre validation — une seule transition doit gagner.
 *
 * Invariants vérifiés partout :
 *   - aucune exception SQL brute (« SQLSTATE ») remontée à l'utilisateur ;
 *   - aucun état partiel : un bon est dans un statut cohérent, pas entre deux ;
 *   - la somme des engagements ne dépasse jamais le reliquat réel.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesPicking;
use App\Models\SalesPickingAllocation;
use App\Models\SalesPickingControl;
use App\Models\SalesPickingItem;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\SalesPickingService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;

/**
 * EXCLUSION DÉCLARÉE — réservée à MySQL.
 *
 * Raison technique, pas de commodité : la course exige de VRAIS processus
 * concurrents. Sous SQLite `:memory:`, chaque processus ouvre sa propre base
 * vide et ne verrait ni la commande ni le stock. SQLite n'implémente pas non
 * plus `SELECT ... FOR UPDATE`, qui est exactement le mécanisme évalué ici.
 *
 * Les identifiants restent IDENTIQUES entre les deux moteurs : sur SQLite ces
 * tests apparaissent en « skipped » avec ce motif, jamais absents de la collecte.
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
 * ISOLATION — obligatoire.
 *
 * Une course impose de COMMITER le décor : des connexions PDO distinctes ne
 * peuvent pas voir les données d'une transaction ouverte. Ce commit annule le
 * mécanisme d'isolation par test, donc tout ce que ce fichier écrit survivrait
 * et contaminerait les tests suivants du même processus. Remède : reconstruction
 * complète de la base après ce fichier.
 */
afterAll(function () {
    RefreshDatabaseState::$migrated = false;
});

/** @return array<string,mixed> */
function pickRaceFixture(float $orderedQty, float $lotQty): array
{
    $fy = FiscalYear::create([
        'label' => 'PICKRACE-'.uniqid(), 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::create([
        'name' => 'OA METAL PICKRACE '.uniqid(), 'email' => 'pickrace-'.uniqid().'@oa-metal.test',
        'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    foreach (['bon_preparations.update', 'bon_preparations.control', 'bon_preparations.validate'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $preparateur = User::factory()->create(['company_id' => $company->id]);
    $preparateur->givePermissionTo('bon_preparations.update');
    $controleur = User::factory()->create(['company_id' => $company->id]);
    $controleur->givePermissionTo(['bon_preparations.control', 'bon_preparations.update']);
    $validateur = User::factory()->create(['company_id' => $company->id]);
    $validateur->givePermissionTo(['bon_preparations.validate', 'bon_preparations.update']);

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'name' => 'Dépôt course', 'code' => 'DEPR-'.uniqid(),
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Kg Race'], ['abbreviation' => 'kgr']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-PR-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 1_000_000, 'total_ttc' => 1_000_000, 'invoiced_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => $product->name, 'quantity' => $orderedQty, 'delivered_quantity' => 0,
        'unit_price' => 10_000, 'line_total_ht' => $orderedQty * 10_000,
        'line_tax' => 0, 'line_total_ttc' => $orderedQty * 10_000,
    ]);

    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-PR-'.uniqid(), 'quantity' => $lotQty, 'initial_quantity' => $lotQty,
        'reserved_quantity' => 0, 'unit_cost' => 800, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere',
        'received_at' => now(),
    ]);

    return compact('company', 'fy', 'order', 'orderItem', 'product', 'warehouse',
        'lot', 'preparateur', 'controleur', 'validateur');
}

/** Le décor doit être COMMITÉ : les workers ont leurs propres connexions. */
function pickRaceCommit(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/**
 * @param  array<int,array{0:string,1:int,2:array<string,mixed>}>  $jobs  [action, userId, args]
 * @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string}
 */
function pickRaceRun(array $jobs, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/picking_race_worker.php');

    $processes = [];
    foreach ($jobs as [$action, $userId, $args]) {
        $processes[] = new Process(
            [PHP_BINARY, $worker, $action, (string) $userId, (string) $startAt, json_encode($args)],
            base_path(), null, null, 60
        );
    }
    foreach ($processes as $p) {
        $p->start();
    }
    foreach ($processes as $p) {
        $p->wait();
    }

    return [
        'codes' => array_map(fn (Process $p) => (int) $p->getExitCode(), $processes),
        'outputs' => array_map(fn (Process $p) => $p->getOutput(), $processes),
        'stderr' => trim(implode("\n", array_map(fn (Process $p) => $p->getErrorOutput(), $processes))),
    ];
}

// ---------------------------------------------------------------------------
// A · Deux préparations sur le MÊME reliquat de commande
// ---------------------------------------------------------------------------

it('course A : deux préparations concurrentes ne dépassent jamais le reliquat', function () {
    // Reliquat 10, chacune demande 8 : les deux ne peuvent pas passer.
    $f = pickRaceFixture(orderedQty: 10, lotQty: 1000);
    pickRaceCommit();

    $args = [
        'order_id' => $f['order']->id, 'order_item_id' => $f['orderItem']->id,
        'quantity' => 8, 'warehouse_id' => $f['warehouse']->id,
    ];
    $race = pickRaceRun([
        ['create', $f['preparateur']->id, $args],
        ['create', $f['preparateur']->id, $args],
    ]);
    DB::reconnect();

    $codes = $race['codes'];
    sort($codes);
    $engaged = (float) SalesPickingItem::where('order_item_id', $f['orderItem']->id)
        ->whereHas('picking', fn ($q) => $q->where('status', '!=', SalesPicking::STATUS_ANNULE))
        ->sum('qty_remaining_snapshot');

    expect($codes)->toBe([0, 2])
        ->and($engaged)->toBe(8.0)
        ->and($engaged)->toBeLessThanOrEqual(10.0)
        ->and(SalesPicking::withoutGlobalScopes()->where('order_id', $f['order']->id)->count())->toBe(1)
        ->and($race['stderr'])->toBe('')
        ->and(implode(' ', $race['outputs']))
        ->toContain('supérieure au reliquat')
        ->not->toContain('SQLSTATE');
});

// ---------------------------------------------------------------------------
// B · Deux préparations allouent le MÊME lot
// ---------------------------------------------------------------------------

it('course B : deux allocations concurrentes ne dépassent pas le disponible du lot', function () {
    // Lot de 10 seulement ; deux bons distincts veulent chacun 8.
    $f = pickRaceFixture(orderedQty: 100, lotQty: 10);

    $service = app(SalesPickingService::class);
    test()->actingAs($f['preparateur']);
    $pickingA = $service->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 8]], ['warehouse_id' => $f['warehouse']->id]);
    $pickingB = $service->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 8]], ['warehouse_id' => $f['warehouse']->id]);
    pickRaceCommit();

    $race = pickRaceRun([
        ['allocate', $f['preparateur']->id, [
            'item_id' => $pickingA->items->first()->id, 'stock_lot_id' => $f['lot']->id,
            'warehouse_id' => $f['warehouse']->id, 'quantity' => 8,
        ]],
        ['allocate', $f['preparateur']->id, [
            'item_id' => $pickingB->items->first()->id, 'stock_lot_id' => $f['lot']->id,
            'warehouse_id' => $f['warehouse']->id, 'quantity' => 8,
        ]],
    ]);
    DB::reconnect();

    $allocated = (float) SalesPickingAllocation::where('stock_lot_id', $f['lot']->id)
        ->where('status', '!=', SalesPickingAllocation::STATUS_ANNULEE)
        ->sum('quantity');

    expect($race['stderr'])->toBe('')
        ->and(implode(' ', $race['outputs']))->not->toContain('SQLSTATE');

    // Le lot ne contient que 10 : deux allocations de 8 le surengageraient.
    // Une seule doit passer, et le total alloué ne peut pas dépasser le stock.
    $codes = $race['codes'];
    sort($codes);

    expect($codes)->toBe([0, 2])
        ->and($allocated)->toBe(8.0)
        ->and($allocated)->toBeLessThanOrEqual(10.0)
        ->and(implode(' ', $race['outputs']))->toContain('déjà alloué');
});

// ---------------------------------------------------------------------------
// C · Modification pendant la validation
// ---------------------------------------------------------------------------

it('course C : une modification concurrente ne corrompt pas un bon en validation', function () {
    $f = pickRaceFixture(orderedQty: 100, lotQty: 1000);

    $service = app(SalesPickingService::class);
    test()->actingAs($f['preparateur']);
    $picking = $service->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 100]], ['warehouse_id' => $f['warehouse']->id]);
    $item = $picking->items->first();
    $alloc = $service->allocate($item, ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 60]);
    $service->start($picking);
    $service->pick($alloc, 60, 'Première tournée.');
    test()->actingAs($f['controleur']);
    $service->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    pickRaceCommit();

    $race = pickRaceRun([
        ['validate', $f['validateur']->id, ['picking_id' => $picking->id]],
        ['allocate', $f['preparateur']->id, [
            'item_id' => $item->id, 'stock_lot_id' => $f['lot']->id,
            'warehouse_id' => $f['warehouse']->id, 'quantity' => 40,
        ]],
    ]);
    DB::reconnect();

    $fresh = SalesPicking::withoutGlobalScopes()->findOrFail($picking->id);
    $outputs = implode(' ', $race['outputs']);

    expect($race['stderr'])->toBe('')
        ->and($outputs)->not->toContain('SQLSTATE');

    // Deux issues saines, jamais d'état intermédiaire :
    //  - la validation gagne : le bon est VALIDÉ et la modification est refusée
    //    (un bon validé est figé) ;
    //  - la modification gagne : le contrôle tombe et la validation est refusée.
    if ($fresh->status === SalesPicking::STATUS_VALIDE) {
        // Le bon est validé : la modification concurrente est refusée par la
        // garde de statut, avec un message qui nomme l'état atteint.
        expect($outputs)->toContain('au statut « valide »')
            ->and($fresh->validated_at)->not->toBeNull();
    } else {
        expect($fresh->status)->toBe(SalesPicking::STATUS_PARTIELLEMENT_PREPARE)
            ->and($fresh->validated_at)->toBeNull()
            ->and($fresh->controlled_at)->toBeNull();
    }
});

// ---------------------------------------------------------------------------
// D · Annulation contre validation
// ---------------------------------------------------------------------------

it('course D : annulation et validation concurrentes — une seule transition gagne', function () {
    $f = pickRaceFixture(orderedQty: 50, lotQty: 1000);

    $service = app(SalesPickingService::class);
    test()->actingAs($f['preparateur']);
    $picking = $service->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]);
    $item = $picking->items->first();
    $alloc = $service->allocate($item, ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50]);
    $service->start($picking);
    $service->pick($alloc, 50);
    test()->actingAs($f['controleur']);
    $service->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);
    pickRaceCommit();

    $race = pickRaceRun([
        ['validate', $f['validateur']->id, ['picking_id' => $picking->id]],
        ['cancel', $f['preparateur']->id, ['picking_id' => $picking->id, 'reason' => 'Client se rétracte.']],
    ]);
    DB::reconnect();

    $fresh = SalesPicking::withoutGlobalScopes()->findOrFail($picking->id);
    $outputs = implode(' ', $race['outputs']);

    expect($race['stderr'])->toBe('')
        ->and($outputs)->not->toContain('SQLSTATE')
        // Un seul statut final possible, jamais les deux marques à la fois.
        ->and($fresh->status)->toBeIn([SalesPicking::STATUS_VALIDE, SalesPicking::STATUS_ANNULE]);

    if ($fresh->status === SalesPicking::STATUS_VALIDE) {
        expect($fresh->cancelled_at)->toBeNull()
            ->and($fresh->validated_at)->not->toBeNull()
            ->and($outputs)->toContain('ne s\'annule pas');
    } else {
        expect($fresh->validated_at)->toBeNull()
            ->and($fresh->cancelled_at)->not->toBeNull()
            ->and($fresh->cancel_reason)->toBe('Client se rétracte.');
    }
});
