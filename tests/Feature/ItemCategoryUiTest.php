<?php

/**
 * [X3 §16-17] Écrans catégories : liste, fiche, propagation (aperçu + exécution),
 * désactivation ; permissions categories.* appliquées côté serveur.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\User;
use App\Services\ProductService;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function uiSetup(): void
{
    $fy = FiscalYear::firstOrCreate(['label' => 'UI-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'UI Co'], ['email' => 'ui@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    (new ItemCategorySeeder())->run();
}

function uiAdmin(): User
{
    $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

it('liste et fiche catégorie rendent (filtres inclus)', function () {
    uiSetup();
    uiAdmin();

    $this->get(route('articles.categories.index', ['strategy' => 'mto']))
        ->assertOk()->assertSee('PF_TOLE_MTO')->assertDontSee('PF_FER_MTS');

    $cat = ItemCategory::where('code', 'PF_TOLE_MTO')->first();
    $this->get(route('articles.categories.show', $cat))
        ->assertOk()->assertSee('Tôles fabriquées sur commande')->assertSee('Propagation');
});

it('refuse l\'accès sans permission categories.view (bouton masqué ≠ autorisation)', function () {
    uiSetup();
    $sans = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

    $this->actingAs($sans)->get(route('articles.categories.index'))->assertForbidden();
    $this->actingAs($sans)->post(route('articles.categories.propagate', ItemCategory::first()), ['fields' => ['controle_qualite']])
        ->assertForbidden();
});

it('crée, désactive puis réactive une catégorie', function () {
    uiSetup();
    uiAdmin();

    $this->post(route('articles.categories.store'), [
        'code' => 'TEST_CAT', 'name' => 'Catégorie de test', 'nature' => 'consommable',
    ])->assertRedirect();
    $cat = ItemCategory::where('code', 'TEST_CAT')->first();
    expect($cat)->not->toBeNull();

    $this->post(route('articles.categories.disable', $cat))->assertRedirect();
    expect($cat->fresh()->is_active)->toBeFalse();
    $this->post(route('articles.categories.disable', $cat))->assertRedirect();
    expect($cat->fresh()->is_active)->toBeTrue();
});

it('aperçu de propagation via HTTP puis exécution', function () {
    uiSetup();
    uiAdmin();
    $cat = ItemCategory::where('code', 'PF_FER_MTS')->first();
    $p = app(ProductService::class)->create(['name' => 'Fer UI', 'item_category_id' => $cat->id]);
    $p->update(['controle_qualite' => false]);

    $this->get(route('articles.categories.propagate.preview', $cat) . '?fields[]=controle_qualite')
        ->assertOk()->assertSee('Fer UI')->assertSee('controle_qualite');

    $this->post(route('articles.categories.propagate', $cat), ['fields' => ['controle_qualite']])
        ->assertRedirect();
    expect($p->fresh()->controle_qualite)->toBeTrue();
});

it('refuse un code dupliqué', function () {
    uiSetup();
    uiAdmin();
    $this->post(route('articles.categories.store'), [
        'code' => 'MARCHANDISE', 'name' => 'Doublon', 'nature' => 'marchandise',
    ])->assertSessionHasErrors('code');
});
