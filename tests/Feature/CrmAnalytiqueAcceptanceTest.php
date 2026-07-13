<?php

/**
 * [CDC — Critère d'acceptation CRM & Comptabilité analytique]
 *
 * « Les modules seront acceptés lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 * CRM :
 *   - PIPELINE : opportunité déplacée de stage → probabilité recalculée (statuts)
 *   - IMPACT AUTO : conversion d'un contact CRM en client (module Gestion)
 *   - ACTIVITÉ : pointage terminé/à faire (statuts)
 *   - DROITS : écriture CRM refusée sans crm.manage
 *
 * Analytique (§12) :
 *   - IMPACT : ligne analytique imputée à un centre de coûts → agrégée dans le rapport
 *   - DROITS : accès analytique refusé sans analytic.view
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function crmSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CRM'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CRM Co'], ['email' => 'crm@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return compact('co', 'u');
}

it('déplace une opportunité dans le pipeline et recalcule la probabilité (statuts)', function () {
    $ctx = crmSetup();
    $contact = CrmContact::create(['company_id' => $ctx['co']->id, 'name' => 'Prospect Alpha']);
    $opp = CrmOpportunity::create([
        'company_id' => $ctx['co']->id, 'crm_contact_id' => $contact->id,
        'title' => 'Toiture usine', 'amount' => 5_000_000, 'stage' => 'prospection', 'probability' => 10,
    ]);

    $this->patch(route('crm.opportunities.move-stage', $opp), ['stage' => 'gagne'])
        ->assertOk()->assertJson(['ok' => true]);

    $opp->refresh();
    expect($opp->stage)->toBe('gagne')
        ->and((int) $opp->probability)->toBe(100); // prob du stage « gagné »
});

it('convertit un contact CRM en client (impact automatique module Gestion)', function () {
    $ctx = crmSetup();
    $contact = CrmContact::create(['company_id' => $ctx['co']->id, 'name' => 'Société Beta', 'email' => 'beta@x.test', 'phone' => '70000000']);

    $before = Client::count();
    $this->post(route('crm.contacts.convert', $contact))->assertRedirect();

    $contact->refresh();
    expect($contact->client_id)->not->toBeNull()
        ->and(Client::count())->toBe($before + 1)
        ->and(Client::find($contact->client_id)->name)->toBe('Société Beta');
});

it('pointe une activité comme terminée (statuts)', function () {
    $ctx = crmSetup();
    $act = CrmActivity::create([
        'company_id' => $ctx['co']->id, 'user_id' => $ctx['u']->id,
        'type' => 'appel', 'subject' => 'Relance devis', 'is_done' => false,
    ]);

    $this->patch(route('crm.activities.toggle-done', $act))->assertRedirect();
    expect((bool) $act->fresh()->is_done)->toBeTrue();
});

it('refuse l’écriture CRM sans la permission crm.manage (droits)', function () {
    $ctx = crmSetup();
    Permission::firstOrCreate(['name' => 'crm.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'crm.manage', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'crm_lecteur', 'guard_name' => 'web']);
    $role->givePermissionTo('crm.view'); // lecture seule
    $u = User::factory()->create(['company_id' => $ctx['co']->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    // Lecture autorisée
    $this->actingAs($u)->get('/crm/opportunities')->assertOk();
    // Écriture refusée (crm.manage requis)
    $this->actingAs($u)->post(route('crm.opportunities.store'), ['title' => 'X', 'amount' => 1000, 'stage' => 'prospection'])
        ->assertForbidden();
});

it('impute une ligne analytique à un centre de coûts et l’agrège dans le rapport (analytique)', function () {
    $ctx = crmSetup();
    $cc = CostCenter::create(['company_id' => $ctx['co']->id, 'code' => 'CC-PROD', 'name' => 'Atelier production', 'is_active' => true]);

    $this->post(route('analytique.lignes.store'), [
        'cost_center_id' => $cc->id, 'date' => now()->toDateString(),
        'label' => 'Consommation matière', 'category' => 'matiere', 'amount' => 250000,
    ])->assertRedirect();

    $resp = $this->get(route('analytique.rapport', ['year' => now()->year]));
    $resp->assertOk();
    $resp->assertSee('Atelier production');

    // Impact : la charge est agrégée sur le centre
    $total = \App\Models\AnalyticLine::where('cost_center_id', $cc->id)->sum('amount');
    expect((int) $total)->toBe(250000);
});

it('refuse l’accès à l’analytique sans la permission analytic.view (droits)', function () {
    $ctx = crmSetup();
    Permission::firstOrCreate(['name' => 'analytic.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits_analytique', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $ctx['co']->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get(route('analytique.rapport'))->assertForbidden();
});
