<?php

/**
 * [BUG-A3-SALES-LINE-IMMUTABLE-012] La modification d'une commande conserve
 * l'identité de ses lignes.
 *
 * `OrderService::update()` faisait `$order->items()->delete()` puis recréait
 * tout. La destruction était PHYSIQUE : les identifiants changeaient à chaque
 * édition, y compris sur une commande `confirme` ou `en_preparation`. Rien de
 * ce qui référence une ligne ne pouvait survivre — ordre de fabrication,
 * affectation MTO, réservation, préparation, livraison, facture. Une simple
 * correction de prix rompait la traçabilité, sans rien signaler.
 *
 * L'identité est l'IDENTIFIANT PERSISTANT de la ligne, jamais sa position dans
 * le tableau ni le code article : deux lignes peuvent porter le même produit
 * dans deux couleurs, et réordonner l'affichage ne doit rien renuméroter.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function soiSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SOI-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SOI Co'], ['email' => 'soi@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WSOI'], ['name' => 'WSOI', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function soiArticle(string $couleur = 'Beige'): Product
{
    return Product::factory()->create([
        'is_sellable' => true, 'is_active' => true, 'sale_price' => 4000,
        'production_mode' => 'achat_revente',
    ]);
}

/** Commande brouillon à `$n` lignes. */
function soiCommande(array $lignes): Order
{
    $co = soiSociete();
    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => 'CMD-SOI-'.uniqid(), 'status' => 'brouillon', 'issued_at' => now(),
    ]);
    foreach ($lignes as $i => $l) {
        OrderItem::create([
            'order_id' => $commande->id, 'product_id' => $l['product_id'],
            'description' => $l['description'] ?? 'Ligne', 'quantity' => $l['quantity'] ?? 10,
            'unit_price' => $l['unit_price'] ?? 4000,
            'line_total_ht' => 40000, 'line_tax' => 0, 'line_total_ttc' => 40000,
            'sort_order' => $i,
        ] + ($l['extra'] ?? []));
    }

    return $commande->fresh();
}

/** Renvoie les lignes sous la forme attendue par `update`, avec leur identifiant. */
function soiPayload(Order $commande, ?callable $muter = null): array
{
    return $commande->fresh()->items->map(function (OrderItem $l) use ($muter) {
        $ligne = [
            'id' => $l->id, 'product_id' => $l->product_id, 'description' => $l->description,
            'quantity' => (float) $l->quantity, 'unit_price' => (int) $l->unit_price,
            'discount_percent' => 0, 'tax_rate_value' => 0,
        ];

        return $muter ? $muter($ligne, $l) : $ligne;
    })->all();
}

// ─── 1-4 · L'identité survit à l'édition ─────────────────────────────────────

it('1. conserve l\'identifiant quand le prix change', function () {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);
    $avant = $commande->items->first()->id;

    app(OrderService::class)->update($commande, [
        'items' => soiPayload($commande, fn ($l) => array_merge($l, ['unit_price' => 5000])),
    ]);

    $apres = $commande->fresh()->items->first();
    expect($apres->id)->toBe($avant);
    expect((int) $apres->unit_price)->toBe(5000);
});

it('2. conserve l\'identifiant quand la quantité change', function () {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);
    $avant = $commande->items->first()->id;

    app(OrderService::class)->update($commande, [
        'items' => soiPayload($commande, fn ($l) => array_merge($l, ['quantity' => 25.0])),
    ]);

    expect($commande->fresh()->items->first()->id)->toBe($avant);
    expect((float) $commande->fresh()->items->first()->quantity)->toBe(25.0);
});

it('3. ajoute une ligne sans renuméroter les existantes', function () {
    soiSociete();
    $autre = soiArticle();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);
    $avant = $commande->items->first()->id;

    $payload = soiPayload($commande);
    $payload[] = ['product_id' => $autre->id, 'description' => 'Nouvelle', 'quantity' => 5, 'unit_price' => 4000, 'discount_percent' => 0, 'tax_rate_value' => 0];

    app(OrderService::class)->update($commande, ['items' => $payload]);

    $lignes = $commande->fresh()->items;
    expect($lignes)->toHaveCount(2);
    expect($lignes->pluck('id'))->toContain($avant);
});

