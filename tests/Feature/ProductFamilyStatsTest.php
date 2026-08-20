<?php

/**
 * [Familles] Les compteurs d'en-tête portent sur la même population que la liste.
 *
 * Le bandeau comptait TOUTES les familles, archivées comprises, pendant que la
 * liste n'affichait que les actives : il annonçait 12 familles au-dessus de
 * 3 lignes. Un écart de 4 pour 1, sans rien à l'écran pour l'expliquer.
 *
 * Un compteur qui ne compte pas ce qu'on voit n'informe pas : il fait douter du
 * reste de la page.
 */

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function famUser(): User
{
    $co = Company::firstOrCreate(['name' => 'FAM'], ['email' => 'fam@fam.io']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

beforeEach(function () {
    ProductFamily::create(['name' => 'Active A', 'code' => 'FAM-A', 'is_active' => true]);
    ProductFamily::create(['name' => 'Active B', 'code' => 'FAM-B', 'is_active' => true]);
    ProductFamily::create(['name' => 'Archivée', 'code' => 'FAM-Z', 'is_active' => false]);
});

it('ne compte que les familles actives quand la liste ne montre que les actives', function () {
    $vue = $this->actingAs(famUser())->get(route('product-families.index'))->assertOk();

    $stats = $vue->viewData('stats');
    $affichees = $vue->viewData('families')->total();

    expect($stats['racines'])->toBe(2);
    expect($stats['racines'])->toBe($affichees);
    // L'archivée n'est pas comptée, mais elle est ANNONCÉE.
    expect($stats['archivees'])->toBe(1);
});

it('compte tout quand la liste montre tout', function () {
    $vue = $this->actingAs(famUser())
        ->get(route('product-families.index', ['statut' => 'toutes']))->assertOk();

    $stats = $vue->viewData('stats');

    expect($stats['racines'])->toBe(3);
    expect($stats['racines'])->toBe($vue->viewData('families')->total());
    // Plus rien n'est masqué : il n'y a plus rien à signaler.
    expect($stats['archivees'])->toBe(0);
});

it('propose toutes les familles actives dans le panneau de navigation', function () {
    // Le panneau était plafonné aux 20 dernières créées : au-delà, une famille
    // devenait introuvable, y compris par son champ « Filtrer » — qui ne cherche
    // que dans les lignes déjà chargées.
    for ($i = 0; $i < 25; $i++) {
        ProductFamily::create(['name' => 'Masse '.$i, 'code' => 'MASS-'.$i, 'is_active' => true]);
    }
    $noyee = ProductFamily::where('code', 'FAM-A')->firstOrFail();

    $panneau = $this->actingAs(famUser())
        ->get(route('product-families.create'))->assertOk()
        ->viewData('selectorFamilies');

    expect($panneau->count())->toBe(ProductFamily::where('is_active', true)->count());
    expect($panneau->pluck('id'))->toContain($noyee->id);
});

it('ne propose pas une famille comme son propre parent', function () {
    $f = ProductFamily::where('code', 'FAM-A')->firstOrFail();

    $autre = ProductFamily::where('code', 'FAM-B')->firstOrFail();
    $html = $this->actingAs(famUser())->get(route('product-families.edit', $f))->assertOk()->content();

    // On isole le SEUL select parent : `value="{id}"` apparaît ailleurs dans la
    // page (panneau de navigation), une recherche globale ne prouverait rien.
    preg_match('/<select[^>]*name="parent_id".*?<\/select>/s', $html, $m);
    expect($m)->not->toBeEmpty();
    $select = $m[0];

    // La garde vit dans le formulaire : elle doit rester vraie au rendu, pas
    // seulement dans l'intention du contrôleur.
    expect($select)->not->toContain('value="'.$f->id.'"');
    // Contrôle en miroir : sans lui, un select vide passerait le test.
    expect($select)->toContain('value="'.$autre->id.'"');
});

it('classe un article rattaché à sa seule sous-famille', function () {
    $parent = ProductFamily::where('code', 'FAM-A')->firstOrFail();
    $sous = ProductFamily::create([
        'name' => 'Sous-famille', 'code' => 'FAM-A1',
        'parent_id' => $parent->id, 'is_active' => true,
    ]);

    // Rattachement par le SEUL second axe : ne compter que family_id
    // laisserait cet article hors du total des articles classifiés.
    Product::factory()->create(['family_id' => null, 'sub_family_id' => $sous->id]);

    $stats = $this->actingAs(famUser())
        ->get(route('product-families.index'))->assertOk()->viewData('stats');

    expect($stats['articles'])->toBe(1);
});
