<?php

/**
 * [Référentiel articles] Le type d'article ne reste jamais vide sans raison.
 *
 * L'héritage catégorie → article vivait dans ProductService seul. Tout ce qui
 * crée un article SANS passer par ce service — seeders, fabriques, imports,
 * écriture directe — y échappait. L'article #28, celui que les SIX ordres de
 * fabrication produisent et qui porte la nomenclature 9, avait une catégorie
 * valide et un `type_article` vide. Il s'affichait « — » dans la colonne Type.
 *
 * Le filet est donc posé sur le MODÈLE, pas sur le service : c'est le seul
 * endroit que tous les chemins traversent.
 *
 * Il ne comble QUE le vide. Un type explicitement choisi n'est jamais écrasé,
 * même divergent : une divergence peut être un choix métier, et
 * `a3:audit-article-classification` la signale au lieu que le modèle la corrige
 * en douce.
 *
 * ATTENTION en relisant : « sous_produit » → « produit fini » n'est PAS une
 * incohérence. `nature` classe finement (9 valeurs), `type_article` classe le
 * FLUX (5 valeurs). Une chute entre en stock comme sortie valorisable. J'avais
 * cru y voir un défaut avant de lire la correspondance.
 */

use App\Models\ItemCategory;
use App\Models\Product;
use App\Services\CategoryDefaultsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function catNature(string $nature, string $code = null): ItemCategory
{
    return ItemCategory::create([
        'code' => $code ?: strtoupper($nature).'-'.uniqid(),
        'name' => 'Catégorie '.$nature,
        'nature' => $nature,
    ]);
}

// ── Le filet du modèle ───────────────────────────────────────────────────────

it('déduit le type d’article de la nature de sa catégorie', function () {
    $p = Product::create([
        'name' => 'Article sans type', 'reference' => 'SANS-'.uniqid(),
        'item_category_id' => catNature('matiere_premiere')->id,
    ]);

    expect($p->fresh()->type_article)->toBe('matiere_premiere');
});

it('classe une chute en « produit fini » — c’est sa classification de flux', function () {
    $p = Product::create([
        'name' => 'Chute test', 'reference' => 'CHUTE-'.uniqid(),
        'item_category_id' => catNature('sous_produit')->id,
    ]);

    expect($p->fresh()->type_article)->toBe('produit_fini');
});

it('n’écrase jamais un type explicitement choisi', function () {
    // Même contre-intuitif : décider à la place de l'utilisateur serait pire que
    // de laisser une divergence visible, que l'audit signale.
    $p = Product::create([
        'name' => 'Article choisi', 'reference' => 'CHOISI-'.uniqid(),
        'item_category_id' => catNature('matiere_premiere')->id,
        'type_article' => 'marchandise',
    ]);

    expect($p->fresh()->type_article)->toBe('marchandise');
});

it('ne déduit rien sans catégorie', function () {
    $p = Product::create(['name' => 'Orphelin', 'reference' => 'ORPH-'.uniqid()]);

    expect($p->fresh()->type_article)->toBeNull();
});

it('comble aussi le vide à la mise à jour, pas seulement à la création', function () {
    // Un article dont on rattache la catégorie après coup doit être classé lui aussi.
    $p = Product::create(['name' => 'Tardif', 'reference' => 'TARD-'.uniqid()]);
    expect($p->fresh()->type_article)->toBeNull();

    $p->update(['item_category_id' => catNature('consommable')->id]);

    expect($p->fresh()->type_article)->toBe('consommable');
});

it('partage la correspondance avec le service, sans la recopier', function () {
    // Une seconde copie de cette table dériverait — c'est ce qui a laissé les
    // statuts fournisseurs au féminin mettre deux indicateurs achats à zéro.
    foreach (['matiere_premiere', 'produit_fini', 'sous_produit', 'chute', 'rebut',
              'marchandise', 'consommable', 'service', 'semi_fini'] as $nature) {
        $attendu = CategoryDefaultsService::natureToTypeArticle($nature);

        $p = Product::create([
            'name' => 'N '.$nature, 'reference' => 'N-'.uniqid(),
            'item_category_id' => catNature($nature)->id,
        ]);

        expect($p->fresh()->type_article)->toBe($attendu);
    }
});

// ── La commande d'audit ──────────────────────────────────────────────────────

it('sort en succès quand tous les articles sont classés', function () {
    Product::create([
        'name' => 'Bien classé', 'reference' => 'OK-'.uniqid(),
        'item_category_id' => catNature('produit_fini')->id,
    ]);

    expect(Artisan::call('a3:audit-article-classification'))->toBe(0);
});

it('signale un article dont le type contredit sa catégorie', function () {
    $p = Product::create([
        'name' => 'Divergent', 'reference' => 'DIV-'.uniqid(),
        'item_category_id' => catNature('matiere_premiere')->id,
        'type_article' => 'service',
    ]);

    $code = Artisan::call('a3:audit-article-classification');

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain($p->reference);
});

it('signale un article sans catégorie', function () {
    $p = Product::create(['name' => 'Sans cat', 'reference' => 'NOCAT-'.uniqid()]);

    expect(Artisan::call('a3:audit-article-classification'))->toBe(1)
        ->and(Artisan::output())->toContain($p->reference);
});

it('ne modifie rien — l’audit constate, il ne répare pas', function () {
    $p = Product::create([
        'name' => 'Divergent intact', 'reference' => 'INTACT-'.uniqid(),
        'item_category_id' => catNature('matiere_premiere')->id,
        'type_article' => 'service',
    ]);

    // Comparaison sur les VALEURS, pas sur l'identité des objets : `get()` rend
    // des stdClass, et deux instances distinctes ne sont jamais identiques au
    // sens strict, même à contenu égal.
    $lire = fn () => (array) DB::table('products')->where('id', $p->id)
        ->first(['type_article', 'item_category_id', 'updated_at']);

    $avant = $lire();
    Artisan::call('a3:audit-article-classification');

    expect($lire())->toBe($avant);
});
