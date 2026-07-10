<?php

/**
 * [CDC §13.4] Seuils d'approbation des demandes d'achat :
 * <500k Chef Service (validate_l1), <5M Directeur (validate_l2), ≥5M DG (validate_l3).
 * L'approbation est BLOQUÉE si l'approbateur n'a pas le niveau du montant.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseRequestService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\Concerns\RefreshDatabase::class);

function thrCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'THR-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Thr Co'], ['email' => 'thr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function thrUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => thrCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

function thrRequest(float $amount): PurchaseRequest
{
    $co = thrCompany();
    return PurchaseRequest::create([
        'company_id'      => $co->id,
        'number'          => 'DA-THR-' . uniqid(),
        'status'          => 'soumis',
        'requested_by'    => User::first()?->id,
        'department'      => 'production',
        'total_estimated' => $amount,
        'needed_at'       => now()->addDays(7),
        'submitted_at'    => now(),
    ]);
}

it('bloque le chef service (l1) sur une DA de 2M — niveau Directeur requis', function () {
    $chefService = thrUser('directeur_usine'); // validate_l1 uniquement
    $this->actingAs($chefService);

    $da = thrRequest(2_000_000);

    expect(fn () => app(PurchaseRequestService::class)->approve($da))
        ->toThrow(RuntimeException::class, 'Direction');
});

it('bloque le daf (l2) sur une DA de 8M — niveau DG requis', function () {
    $daf = thrUser('daf'); // validate_l1 + l2, pas l3
    $this->actingAs($daf);

    $da = thrRequest(8_000_000);

    expect(fn () => app(PurchaseRequestService::class)->approve($da))
        ->toThrow(RuntimeException::class, 'Direction Générale');
});

it('permet au chef service (l1) d\'approuver une DA de 300k', function () {
    $chefService = thrUser('directeur_usine');
    $this->actingAs($chefService);

    $da = thrRequest(300_000);
    app(PurchaseRequestService::class)->approve($da);

    expect($da->fresh()->status)->toBe('approuve');
});

it('permet au daf (l2) d\'approuver une DA de 2M', function () {
    $daf = thrUser('daf');
    $this->actingAs($daf);

    $da = thrRequest(2_000_000);
    app(PurchaseRequestService::class)->approve($da);

    expect($da->fresh()->status)->toBe('approuve');
});

it('permet au directeur (DG, l3) d\'approuver une DA de 8M', function () {
    $dg = thrUser('directeur'); // toutes permissions dont validate_l3
    $this->actingAs($dg);

    $da = thrRequest(8_000_000);
    app(PurchaseRequestService::class)->approve($da);

    expect($da->fresh()->status)->toBe('approuve');
});
