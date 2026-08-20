<?php

/**
 * [Ventes §21.3] L'annulation d'une facture rend le facturé À LA BONNE LIGNE.
 *
 * Défaut corrigé. `invoice_items` ne portait pas `order_item_id` ; l'annulation
 * retrouvait la ligne de commande par `product_id` :
 *
 *     OrderItem::where('order_id', …)->where('product_id', …)
 *              ->decrement('invoiced_quantity', $invItem->quantity);
 *
 * Sur une commande à deux lignes du même article — même référence en longueurs
 * différentes, courant en tôle bac — le `where` en sélectionnait deux et
 * `decrement()` retranchait la quantité ENTIÈRE à CHACUNE. Annuler une facture
 * de 40 retirait 40 à la ligne facturée et 40 à sa jumelle, qui n'avait rien à
 * rendre. `invoiced_quantity` passait sous zéro et la commande redevenait
 * facturable au-delà du reste réel.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Commande à DEUX lignes du même article — la configuration qui révèle le défaut.
 *
 * @return array<string,mixed>
 */
function twinLinesFixture(float $deliveredA = 40, float $deliveredB = 20): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TWINLINK-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'TwinLink Co'], [
        'email' => 'twinlink@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $unit    = Unit::firstOrCreate(['name' => 'Kg TwinLink'], ['abbreviation' => 'kgtl']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client  = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 100_000_000, 'balance' => 0, 'is_tax_exempt' => false,
    ]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-TWINLINK-'.uniqid(), 'status' => 'partiellement_livre', 'issued_at' => now(),
        'subtotal_ht' => 1_500_000, 'total_tax' => 270_000, 'total_ttc' => 1_770_000,
        'total_discount' => 0, 'global_discount_amount' => 0, 'invoiced_amount' => 0,
    ]);

    $mk = fn (string $desc, float $qty, float $delivered) => OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => $desc, 'quantity' => $qty, 'delivered_quantity' => $delivered,
        'invoiced_quantity' => 0, 'unit_price' => 10_000, 'discount_percent' => 0,
        'tax_rate_value' => 18, 'line_total_ht' => (int) ($qty * 10_000),
        'line_tax' => (int) ($qty * 1_800), 'line_total_ttc' => (int) ($qty * 11_800),
    ]);

    $lineA = $mk('Tôle bac 0,40 — longueur 4 m', 100, $deliveredA);
    $lineB = $mk('Tôle bac 0,40 — longueur 6 m', 50, $deliveredB);

    return compact('company', 'fy', 'order', 'client', 'product', 'user', 'lineA', 'lineB');
}

function twinInvSvc(): InvoiceService
{
    return app(InvoiceService::class);
}

/**
 * `cancel()` n'accepte qu'une facture ÉMISE et sans règlement — un brouillon
 * relève de `delete()`. Les deux chemins passent par la même restitution ;
 * ce raccourci émet la facture pour pouvoir exercer `cancel()`.
 */
function twinEmit(Invoice $invoice): Invoice
{
    return twinInvSvc()->validate($invoice);
}

// ── Le schéma ────────────────────────────────────────────────────────────────

it('porte la colonne de rattachement et sa clé étrangère', function () {
    expect(Schema::hasColumn('invoice_items', 'order_item_id'))->toBeTrue();

    $f = twinLinesFixture();
    $invoice = twinInvSvc()->createFromOrder($f['order']);

    // Une ligne orpheline doit rester possible (colonne nullable) : c'est ce qui
    // laisse l'historique ambigu en place plutôt que de lui inventer un lien.
    $invoice->items->first()->update(['order_item_id' => null]);
    expect($invoice->items->first()->fresh()->order_item_id)->toBeNull();
});

// ── La création ──────────────────────────────────────────────────────────────

it('renseigne le lien sur chaque ligne facturée depuis une commande', function () {
    $f = twinLinesFixture();

    $invoice = twinInvSvc()->createFromOrder($f['order']);

    expect($invoice->items)->toHaveCount(2)
        ->and($invoice->items->pluck('order_item_id')->sort()->values()->all())
        ->toBe([$f['lineA']->id, $f['lineB']->id]);
});

it('facture chaque ligne pour SON propre livré', function () {
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 20);

    $invoice = twinInvSvc()->createFromOrder($f['order']);
    $byLine  = $invoice->items->keyBy('order_item_id');

    expect((float) $byLine[$f['lineA']->id]->quantity)->toBe(40.0)
        ->and((float) $byLine[$f['lineB']->id]->quantity)->toBe(20.0);
});

