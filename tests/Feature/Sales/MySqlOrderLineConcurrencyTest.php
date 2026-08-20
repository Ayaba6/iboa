<?php

/**
 * [BUG-A3-SALES-LINE-IMMUTABLE-012] Course MySQL RÉELLE sur les lignes de commande.
 *
 * Deux processus PHP indépendants modifient la même commande au même instant,
 * avec leurs propres connexions PDO. Un test mono-processus ne prouverait rien
 * du verrouillage : il rejouerait la logique en série.
 *
 * Ce qui est éprouvé n'est pas l'absence d'erreur SQL, mais l'invariant métier :
 *
 *     Transaction A lit 4000, transaction B lit 4000,
 *     A écrit 5000, B écrit 6000 sans avoir vu 5000.
 *
 * `lockForUpdate()` sur la commande PUIS sur ses lignes doit sérialiser les deux :
 * la seconde attend, relit l'état committé, et écrit par-dessus. Le résultat
 * final est donc l'un des deux prix — jamais un mélange, jamais une ligne
 * dupliquée, jamais une ligne perdue.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

/** Le décor doit être COMMITÉ : les workers ont leurs propres connexions. */
function olcCommitFixture(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/** @return array{order:Order,user:User} */
function olcScenario(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'OLC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'OLC Co'], ['email' => 'olc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WOLC'], ['name' => 'WOLC', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $article = Product::factory()->create([
        'is_sellable' => true, 'is_active' => true, 'sale_price' => 4000,
        'production_mode' => 'achat_revente',
    ]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-OLC-'.uniqid(), 'status' => 'brouillon', 'issued_at' => now(),
    ]);
    foreach (['Ligne A', 'Ligne B'] as $i => $libelle) {
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $article->id,
            'description' => $libelle, 'quantity' => 10, 'unit_price' => 4000,
            'line_total_ht' => 40000, 'line_tax' => 0, 'line_total_ttc' => 40000,
            'sort_order' => $i,
        ]);
    }

    return ['order' => $order->fresh(), 'user' => $u];
}

/** Lance deux workers en parallèle, départ synchronisé. */
function olcRace(int $orderId, int $userId, array $prix, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/order_line_race_worker.php');

    $processes = [];
    foreach ($prix as $p) {
        $processes[] = new Process(
            [PHP_BINARY, $worker, (string) $orderId, (string) $userId, (string) $p, (string) $startAt],
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
        'codes'   => array_map(fn (Process $p) => (int) $p->getExitCode(), $processes),
        'outputs' => array_map(fn (Process $p) => $p->getOutput(), $processes),
        'stderr'  => trim(implode("\n", array_map(fn (Process $p) => $p->getErrorOutput(), $processes))),
    ];
}

/**
 * EXCLUSION DÉCLARÉE — inexécutable sur SQLite, pour raison technique.
 *
 * La course exige de VRAIS processus concurrents. Sous SQLite `:memory:`, chaque
 * processus ouvre sa propre base vide : les workers ne verraient pas la
 * commande. SQLite ne fournit pas non plus `SELECT ... FOR UPDATE`, qui est
 * précisément le mécanisme évalué. Le test apparaît donc « skipped » avec ce
 * motif plutôt qu'absent de la collecte.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Course concurrente : exige MySQL et de vrais processus (SELECT ... FOR UPDATE).');
    }
});

it('sérialise deux modifications concurrentes sans perdre ni dupliquer de ligne', function () {
    ['order' => $order, 'user' => $user] = olcScenario();
    $idsAvant = $order->items->pluck('id')->sort()->values()->all();
    olcCommitFixture();

    $course = olcRace($order->id, $user->id, [5000, 6000]);

    // Aucune erreur technique : ni deadlock remonté brut, ni SQLSTATE.
    expect($course['codes'])->not->toContain(3);
    expect($course['stderr'])->not->toContain('SQLSTATE');

    $apres = Order::find($order->id)->items()->get();

    // Ni ligne perdue, ni ligne dupliquée : les identifiants sont les mêmes.
    expect($apres->pluck('id')->sort()->values()->all())->toBe($idsAvant);
    expect($apres)->toHaveCount(2);

    // Le prix final est celui d'UN des deux workers, jamais un mélange : les
    // deux lignes portent forcément la même valeur.
    $prix = $apres->pluck('unit_price')->map(fn ($p) => (int) $p)->unique()->values()->all();
    expect($prix)->toHaveCount(1);
    expect($prix[0])->toBeIn([5000, 6000]);
});

it('ne laisse aucune ligne supprimée derrière une course', function () {
    ['order' => $order, 'user' => $user] = olcScenario();
    olcCommitFixture();

    olcRace($order->id, $user->id, [5000, 6000]);

    // Les deux workers envoient les MÊMES lignes : aucune ne doit être vue comme
    // absente, donc aucune suppression logique ne doit se produire. Une course
    // mal sérialisée retirerait la ligne que l'autre vient d'écrire.
    $supprimees = OrderItem::withTrashed()
        ->where('order_id', $order->id)
        ->whereNotNull('deleted_at')->count();

    expect($supprimees)->toBe(0);
});

it('applique exactement une fois chaque modification acceptée', function () {
    ['order' => $order, 'user' => $user] = olcScenario();
    olcCommitFixture();

    $course = olcRace($order->id, $user->id, [7000, 8000]);

    // Les deux passent — la commande est en brouillon, aucune règle ne les
    // refuse. Ce qui compte est qu'aucun n'ait écrit sur un état périmé.
    $acceptes = count(array_filter($course['codes'], fn ($c) => $c === 0));
    expect($acceptes)->toBe(2);

    $total = (int) Order::find($order->id)->fresh()->subtotal_ht;
    $lignes = Order::find($order->id)->items()->get();

    // Le total recalculé correspond aux lignes réellement en base : si un worker
    // avait écrit sur une lecture périmée, le total et les lignes divergeraient.
    expect($total)->toBe((int) $lignes->sum('line_total_ht'));
});
