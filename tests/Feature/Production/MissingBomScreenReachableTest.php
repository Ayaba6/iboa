<?php

/**
 * [Production] L'écran « Articles sans nomenclature » est atteignable.
 *
 * Il existait, fonctionnait, et n'était lié DEPUIS NULLE PART : deux références
 * dans tout le dépôt — sa classe et sa route. Ni menu, ni bouton, ni test. On
 * n'y accédait qu'en tapant l'URL.
 *
 * Ce qu'il montre n'est pas anodin : les articles fabricables (ou MTO) dépourvus
 * de nomenclature active ne peuvent pas être lancés en production, et le MRP les
 * écarte de ses propositions d'OF. Sans cet écran, rien n'explique leur absence —
 * on constate qu'un article ne sort jamais, sans savoir pourquoi.
 *
 * ATTENTION en relisant ces tests : deux autres écrans que j'avais crus orphelins
 * ne l'étaient pas — les bobines sont liées depuis la fiche article, les plans de
 * maintenance depuis l'écran Maintenance. Un écran hors menu n'est pas forcément
 * injoignable. C'est l'absence TOTALE de référence qui fait l'orphelin.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function mbUser(array $permissions = ['production.view', 'production.report.view']): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MB-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'MissingBom Co'], [
        'email' => 'mb@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'mb_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('expose le lien dans le menu à qui peut lire les rapports de production', function () {
    mbUser();

    $this->get(route('production.orders.index'))->assertOk()
        ->assertSee('Articles sans nomenclature')
        ->assertSee(route('production.articles-sans-nomenclature'));
});

it('masque le lien à qui n’a pas le droit sur les rapports', function () {
    // La permission annoncée par le menu doit être CELLE qui garde la route.
    // Un lien affiché plus largement mènerait à un 403 — c'est le piège que ce
    // test ferme.
    mbUser(['production.view']);

    $this->get(route('production.orders.index'))->assertOk()
        ->assertDontSee('Articles sans nomenclature');
});

it('refuse la page à qui n’a pas le droit sur les rapports', function () {
    mbUser(['production.view']);

    $this->get(route('production.articles-sans-nomenclature'))->assertForbidden();
});

it('liste un article fabricable dépourvu de nomenclature active', function () {
    mbUser();
    $sansBom = Product::factory()->create(['is_manufacturable' => true, 'name' => 'Tole sans nomenclature']);

    $this->get(route('production.articles-sans-nomenclature'))->assertOk()
        ->assertSee('Tole sans nomenclature');
});

it('n’y liste pas un article doté d’une nomenclature active', function () {
    mbUser();
    $avecBom = Product::factory()->create(['is_manufacturable' => true, 'name' => 'Tole equipee']);
    BillOfMaterial::create([
        'company_id' => Company::first()->id, 'product_id' => $avecBom->id,
        'name' => 'BOM equipee', 'is_active' => true,
    ]);

    $this->get(route('production.articles-sans-nomenclature'))->assertOk()
        ->assertDontSee('Tole equipee');
});

it('y ramène un article dont la nomenclature a été désactivée', function () {
    // Une nomenclature inactive ne lance rien : l'article redevient bloquant, et
    // doit réapparaître dans l'écran qui explique pourquoi il ne sort plus.
    mbUser();
    $p = Product::factory()->create(['is_manufacturable' => true, 'name' => 'Tole desactivee']);
    BillOfMaterial::create([
        'company_id' => Company::first()->id, 'product_id' => $p->id,
        'name' => 'BOM desactivee', 'is_active' => false,
    ]);

    $this->get(route('production.articles-sans-nomenclature'))->assertOk()
        ->assertSee('Tole desactivee');
});