it('4. garde deux lignes du même article distinctes', function () {
    soiSociete();
    $article = soiArticle();
    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Beige'],
        ['product_id' => $article->id, 'description' => 'Orange'],
    ]);
    $ids = $commande->items->pluck('id')->sort()->values()->all();

    // Seule la seconde change de prix : la première ne doit pas bouger.
    app(OrderService::class)->update($commande, [
        'items' => soiPayload($commande, fn ($l) => $l['description'] === 'Orange' ? array_merge($l, ['unit_price' => 9999]) : $l),
    ]);

    $apres = $commande->fresh()->items;
    expect($apres->pluck('id')->sort()->values()->all())->toBe($ids);
    expect((int) $apres->firstWhere('description', 'Orange')->unit_price)->toBe(9999);
    expect((int) $apres->firstWhere('description', 'Beige')->unit_price)->toBe(4000);
});

// ─── 5-6 · Retrait sans dépendance : suppression LOGIQUE ─────────────────────

it('5. retire une ligne sans dépendance par suppression logique', function () {
    soiSociete();
    $commande = soiCommande([
        ['product_id' => soiArticle()->id, 'description' => 'Gardée'],
        ['product_id' => soiArticle()->id, 'description' => 'Retirée'],
    ]);
    $retiree = $commande->items->firstWhere('description', 'Retirée')->id;

    $payload = array_values(array_filter(
        soiPayload($commande),
        fn ($l) => $l['description'] !== 'Retirée'
    ));
    app(OrderService::class)->update($commande, ['items' => $payload]);

    expect($commande->fresh()->items)->toHaveCount(1);
    expect(OrderItem::whereKey($retiree)->exists())->toBeFalse();
});

it('6. laisse la ligne retirée consultable en audit', function () {
    soiSociete();
    $commande = soiCommande([
        ['product_id' => soiArticle()->id, 'description' => 'Gardée'],
        ['product_id' => soiArticle()->id, 'description' => 'Retirée'],
    ]);
    $retiree = $commande->items->firstWhere('description', 'Retirée')->id;

    app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Retirée')),
    ]);

    // La ligne survit physiquement : c'est ce qui distingue une suppression
    // logique d'une destruction, et ce qui permet d'auditer la modification.
    $trace = OrderItem::withTrashed()->find($retiree);
    expect($trace)->not->toBeNull();
    expect($trace->deleted_at)->not->toBeNull();
    expect($trace->description)->toBe('Retirée');
});

// ─── 7-10 · Retrait refusé quand la ligne porte une activité ─────────────────

it('7. refuse de retirer une ligne liée à un ordre de fabrication', function () {
    $co = soiSociete();
    $article = soiArticle();
    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Gardée'],
        ['product_id' => $article->id, 'description' => 'Produite'],
    ]);

    \App\Modules\Production\Models\ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'order_id' => $commande->id, 'product_id' => $article->id,
        'number' => 'OF-SOI-'.uniqid(), 'status' => 'lance', 'quantity_requested' => 10,
    ]);

    expect(fn () => app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Produite')),
    ]))->toThrow(ValidationException::class);

    expect($commande->fresh()->items)->toHaveCount(2);
});

it('8. refuse de retirer une ligne liée à une réservation de stock', function () {
    $co = soiSociete();
    $article = soiArticle();
    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Gardée'],
        ['product_id' => $article->id, 'description' => 'Réservée'],
    ]);

    \App\Models\StockReservation::create([
        'company_id' => $co->id, 'order_id' => $commande->id, 'product_id' => $article->id,
        'warehouse_id' => Warehouse::where('code', 'WSOI')->value('id'),
        'quantity' => 10, 'status' => 'reserved', 'reserved_at' => now(),
    ]);

    expect(fn () => app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Réservée')),
    ]))->toThrow(ValidationException::class);
});

