<?php

/**
 * [D2] Un article vendable doit porter une stratégie d'approvisionnement.
 *
 * Règle : `production_mode` absent ou inconnu ⇒ opération REFUSÉE, dès la ligne
 * de devis. Fail-closed — jamais de repli silencieux sur MTS ni sur MTO.
 *
 * CAUSE RACINE, et ce qui dicte la forme de la garde. Le vocabulaire structuré
 * existe déjà et couvre tous les cas :
 *
 *     item_categories.strategy = ENUM('mto','mts','achat_revente','service','conso_interne')
 *
 * Mais `CategoryDefaultsService:33` ne recopie sur l'article que 'mto' et 'mts',
 * et écrase le reste à NULL :
 *
 *     'production_mode' => in_array($cat->strategy, ['mto','mts'], true) ? $cat->strategy : null
 *
 * D'où 19 articles à NULL en base : la catégorie connaît la stratégie, l'article
 * l'oublie. `products.production_mode` est par ailleurs un `varchar(3)` — il ne
 * peut PAS contenir 'achat_revente' ni 'service'.
 *
 * La garde résout donc la stratégie en deux temps — article, puis catégorie —
 * plutôt que d'exiger une valeur que la colonne ne peut pas stocker. Un article
 * de revente correctement catégorisé passe ; un article sans stratégie nulle
 * part est refusé.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuoteService;
use App\Services\Sales\FulfillmentStrategyResolver;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function fsgAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'FSG-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'FSG Co'], ['email' => 'fsg@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

/** Catégorie portant une stratégie donnée (ou aucune). */
function fsgCategorie(string $code, ?string $strategy): ItemCategory
{
    return ItemCategory::firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'strategy' => $strategy, 'is_sellable' => true, 'is_stockable' => true]
    );
}

function fsgArticle(?string $mode, ?ItemCategory $categorie = null, bool $vendable = true): Product
{
    return Product::factory()->create([
        'production_mode'  => $mode,
        'item_category_id' => $categorie?->id,
        'is_sellable'      => $vendable,
        'is_active'        => true,
        'sale_price'       => 4000,
    ]);
}