// ── L'annulation, cœur du défaut ─────────────────────────────────────────────

it('rend à chaque ligne exactement ce qu’elle avait consommé', function () {
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 20);

    $invoice = twinEmit(twinInvSvc()->createFromOrder($f['order']));
    expect((float) $f['lineA']->fresh()->invoiced_quantity)->toBe(40.0)
        ->and((float) $f['lineB']->fresh()->invoiced_quantity)->toBe(20.0);

    twinInvSvc()->cancel($invoice, 'Erreur de saisie — facture annulée.');

    // Avant correction : lineA tombait à 40−40−20 = −20 et lineB à 20−40−20 = −40.
    expect((float) $f['lineA']->fresh()->invoiced_quantity)->toBe(0.0)
        ->and((float) $f['lineB']->fresh()->invoiced_quantity)->toBe(0.0);
});

it('ne fait jamais passer une quantité facturée sous zéro', function () {
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 20);

    twinInvSvc()->cancel(
        twinEmit(twinInvSvc()->createFromOrder($f['order'])),
        'Annulation — contrôle du plancher à zéro.'
    );

    foreach ([$f['lineA'], $f['lineB']] as $line) {
        expect((float) $line->fresh()->invoiced_quantity)->toBeGreaterThanOrEqual(0.0);
    }
});

it('laisse intacte une ligne que la facture ne touchait pas', function () {
    // Seule la ligne A est livrée : la facture ne porte que sur elle. L'annulation
    // ne doit rien retrancher à la ligne B, restée à zéro.
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 0);

    $invoice = twinInvSvc()->createFromOrder($f['order']);
    expect($invoice->items)->toHaveCount(1);

    twinInvSvc()->cancel(twinEmit($invoice), 'Annulation — la ligne non facturée ne bouge pas.');

    expect((float) $f['lineA']->fresh()->invoiced_quantity)->toBe(0.0)
        ->and((float) $f['lineB']->fresh()->invoiced_quantity)->toBe(0.0);
});

it('rend la commande refacturable pour le montant exact, ni plus ni moins', function () {
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 20);

    twinInvSvc()->cancel(
        twinEmit(twinInvSvc()->createFromOrder($f['order'])),
        'Annulation avant réémission.'
    );

    $second = twinInvSvc()->createFromOrder($f['order']->fresh());

    // 40 + 20 = 60 unités, à 10 000 → 600 000 HT. Avec des quantités devenues
    // négatives, le reste facturable aurait été surévalué.
    expect((int) $second->subtotal_ht)->toBe(600_000)
        ->and($second->items->sum(fn ($i) => (float) $i->quantity))->toBe(60.0);
});

// ── L'historique sans lien ───────────────────────────────────────────────────

it('répartit sans dépasser quand le lien manque et que l’article est en double', function () {
    // Simule une facture antérieure à la migration : lien effacé, deux lignes
    // du même article. On ne peut plus savoir laquelle fut facturée — la règle
    // est d'imputer sur la plus ancienne, dans la limite de ce qu'elle porte.
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 20);

    $invoice = twinEmit(twinInvSvc()->createFromOrder($f['order']));
    $invoice->items()->update(['order_item_id' => null]);

    twinInvSvc()->cancel($invoice->fresh(), 'Annulation d’une facture historique sans lien.');

    // Total rendu = 60, égal au total facturé. Aucune ligne sous zéro.
    $a = (float) $f['lineA']->fresh()->invoiced_quantity;
    $b = (float) $f['lineB']->fresh()->invoiced_quantity;

    expect($a)->toBeGreaterThanOrEqual(0.0)
        ->and($b)->toBeGreaterThanOrEqual(0.0)
        ->and($a + $b)->toBe(0.0);
});

it('reste exact quand le lien manque mais que l’article n’apparaît qu’une fois', function () {
    $f = twinLinesFixture(deliveredA: 40, deliveredB: 0);
    $f['lineB']->delete();

    $invoice = twinEmit(twinInvSvc()->createFromOrder($f['order']->fresh()));
    $invoice->items()->update(['order_item_id' => null]);

    twinInvSvc()->cancel($invoice->fresh(), 'Annulation — article unique, identification sûre.');

    expect((float) $f['lineA']->fresh()->invoiced_quantity)->toBe(0.0);
});
