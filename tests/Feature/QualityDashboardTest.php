<?php

/**
 * [QUA-08] Tableau de bord indicateurs qualité — agrégations.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qdAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QD'], ['email' => 'qd@qa.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('calcule taux NC, délai et réclamations', function () {
    $this->actingAs(qdAdmin());
    $cid = currentCompany()->id;

    // 4 inspections dont 1 non conforme → taux NC = 25 %
    QualityInspection::factory()->count(3)->create(['company_id' => $cid, 'status' => 'conforme', 'quantity_checked' => 100, 'quantity_rejected' => 0]);
    QualityInspection::factory()->create(['company_id' => $cid, 'status' => 'non_conforme', 'quantity_checked' => 100, 'quantity_rejected' => 10]);

    // NC : 1 ouverte critique, 1 clôturée (3 j) issue client
    NonConformity::create(['company_id' => $cid, 'reference' => 'NC-A', 'title' => 'x', 'severity' => 'critique', 'status' => 'ouverte']);
    $ncB = NonConformity::create([
        'company_id' => $cid, 'reference' => 'NC-B', 'title' => 'y', 'severity' => 'majeure', 'status' => 'cloturee',
        'client_claim' => true, 'closed_at' => '2026-06-04',
    ]);
    // created_at n'est pas fillable → on le force pour un délai déterministe (3 j).
    $ncB->forceFill(['created_at' => '2026-06-01'])->save();

    $res = $this->get(route('qualite.dashboard'))->assertOk()->assertSee('Indicateurs qualité');

    $kpis = $res->viewData('kpis');
    expect($kpis['taux_nc'])->toBe(25.0);
    expect($kpis['taux_rebut'])->toBe(2.5);   // 10 / 400
    expect($kpis['nc_open'])->toBe(1);
    expect($kpis['client_claims'])->toBe(1);
    expect($kpis['avg_lead'])->toBe(3.0);
});

it('gère l’absence de données sans division par zéro', function () {
    $this->actingAs(qdAdmin());

    $res = $this->get(route('qualite.dashboard', ['year' => 2020]))->assertOk();

    $kpis = $res->viewData('kpis');
    expect($kpis['taux_nc'])->toBe(0.0);
    expect($kpis['avg_lead'])->toBeNull();
    expect($kpis['capa_efficacite'])->toBeNull();
});