function fsgDevis(Product $produit, int $quantite = 10)
{
    $unit = Unit::firstOrCreate(['name' => 'Unité FSG'], ['abbreviation' => 'ufsg']);
    $tva  = TaxRate::firstOrCreate(['name' => 'TVA 18 FSG'], ['short_name' => 'TVA18F', 'rate' => 18, 'is_active' => true]);

    return app(QuoteService::class)->create([
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $produit->id, 'description' => $produit->name,
            'quantity' => $quantite, 'unit_price' => 4000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);
}

// ─── 1-2 · Refus à la saisie ─────────────────────────────────────────────────

it('1. refuse d\'ajouter au devis un article vendable sans stratégie', function () {
    fsgAdmin();
    // Ni mode sur l'article, ni stratégie sur la catégorie : rien nulle part.
    $produit = fsgArticle(null, fsgCategorie('SANS_STRATEGIE', null));

    expect(fn () => fsgDevis($produit))->toThrow(ValidationException::class);
});

it('2. refuse un mode de production inconnu', function () {
    fsgAdmin();
    // `production_mode` est un varchar : rien n'empêche une valeur hors liste
    // d'y entrer par import ou reprise de données.
    $produit = fsgArticle('xyz', fsgCategorie('CAT_XYZ', null));

    expect(fn () => fsgDevis($produit))->toThrow(ValidationException::class);
});

// ─── 3-5 · Défense en profondeur sur les étapes suivantes ────────────────────

it('3. refuse la soumission d\'un devis historique portant un article sans stratégie', function () {
    fsgAdmin();
    $produit = fsgArticle('mto', fsgCategorie('CAT_MTO_D2', 'mto'));
    $devis = fsgDevis($produit);

    // L'article devient incomplet APRÈS coup — reprise de données, changement de
    // catégorie. Le devis existe déjà : sa consultation reste permise, sa
    // soumission ne l'est plus.
    $produit->update(['production_mode' => null, 'item_category_id' => fsgCategorie('SANS_STRATEGIE', null)->id]);

    expect(fn () => app(\App\Services\CommercialWorkflowService::class)->submit($devis->fresh()))
        ->toThrow(ValidationException::class);
});

it('4. refuse la conversion en commande d\'un devis portant un article sans stratégie', function () {
    fsgAdmin();
    $produit = fsgArticle('mto', fsgCategorie('CAT_MTO_D2', 'mto'));
    $devis = fsgDevis($produit);
    $devis->update(['status' => 'valide']);

    $produit->update(['production_mode' => null, 'item_category_id' => fsgCategorie('SANS_STRATEGIE', null)->id]);

    expect(fn () => app(QuoteService::class)->convertToOrder($devis->fresh()))
        ->toThrow(ValidationException::class);
});

it('5. refuse la confirmation d\'une commande portant un article sans stratégie', function () {
    fsgAdmin();
    $produit = fsgArticle('mto', fsgCategorie('CAT_MTO_D2', 'mto'));
    $devis = fsgDevis($produit);
    $devis->update(['status' => 'valide']);
    $commande = app(QuoteService::class)->convertToOrder($devis->fresh());

    $produit->update(['production_mode' => null, 'item_category_id' => fsgCategorie('SANS_STRATEGIE', null)->id]);

    $wf = app(\App\Services\CommercialWorkflowService::class);
    expect(fn () => $wf->submit($commande->fresh()))->toThrow(ValidationException::class);
});

// ─── 6-7 · Ce que la garde ne doit PAS bloquer ───────────────────────────────

it('6. laisse tranquille une matière première non vendable sans mode', function () {
    fsgAdmin();
    // Une bobine ne se vend pas : l'absence de stratégie commerciale est normale
    // et ne doit déclencher aucun blocage. 13 des 19 articles à NULL sont dans
    // ce cas — les bloquer serait un faux positif de masse.
    $bobine = fsgArticle(null, fsgCategorie('MP_BOBINE_D2', 'achat_revente'), vendable: false);

    expect(app(FulfillmentStrategyResolver::class)->isBlocked($bobine))->toBeFalse();
});

it('7. laisse passer un article de revente correctement catégorisé', function () {
    fsgAdmin();
    // `MDS_TLO_AZC_0.30` en base : marchandise achetée-revendue, vendable,
    // `production_mode` NULL parce que la colonne est un varchar(3) incapable de
    // stocker 'achat_revente'. Sa catégorie, elle, le sait.
    $marchandise = fsgArticle(null, fsgCategorie('MARCHANDISE_D2', 'achat_revente'));

    $strategie = app(FulfillmentStrategyResolver::class)->resolve($marchandise);
    expect($strategie)->toBe('achat_revente');

    $devis = fsgDevis($marchandise);
    expect($devis->items)->toHaveCount(1);
});

it('7bis. résout la stratégie depuis l\'article en priorité, la catégorie en repli', function () {
    fsgAdmin();
    $resolver = app(FulfillmentStrategyResolver::class);

    // L'article prime.
    $mto = fsgArticle('mto', fsgCategorie('CAT_ACHAT_D2', 'achat_revente'));
    expect($resolver->resolve($mto))->toBe('mto');

    // À défaut, la catégorie.
    $achat = fsgArticle(null, fsgCategorie('CAT_ACHAT_D2', 'achat_revente'));
    expect($resolver->resolve($achat))->toBe('achat_revente');

    // Ni l'un ni l'autre : null, et la garde bloque un article vendable.
    $rien = fsgArticle(null, fsgCategorie('SANS_STRATEGIE', null));
    expect($resolver->resolve($rien))->toBeNull();
    expect($resolver->isBlocked($rien))->toBeTrue();
});
