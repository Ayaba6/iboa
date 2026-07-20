<?php

/**
 * [CDC §33] Scénario E2E dédié — client comptant avec ACOMPTE obligatoire 50 %.
 *
 * Tôle bac prélaquée MTO, 4 000 FCFA/m, TVA 18 %.
 * 10 tôles × 5 m = 50 m → HT 200 000, TVA 36 000, TTC 236 000, acompte 118 000.
 *
 * Vérifie : métrage recalculé serveur, montants, gate financière franchie par
 * l'acompte 50 % (SANS bypass), chaîne jusqu'à produit fini prêt à livrer.
 * Complète OrderToCashFullChainTest (qui, lui, court-circuite la gate en crédit).
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SalesSetting;
use App\Models\TaxRate;
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
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function acCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'AC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'AC Co'], ['email' => 'ac@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function acAdmin(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

it('CDC §33 : client acompte 50 % — devis→commande→acompte→OF→production→PF prêt à livrer', function () {
    $company = acCompany();
    $user = acAdmin($company);
    $this->actingAs($user);

    // Seuil d'acompte à 50 % (scénario §33).
    SalesSetting::current()->update(['deposit_required_rate' => 50]);

    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% AC'], ['short_name' => 'TVA18AC', 'rate' => 18, 'is_active' => true]);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-AC'], ['name' => 'Dépôt AC', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]);

    // Client « comptant » avec acompte obligatoire (mode acompte).
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'acompte']);

    // Tôle bac prélaquée rouge 0,27 — fabriquée à la commande, 4 000 FCFA/m.
    $tole = Product::factory()->create([
        'is_stockable' => true, 'valuation_method' => 'cmp', 'production_mode' => 'mto',
        'sale_price' => 4000, 'min_sale_price' => 0,
    ]);

    $machine = ProductionMachine::create(['company_id' => $company->id, 'code' => 'MX-AC', 'name' => 'Profileuse AC', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $company->id, 'machine_id' => $machine->id, 'code' => 'L-AC', 'name' => 'Ligne AC', 'is_active' => true]);
    $bom = BillOfMaterial::create(['company_id' => $company->id, 'product_id' => $tole->id, 'name' => 'BOM AC', 'is_active' => true, 'labor_per_unit' => 100, 'machine_time_per_unit' => 2]);

    // ── Devis : 10 tôles × 5 m ──────────────────────────────────────────────
    $quote = app(QuoteService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $tole->id, 'description' => 'Tôle bac prélaquée rouge 0,27',
            'nb_toles' => 10, 'metrage_par_tole' => 5, 'quantity' => 1,
            'unit_price' => 4000, 'discount_percent' => 0,
            'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // Métrage recalculé serveur + montants §33.
    $qLine = $quote->items()->first();
    expect((float) $qLine->quantity)->toBe(50.0)            // 10 × 5
        ->and((float) $qLine->nb_toles)->toBe(10.0)
        ->and((float) $qLine->metrage_par_tole)->toBe(5.0)
        ->and((float) $quote->subtotal_ht)->toBe(200000.0)
        ->and((float) $quote->total_ttc)->toBe(236000.0);

    // ── Validation devis → conversion commande ──────────────────────────────
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($quote);
    $wf->validateQuote($quote->fresh());
    $order = app(QuoteService::class)->convertToOrder($quote->fresh());

    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    $order->refresh();
    expect($order->status)->toBe('confirme');

    // ── OF auto MTO ─────────────────────────────────────────────────────────
    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $tole->id)->first();
    expect($of)->not->toBeNull();
    $of->update(['bill_of_material_id' => $bom->id, 'production_line_id' => $line->id]);

    // ── Acompte 50 % (118 000) — encaissement libre confirmé ────────────────
    ClientPayment::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'status' => 'confirme',
        'is_acompte' => true, 'amount' => 118000, 'unallocated_amount' => 118000,
        'payment_date' => now(), 'number' => 'ENC-AC-' . uniqid(),
    ]);

    // La gate financière passe grâce à l'acompte 50 % (aucun bypass).
    app(ProductionService::class)->checkFinancialGate($of->fresh()); // ne lève pas

    // ── Production ──────────────────────────────────────────────────────────
    $prod = app(ProductionService::class);
    $prod->allocateMaterial($of->fresh());
    $prod->submitForValidation($of->fresh());
    $prod->validateByChef($of->fresh());
    $prod->validateByResponsable($of->fresh());
    $prod->launch($of->fresh());   // gate franchie par acompte
    $prod->start($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('en_cours');

    $coil = Coil::create(['company_id' => $company->id, 'reference' => 'BOB-AC', 'initial_weight' => 500, 'remaining_weight' => 500, 'cost_per_kg' => 400, 'purchase_price' => 200000, 'status' => 'disponible']);
    app(CoilConsumptionService::class)->consume($of, $coil, 200);

    $output = app(ProductionStockService::class)->recordOutput($of, [
        'product_id' => $tole->id, 'warehouse_id' => $warehouse->id,
        'quantity' => 50, 'length' => 5, 'unit_cost' => 1600,
    ]);
    expect($output->stock_movement_id)->not->toBeNull();

    // ── Contrôle qualité conforme (modèle direct — hors garde SoD HTTP) ──────
    ProductionQualityControl::create([
        'company_id' => $company->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);
    $prod->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    // ── Résultat §33 : PF prêt à livrer ─────────────────────────────────────
    expect((float) ProductStock::where('product_id', $tole->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(50.0);

    // Commande livrable : le BL se crée avec la ligne PF.
    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());
    expect($dn->items)->toHaveCount(1);
});
