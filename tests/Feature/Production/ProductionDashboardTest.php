<?php

/**
 * [Refonte Prod X3 §4] Tableau de bord production :
 * KPIs rangée 2 (parc, bobines, qualité, marge) + chaîne de production visuelle.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function dashAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DASH'], ['email' => 'dash@dash.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WDH'], ['name' => 'WDH', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('affiche les KPIs X3 rangée 2 sur le dashboard production', function () {
    $this->actingAs(dashAdmin());

    $this->get(route('production.dashboard'))
        ->assertOk()
        ->assertSee('OF à lancer')->assertSee('Tonnage produit')
        ->assertSee('Bobines')->assertSee('Machines')
        ->assertSee('NC ouvertes')->assertSee('Marge estimée');
});

it('affiche la chaîne de production visuelle avec ses étapes', function () {
    $this->actingAs(dashAdmin());

    $this->get(route('production.dashboard'))
        ->assertOk()
        ->assertSee('Chaîne de production')
        ->assertSee('Commande client')->assertSee('Réservation matière')
        ->assertSee('Contrôle qualité')->assertSee('Stock PF')
        ->assertSee('Livraison')->assertSee('Encaissement');
});
