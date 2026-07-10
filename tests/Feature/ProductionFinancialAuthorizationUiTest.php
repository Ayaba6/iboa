<?php

/**
 * [CDC §13.2] Gate financière au lancement OF — le service bloquait déjà
 * `launch()` correctement (ProductionService::checkFinancialGate), mais
 * AUCUN bouton UI n'exposait l'action de déblocage (DAF/DG). Un DAF qui
 * tombait sur l'erreur n'avait aucun moyen d'agir depuis l'écran OF.
 * Ce test couvre le bouton ajouté à resources/views/production/orders/show.blade.php.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Modules\Production\Models\ProductionOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\Concerns\RefreshDatabase::class);

function finAuthCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'FINAUTH-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'FinAuth Co'], ['email' => 'finauth@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function finAuthUser(string $role): \App\Models\User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = \App\Models\User::factory()->create(['company_id' => finAuthCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

it('affiche le bouton d\'autorisation financière au DAF et permet de débloquer l\'OF', function () {
    $daf        = finAuthUser('daf');
    $commercial = finAuthUser('commercial');
    $company    = finAuthCompany();
    $client     = Client::factory()->create(['is_active' => true]);

    $of = ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-FINAUTH-001', 'status' => 'brouillon', 'quantity_requested' => 10,
        'payment_mode' => 'credit', 'payment_rate' => 0,
        'financial_authorization' => 'pending',
    ]);

    // Le DAF voit le bouton de déblocage sur la page OF.
    $this->actingAs($daf);
    $this->get(route('production.orders.show', $of))
        ->assertOk()
        ->assertSee('Validation financière requise')
        ->assertSee(route('production.orders.authorize-finance', $of), false);

    // Le commercial (pas de production.approve_financial) ne voit pas le bouton.
    $this->actingAs($commercial);
    $this->get(route('production.orders.show', $of))
        ->assertOk()
        ->assertDontSee(route('production.orders.authorize-finance', $of), false);

    // Le commercial ne peut pas non plus poster l'action directement (403).
    $this->post(route('production.orders.authorize-finance', $of), ['bypass' => true])
        ->assertForbidden();

    // Le DAF débloque l'OF.
    $this->actingAs($daf);
    $this->post(route('production.orders.authorize-finance', $of), [
        'bypass' => true, 'financial_notes' => 'Dérogation accordée par téléphone, confirmée par écrit.',
    ])->assertRedirect();

    $of->refresh();
    expect($of->financial_authorization)->toBe('bypassed')
        ->and($of->financial_authorized_by)->toBe($daf->id);

    // OF lançable maintenant — la gate financière ne bloque plus.
    app(\App\Modules\Production\Services\ProductionService::class)->launch($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('lance');
});

it('refuse une seconde autorisation si déjà accordée', function () {
    $daf     = finAuthUser('daf');
    $company = finAuthCompany();

    $of = ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-FINAUTH-002', 'status' => 'brouillon', 'quantity_requested' => 10,
        'financial_authorization' => 'approved', 'financial_authorized_at' => now(),
    ]);

    $this->actingAs($daf);
    $this->post(route('production.orders.authorize-finance', $of), ['bypass' => true])
        ->assertStatus(422);
});
