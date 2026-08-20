<?php

/**
 * [CDC §27] Processus cible principal — chaîne complète bout-en-bout :
 *
 *   Commande client → Validation → OF → Réservation matière → Production
 *   → Contrôle qualité → Stock PF → Livraison → Facture → Encaissement
 *
 * Contrairement aux tests précédents (SalesFullFlowTest, ProductionCostTest...)
 * qui vérifient chaque module isolément, celui-ci suit UNE seule commande à
 * travers TOUS les modules pour détecter les bugs de jonction (ex. le BL qui
 * ne retrouve pas le stock produit par l'OF lié, le garde-fou qualité, etc.)
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\ClientPaymentService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function chainAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = chainCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function chainCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'CHAIN-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Chain Co'],
        ['email' => 'chain@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

it('parcourt Devis → Commande → Validation → OF → Réservation → Production → Qualité → Stock PF → Livraison → Facture → Encaissement', function () {

    $user    = chainAdmin();
    $company = chainCompany();
    $this->actingAs($user);

    // [Stabilité] Plafond de crédit FIXÉ, jamais tiré au sort.
    //
    // `ClientFactory` choisit `credit_limit` au hasard parmi
    // [500 000, 1 000 000, 2 000 000, 5 000 000]. La commande de ce parcours vaut
    // 50 × 10 000 = 500 000 HT, soit 590 000 TTC : au tirage « 500 000 », la garde
    // d'encours bloquait la soumission et le test échouait. Une fois sur quatre,
    // sur n'importe quel moteur — instabilité qui ressemblait à un écart
    // SQLite/MySQL sans en être un.
    //
    // Le sujet de ce test est la CHAÎNE devis → encaissement, pas le contrôle de
    // crédit, qui a ses propres tests. Le plafond est donc mis hors de portée.
    $client  = Client::factory()->create([
        'is_active'    => true,
        'credit_limit' => 50_000_000,
    ]);
    $unit    = Unit::firstOrCreate(['name' => 'Pièce CH'], ['abbreviation' => 'pcch']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% CH'], ['short_name' => 'TVA18CH', 'rate' => 18, 'is_active' => true]);

    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-CHAIN'],
        ['name' => 'Dépôt Chain', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]
    );

    // Article fini fabriqué à la commande (MTO), aucun stock initial.
    $finished = Product::factory()->create([
        'is_stockable'     => true,
        'valuation_method' => 'cmp',
        'production_mode'  => 'mto',
    ]);

    $machine = ProductionMachine::create([
        'company_id' => $company->id, 'code' => 'MX-CHAIN', 'name' => 'Profileuse Chain',
        'type' => 'profilage', 'hourly_cost' => 5_000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create([
        'company_id' => $company->id, 'machine_id' => $machine->id, 'code' => 'L-CHAIN', 'name' => 'Ligne Chain', 'is_active' => true,
    ]);
    $bom = BillOfMaterial::create([
        'company_id' => $company->id, 'product_id' => $finished->id, 'name' => 'BOM Chain',
        'is_active' => true, 'labor_per_unit' => 100, 'machine_time_per_unit' => 2,
    ]);

    // ── Étape 0 : Devis → validation → conversion en Commande ────────────────
    /** @var OrderService $orderSvc */
    $orderSvc = app(OrderService::class);
    $quoteSvc = app(\App\Services\QuoteService::class);

    $lineItem = [
        'product_id' => $finished->id, 'description' => 'Tôle bac sur mesure',
        'quantity' => 50, 'unit_price' => 10_000, 'discount_percent' => 0, 'unit_id' => $unit->id,
        'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
    ];

    $quote = $quoteSvc->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [$lineItem],
    ]);
    expect($quote->status)->toBe('brouillon');

    $wf = app(CommercialWorkflowService::class);
    $wf->submit($quote);
    $wf->validateQuote($quote->fresh());
    expect($quote->fresh()->status)->toBe('valide');

    // ── Étape 1 : Commande issue du devis (brouillon, quote_id renseigné) ─────
    $order = $quoteSvc->convertToOrder($quote->fresh());
    expect($order->status)->toBe('brouillon')
        ->and($order->quote_id)->toBe($quote->id);

    // ── Étape 2 : Validation (commerciale puis financière) ───────────────────
    /** @var CommercialWorkflowService $workflow */
    $workflow = app(CommercialWorkflowService::class);
    $workflow->submit($order);
    $workflow->validateOrder($order->fresh());
    $order->refresh();
    expect($order->status)->toBe('confirme');

    // ── Étape 3 : OF auto-généré (MTO, stock insuffisant) ────────────────────
    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $finished->id)->first();
    expect($of)->not->toBeNull()
        ->and($of->status)->toBe('brouillon')
        ->and((float) $of->quantity_requested)->toBe(50.0);

    $of->update(['bill_of_material_id' => $bom->id, 'production_line_id' => $line->id]);

    /** @var ProductionService $prodSvc */
    $prodSvc = app(ProductionService::class);
    $prodSvc->allocateMaterial($of->fresh());
    $prodSvc->submitForValidation($of->fresh());
    $prodSvc->validateByChef($of->fresh());
    $prodSvc->validateByResponsable($of->fresh());

    // Court-circuite la validation financière (déjà couverte par §13.2 ailleurs) :
    // OF lié à une commande crédit par défaut → autorisation manuelle DAF.
    $of->fresh()->update(['financial_authorization' => 'bypassed', 'financial_authorized_at' => now(), 'financial_authorized_by' => $user->id]);

    $prodSvc->launch($of->fresh());
    $prodSvc->start($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('en_cours');

    // ── Étape 4 : Réservation matière (consommation bobine) ──────────────────
    $coil = Coil::create([
        'company_id' => $company->id, 'reference' => 'BOB-CHAIN', 'initial_weight' => 500,
        'remaining_weight' => 500, 'cost_per_kg' => 400, 'purchase_price' => 200_000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($of, $coil, 200);
    $coil->refresh();
    expect((float) $coil->remaining_weight)->toBe(300.0);

    // ── Étape 5 : Production — sortie produit fini → entrée stock ────────────
    /** @var ProductionStockService $stockSvc */
    $stockSvc = app(ProductionStockService::class);
    $output = $stockSvc->recordOutput($of, [
        'product_id' => $finished->id, 'warehouse_id' => $warehouse->id,
        'quantity' => 50, 'length' => 1, 'unit_cost' => 1_600,
    ]);
    expect($output->stock_movement_id)->not->toBeNull();

    $stockAfterProd = ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->first();
    expect((float) $stockAfterProd->quantity)->toBe(50.0);

    // ── Étape 6 : Contrôle qualité — conforme ─────────────────────────────────
    $qc = ProductionQualityControl::create([
        'company_id' => $company->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    expect($qc->status)->toBe('conforme');

    // [CDC §13.3] Visa chef d'équipe sur la déclaration avant clôture OF.
    //
    // [MTO §15 — adaptation documentée du 30/07/2026] La LIBÉRATION QUALITÉ est
    // désormais exigée en plus du visa. La chaîne livrait auparavant une
    // production que personne n'avait libérée : le garde sommait `quantity` sans
    // regarder `quality_released_at`. Ce n'est pas la fixture qui contournait la
    // règle, c'est la règle qui n'existait pas. Le contrôle qualité conforme
    // ci-dessus étant acquis, la libération est l'étape qui manquait au parcours.
    $output->update([
        'status'              => 'validee',
        'validated_at'        => now(),
        'quality_released_at' => now(),
    ]);

    $prodSvc->finish($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('termine');

    // ── Étape 7 : Stock PF confirmé disponible pour livraison ────────────────
    expect((float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(50.0);

    // ── Étape 8 : Livraison ───────────────────────────────────────────────────
    $dn = $orderSvc->createDeliveryNote($order->fresh());
    expect($dn->items)->toHaveCount(1);

    $stockBeforeShip = (float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity');

    app(DeliveryNoteService::class)->validate($dn);
    $dn->refresh();
    $order->refresh();

    expect($dn->status)->toBe('valide')
        ->and($order->status)->toBe('livre');

    $stockAfterShip = (float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity');
    expect($stockBeforeShip - $stockAfterShip)->toBe(50.0);

    // ── Étape 9 : Facture ─────────────────────────────────────────────────────
    $invoice = app(DeliveryNoteService::class)->createInvoice($dn);
    app(InvoiceService::class)->validate($invoice);
    $invoice->refresh();

    expect($invoice->status)->toBeIn(['emise', 'envoyee'])
        ->and((float) $invoice->total_ttc)->toBe(50 * 10_000 * 1.18)
        ->and($invoice->remaining_amount)->toBe($invoice->total_ttc);

    // ── Étape 10 : Encaissement ────────────────────────────────────────────────
    $cashAccount = CashAccount::factory()->create([
        'company_id' => $company->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true,
    ]);

    $payment = app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cashAccount->id,
        'amount'          => $invoice->total_ttc,
        'method'          => 'virement',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [[
            'invoice_id'       => $invoice->id,
            'allocated_amount' => $invoice->total_ttc,
        ]],
    ]);

    $invoice->refresh();
    expect($payment->amount)->toBe($invoice->total_ttc)
        ->and($invoice->status)->toBe('payee')
        ->and($invoice->remaining_amount)->toBe(0);
});
