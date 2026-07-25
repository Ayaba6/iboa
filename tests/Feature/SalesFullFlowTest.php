<?php

/**
 * Test end-to-end du cycle de vente complet, conforme CDC §16.1 :
 *
 *   Devis → Commande → Validation commerciale → Validation financière
 *   → Préparation → Livraison → Facturation
 *
 * Chemin A (via devis) : Devis accepté → convertToOrder (brouillon)
 *   → submit → validateOrder → BL → Facture
 *
 * Chemin B (commande directe) : Order::create (brouillon)
 *   → submit → validateOrder → BL → Facture
 *
 * Les deux chemins convergent désormais sur le même circuit de validation :
 * convertToOrder() ne bypasse plus la commande directement en `confirme`.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\OrderService;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Fixtures ────────────────────────────────────────────────────────────────

function saleAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = saleCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function saleCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'SF-2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'SalesFlow Co'],
        ['email' => 'sf@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function saleProduct(int $stockQty = 100): Product
{
    $product = Product::factory()->create([
        'is_stockable'         => true,
        'valuation_method'     => 'cmp',
        'purchase_price'       => 5_000,
        'weighted_avg_cost'    => 5_000,
        'allow_negative_stock' => false,
    ]);

    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-SF'],
        ['name' => 'Dépôt SF', 'company_id' => saleCompany()->id, 'is_active' => true, 'is_default' => true]
    );

    ProductStock::firstOrCreate(
        ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
        ['quantity' => $stockQty, 'reserved_quantity' => 0, 'avg_cost' => 5_000]
    );

    return $product;
}

function saleItemData(Product $product, TaxRate $taxRate, Unit $unit, int $qty = 10): array
{
    return [
        'product_id'       => $product->id,
        'description'      => 'Fer à béton 10mm',
        'quantity'         => $qty,
        'unit_price'       => 50_000,
        'discount_percent' => 0,
        'tax_rate_id'      => $taxRate->id,
        'tax_rate_value'   => 18,
        'unit_id'          => $unit->id,
    ];
}

// ─── Chemin A : Devis → Commande → Validation commerciale → Validation ────────
// ─── financière → Préparation → Livraison → Facturation (CDC §16.1) ──────────
//
// [CONFORMITÉ CDC] convertToOrder() crée désormais la commande en brouillon —
// elle n'échappe plus au circuit submit→validateOrder. Toute commande, qu'elle
// vienne d'un devis ou d'une saisie directe, passe par les deux mêmes gardes
// fous avant que la production/livraison ne puisse démarrer.

describe('Chemin A — Devis → Validation commerciale → financière → BL → Facture', function () {

    it('parcourt Devis → Commande brouillon → submit → validate → BL → Facture', function () {

        $user    = saleAdmin();
        $client  = Client::factory()->create(['is_active' => true]);
        $product = saleProduct(200);
        $taxRate = TaxRate::firstOrCreate(
            ['name' => 'TVA 18% SF'],
            ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]
        );
        $unit = Unit::firstOrCreate(['name' => 'Kg SF'], ['abbreviation' => 'kg']);

        $this->actingAs($user);

        // ── Étape 1 : Créer le devis ─────────────────────────────────────────
        /** @var QuoteService $quoteSvc */
        $quoteSvc = app(QuoteService::class);

        $quote = $quoteSvc->create([
            'client_id'  => $client->id,
            'issued_at'  => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'items'      => [saleItemData($product, $taxRate, $unit, 10)],
        ]);

        expect($quote->status)->toBe('brouillon')
            ->and($quote->subtotal_ht)->toBe(500_000)
            ->and($quote->total_ttc)->toBe(590_000);

        // ── Étape 2 : Accepter le devis ───────────────────────────────────────
        $quoteSvc->accept($quote);
        $quote->refresh();
        expect($quote->status)->toBe('accepte');

        // ── Étape 3 : Convertir en commande — démarre en brouillon ───────────
        $order = $quoteSvc->convertToOrder($quote);
        $quote->refresh();

        expect($order->status)->toBe('brouillon')
            ->and($order->client_id)->toBe($client->id)
            ->and($order->subtotal_ht)->toBe(500_000)
            ->and($order->total_ttc)->toBe(590_000)
            ->and($order->items)->toHaveCount(1)
            ->and($quote->status)->toBe('converti');

        // Pas de réservation stock tant que la commande n'est pas validée.
        $stockAtBrouillon = ProductStock::where('product_id', $product->id)->value('reserved_quantity');
        expect((float) $stockAtBrouillon)->toBe(0.0);

        // ── Étape 4 : Validation commerciale (soumission) ────────────────────
        /** @var CommercialWorkflowService $workflow */
        $workflow = app(CommercialWorkflowService::class);

        $workflow->submit($order);
        $order->refresh();

        expect($order->status)->toBe('en_attente_validation')
            ->and($order->submitted_by)->toBe($user->id)
            ->and($order->submitted_at)->not->toBeNull();

        // ── Étape 5 : Validation financière (approbation + réservation stock) ─
        $workflow->validateOrder($order);
        $order->refresh();

        expect($order->status)->toBe('confirme')
            ->and($order->validated_by)->toBe($user->id)
            ->and($order->validated_at)->not->toBeNull();

        // [STOCK] OrderConfirmed → ReserveStockOnOrderConfirmed a bien réservé.
        $reservedAfterValidation = ProductStock::where('product_id', $product->id)->value('reserved_quantity');
        expect((float) $reservedAfterValidation)->toBe(10.0);

        // ── Étape 6 : Préparation — bon de livraison ─────────────────────────
        /** @var OrderService $orderSvc */
        $orderSvc = app(OrderService::class);

        $dn = $orderSvc->createDeliveryNote($order);
        $order->refresh();

        expect($dn)->toBeInstanceOf(DeliveryNote::class)
            ->and($dn->order_id)->toBe($order->id)
            ->and($dn->items)->toHaveCount(1)
            ->and($dn->status)->toBe('brouillon')
            ->and($order->status)->toBe('confirme');

        // ── Étape 7 : Livraison (validation BL + sortie de stock) ────────────
        $stockBefore = ProductStock::where('product_id', $product->id)->value('quantity');

        app(\App\Services\DeliveryNoteService::class)->validate($dn);
        $dn->refresh();
        $order->refresh();

        $stockAfter = ProductStock::where('product_id', $product->id)->value('quantity');

        expect($dn->status)->toBe('valide')
            ->and($dn->validated_at)->not->toBeNull()
            ->and($stockBefore - $stockAfter)->toBe(10.0)
            ->and($order->status)->toBe('livre');

        // ── Étape 8 : Facturation ─────────────────────────────────────────────
        /** @var DeliveryNoteService $dnSvc */
        $dnSvc   = app(DeliveryNoteService::class);
        $invoice = $dnSvc->createInvoice($dn);

        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->client_id)->toBe($client->id)
            ->and($invoice->subtotal_ht)->toBe(500_000)
            ->and($invoice->total_ttc)->toBe(590_000)
            ->and($invoice->items)->toHaveCount(1);

        app(\App\Services\InvoiceService::class)->validate($invoice);
        $invoice->refresh();

        // Avec QUEUE_CONNECTION=sync : SendInvoiceEmailJob s'exécute immédiatement
        // et fait passer la facture de 'emise' à 'envoyee' si le client a un email.
        expect($invoice->status)->toBeIn(['emise', 'envoyee'])
            ->and($invoice->validated_at)->not->toBeNull()
            ->and($invoice->remaining_amount)->toBe(590_000)
            ->and($invoice->number)->toStartWith('F');
    });

    it('bloque la préparation tant que la commande issue d\'un devis n\'est pas validée', function () {
        $user    = saleAdmin();
        $client  = Client::factory()->create(['is_active' => true]);
        $product = saleProduct(50);
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% SF'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Kg SF'], ['abbreviation' => 'kg']);

        $this->actingAs($user);

        $quoteSvc = app(QuoteService::class);
        $quote = $quoteSvc->create([
            'client_id'  => $client->id,
            'issued_at'  => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'items'      => [saleItemData($product, $taxRate, $unit, 4)],
        ]);
        $quoteSvc->accept($quote);
        $order = $quoteSvc->convertToOrder($quote);

        expect($order->status)->toBe('brouillon');

        // Sans submit+validate, la préparation doit être refusée.
        expect(fn () => app(OrderService::class)->createDeliveryNote($order))
            ->toThrow(\RuntimeException::class);
    });
});

