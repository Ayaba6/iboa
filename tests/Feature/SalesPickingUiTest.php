<?php

/**
 * [Ventes §18] Écrans des bons de préparation — parcours HTTP authentifiés.
 *
 * PÉRIMÈTRE DE LA PREUVE : les routes réelles sont appelées en session
 * authentifiée avec de vrais rôles ; les gardes, les transitions et les données
 * affichées sont constatées. Le comportement du NAVIGATEUR n'est pas prouvé :
 * aucune page n'est ouverte, aucune modale n'est cliquée, aucun JavaScript n'est
 * exécuté. Ne pas présenter ces tests comme « parcours navigateur ».
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesPicking;
use App\Models\SalesPickingControl;
use App\Models\StockLot;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\SalesPickingService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function uiFixture(float $orderedQty = 100): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'UIPICK-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Ui Pick Co'], [
        'email' => 'uipick@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    foreach (['bon_preparations.view', 'bon_preparations.update', 'bon_preparations.control', 'bon_preparations.validate'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $preparateur = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now(), 'is_active' => true]);
    $preparateur->givePermissionTo(['bon_preparations.view', 'bon_preparations.update']);
    $controleur = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now(), 'is_active' => true]);
    $controleur->givePermissionTo(['bon_preparations.view', 'bon_preparations.control']);
    $validateur = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now(), 'is_active' => true]);
    $validateur->givePermissionTo(['bon_preparations.view', 'bon_preparations.validate']);
    $lecteur = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now(), 'is_active' => true]);
    $lecteur->givePermissionTo('bon_preparations.view');

    $warehouse = Warehouse::create([
        'company_id' => $company->id, 'name' => 'Dépôt UI', 'code' => 'DEPU-'.uniqid(),
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Kg Ui'], ['abbreviation' => 'kgu']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-UI-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 1_000_000, 'total_ttc' => 1_000_000, 'invoiced_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => 'Tôle bac 0,40 galva', 'quantity' => $orderedQty, 'delivered_quantity' => 0,
        'unit_price' => 10_000, 'line_total_ht' => $orderedQty * 10_000,
        'line_tax' => 0, 'line_total_ttc' => $orderedQty * 10_000,
    ]);
    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-UI-'.uniqid(), 'quantity' => 1000, 'initial_quantity' => 1000,
        'reserved_quantity' => 0, 'unit_cost' => 800, 'status' => 'disponible',
        'valuation_status' => 'valorisation_definitive', 'quality_status' => 'libere', 'received_at' => now(),
    ]);

    return compact('company', 'fy', 'order', 'orderItem', 'product', 'warehouse',
        'lot', 'preparateur', 'controleur', 'validateur', 'lecteur');
}

// ---------------------------------------------------------------------------

it('affiche la liste des bons de préparation', function () {
    $f = uiFixture();

    test()->actingAs($f['lecteur'])
        ->get(route('ventes.preparations.index'))
        ->assertOk()
        ->assertSee('Bons de préparation quantifiés');
});

it('affiche l écran de création avec le RELIQUAT réel, pas la quantité commandée', function () {
    $f = uiFixture(orderedQty: 100);
    $f['orderItem']->update(['delivered_quantity' => 30]);

    test()->actingAs($f['preparateur'])
        ->get(route('ventes.preparations.create', ['order_id' => $f['order']->id]))
        ->assertOk()
        // Reliquat = 100 − 30 = 70. C'est cette valeur qui doit être proposée.
        ->assertSee('70', escape: false)
        ->assertSee('Tôle bac 0,40 galva');
});

it('crée un bon depuis la route réelle', function () {
    $f = uiFixture(orderedQty: 100);

    test()->actingAs($f['preparateur'])
        ->post(route('ventes.preparations.store'), [
            'order_id' => $f['order']->id,
            'warehouse_id' => $f['warehouse']->id,
            'lines' => [['order_item_id' => $f['orderItem']->id, 'quantity' => 60]],
        ])
        ->assertRedirect();

    $picking = SalesPicking::first();
    expect($picking)->not->toBeNull()
        ->and($picking->status)->toBe(SalesPicking::STATUS_BROUILLON)
        ->and((float) $picking->items->first()->qty_remaining_snapshot)->toBe(60.0);
});

it('refuse une quantité au-delà du reliquat et restitue le motif EXACT', function () {
    $f = uiFixture(orderedQty: 100);
    $f['orderItem']->update(['delivered_quantity' => 90]);

    test()->actingAs($f['preparateur'])
        ->post(route('ventes.preparations.store'), [
            'order_id' => $f['order']->id,
            'warehouse_id' => $f['warehouse']->id,
            'lines' => [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]],
        ])
        ->assertRedirect();

    // Le message du service passe TEL QUEL : il porte les chiffres dont
    // l'utilisateur a besoin pour corriger.
    expect(session('error') ?? '')->toContain('supérieure au reliquat')
        ->and(SalesPicking::count())->toBe(0);
});

it('la même clé d idempotence ne crée qu un seul bon', function () {
    $f = uiFixture(orderedQty: 100);
    $payload = [
        'order_id' => $f['order']->id,
        'warehouse_id' => $f['warehouse']->id,
        'idempotency_key' => 'UI-KEY-1',
        'lines' => [['order_item_id' => $f['orderItem']->id, 'quantity' => 40]],
    ];

    test()->actingAs($f['preparateur'])->post(route('ventes.preparations.store'), $payload);
    test()->actingAs($f['preparateur'])->post(route('ventes.preparations.store'), $payload);

    expect(SalesPicking::count())->toBe(1);
});

it('affiche la fiche avec les neuf quantités séparées', function () {
    $f = uiFixture(orderedQty: 100);
    test()->actingAs($f['preparateur']);
    $svc = app(SalesPickingService::class);
    $picking = $svc->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 60]], ['warehouse_id' => $f['warehouse']->id]);
    $alloc = $svc->allocate($picking->items->first(), [
        'stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 60,
    ]);
    $svc->start($picking);
    $svc->pick($alloc, 55, 'Un paquet abîmé.');

    test()->actingAs($f['lecteur'])
        ->get(route('ventes.preparations.show', $picking))
        ->assertOk()
        ->assertSee('Commandé')->assertSee('Alloué')->assertSee('Prélevé')
        ->assertSee('Contrôlé')->assertSee('Validé')->assertSee('Écart')
        // Le motif d'écart est visible : jamais un écart muet.
        ->assertSee('Un paquet abîmé.', escape: false)
        ->assertSee($f['lot']->lot_number);
});

it('interdit à un simple lecteur de lancer, contrôler ou valider', function () {
    $f = uiFixture(orderedQty: 50);
    test()->actingAs($f['preparateur']);
    $picking = app(SalesPickingService::class)->create(
        $f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]
    );

    test()->actingAs($f['lecteur'])->post(route('ventes.preparations.start', $picking))->assertForbidden();
    test()->actingAs($f['lecteur'])->post(route('ventes.preparations.control', $picking), ['result' => 'conforme'])->assertForbidden();
    test()->actingAs($f['lecteur'])->post(route('ventes.preparations.validate', $picking))->assertForbidden();
    test()->actingAs($f['lecteur'])->post(route('ventes.preparations.cancel', $picking), ['reason' => 'test'])->assertForbidden();

    expect($picking->fresh()->status)->toBe(SalesPicking::STATUS_BROUILLON);
});

it('interdit au préparateur de contrôler et au contrôleur de valider', function () {
    $f = uiFixture(orderedQty: 50);
    test()->actingAs($f['preparateur']);
    $svc = app(SalesPickingService::class);
    $picking = $svc->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]);
    $alloc = $svc->allocate($picking->items->first(), ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 50]);
    $svc->start($picking);
    $svc->pick($alloc, 50);

    // Le préparateur n'a pas la permission de contrôle : refus au niveau route.
    test()->actingAs($f['preparateur'])
        ->post(route('ventes.preparations.control', $picking), ['result' => 'conforme'])
        ->assertForbidden();

    test()->actingAs($f['controleur'])
        ->post(route('ventes.preparations.control', $picking), ['result' => 'conforme', 'checkpoints' => ['quantite' => 1]])
        ->assertRedirect();
    expect($picking->fresh()->status)->toBe(SalesPicking::STATUS_CONTROLE);

    // Le contrôleur n'a pas la permission de validation.
    test()->actingAs($f['controleur'])
        ->post(route('ventes.preparations.validate', $picking))
        ->assertForbidden();

    test()->actingAs($f['validateur'])
        ->post(route('ventes.preparations.validate', $picking))
        ->assertRedirect();
    expect($picking->fresh()->status)->toBe(SalesPicking::STATUS_VALIDE);
});

it('exige un motif pour annuler, depuis la route', function () {
    $f = uiFixture(orderedQty: 50);
    test()->actingAs($f['preparateur']);
    $picking = app(SalesPickingService::class)->create(
        $f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]
    );

    test()->actingAs($f['preparateur'])
        ->post(route('ventes.preparations.cancel', $picking), ['reason' => ''])
        ->assertSessionHasErrors('reason');
    expect($picking->fresh()->status)->not->toBe(SalesPicking::STATUS_ANNULE);

    test()->actingAs($f['preparateur'])
        ->post(route('ventes.preparations.cancel', $picking), ['reason' => 'Commande annulée par le client.'])
        ->assertRedirect();
    expect($picking->fresh()->status)->toBe(SalesPicking::STATUS_ANNULE);
});

it('crée le bon de livraison depuis la route, sur les quantités validées', function () {
    $f = uiFixture(orderedQty: 100);
    test()->actingAs($f['preparateur']);
    $svc = app(SalesPickingService::class);
    $picking = $svc->create($f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 60]], ['warehouse_id' => $f['warehouse']->id]);
    $alloc = $svc->allocate($picking->items->first(), ['stock_lot_id' => $f['lot']->id, 'warehouse_id' => $f['warehouse']->id, 'quantity' => 60]);
    $svc->start($picking);
    $svc->pick($alloc, 60);
    test()->actingAs($f['controleur']);
    $svc->control($picking, ['quantite' => true], SalesPickingControl::RESULT_CONFORME);

    test()->actingAs($f['validateur'])->post(route('ventes.preparations.validate', $picking));
    test()->actingAs($f['validateur'])
        ->post(route('ventes.preparations.delivery-note', $picking))
        ->assertRedirect();

    $dn = App\Models\DeliveryNote::first();
    expect($dn)->not->toBeNull()
        ->and((float) $dn->items->first()->quantity)->toBe(60.0)
        ->and($dn->items->first()->sales_picking_item_id)->toBe($picking->items->first()->id);
});

it('aucune confirmation native ne subsiste sur les écrans de préparation', function () {
    $f = uiFixture(orderedQty: 50);
    test()->actingAs($f['preparateur']);
    $picking = app(SalesPickingService::class)->create(
        $f['order'], [['order_item_id' => $f['orderItem']->id, 'quantity' => 50]], ['warehouse_id' => $f['warehouse']->id]
    );

    foreach ([
        route('ventes.preparations.index'),
        route('ventes.preparations.create', ['order_id' => $f['order']->id]),
        route('ventes.preparations.show', $picking),
    ] as $url) {
        $html = test()->actingAs($f['preparateur'])->get($url)->getContent();
        $body = preg_replace('#<script\b[^>]*>.*?</script>#si', '', $html);

        expect(preg_match('#(onsubmit|onclick)="[^"]*confirm\s*\(#i', $body))->toBe(0);
    }
});
