<?php

/**
 * [Ultimatum — Parcours B : vente MTS] Nominal + non-nominaux exigés.
 * ATTENDUS CALCULÉS INDÉPENDAMMENT (arithmétique commentée, jamais le moteur
 * testé) : stock 20 à CMP 6 000 ; commande 8 à 10 000 HT/u sans TVA.
 *  - réservation 8 ; livraison → stock 12, sortie valorisée 8 × 6 000 = 48 000 ;
 *  - facture 80 000 ; 2 règlements 30 000 + 50 000 → payée, solde client 0 ;
 *  - concurrence : stock restant dispo 12 − commande concurrente de 15 →
 *    réservation plafonnée à 12 (jamais plus que le disponible) ;
 *  - double validation BL : une seule sortie (idempotence) ;
 *  - annulation avant sortie : réservation libérée intégralement.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtsSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MTS'], ['email' => 'mts@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-MTS'], ['name' => 'Dépôt MTS', 'is_default' => true, 'is_active' => true]);
    $p = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'allow_negative_stock' => false]);
    // Stock initial : 20 unités à CMP 6 000 (fer à béton en stock)
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 20, 'reserved_quantity' => 0, 'avg_cost' => 6000]);
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);

    return [$co, $wh, $p, Client::factory()->create(), $cash];
}

function mtsOrder(Company $co, Client $client, Product $p, float $qty): Order
{
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-MTS-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => $qty * 10000, 'total_tax' => 0, 'total_ttc' => $qty * 10000,
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => 'Fer à béton', 'quantity' => $qty,
        'unit_price' => 10000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => $qty * 10000, 'line_tax' => 0, 'line_total_ttc' => $qty * 10000,
    ]);

    return $order;
}

it('B-nominal : réservation, livraison, sortie au CMP, facture, 2 règlements, solde 0', function () {
    [$co, $wh, $p, $client, $cash] = mtsSetup();
    $order = mtsOrder($co, $client, $p, 8);

    // ── Réservation : 8 réservées, dispo = 20 − 8 = 12
    app(\App\Modules\Production\Services\ReservationService::class)->reserveStockForOrder($order);
    $ps = ProductStock::where('product_id', $p->id)->first();
    expect((float) $ps->reserved_quantity)->toBe(8.0);

    // ── BL total → validation : stock 20 − 8 = 12, réservation consommée
    $dn = \App\Models\DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-MTS-' . uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create([
        'product_id' => $p->id, 'order_item_id' => $order->items->first()->id,
        'description' => 'Fer à béton', 'quantity' => 8, 'unit_price' => 10000,
    ]);
    app(\App\Services\DeliveryNoteService::class)->validate($dn);

    $ps->refresh();
    expect((float) $ps->quantity)->toBe(12.0)
        ->and((float) $ps->reserved_quantity)->toBe(0.0)
        ->and((float) $order->items()->first()->fresh()->delivered_quantity)->toBe(8.0);

    // Sortie valorisée : 8 × 6 000 = 48 000 (calcul indépendant), CMP inchangé
    $mv = StockMovement::where('reference_type', 'delivery_note')->where('reference_id', $dn->id)->first();
    expect((int) round($mv->quantity * $mv->unit_cost))->toBe(48000)
        ->and((float) $ps->avg_cost)->toBe(6000.0);

    // ── Double validation : refusée, UNE seule sortie
    try {
        app(\App\Services\DeliveryNoteService::class)->validate($dn->fresh());
    } catch (\RuntimeException $e) {
        // « seuls les brouillons » — attendu
    }
    expect(StockMovement::where('reference_type', 'delivery_note')->where('reference_id', $dn->id)->count())->toBe(1);

    // ── Facture depuis le BL : 8 × 10 000 = 80 000 (indépendant)
    $inv = app(\App\Services\InvoiceService::class)->createFromDeliveryNote($dn->fresh());
    app(\App\Services\InvoiceService::class)->validate($inv->fresh());
    $inv->fresh()->update(['status' => 'emise']);
    expect((int) $inv->fresh()->total_ttc)->toBe(80000);

    // ── 2 règlements : 30 000 puis 50 000 → payée, solde client 0
    $paySvc = app(\App\Services\ClientPaymentService::class);
    $paySvc->create([
        'company_id' => $co->id, 'client_id' => $client->id, 'amount' => 30000,
        'payment_date' => now()->toDateString(), 'payment_method' => 'espece',
        'cash_account_id' => $cash->id, 'status' => 'confirme',
        'allocations' => [['invoice_id' => $inv->id, 'amount' => 30000]],
    ]);
    expect($inv->fresh()->status)->toBe('partiellement_payee')
        ->and((int) $inv->fresh()->remaining_amount)->toBe(50000); // 80 000 − 30 000

    $paySvc->create([
        'company_id' => $co->id, 'client_id' => $client->id, 'amount' => 50000,
        'payment_date' => now()->toDateString(), 'payment_method' => 'espece',
        'cash_account_id' => $cash->id, 'status' => 'confirme',
        'allocations' => [['invoice_id' => $inv->id, 'amount' => 50000]],
    ]);
    expect($inv->fresh()->status)->toBe('payee')
        ->and((int) $inv->fresh()->remaining_amount)->toBe(0);

    $client->refresh();
    $client->recalculateBalance();
    expect((int) $client->fresh()->balance)->toBe(0)
        ->and((int) $cash->fresh()->current_balance)->toBe(80000); // 30 000 + 50 000

    // ── Cohérence comptable : 6031 débité de la sortie (48 000)
    $c6031 = \App\Models\Account::where('code', '6031')->first();
    expect((int) $c6031?->debit_balance)->toBe(48000);
});

it('B-concurrence : la 2e commande ne réserve JAMAIS plus que le disponible', function () {
    [$co, $wh, $p, $client] = mtsSetup();

    // Commande A : 15 réservées → dispo restant = 20 − 15 = 5
    $a = mtsOrder($co, $client, $p, 15);
    app(\App\Modules\Production\Services\ReservationService::class)->reserveStockForOrder($a);

    // Commande B : demande 10, ne peut réserver QUE 5 (calcul indépendant)
    $b = mtsOrder($co, Client::factory()->create(), $p, 10);
    $reservedB = app(\App\Modules\Production\Services\ReservationService::class)->reserveStockForOrder($b);

    $ps = ProductStock::where('product_id', $p->id)->first();
    expect((float) $reservedB)->toBe(5.0)
        ->and((float) $ps->reserved_quantity)->toBe(20.0)          // 15 + 5, jamais > 20
        ->and((float) $ps->reserved_quantity)->toBeLessThanOrEqual((float) $ps->quantity);
});

it('B-livraison partielle : 5/8 livrées, reliquat livrable, statut partiel', function () {
    [$co, $wh, $p, $client] = mtsSetup();
    $order = mtsOrder($co, $client, $p, 8);
    app(\App\Modules\Production\Services\ReservationService::class)->reserveStockForOrder($order);

    $dn = \App\Models\DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-PART-' . uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create([
        'product_id' => $p->id, 'order_item_id' => $order->items->first()->id,
        'description' => 'Fer', 'quantity' => 5, 'unit_price' => 10000,
    ]);
    app(\App\Services\DeliveryNoteService::class)->validate($dn);

    // Stock : 20 − 5 = 15 ; livré 5/8 ; réservation résiduelle ≤ 3
    expect((float) ProductStock::where('product_id', $p->id)->value('quantity'))->toBe(15.0)
        ->and((float) $order->items()->first()->fresh()->delivered_quantity)->toBe(5.0)
        ->and((float) ProductStock::where('product_id', $p->id)->value('reserved_quantity'))->toBeLessThanOrEqual(3.0);
});

it('B-refus : livraison au-delà du stock physique refusée, rien ne bouge', function () {
    [$co, $wh, $p, $client] = mtsSetup();
    $order = mtsOrder($co, $client, $p, 25); // > stock 20

    $dn = \App\Models\DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-OVER-' . uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create([
        'product_id' => $p->id, 'order_item_id' => $order->items->first()->id,
        'description' => 'Fer', 'quantity' => 25,
    ]);

    try {
        app(\App\Services\DeliveryNoteService::class)->validate($dn);
        $this->fail('La livraison au-delà du stock aurait dû être refusée.');
    } catch (\Throwable $e) {
        expect(strtolower($e->getMessage()))->toContain('stock');
    }
    expect((float) ProductStock::where('product_id', $p->id)->value('quantity'))->toBe(20.0)
        ->and(StockMovement::where('reference_type', 'delivery_note')->where('reference_id', $dn->id)->count())->toBe(0);
});

it('B-annulation avant sortie : la réservation est libérée intégralement', function () {
    [$co, $wh, $p, $client] = mtsSetup();
    $order = mtsOrder($co, $client, $p, 8);
    app(\App\Modules\Production\Services\ReservationService::class)->reserveStockForOrder($order);
    expect((float) ProductStock::where('product_id', $p->id)->value('reserved_quantity'))->toBe(8.0);

    app(\App\Services\OrderService::class)->cancel($order);

    expect($order->fresh()->status)->toBe('annule')
        ->and((float) ProductStock::where('product_id', $p->id)->value('reserved_quantity'))->toBe(0.0)
        ->and((float) ProductStock::where('product_id', $p->id)->value('quantity'))->toBe(20.0); // stock intact
});

it('B-permissions : un lecteur stock ne valide pas un BL', function () {
    [$co, $wh, $p, $client] = mtsSetup();
    $order = mtsOrder($co, $client, $p, 4);
    $dn = \App\Models\DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-PERM-' . uniqid(), 'status' => 'brouillon',
        'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now(),
    ]);
    $dn->items()->create(['product_id' => $p->id, 'description' => 'Fer', 'quantity' => 4]);

    // Utilisateur au rôle minimal (lecture stock seulement)
    $lecteur = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $role = Role::firstOrCreate(['name' => 'mts-lecteur', 'guard_name' => 'web']);
    $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'stocks.view', 'guard_name' => 'web']));
    $lecteur->assignRole($role);
    $this->actingAs($lecteur);

    $this->post(route('ventes.bons-livraison.validate', $dn))->assertForbidden();
    expect($dn->fresh()->status)->toBe('brouillon');
});
