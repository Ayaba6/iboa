<?php

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\Supplier;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function prodAdmin(): User
{
    $company = Company::firstOrCreate(
        ['name' => 'Prod Test Co'],
        ['email' => 'prod@iboa.test']
    );
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('renders production referentiel index pages', function () {
    $this->actingAs(prodAdmin());

    $this->get(route('production.machines.index'))->assertOk();
    $this->get(route('production.lines.index'))->assertOk();
    $this->get(route('production.coils.index'))->assertOk();
    $this->get(route('production.bom.index'))->assertOk();
});

it('renders production create forms', function () {
    $this->actingAs(prodAdmin());

    $this->get(route('production.machines.create'))->assertOk();
    $this->get(route('production.lines.create'))->assertOk();
    $this->get(route('production.coils.create'))->assertOk();
    $this->get(route('production.bom.create'))->assertOk();
});

it('creates a machine', function () {
    $this->actingAs(prodAdmin());

    $this->post(route('production.machines.store'), [
        'code' => 'M1', 'name' => 'Profileuse 1', 'type' => 'profilage',
        'hourly_cost' => 5000, 'status' => 'active', 'is_active' => '1',
    ])->assertRedirect(route('production.machines.index'));

    expect(ProductionMachine::where('code', 'M1')->exists())->toBeTrue();
});

it('receives a coil and computes cost per kg', function () {
    $this->actingAs(prodAdmin());

    $supplier = Supplier::create([
        'code' => 'FOUR-PR', 'type' => 'entreprise', 'name' => 'Fournisseur PR',
        'is_active' => true, 'country' => 'Burkina Faso', 'balance' => 0,
    ]);

    $this->post(route('production.coils.store'), [
        'reference' => 'BOB-001', 'initial_weight' => 2000, 'purchase_price' => 1000000,
        'supplier_id' => $supplier->id, 'lot_number' => 'LOT-PR-001',
        'color' => 'RAL 9006', 'thickness' => 0.5, 'width' => 1000,
    ])->assertRedirect(route('production.coils.index'));

    $coil = Coil::where('reference', 'BOB-001')->first();
    expect($coil)->not->toBeNull();
    expect($coil->remaining_weight)->toEqual(2000.0);
    expect((float) $coil->cost_per_kg)->toEqual(500.0);
    expect($coil->status)->toBe('disponible');
});

it('creates a bom with component lines', function () {
    $this->actingAs(prodAdmin());

    $this->post(route('production.bom.store'), [
        'name' => 'Bac 0.40 Galva', 'sheet_type' => 'bac', 'thickness' => 0.40,
        'consumption_per_meter' => 3.2, 'standard_waste_rate' => 5,
        'lines' => [
            ['label' => 'Faîtière', 'quantity_per_meter' => 0.1, 'waste_rate' => 2],
            ['label' => '', 'product_id' => ''], // empty -> skipped
        ],
    ])->assertRedirect(route('production.bom.index'));

    $bom = BillOfMaterial::where('name', 'Bac 0.40 Galva')->first();
    expect($bom)->not->toBeNull();
    expect($bom->lines()->count())->toBe(1);
});

it('creates a production line linked to a machine', function () {
    $this->actingAs(prodAdmin());

    $machine = ProductionMachine::create([
        'company_id' => Company::first()->id, 'code' => 'M9', 'name' => 'Mix',
        'type' => 'mixte', 'status' => 'active', 'is_active' => true,
    ]);

    $this->post(route('production.lines.store'), [
        'code' => 'L1', 'name' => 'Ligne 1', 'machine_id' => $machine->id, 'is_active' => '1',
    ])->assertRedirect(route('production.lines.index'));

    expect(ProductionLine::where('code', 'L1')->first()->machine_id)->toBe($machine->id);
});
