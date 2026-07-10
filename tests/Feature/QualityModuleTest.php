<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QA'], ['email' => 'qa@qa.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('renders quality index pages', function () {
    $this->actingAs(qaAdmin());
    $this->get(route('qualite.inspections.index'))->assertOk()->assertSee('Contrôles qualité');
    $this->get(route('qualite.non-conformities.index'))->assertOk()->assertSee('Non-conformités');
});

it('creates a reception quality inspection with auto reference', function () {
    $this->actingAs(qaAdmin());
    $this->post(route('qualite.inspections.store'), [
        'type' => 'reception', 'status' => 'non_conforme', 'quantity_checked' => 100, 'quantity_rejected' => 8,
    ])->assertRedirect(route('qualite.inspections.index'));

    $i = QualityInspection::first();
    expect($i->reference)->toBe('CQ-00001');
    expect($i->status)->toBe('non_conforme');
    expect((float) $i->quantity_rejected)->toEqual(8.0);
});

it('creates and closes a non-conformity with corrective action', function () {
    $this->actingAs(qaAdmin());
    $insp = QualityInspection::factory()->create();

    $this->post(route('qualite.non-conformities.store'), [
        'title' => 'Défaut épaisseur', 'severity' => 'majeure', 'status' => 'ouverte',
        'quality_inspection_id' => $insp->id, 'description' => 'Tôle hors tolérance',
    ])->assertRedirect(route('qualite.non-conformities.index'));

    $nc = NonConformity::first();
    expect($nc->reference)->toBe('NC-00001');
    expect($nc->severity)->toBe('majeure');

    // close with corrective action → closed_at set
    $this->put(route('qualite.non-conformities.update', $nc), [
        'title' => $nc->title, 'severity' => 'majeure', 'status' => 'cloturee',
        'corrective_action' => 'Recalibrage profileuse + contrôle renforcé',
    ])->assertRedirect();

    $nc->refresh();
    expect($nc->status)->toBe('cloturee');
    expect($nc->closed_at)->not->toBeNull();
    expect($nc->corrective_action)->toContain('Recalibrage');
});

it('refuse de clôturer une non-conformité sans action corrective (CDC §12.4)', function () {
    $this->actingAs(qaAdmin());
    $insp = QualityInspection::factory()->create();

    $this->post(route('qualite.non-conformities.store'), [
        'title' => 'Défaut largeur', 'severity' => 'mineure', 'status' => 'ouverte',
        'quality_inspection_id' => $insp->id,
    ]);
    $nc = NonConformity::first();

    $this->put(route('qualite.non-conformities.update', $nc), [
        'title' => $nc->title, 'severity' => 'mineure', 'status' => 'cloturee',
        // corrective_action omise volontairement
    ])->assertSessionHasErrors('corrective_action');

    $nc->refresh();
    expect($nc->status)->toBe('ouverte')
        ->and($nc->closed_at)->toBeNull();
});

it('blocks quality management without permission', function () {
    $co = Company::firstOrCreate(['name' => 'QA'], ['email' => 'qa@qa.io']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'viewer_q', 'guard_name' => 'web']);
    $role->syncPermissions(['production.view']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get(route('qualite.inspections.create'))->assertForbidden();
});
