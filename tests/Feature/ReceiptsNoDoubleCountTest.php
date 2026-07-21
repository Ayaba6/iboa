<?php

/**
 * [Contrôles pré-push d8b0220] confirmedReceipts() ne double-compte jamais :
 * - BP caisse puis facture réglée du même argent → plafonné au TTC (identique) ;
 * - acompte libre puis affecté à une facture → transfert, total inchangé ;
 * - paiements non confirmés exclus.
 * + mesure du nombre de requêtes du tableau des commandes à produire.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentAllocation;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function rcCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'RC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'RC Co'], ['email' => 'rc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function rcOrder(Company $co, int $ttc = 1000000): Order
{
    $client = Client::factory()->create(['payment_mode' => 'comptant']);
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-RC-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => $ttc,
    ]);
    $p = Product::factory()->create(['production_mode' => 'mto']);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 10,
        'unit_price' => 4000, 'line_total_ht' => $ttc, 'line_tax' => 0, 'line_total_ttc' => $ttc, 'sort_order' => 0,
    ]);

    return $order;
}

function rcInvoice(Order $order): Invoice
{
    return Invoice::create([
        'company_id' => $order->company_id, 'fiscal_year_id' => $order->fiscal_year_id,
        'client_id' => $order->client_id, 'order_id' => $order->id,
        'number' => 'FA-RC-' . uniqid(), 'status' => 'emise', 'issued_at' => now(),
        'total_ttc' => (int) $order->total_ttc, 'remaining_amount' => (int) $order->total_ttc,
    ]);
}

it('BP caisse puis facture réglée du même argent : le montant confirmé reste identique (plafond TTC)', function () {
    $co = rcCompany();
    $order = rcOrder($co, 1000000);

    // 1. Paiement caisse intégral → BP
    BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id,
        'number' => 'BP-RC-' . uniqid(), 'status' => 'en_attente', 'payment_amount' => 1000000,
    ]);
    expect($order->fresh()->confirmedReceipts())->toBe(1000000);

    // 2. Facture créée, puis le MÊME argent ressaisi en trésorerie et alloué.
    $invoice = rcInvoice($order);
    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'status' => 'confirme',
        'is_acompte' => false, 'amount' => 1000000, 'unallocated_amount' => 0,
        'payment_date' => now(), 'number' => 'ENC-RC-' . uniqid(),
    ]);
    ClientPaymentAllocation::create([
        'client_payment_id' => $pay->id, 'invoice_id' => $invoice->id, 'amount' => 1000000, 'allocated_at' => now(),
    ]);

    // Sans plafond : 2 000 000. Avec plafond TTC : identique à avant.
    expect($order->fresh()->confirmedReceipts())->toBe(1000000);
});

it('acompte libre puis affecté à une facture : transfert sans double comptage', function () {
    $co = rcCompany();
    $order = rcOrder($co, 1000000);

    $acompte = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'status' => 'confirme',
        'is_acompte' => true, 'amount' => 500000, 'unallocated_amount' => 500000,
        'payment_date' => now(), 'number' => 'ENC-RC-' . uniqid(),
    ]);
    expect($order->fresh()->confirmedReceipts())->toBe(500000);

    // Affectation de l'acompte à la facture : unallocated ↓, allocation ↑ — total stable.
    $invoice = rcInvoice($order);
    ClientPaymentAllocation::create([
        'client_payment_id' => $acompte->id, 'invoice_id' => $invoice->id, 'amount' => 500000, 'allocated_at' => now(),
    ]);
    $acompte->update(['unallocated_amount' => 0]);

    expect($order->fresh()->confirmedReceipts())->toBe(500000);
});

it('exclut les paiements non confirmés (brouillon/annulé) et les BP annulés', function () {
    $co = rcCompany();
    $order = rcOrder($co, 1000000);

    ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'status' => 'brouillon',
        'is_acompte' => true, 'amount' => 300000, 'unallocated_amount' => 300000,
        'payment_date' => now(), 'number' => 'ENC-RC-' . uniqid(),
    ]);
    ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'status' => 'annule',
        'is_acompte' => true, 'amount' => 300000, 'unallocated_amount' => 300000,
        'payment_date' => now(), 'number' => 'ENC-RC-' . uniqid(),
    ]);
    BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id,
        'number' => 'BP-RC-' . uniqid(), 'status' => 'annule', 'payment_amount' => 400000,
    ]);

    expect($order->fresh()->confirmedReceipts())->toBe(0);
});

it('reflète immédiatement un paiement ajouté ou annulé sur la MÊME instance (pas de valeur périmée)', function () {
    $co = rcCompany();
    $order = rcOrder($co, 1000000);

    // 1-2. Même instance, avant paiement.
    expect($order->confirmedReceipts())->toBe(0);

    // 3-5. BP confirmé créé → le second appel sur la MÊME instance voit le paiement.
    $bp = BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id,
        'number' => 'BP-RC-' . uniqid(), 'status' => 'en_attente', 'payment_amount' => 1000000,
    ]);
    expect($order->confirmedReceipts())->toBe(1000000);

    // Contrôle inverse : annulation du BP → retombe à 0 sur la même instance.
    $bp->update(['status' => 'annule']);
    expect($order->confirmedReceipts())->toBe(0);

    // Nouvelle allocation facture → visible immédiatement.
    $invoice = rcInvoice($order);
    $pay = ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'status' => 'confirme',
        'is_acompte' => false, 'amount' => 400000, 'unallocated_amount' => 0,
        'payment_date' => now(), 'number' => 'ENC-RC-' . uniqid(),
    ]);
    ClientPaymentAllocation::create([
        'client_payment_id' => $pay->id, 'invoice_id' => $invoice->id, 'amount' => 400000, 'allocated_at' => now(),
    ]);
    expect($order->confirmedReceipts())->toBe(400000);

    // Révocation du paiement (annulé) → retombe à 0 sur la même instance.
    $pay->update(['status' => 'annule']);
    expect($order->confirmedReceipts())->toBe(0);
});

it('mesure les requêtes du tableau des commandes à produire (documentation N+1)', function () {
    $co = rcCompany();
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($u);

    foreach (range(1, 5) as $i) {
        $o = rcOrder($co, 100000);
        BonPreparation::create([
            'company_id' => $co->id, 'order_id' => $o->id,
            'number' => 'BP-RC-' . uniqid(), 'status' => 'en_attente', 'payment_amount' => 100000,
        ]);
    }

    DB::enableQueryLog();
    $this->get(route('production.orders.eligible'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // ~4 requêtes par commande (invoices, allocations, BP sum, acomptes) : N+1 assumé
    // et documenté — acceptable au volume actuel (< 30 commandes éligibles).
    // Garde-fou : alerte si dérive majeure.
    expect($count)->toBeLessThan(80);
})->group('perf');
