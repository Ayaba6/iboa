<?php

/**
 * [CDC §13.8/§14] Maintenance préventive — plans + génération automatique
 * d'interventions, et pièces de rechange consommées (sortie de stock réelle).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\MaintenancePlan;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Services\MaintenancePlanService;
use App\Modules\Production\Services\MaintenanceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mpAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MP-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MP Co'], ['email' => 'mp@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    return $u;
}

function mpMachine(): ProductionMachine
{
    return ProductionMachine::create([
        'company_id' => Company::first()->id, 'code' => 'MX-' . rand(1000, 9999),
        'name' => 'Profileuse MP', 'type' => 'profilage', 'status' => 'active', 'is_active' => true,
    ]);
}

describe('Plans de maintenance préventive', function () {

    it('crée un plan et calcule sa première échéance', function () {
        $this->actingAs(mpAdmin());
        $machine = mpMachine();

        $plan = app(MaintenancePlanService::class)->create([
            'machine_id'     => $machine->id,
            'name'           => 'Graissage hebdomadaire',
            'frequency_days' => 7,
        ]);

        expect($plan->next_due_at->toDateString())->toBe(now()->addDays(7)->toDateString())
            ->and($plan->is_active)->toBeTrue();
    });

    it('génère une intervention pour chaque plan dû et avance l\'échéance', function () {
        $this->actingAs(mpAdmin());
        $machine = mpMachine();

        $plan = MaintenancePlan::create([
            'company_id' => $machine->company_id, 'machine_id' => $machine->id,
            'name' => 'Contrôle courroie', 'frequency_days' => 30,
            'next_due_at' => now()->subDay(), // déjà dû
            'is_active' => true,
        ]);

        $generated = app(MaintenancePlanService::class)->generateDueInterventions();

        expect($generated)->toHaveCount(1);
        $intervention = $generated->first();
        expect($intervention->type)->toBe('preventive')
            ->and($intervention->status)->toBe('planifie')
            ->and($intervention->maintenance_plan_id)->toBe($plan->id)
            ->and($intervention->title)->toBe('Contrôle courroie');

        $plan->refresh();
        expect($plan->next_due_at->toDateString())->toBe(now()->addDays(30)->toDateString())
            ->and($plan->last_generated_at->toDateString())->toBe(now()->toDateString());

        // Re-générer immédiatement ne recrée rien (plus dû).
        expect(app(MaintenancePlanService::class)->generateDueInterventions())->toHaveCount(0);
    });

    it('ignore les plans inactifs ou non encore dus', function () {
        $this->actingAs(mpAdmin());
        $machine = mpMachine();

        MaintenancePlan::create([
            'company_id' => $machine->company_id, 'machine_id' => $machine->id,
            'name' => 'Plan inactif', 'frequency_days' => 30,
            'next_due_at' => now()->subDay(), 'is_active' => false,
        ]);
        MaintenancePlan::create([
            'company_id' => $machine->company_id, 'machine_id' => $machine->id,
            'name' => 'Plan futur', 'frequency_days' => 30,
            'next_due_at' => now()->addDays(10), 'is_active' => true,
        ]);

        expect(app(MaintenancePlanService::class)->generateDueInterventions())->toHaveCount(0);
    });

    it('génère via la route HTTP', function () {
        $this->actingAs(mpAdmin());
        $machine = mpMachine();
        MaintenancePlan::create([
            'company_id' => $machine->company_id, 'machine_id' => $machine->id,
            'name' => 'Vidange', 'frequency_days' => 60,
            'next_due_at' => now()->subDay(), 'is_active' => true,
        ]);

        $this->post(route('production.maintenance-plans.generate'))->assertRedirect();
        expect(MachineMaintenance::where('title', 'Vidange')->exists())->toBeTrue();
    });
});

describe('Pièces de rechange consommées', function () {

    it('consomme une pièce : sortie de stock réelle + ligne MaintenancePart', function () {
        $this->actingAs(mpAdmin());
        $co      = Company::first();
        $machine = mpMachine();
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MP'],
            ['name' => 'Dépôt MP', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]
        );
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20, 'reserved_quantity' => 0, 'avg_cost' => 2500]);

        $maintenance = MachineMaintenance::create([
            'company_id' => $co->id, 'machine_id' => $machine->id,
            'type' => 'corrective', 'title' => 'Remplacement roulement', 'status' => 'en_cours',
        ]);

        $part = app(MaintenanceService::class)->consumePart($maintenance, $product->id, 2, $warehouse->id);

        expect($part->quantity)->toEqual(2.0)
            ->and((int) $part->unit_cost)->toBe(2500)
            ->and($part->stock_movement_id)->not->toBeNull();

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->quantity)->toBe(18.0);

        $maintenance->refresh();
        expect($maintenance->totalCost())->toBe(5000); // 2 * 2500, cost manuel = 0
    });

    it('bloque l\'ajout de pièce sur une intervention déjà clôturée', function () {
        $this->actingAs(mpAdmin());
        $co      = Company::first();
        $machine = mpMachine();
        $product = Product::factory()->create(['is_stockable' => true]);
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MP2'],
            ['name' => 'Dépôt MP2', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]
        );
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

        $maintenance = MachineMaintenance::create([
            'company_id' => $co->id, 'machine_id' => $machine->id,
            'type' => 'corrective', 'title' => 'Terminée', 'status' => 'termine', 'ended_at' => now(),
        ]);

        expect(fn () => app(MaintenanceService::class)->consumePart($maintenance, $product->id, 1, $warehouse->id))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('ajoute une pièce via la route HTTP et l\'affiche sur le formulaire', function () {
        $this->actingAs(mpAdmin());
        $co      = Company::first();
        $machine = mpMachine();
        $product = Product::factory()->create(['is_stockable' => true, 'name' => 'Roulement SKF']);
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MP3'],
            ['name' => 'Dépôt MP3', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]
        );
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 3000]);

        $maintenance = MachineMaintenance::create([
            'company_id' => $co->id, 'machine_id' => $machine->id,
            'type' => 'corrective', 'title' => 'Test pièce HTTP', 'status' => 'planifie',
        ]);

        $this->post(route('production.maintenance.parts.store', $maintenance), [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1,
        ])->assertRedirect();

        $this->get(route('production.maintenance.edit', $maintenance))
            ->assertOk()
            ->assertSee('Roulement SKF');
    });
});

it('affiche le tableau de bord maintenance avec les plans et KPI', function () {
    $this->actingAs(mpAdmin());
    $machine = mpMachine();
    MaintenancePlan::create([
        'company_id' => $machine->company_id, 'machine_id' => $machine->id,
        'name' => 'Plan visible', 'frequency_days' => 30,
        'next_due_at' => now()->subDay(), 'is_active' => true,
    ]);

    $this->get(route('production.maintenance.index'))->assertOk();
    $this->get(route('production.maintenance-plans.index'))
        ->assertOk()
        ->assertSee('Plan visible')
        ->assertSee('Due');
});
