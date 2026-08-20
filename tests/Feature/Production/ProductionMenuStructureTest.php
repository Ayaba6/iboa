<?php

/**
 * [Navigation] Structure du menu Production — 8 sections, sans doublon.
 *
 * Le menu était PLAT : une vingtaine d'entrées à la suite, sans regroupement,
 * avec plusieurs intitulés désignant le même écran.
 *
 * Doublons supprimés :
 *   - « Machines » et « Équipements » — une machine EST un équipement ;
 *   - « Indicateurs qualité » — c'était le tableau de bord qualité, autrement nommé ;
 *   - « Prévision trésorerie » — déplacée dans le module Trésorerie, où une
 *     prévision de trésorerie a sa place quelle que soit son origine.
 *
 * Les écrans ABSENTS ne sont pas listés : Ordonnancement, Consommations
 * matières, Produits finis et Pertes/chutes n'ont aucune route. Une entrée de
 * menu vers une route inexistante ferait planter le rendu de TOUTES les pages —
 * `route()` lève avant même d'afficher quoi que ce soit.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** Utilisateur voyant l'intégralité du menu Production. */
function menuUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MENU-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'Menu Co'], [
        'email' => 'menu@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'menu_complet', 'guard_name' => 'web']);
    foreach ([
        'production.view', 'production.create', 'production.update',
        'production.report.view', 'production.cost.view',
        'maintenance.view', 'quality.view',
    ] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

function menuHtml(): string
{
    return test()->get(route('production.orders.index'))->assertOk()->getContent();
}

it('regroupe le menu en huit sections', function () {
    menuUser();
    $html = menuHtml();

    foreach (['Planification', 'Fabrication', 'Données techniques', 'Maintenance',
              'Qualité', 'MRP', 'Rapports et analyses'] as $section) {
        expect($html)->toContain($section);
    }

    // « Tableau de bord » ouvre le menu sans en-tête de section : huitième bloc.
    expect($html)->toContain('Tableau de bord');
});

it('fusionne « Machines » et « Équipements » sous un seul intitulé', function () {
    menuUser();
    $html = menuHtml();

    expect($html)->toContain('Équipements de production')
        ->and($html)->not->toContain('>Machines<');
});

it('ne nomme plus le tableau de bord qualité « Indicateurs qualité »', function () {
    menuUser();
    $html = menuHtml();

    expect($html)->toContain('Tableau de bord qualité')
        ->and($html)->not->toContain('Indicateurs qualité');
});

it('sort la prévision de trésorerie du menu Production', function () {
    // Elle n'est pas supprimée : elle rejoint le module Trésorerie. Sa route et
    // sa permission sont inchangées — seul l'endroit où on la trouve change.
    menuUser();

    expect(menuHtml())->not->toContain('Prévision trésorerie');
    expect(route('production.treasury'))->toBeString();
});

it('expose les six analyses demandées, chacune avec son type', function () {
    menuUser();
    $html = menuHtml();

    foreach (['couts', 'respect_programme', 'rendement', 'machine', 'operateur'] as $type) {
        expect($html)->toContain('type='.$type);
    }
    expect($html)->toContain('Coûts de production')
        ->and($html)->toContain('Rendement matière')
        ->and($html)->toContain('Performance des équipements')
        ->and($html)->toContain('Productivité');
});

it('n’allume qu’une seule analyse à la fois', function () {
    // Les six pointent la MÊME route : sans discriminant sur le type, elles
    // s'allumeraient toutes ensemble et le menu ne dirait plus où l'on est.
    menuUser();

    $html = $this->get(route('production.reports', ['type' => 'rendement']))->assertOk()->getContent();

    // La classe active n'apparaît qu'une fois dans le bloc des analyses.
    $actives = substr_count($html, 'bg-[#00A651]/20 text-emerald-300');
    expect($actives)->toBe(1);
});

it('expose le planning de maintenance, jusqu’ici hors menu', function () {
    menuUser();

    expect(menuHtml())->toContain('Planning de maintenance')
        ->and(menuHtml())->toContain('Interventions');
});

it('ne référence aucune route inexistante', function () {
    // Garde structurelle : `route()` lèverait à la construction du menu, donc
    // AUCUNE page ne s'afficherait. Ce test échoue au premier lien mort ajouté.
    menuUser();

    $this->get(route('production.orders.index'))->assertOk();
    $this->get(route('production.dashboard'))->assertOk();
});