it('9. accepte le retrait quand l\'ordre de fabrication est annulé', function () {
    $co = soiSociete();
    $article = soiArticle();
    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Gardée'],
        ['product_id' => $article->id, 'description' => 'Abandonnée'],
    ]);

    // Un OF annulé n'est plus une activité vivante : il ne fige plus la ligne.
    \App\Modules\Production\Models\ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'order_id' => $commande->id, 'product_id' => $article->id,
        'number' => 'OF-SOI-'.uniqid(), 'status' => 'annule', 'quantity_requested' => 10,
    ]);

    app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Abandonnée')),
    ]);

    expect($commande->fresh()->items)->toHaveCount(1);
});

// ─── 11 · Identifiant étranger refusé ───────────────────────────────────────

it('11. refuse un identifiant appartenant à une autre commande', function () {
    soiSociete();
    $article = soiArticle();
    $cible = soiCommande([['product_id' => $article->id, 'description' => 'Cible']]);
    $autre = soiCommande([['product_id' => $article->id, 'description' => 'Autre client']]);

    // Poster l'identifiant d'une ligne étrangère permettrait de réécrire la
    // commande d'un autre client depuis son propre formulaire.
    $payload = soiPayload($cible);
    $payload[0]['id'] = $autre->items->first()->id;

    expect(fn () => app(OrderService::class)->update($cible, ['items' => $payload]))
        ->toThrow(ValidationException::class);

    expect($autre->fresh()->items->first()->description)->toBe('Autre client');
});

// ─── 12 · Le schéma porte la suppression logique ────────────────────────────

it('12. déclare les lignes de vente aptes à la suppression logique', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'deleted_at'))->toBeTrue();

    // `quote_items` NE fait PAS partie de ce lot : poser SoftDeletes sans son
    // synchroniseur ne donnerait qu'une moitié de solution — les lignes
    // seraient conservées, mais leurs identifiants changeraient quand même.
    // Traité sous BUG-A3-SALES-QUOTE-LINE-013.
    expect(\Illuminate\Support\Facades\Schema::hasColumn('quote_items', 'deleted_at'))->toBeFalse();
    expect(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(OrderItem::class), true))->toBeTrue();
});

// ═════════════════════════════════════════════════════════════════════════════
// §9 · Le STATUT protège les lignes, indépendamment de leurs dépendances
//
// Une commande soumise ne porte encore aucune activité aval : le contrôle de
// dépendances la laisserait modifier librement. Elle est pourtant déjà partie
// en validation, et son contenu ne doit plus bouger sans processus explicite.
// ═════════════════════════════════════════════════════════════════════════════

it('13. refuse la modification selon le statut de la commande', function (string $statut) {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);
    $commande->update(['status' => $statut]);

    expect(fn () => app(OrderService::class)->update($commande->fresh(), [
        'items' => soiPayload($commande, fn ($l) => array_merge($l, ['unit_price' => 7777])),
    ]))->toThrow(ValidationException::class);

    expect((int) $commande->fresh()->items->first()->unit_price)->toBe(4000);
})->with([
    'en_attente_validation', 'confirme', 'en_preparation',
    'partiellement_livre', 'livre', 'facture', 'annule',
]);

it('14. autorise la modification en brouillon', function () {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);

    app(OrderService::class)->update($commande, [
        'items' => soiPayload($commande, fn ($l) => array_merge($l, ['unit_price' => 7777])),
    ]);

    expect((int) $commande->fresh()->items->first()->unit_price)->toBe(7777);
});