// ─── Chemin B : Commande directe avec workflow submit → validate ──────────────

describe('Chemin B — Validation commerciale → financière → BL → Facture', function () {

    it('parcourt Order brouillon → submit → validate → BL → Facture', function () {

        $user    = saleAdmin();
        $client  = Client::factory()->create(['is_active' => true]);
        $product = saleProduct(200);
        $taxRate = TaxRate::firstOrCreate(
            ['name' => 'TVA 18% SF'],
            ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]
        );
        $unit = Unit::firstOrCreate(['name' => 'Kg SF'], ['abbreviation' => 'kg']);

        $this->actingAs($user);

        // ── Étape 1 : Créer la commande directement (brouillon) ──────────────
        /** @var OrderService $orderSvc */
        $orderSvc = app(OrderService::class);

        $order = $orderSvc->create([
            'client_id'  => $client->id,
            'issued_at'  => now()->toDateString(),
            'items'      => [saleItemData($product, $taxRate, $unit, 5)],
        ]);

        expect($order->status)->toBe('brouillon')
            ->and($order->subtotal_ht)->toBe(250_000)
            ->and($order->total_ttc)->toBe(295_000);

        // ── Étape 2 : Validation commerciale (soumission) ────────────────────
        /** @var CommercialWorkflowService $workflow */
        $workflow = app(CommercialWorkflowService::class);

        $workflow->submit($order);
        $order->refresh();

        expect($order->status)->toBe('en_attente_validation')
            ->and($order->submitted_by)->toBe($user->id)
            ->and($order->submitted_at)->not->toBeNull();

        // ── Étape 3 : Validation financière (approbation) ────────────────────
        $workflow->validateOrder($order);
        $order->refresh();

        expect($order->status)->toBe('confirme')
            ->and($order->validated_by)->toBe($user->id)
            ->and($order->validated_at)->not->toBeNull();

        // ── Étape 4 : Préparation — bon de livraison ─────────────────────────
        $dn = $orderSvc->createDeliveryNote($order);
        $order->refresh();

        expect($dn->order_id)->toBe($order->id)
            ->and($dn->items)->toHaveCount(1)
            ->and($dn->status)->toBe('brouillon')
            ->and($order->status)->toBe('confirme');

        // ── Étape 5 : Livraison ───────────────────────────────────────────────
        $stockBefore = ProductStock::where('product_id', $product->id)->value('quantity');

        app(\App\Services\DeliveryNoteService::class)->validate($dn);
        $dn->refresh();
        $order->refresh();

        $stockAfter = ProductStock::where('product_id', $product->id)->value('quantity');

        expect($dn->status)->toBe('valide')
            ->and($stockBefore - $stockAfter)->toBe(5.0)
            ->and($order->status)->toBe('livre');

        // ── Étape 6 : Facturation ─────────────────────────────────────────────
        $invoice = app(DeliveryNoteService::class)->createInvoice($dn);
        app(\App\Services\InvoiceService::class)->validate($invoice->fresh());
        $invoice->refresh();

        expect($invoice->status)->toBeIn(['emise', 'envoyee'])
            ->and($invoice->total_ttc)->toBe(295_000)
            ->and($invoice->number)->toStartWith('F');
    });
});

