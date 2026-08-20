<?php

/**
 * [Ventes §21.2] La facturation est LIMITÉE AUX QUANTITÉS LIVRÉES.
 *
 * `createFromOrder()` facturait la quantité COMMANDÉE sans jamais lire
 * `delivered_quantity`. Le bouton « Créer une facture depuis cette commande »
 * étant affiché aux statuts `en_preparation` et `partiellement_livre`, une
 * commande de 100 livrée à 40 produisait une facture de 100.
 *
 * L'enjeu n'est pas ergonomique. En SYSCOHADA le produit se constate au
 * transfert de propriété : facturer du non-livré crée un compte 70 anticipé,
 * une TVA collectée exigible sur des biens non livrés et une créance
 * surévaluée.
 *
 * Aucune dérogation n'est prévue : un acompte se comptabilise en 419 et passe
 * par `client_payments.is_acompte`, pas par une facture de vente.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function invDelivFixture(float $ordered = 100, float $delivered = 0, int $unitPrice = 10_000, float $taxRate = 18): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'INVDEL-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'InvDel Co'], [
        'email' => 'invdel@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $unit = Unit::firstOrCreate(['name' => 'Kg InvDel'], ['abbreviation' => 'kgi']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => 100_000_000, 'balance' => 0, 'is_tax_exempt' => false,
    ]);

    $ht = (int) round($ordered * $unitPrice);
    $tax = (int) round($ht * $taxRate / 100);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-INVDEL-'.uniqid(), 'status' => 'partiellement_livre', 'issued_at' => now(),
        'subtotal_ht' => $ht, 'total_tax' => $tax, 'total_ttc' => $ht + $tax,
        'total_discount' => 0, 'global_discount_amount' => 0, 'invoiced_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => 'Tôle bac 0,40', 'quantity' => $ordered,
        'delivered_quantity' => $delivered, 'invoiced_quantity' => 0,
        'unit_price' => $unitPrice, 'discount_percent' => 0, 'tax_rate_value' => $taxRate,
        'line_total_ht' => $ht, 'line_tax' => $tax, 'line_total_ttc' => $ht + $tax,
    ]);

    return compact('company', 'fy', 'order', 'orderItem', 'client', 'product', 'user');
}

function invService(): InvoiceService
{
    return app(InvoiceService::class);
}

// ---------------------------------------------------------------------------

it('facture le LIVRÉ, pas le commandé, sur une commande partiellement livrée', function () {
    // Commandé 100, livré 40 : la facture doit porter sur 40.
    $f = invDelivFixture(ordered: 100, delivered: 40);

    $invoice = invService()->createFromOrder($f['order']);

    expect($invoice->items)->toHaveCount(1)
        ->and((float) $invoice->items->first()->quantity)->toBe(40.0)
        // 40 × 10 000 = 400 000 HT, et non 1 000 000.
        ->and((int) $invoice->items->first()->line_total_ht)->toBe(400_000)
        ->and((int) $invoice->subtotal_ht)->toBe(400_000)
        // TVA calculée sur le livré : 400 000 × 18 % = 72 000.
        ->and((int) $invoice->total_tax)->toBe(72_000)
        ->and((int) $invoice->total_ttc)->toBe(472_000);
});

it('refuse toute facture quand rien n est livré', function () {
    $f = invDelivFixture(ordered: 100, delivered: 0);

    expect(fn () => invService()->createFromOrder($f['order']))
        ->toThrow(RuntimeException::class, 'rien à facturer');

    expect(App\Models\Invoice::count())->toBe(0);
});

it('le message de refus oriente vers l acompte, pas vers un contournement', function () {
    $f = invDelivFixture(ordered: 50, delivered: 0);

    try {
        invService()->createFromOrder($f['order']);
        $this->fail('La facture aurait dû être refusée.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('LIVRÉES')
            ->and($e->getMessage())->toContain('acompte');
    }
});

it('incrémente invoiced_quantity du facturé réel, jamais du commandé', function () {
    $f = invDelivFixture(ordered: 100, delivered: 40);

    invService()->createFromOrder($f['order']);

    // 40 et non 100 : sinon le reliquat à facturer deviendrait négatif.
    expect((float) $f['orderItem']->fresh()->invoiced_quantity)->toBe(40.0);
});

it('laisse la commande hors du statut « facturé » tant qu il reste à facturer', function () {
    $f = invDelivFixture(ordered: 100, delivered: 40);

    invService()->createFromOrder($f['order']);

    // Marquer « facturé » une commande dont il reste 60 à livrer masquerait
    // le reliquat dans toutes les listes et tous les indicateurs.
    expect($f['order']->fresh()->status)->toBe('partiellement_livre');
});

it('passe la commande à « facturé » quand tout le commandé est livré et facturé', function () {
    $f = invDelivFixture(ordered: 100, delivered: 100);

    invService()->createFromOrder($f['order']);

    expect($f['order']->fresh()->status)->toBe('facture')
        ->and((float) $f['orderItem']->fresh()->invoiced_quantity)->toBe(100.0);
});

it('ne refacture pas ce qui est déjà facturé', function () {
    $f = invDelivFixture(ordered: 100, delivered: 40);
    invService()->createFromOrder($f['order']);

    // Une deuxième facture sur la même commande est refusée par la garde
    // anti-double-facturation, avant même le calcul des quantités.
    expect(fn () => invService()->createFromOrder($f['order']->fresh()))
        ->toThrow(RuntimeException::class, 'existe déjà');
});

it('facture le complément après une livraison supplémentaire', function () {
    $f = invDelivFixture(ordered: 100, delivered: 40);
    $first = invService()->createFromOrder($f['order']);
    expect((float) $first->items->first()->quantity)->toBe(40.0);

    // La première facture est annulée, 30 unités de plus sont livrées.
    $first->update(['status' => 'annulee']);
    $f['orderItem']->update(['delivered_quantity' => 70]);

    $second = invService()->createFromOrder($f['order']->fresh());

    // Reste facturable = 70 livrés − 40 déjà facturés = 30.
    expect((float) $second->items->first()->quantity)->toBe(30.0)
        ->and((int) $second->subtotal_ht)->toBe(300_000)
        ->and((float) $f['orderItem']->fresh()->invoiced_quantity)->toBe(70.0);
});

it('exclut les lignes sans rien de livrable et conserve les autres', function () {
    $f = invDelivFixture(ordered: 100, delivered: 60);

    // Deuxième ligne : commandée mais pas livrée du tout.
    $second = OrderItem::create([
        'order_id' => $f['order']->id, 'product_id' => $f['product']->id,
        'description' => 'Fer à béton 12', 'quantity' => 50,
        'delivered_quantity' => 0, 'invoiced_quantity' => 0,
        'unit_price' => 5_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        'line_total_ht' => 250_000, 'line_tax' => 45_000, 'line_total_ttc' => 295_000,
    ]);

    $invoice = invService()->createFromOrder($f['order']->fresh());

    // Une seule ligne facturée : celle qui a du livré.
    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->description)->toBe('Tôle bac 0,40')
        ->and((float) $invoice->items->first()->quantity)->toBe(60.0)
        ->and((float) $second->fresh()->invoiced_quantity)->toBe(0.0);
});

it('applique la remise de ligne sur la quantité livrée', function () {
    $f = invDelivFixture(ordered: 100, delivered: 40);
    $f['orderItem']->update(['discount_percent' => 10]);

    $invoice = invService()->createFromOrder($f['order']->fresh());

    // 40 × 10 000 × 0,9 = 360 000 HT.
    expect((int) $invoice->items->first()->line_total_ht)->toBe(360_000)
        ->and((int) $invoice->items->first()->line_tax)->toBe(64_800);
});

it('rattache chaque ligne de facture à SA ligne de commande, pas au produit', function () {
    // [Ventes §21.3] Corrigé. Ce test figeait auparavant le défaut : faute de
    // colonne `order_item_id`, deux lignes de commande portant le MÊME article —
    // cas courant sur tôle bac, même référence en longueurs différentes —
    // étaient indistinguables à l'annulation d'une facture.
    expect(\Illuminate\Support\Facades\Schema::hasColumn('invoice_items', 'order_item_id'))->toBeTrue();

    $f = invDelivFixture(ordered: 100, delivered: 40);
    $twin = OrderItem::create([
        'order_id' => $f['order']->id, 'product_id' => $f['product']->id,
        'description' => 'Tôle bac 0,40 — longueur 6 m', 'quantity' => 20,
        'delivered_quantity' => 20, 'invoiced_quantity' => 0,
        'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        'line_total_ht' => 200_000, 'line_tax' => 36_000, 'line_total_ttc' => 236_000,
    ]);

    $invoice = invService()->createFromOrder($f['order']->fresh());

    // Les deux lignes sont facturées séparément et pour leur propre livré.
    expect($invoice->items)->toHaveCount(2)
        ->and((float) $f['orderItem']->fresh()->invoiced_quantity)->toBe(40.0)
        ->and((float) $twin->fresh()->invoiced_quantity)->toBe(20.0);

    // Chaque ligne de facture désigne sa ligne de commande d'origine.
    $byOrderItem = $invoice->items->keyBy('order_item_id');
    expect($byOrderItem->keys()->sort()->values()->all())
        ->toBe([$f['orderItem']->id, $twin->id])
        ->and((float) $byOrderItem[$f['orderItem']->id]->quantity)->toBe(40.0)
        ->and((float) $byOrderItem[$twin->id]->quantity)->toBe(20.0);
});
