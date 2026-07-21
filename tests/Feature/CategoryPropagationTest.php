<?php

/**
 * [X3 §8] Propagation contrôlée catégorie → articles existants :
 * aperçu, champs choisis uniquement, liste noire (prix/stocks/unités),
 * transaction, non-automatique.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\User;
use App\Services\CategoryPropagationService;
use App\Services\ProductService;
use Database\Seeders\ItemCategorySeeder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cpSetup(): ItemCategory
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CP-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CP Co'], ['email' => 'cp@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    (new ItemCategorySeeder())->run();
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return ItemCategory::where('code', 'PF_FER_MTS')->first();
}

it('la liste noire exclut prix, seuils de stock et unités de la propagation', function () {
    $cat = cpSetup();
    $fields = app(CategoryPropagationService::class)->propagatableFields($cat);

    foreach (CategoryPropagationService::FORBIDDEN as $forbidden) {
        expect($fields)->not->toContain($forbidden);
    }
    expect($fields)->toContain('controle_qualite'); // comportement propagable
});

it('aperçu : signale uniquement les articles dont la valeur diffère', function () {
    $cat = cpSetup();
    $svc = app(CategoryPropagationService::class);
    $conforme = app(ProductService::class)->create(['name' => 'Fer conforme', 'item_category_id' => $cat->id]);
    $divergent = app(ProductService::class)->create(['name' => 'Fer divergent', 'item_category_id' => $cat->id]);
    $divergent->update(['controle_qualite' => false]); // personnalisé (catégorie = true)

    $preview = $svc->preview($cat, ['controle_qualite']);

    expect($preview['count'])->toBe(1)
        ->and($preview['articles'][0]['id'])->toBe($divergent->id)
        ->and($preview['articles'][0]['diff']['controle_qualite']['vers'])->toBeTrue();
});

it('propage les champs choisis en transaction et ne touche pas les autres', function () {
    $cat = cpSetup();
    $svc = app(CategoryPropagationService::class);
    $p = app(ProductService::class)->create(['name' => 'Fer à corriger', 'item_category_id' => $cat->id]);
    $p->update(['controle_qualite' => false, 'sale_price' => 7777]); // prix personnalisé

    $report = $svc->propagate($cat, ['controle_qualite', 'sale_price' /* ignoré : liste noire */]);

    $p->refresh();
    expect($p->controle_qualite)->toBeTrue()          // propagé
        ->and((int) $p->sale_price)->toBe(7777)       // prix JAMAIS écrasé
        ->and($report['fields'])->not->toContain('sale_price')
        ->and($report['count'])->toBe(1);
});

it('modifier la catégorie seule ne propage rien (action distincte)', function () {
    $cat = cpSetup();
    $p = app(ProductService::class)->create(['name' => 'Fer stable', 'item_category_id' => $cat->id]);
    expect($p->controle_qualite)->toBeTrue();

    $cat->update(['qc_required' => false]);

    expect($p->fresh()->controle_qualite)->toBeTrue(); // inchangé sans propagation
});
