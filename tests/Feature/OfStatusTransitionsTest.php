<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ofStatusAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'OFS'], ['email' => 'ofs@ofs.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function makeOf(string $status = 'brouillon'): ProductionOrder
{
    $co = Company::first();

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-' . uniqid(), 'status' => $status, 'quantity_requested' => 10, 'controle_qualite_obligatoire' => false,
    ]);
}

it('transitions brouillon → matière allouée → lancé', function () {
    $this->actingAs(ofStatusAdmin());
    $svc = app(ProductionService::class);
    $of = makeOf('brouillon');

    $svc->allocateMaterial($of);
    expect($of->fresh()->status)->toBe('matiere_allouee');

    $svc->launch($of);
    expect($of->fresh()->status)->toBe('lance');
});

it('launches directly from brouillon (allocation optionnelle)', function () {
    $this->actingAs(ofStatusAdmin());
    $of = makeOf('brouillon');
    app(ProductionService::class)->launch($of);
    expect($of->fresh()->status)->toBe('lance');
});

it('transitions en_cours → terminé partiellement → terminé', function () {
    $this->actingAs(ofStatusAdmin());
    $svc = app(ProductionService::class);
    $of = makeOf('en_cours');

    $svc->markPartiallyDone($of);
    expect($of->fresh()->status)->toBe('termine_partiellement');
    expect($of->fresh()->isInProgress())->toBeTrue(); // peut encore produire

    // Une quantité produite réelle est requise pour clôturer (garde cohérence stock).
    $of->update(['quantity_produced' => 10]);
    $svc->finish($of);
    expect($of->fresh()->status)->toBe('termine');
});

it('labels and colors cover the new statuses', function () {
    $this->actingAs(ofStatusAdmin());
    expect(makeOf('matiere_allouee')->statusLabel())->toBe('Matière allouée');
    expect(makeOf('termine_partiellement')->statusLabel())->toBe('Terminé partiellement');
    expect(makeOf('matiere_allouee')->statusColor())->toBe('amber');
});
