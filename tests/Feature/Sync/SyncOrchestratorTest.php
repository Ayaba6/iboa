<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Sync\SyncOrchestrator;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function syncAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SyncCo'], ['email' => 'sync@co.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function syncProduct(): Product
{
    return Product::factory()->create();
}

it('journalise une synchronisation réussie', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();

    $ran = false;
    app(SyncOrchestrator::class)->run(
        sourceModule: 'test', targetModule: 'stock',
        eventName: 'test.event', action: 'do_thing',
        source: $product,
        callback: function () use (&$ran) { $ran = true; return 'ok'; },
    );

    expect($ran)->toBeTrue();
    $log = SyncLog::first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SyncLog::STATUS_SUCCESS)
        ->and($log->source_module)->toBe('test')
        ->and($log->target_module)->toBe('stock')
        ->and((int) $log->source_id)->toBe($product->id);
});

it('est idempotent — la callback ne se rejoue pas après un succès', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();
    $orchestrator = app(SyncOrchestrator::class);
    $runs = 0;
    $cb = function () use (&$runs) { $runs++; };

    $orchestrator->run('test', 'stock', 'test.event', 'do_thing', $product, $cb);
    $orchestrator->run('test', 'stock', 'test.event', 'do_thing', $product, $cb);

    expect($runs)->toBe(1)
        ->and(SyncLog::where('status', SyncLog::STATUS_SUCCESS)->count())->toBe(1)
        ->and(SyncLog::where('status', SyncLog::STATUS_SKIPPED)->count())->toBe(1);
});

it('journalise un échec avec le message et relance l\'exception', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();

    expect(fn () => app(SyncOrchestrator::class)->run(
        'test', 'compta', 'test.event', 'boom', $product,
        fn () => throw new RuntimeException('explosion contrôlée'),
    ))->toThrow(RuntimeException::class);

    $log = SyncLog::first();
    expect($log->status)->toBe(SyncLog::STATUS_FAILED)
        ->and($log->message)->toContain('explosion contrôlée');
});

it('relance un échec via retry avec un handler idempotent', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();

    // Handler de test : incrémente un compteur statique
    $log = SyncLog::create([
        'source_module' => 'test', 'target_module' => 'stock',
        'event_name' => 'test.event', 'action' => 'retryable',
        'source_type' => $product->getMorphClass(), 'source_id' => $product->id,
        'status' => SyncLog::STATUS_FAILED, 'handler_class' => SyncTestHandler::class,
        'attempts' => 1,
    ]);

    SyncTestHandler::$calls = 0;
    $result = app(SyncOrchestrator::class)->retry($log);

    expect(SyncTestHandler::$calls)->toBe(1)
        ->and($result->status)->toBe(SyncLog::STATUS_SUCCESS)
        ->and($result->attempts)->toBe(2);
});

it('refuse de relancer une synchronisation sans handler', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();

    $log = SyncLog::create([
        'source_module' => 'test', 'target_module' => 'stock',
        'event_name' => 'test.event', 'action' => 'no_handler',
        'source_type' => $product->getMorphClass(), 'source_id' => $product->id,
        'status' => SyncLog::STATUS_FAILED, 'attempts' => 1,
    ]);

    expect(fn () => app(SyncOrchestrator::class)->retry($log))->toThrow(RuntimeException::class);
});

it('affiche l\'écran admin des synchronisations et permet le retry', function () {
    $this->actingAs(syncAdmin());
    $product = syncProduct();

    $log = SyncLog::create([
        'source_module' => 'achats', 'target_module' => 'stock',
        'event_name' => 'reception.validated', 'action' => 'create_stock_entries',
        'source_type' => $product->getMorphClass(), 'source_id' => $product->id,
        'status' => SyncLog::STATUS_FAILED, 'handler_class' => SyncTestHandler::class,
        'attempts' => 1, 'message' => 'panne réseau',
    ]);

    $this->get(route('sync-logs.index'))
        ->assertOk()
        ->assertSee('Synchronisations inter-modules')
        ->assertSee('reception.validated')
        ->assertSee('panne réseau');

    SyncTestHandler::$calls = 0;
    $this->post(route('sync-logs.retry', $log))->assertRedirect();
    expect($log->fresh()->status)->toBe(SyncLog::STATUS_SUCCESS);
});

class SyncTestHandler
{
    public static int $calls = 0;

    public function __invoke($source, array $payload = []): void
    {
        self::$calls++;
    }
}