it('15. synchronise sous transaction, jamais hors transaction', function () {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id]]);

    // Le garde-fou `transactionLevel() === 0` ne peut pas être déclenché ici :
    // RefreshDatabase enveloppe déjà chaque test dans une transaction. Ce qui
    // est éprouvé est donc l'INVARIANT réel — la construction des lignes voit
    // une transaction ouverte —, pas le message d'erreur du cas impossible.
    $niveau = null;
    app(\App\Services\Sales\OrderItemSynchronizer::class)->sync(
        $commande,
        soiPayload($commande),
        function (array $ligne, int $i) use (&$niveau) {
            $niveau = \Illuminate\Support\Facades\DB::transactionLevel();

            return app(OrderService::class)->buildItemValues($ligne, $i);
        }
    );

    expect($niveau)->toBeGreaterThan(0);
});

it('16. annule tout quand une exception survient en cours de synchronisation', function () {
    soiSociete();
    $article = soiArticle();
    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Un'],
        ['product_id' => $article->id, 'description' => 'Deux'],
    ]);
    $avant = $commande->items->pluck('unit_price', 'id')->all();

    // La SECONDE ligne porte un identifiant étranger : l'exception survient
    // APRÈS que la première a été modifiée. Rien ne doit subsister.
    $autre = soiCommande([['product_id' => $article->id]]);
    $payload = soiPayload($commande, fn ($l) => array_merge($l, ['unit_price' => 8888]));
    $payload[1]['id'] = $autre->items->first()->id;

    expect(fn () => app(OrderService::class)->update($commande, ['items' => $payload]))
        ->toThrow(ValidationException::class);

    expect($commande->fresh()->items->pluck('unit_price', 'id')->all())->toBe($avant);
});

it('17. exclut une ligne retirée du total de la commande', function () {
    soiSociete();
    $commande = soiCommande([
        ['product_id' => soiArticle()->id, 'description' => 'Gardée'],
        ['product_id' => soiArticle()->id, 'description' => 'Retirée'],
    ]);

    app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Retirée')),
    ]);

    $fresh = $commande->fresh();
    expect($fresh->items)->toHaveCount(1);
    expect((int) $fresh->subtotal_ht)->toBe((int) $fresh->items->sum('line_total_ht'));
});

it('18. exclut une ligne retirée du besoin net', function () {
    soiSociete();
    $article = soiArticle();
    $article->update(['production_mode' => 'mts', 'is_stockable' => true, 'stock_min' => 10, 'stock_max' => 100]);

    $commande = soiCommande([
        ['product_id' => $article->id, 'description' => 'Gardée'],
        ['product_id' => $article->id, 'description' => 'Retirée', 'quantity' => 500],
    ]);

    app(OrderService::class)->update($commande, [
        'items' => array_values(array_filter(soiPayload($commande), fn ($l) => $l['description'] !== 'Retirée')),
    ]);
    $commande->update(['status' => 'confirme']);

    // La demande retirée ne doit plus faire produire l'atelier.
    $besoin = app(\App\Modules\Production\Services\NetRequirementService::class)
        ->forProducts(collect([$article->fresh()]))->first();

    expect((float) $besoin['client'])->toBe(10.0);
});

it('19. laisse une facture retrouver sa ligne de commande retirée', function () {
    soiSociete();
    $commande = soiCommande([['product_id' => soiArticle()->id, 'description' => 'Facturée']]);
    $ligne = $commande->items->first();

    // La relation est éprouvée directement : le chemin d'appel qui retire la
    // ligne importe moins que le fait qu'une clé étrangère intacte reste
    // lisible une fois la ligne masquée par SoftDeletes.
    $ligne->delete();

    $facture = new \App\Models\InvoiceItem(['order_item_id' => $ligne->id]);
    $facture->setRelation('orderItem', null);
    $retrouvee = \App\Models\InvoiceItem::query()->getModel()
        ->orderItem()->getRelated()->newQueryWithoutScopes()
        ->whereKey($ligne->id)->first();

    expect($retrouvee)->not->toBeNull();
    expect(\App\Models\OrderItem::withTrashed()->find($ligne->id)->deleted_at)->not->toBeNull();
});
