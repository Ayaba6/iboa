<?php

/**
 * [QUA-07] Libération qualité des lots — décision libéré / refusé / dérogation.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Quality\Models\QualityRelease;
use App\Modules\Quality\Services\QualityReleaseService;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function qrAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QR'], ['email' => 'qr@qa.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function qrBatch(): ProductionBatch
{
    $batch = ProductionBatch::factory()->create([
        'company_id' => currentCompany()->id, 'status' => 'en_cours', 'quantity' => 120,
    ]);
    $batch->productionOrder?->update(['controle_qualite_obligatoire' => false]);

    return $batch->fresh();
}

it('libère un lot et le passe conforme', function () {
    $this->actingAs(qrAdmin());
    $batch = qrBatch();

    $rel = app(QualityReleaseService::class)->decide($batch, 'libere', 'Contrôles OK');

    expect($rel->status)->toBe('libere')->and($rel->isReleased())->toBeTrue();
    expect((float) $rel->quantity)->toBe(120.0);
    expect($batch->fresh()->status)->toBe('conforme');
});

it('libère sous dérogation avec référence', function () {
    $this->actingAs(qrAdmin());
    $batch = qrBatch();

    $rel = app(QualityReleaseService::class)->decide($batch, 'derogation', 'Écart mineur toléré', 'DER-2026-005');

    expect($rel->status)->toBe('derogation')->and($rel->isReleased())->toBeTrue();
    expect($rel->derogation_reference)->toBe('DER-2026-005');
    expect($batch->fresh()->status)->toBe('conforme');
});

it('refuse un lot et le rebloque en cours', function () {
    $this->actingAs(qrAdmin());
    $batch = qrBatch();
    $svc = app(QualityReleaseService::class);
    $svc->decide($batch, 'libere');            // d'abord libéré → conforme
    expect($batch->fresh()->status)->toBe('conforme');

    $svc->decide($batch->fresh(), 'refuse', 'Défaut détecté a posteriori');

    expect(QualityRelease::where('production_batch_id', $batch->id)->count())->toBe(1); // une seule décision
    expect(QualityRelease::first()->status)->toBe('refuse');
    expect($batch->fresh()->status)->toBe('en_cours');
    expect($batch->fresh()->isQualityReleased())->toBeFalse();
});

it('exige une référence pour une dérogation via le contrôleur', function () {
    $this->actingAs(qrAdmin());
    $batch = qrBatch();

    $this->post(route('qualite.releases.decide', $batch), ['decision' => 'derogation'])
        ->assertSessionHasErrors('derogation_reference');

    expect(QualityRelease::count())->toBe(0);
});
