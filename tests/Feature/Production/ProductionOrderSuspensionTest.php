<?php

/**
 * [Refonte Prod X3] Statut « suspendu » :
 *  - suspension possible depuis lance / en_cours / termine_partiellement ;
 *  - déclarations & clôture bloquées pendant la suspension ;
 *  - la reprise restaure le statut d'origine ;
 *  - suspension impossible depuis brouillon / termine.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function suspAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SUSP'], ['email' => 'susp@susp.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WS'], ['name' => 'WS', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function suspOf(string $status = 'en_cours'): ProductionOrder
{
    $co = Company::first();
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-SUSP-' . uniqid(), 'status' => $status,
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => $pf->id, 'launched_at' => now(),
    ]);
}

it('suspend un OF en cours et mémorise le statut d\'origine', function () {
    $this->actingAs(suspAdmin());
    $of = suspOf('en_cours');

    $this->post(route('production.orders.suspend', $of), ['reason' => 'Panne machine'])
        ->assertRedirect()->assertSessionHas('success');

    $of->refresh();
    expect($of->status)->toBe('suspendu');
    expect($of->suspended_from)->toBe('en_cours');
    expect($of->suspended_at)->not->toBeNull();
    expect($of->notes)->toContain('Panne machine');
});

it('bloque la clôture d\'un OF suspendu', function () {
    $this->actingAs(suspAdmin());
    $of = suspOf('en_cours');
    $this->post(route('production.orders.suspend', $of));

    expect(fn () => app(ProductionService::class)->finish($of->fresh()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('reprend un OF suspendu en restaurant le statut d\'origine', function () {
    $this->actingAs(suspAdmin());
    $of = suspOf('termine_partiellement');
    $this->post(route('production.orders.suspend', $of));

    $this->post(route('production.orders.resume', $of))
        ->assertRedirect()->assertSessionHas('success');

    $of->refresh();
    expect($of->status)->toBe('termine_partiellement');
    expect($of->suspended_from)->toBeNull();
    expect($of->suspended_at)->toBeNull();
});

it('refuse de suspendre un OF brouillon ou terminé', function () {
    $this->actingAs(suspAdmin());

    $this->post(route('production.orders.suspend', suspOf('brouillon')))->assertStatus(422);
    $this->post(route('production.orders.suspend', suspOf('termine')))->assertStatus(422);
});

it('refuse de reprendre un OF non suspendu', function () {
    $this->actingAs(suspAdmin());

    $this->post(route('production.orders.resume', suspOf('en_cours')))->assertStatus(422);
});
