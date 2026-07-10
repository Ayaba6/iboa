<?php

/**
 * [Sync ERP] Réception fournisseur → stock : journalisation sync_logs,
 * idempotence de la relance (jamais deux mouvements pour la même ligne).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Reception;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sync\Handlers\ReplayReceptionStockSync;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function p2sAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'P2S-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'P2S Co'], ['email' => 'p2s@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function p2sReception(User $user): array
{
    $supplier = Supplier::create([
        'code' => 'P2S-' . random_int(1000, 9999), 'type' => 'entreprise',
        'name' => 'Fournisseur P2S', 'is_active' => true, 'balance' => 0,
    ]);
    $product = Product::factory()->create(['is_stockable' => true]);
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-P2S'],
        ['name' => 'Dépôt P2S', 'company_id' => $user->company_id, 'is_active' => true, 'is_default' => true, 'can_purchase' => true]
    );

    $reception = Reception::create([
        'company_id'  => $user->company_id,
        'supplier_id' => $supplier->id,
        'number'      => 'REC-P2S-' . random_int(1000, 9999),
        'status'      => 'brouillon',
        'received_at' => now(),
        'created_by'  => $user->id,
    ]);
    $item = $reception->items()->create([
        'product_id'        => $product->id,
        'description'       => 'Ligne test',
        'quantity'          => 50,
        'received_quantity' => 0,
        'unit_cost'         => 1000,
    ]);

    return [$reception, $item, $warehouse, $product];
}

it('journalise la réception validée dans sync_logs et crée les mouvements', function () {
    $user = p2sAdmin();
    $this->actingAs($user);
    [$reception, $item, $warehouse, $product] = p2sReception($user);

    $this->post(route('achats.receptions.validate', $reception), [
        'warehouse_id' => $warehouse->id,
        'items'        => [$item->id => ['received_quantity' => 50]],
    ])->assertRedirect();

    expect($reception->fresh()->status)->toBe('valide');

    // Mouvement stock créé
    $movements = StockMovement::where('reference_type', 'reception')->where('reference_id', $reception->id)->get();
    expect($movements)->toHaveCount(1)
        ->and((float) $movements->first()->quantity)->toBe(50.0);

    // sync_log success avec la bonne clé logique
    $log = SyncLog::forLogicalKey($reception->getMorphClass(), $reception->id, 'stock', 'create_stock_entries')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SyncLog::STATUS_SUCCESS)
        ->and($log->source_module)->toBe('achats')
        ->and($log->event_name)->toBe('reception.validated');
});

it('la relance du handler ne crée jamais un deuxième mouvement (idempotence)', function () {
    $user = p2sAdmin();
    $this->actingAs($user);
    [$reception, $item, $warehouse] = p2sReception($user);

    $this->post(route('achats.receptions.validate', $reception), [
        'warehouse_id' => $warehouse->id,
        'items'        => [$item->id => ['received_quantity' => 50]],
    ])->assertRedirect();

    $countBefore = StockMovement::where('reference_type', 'reception')->where('reference_id', $reception->id)->count();

    // Relance manuelle du handler (simule un retry admin)
    [$created, $skipped] = app(ReplayReceptionStockSync::class)($reception->fresh('items'));

    $countAfter = StockMovement::where('reference_type', 'reception')->where('reference_id', $reception->id)->count();
    expect($created)->toBe(0)
        ->and($countAfter)->toBe($countBefore);
});
