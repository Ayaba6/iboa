<?php

/**
 * [QA §26] Scénarios E2E obligatoires B et C.
 *
 * B — MTO approuvé : commande crédit NON réglée → gate de lancement refuse →
 *     approbation gérant (route réelle, motif) → lancement passe → production →
 *     clôture → BL.
 * C — MTS : article sous seuil → OF SANS commande client → production →
 *     stock général ↑ → vente ultérieure servie sur stock → BL.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function e2eAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'E2E Co'], ['email' => 'e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('B — MTO approuvé : gate refusée puis approbation gérant, production, livraison', function () {
    $user = e2eAdmin();
    $co   = Company::first();
    $this->actingAs($user);

    // [BUG-A3-MTO-FIN-001 §2] CHANGEMENT DE RÈGLE MÉTIER — à valider.
    // Ce scénario reposait sur « client à crédit = refus systématique ». La
    // consigne §2 impose désormais d'évaluer le crédit (plafond, exposition
    // courante et prévisionnelle, impayés échus). Avec le plafond par défaut de
    // la factory — 5 000 000 pour une commande de 59 000 TTC — ce client
    // passerait maintenant SANS approbation gérant, et le parcours « refus puis
    // approbation » n'aurait plus de sujet.
    // Le plafond est donc mis à 0 : aucun crédit accordé, ce qui reste un refus
    // et conserve au scénario B le chemin qu'il a été écrit pour éprouver.
    $client  = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 0]);
    $unit    = Unit::firstOrCreate(['name' => 'Pièce E2E'], ['abbreviation' => 'pe2e']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% E2E'], ['short_name' => 'TVAE2E', 'rate' => 18, 'is_active' => true]);
    $wh      = Warehouse::firstOrCreate(['code' => 'WH-E2E'], ['name' => 'Dépôt E2E', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    $tole = Product::factory()->create([
        'name' => 'Tôle E2E B', 'is_stockable' => true,
        'is_manufacturable' => true, 'production_mode' => 'mto',
    ]);
    $matiere = Product::factory()->create(['name' => 'Matière E2E B', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $matiere->id, 'warehouse_id' => $wh->id, 'quantity' => 500, 'reserved_quantity' => 0]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $tole->id, 'name' => 'BOM E2E B', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $matiere->id, 'quantity_per_meter' => 1, 'unit_id' => $unit->id, 'sort_order' => 1]);

    // 1. Commande crédit confirmée, AUCUN règlement → OF MTO auto.
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $tole->id, 'description' => $tole->name,
            'quantity' => 10, 'unit_price' => 5_000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());

    $of = ProductionOrder::where('order_id', $order->id)->first();
    expect($of)->not->toBeNull();

    // 2. Validations atelier, puis gate de lancement REFUSE (crédit, 0 encaissé).
    $of->update(['bill_of_material_id' => $bom->id]);
    $svc = app(ProductionService::class);
    $svc->submitForValidation($of->fresh());
    $svc->validateByChef($of->fresh());
    $svc->validateByResponsable($of->fresh());
    expect(fn () => $svc->launch($of->fresh()))->toThrow(ValidationException::class);

    // 3. Approbation gérant via la ROUTE réelle (motif obligatoire).
    $this->post(route('ventes.commandes.approve-production', $order), [
        'motif' => 'Client historique fiable — production autorisée sans acompte',
        'valide_jours' => 15,
    ])->assertSessionHas('success');
    expect($order->fresh()->production_approved)->toBeTrue();

    // 4. La gate reconnaît l'approbation : lancement + démarrage passent.
    $svc->launch($of->fresh(), force: true); // force = dérogation matière (pas de BOM dans ce scénario)
    $svc->start($of->fresh());
    expect($of->fresh()->status)->toBe('en_cours');

    // [BUG-A3-MTO-FIN-001] Ce cas vérifiait auparavant
    // `financial_authorization === 'approved'` sur l'OF. La garde recopiait alors
    // l'approbation de la commande dans cette colonne — au lancement automatique
    // près, c'est le même geste qui a produit la fausse piste d'audit
    // d'OF-2026-0007. L'approbation vit là où elle a été prise, sur la COMMANDE,
    // avec son auteur et sa validité ; l'OF ne la duplique plus, il la consulte.
    expect($of->fresh()->financial_authorization)->toBeNull();
    expect($order->fresh()->production_approved_by)->not->toBeNull();
    expect($of->fresh()->order->productionFinancialRequirement($of->fresh())->type)
        ->toBe(\App\Services\Production\ProductionFinancialRequirement::TYPE_MANUAL_OVERRIDE);

    // 5. Déclaration + CQ + clôture.
    $output = app(ProductionStockService::class)->recordOutput($of->fresh(), [
        'product_id' => $tole->id, 'warehouse_id' => $wh->id,
        'quantity' => 10, 'length' => 1, 'unit_cost' => 3_000, 'lot_number' => 'LOT-E2E-B',
    ]);
    ProductionQualityControl::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);
    $svc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    // 6. Stock PF disponible pour livraison.
    expect((float) ProductStock::where('product_id', $tole->id)->where('warehouse_id', $wh->id)->value('quantity'))
        ->toBeGreaterThanOrEqual(10.0);
});

it('C — MTS : OF sans client depuis le besoin, stock général, vente servie sur stock', function () {
    $user = e2eAdmin();
    $co   = Company::first();
    $this->actingAs($user);

    $unit    = Unit::firstOrCreate(['name' => 'Barre E2E'], ['abbreviation' => 'be2e']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% E2E'], ['short_name' => 'TVAE2E', 'rate' => 18, 'is_active' => true]);
    $wh      = Warehouse::firstOrCreate(['code' => 'WH-E2E'], ['name' => 'Dépôt E2E', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    // 1. Article MTS sous seuil (stock 0, min 50).
    $fer = Product::factory()->create([
        'name' => 'Fer E2E C', 'is_stockable' => true, 'is_manufacturable' => true,
        'production_mode' => 'mts', 'stock_min' => 50, 'stock_max' => 100, 'sale_price' => 5000,
        'is_sellable' => true,
    ]);

    // Le tableau de planification MTS propose l'article en rupture.
    $this->get(route('production.orders.mts'))->assertOk()->assertSee('Fer E2E C');

    // 2. OF SANS commande client (planification MTS) — aucune gate financière.
    $matiere = Product::factory()->create(['name' => 'Fil machine E2E C', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $matiere->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $fer->id, 'name' => 'BOM E2E C', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $matiere->id, 'quantity_per_meter' => 1, 'unit_id' => $unit->id, 'sort_order' => 1]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-MTS-E2E', 'status' => 'brouillon',
        'product_id' => $fer->id, 'quantity_requested' => 60,
        'bill_of_material_id' => null,
    ]);
    $of->update(['bill_of_material_id' => $bom->id]);
    $svc = app(ProductionService::class);
    $svc->submitForValidation($of->fresh());
    $svc->validateByChef($of->fresh());
    $svc->validateByResponsable($of->fresh());
    $svc->launch($of->fresh(), force: true);
    $svc->start($of->fresh());

    // 3. Production déclarée + CQ → stock GÉNÉRAL (pas de réservation client).
    $output = app(ProductionStockService::class)->recordOutput($of->fresh(), [
        'product_id' => $fer->id, 'warehouse_id' => $wh->id,
        'quantity' => 60, 'length' => 12, 'unit_cost' => 3_500, 'lot_number' => 'LOT-E2E-C',
    ]);
    ProductionQualityControl::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now()]);
    $svc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine')
        ->and((float) ProductStock::where('product_id', $fer->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(60.0)
        ->and(\App\Models\StockReservation::where('production_order_id', $of->id)->where('status', 'reserved')->count())->toBe(0);

    // 4. Vente ultérieure servie sur stock : PAS de nouvel OF (produit dispo).
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'comptant']);
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $fer->id, 'description' => $fer->name,
            'quantity' => 20, 'unit_price' => 5_000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    expect($order->fresh()->status)->toBe('confirme')
        ->and(ProductionOrder::where('order_id', $order->id)->exists())->toBeFalse();

    // Le stock est réservé pour la commande (20 sur 60).
    expect((float) ProductStock::where('product_id', $fer->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))
        ->toBe(20.0);
});