// ─── Garde-fous ───────────────────────────────────────────────────────────────

describe('Règles métier ventes', function () {

    it('bloque la livraison si commande non confirmée', function () {
        $user    = saleAdmin();
        $client  = Client::factory()->create(['is_active' => true]);
        $product = saleProduct(50);
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% SF'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Kg SF'], ['abbreviation' => 'kg']);

        $this->actingAs($user);

        // Commande directe — reste en brouillon (pas de submit/validate)
        $order = app(OrderService::class)->create([
            'client_id' => $client->id,
            'issued_at' => now()->toDateString(),
            'items'     => [saleItemData($product, $taxRate, $unit, 3)],
        ]);

        expect($order->status)->toBe('brouillon');

        expect(fn () => app(OrderService::class)->createDeliveryNote($order))
            ->toThrow(\RuntimeException::class);
    });

    it('bloque la soumission d\'une commande déjà soumise', function () {
        $user    = saleAdmin();
        $client  = Client::factory()->create(['is_active' => true]);
        $product = saleProduct(50);
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% SF'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Kg SF'], ['abbreviation' => 'kg']);

        $this->actingAs($user);

        $order = app(OrderService::class)->create([
            'client_id' => $client->id,
            'issued_at' => now()->toDateString(),
            'items'     => [saleItemData($product, $taxRate, $unit, 2)],
        ]);

        $workflow = app(CommercialWorkflowService::class);
        $workflow->submit($order);          // OK
        $order->refresh();

        expect(fn () => $workflow->submit($order))   // doublon → exception
            ->toThrow(\RuntimeException::class);
    });

    it('ne peut pas convertir un devis déjà converti', function () {
        $user   = saleAdmin();
        $client = Client::factory()->create(['is_active' => true]);
        saleCompany();

        $this->actingAs($user);

        $quote = Quote::factory()->create([
            'company_id' => saleCompany()->id,
            'client_id'  => $client->id,
            'status'     => 'accepte',
        ]);

        $quoteSvc = app(QuoteService::class);

        expect(fn () => $quoteSvc->convertToOrder($quote))->toThrow(\RuntimeException::class);
    });
});
