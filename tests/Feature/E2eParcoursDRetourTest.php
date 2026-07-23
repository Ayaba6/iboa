<?php

/**
 * [Ultimatum — Parcours D : retour client] Attendus INDÉPENDANTS.
 * Vente livrée de 10 u à 5 000 HT (50 000, sans TVA), CMP 3 000.
 *  - retour partiel 4 u : 2 restock (+2 vendable), 1 quarantaine (+1 DÉPÔT
 *    QUAR, PAS vendable), 1 rebut (rien) ;
 *  - avoir = 4 × 5 000 = 20 000 ; imputation → remaining 50 000 − 20 000 = 30 000 ;
 *  - remboursement réel : caisse −15 000, 411 soldé, statut rembourse,
 *    refus sans provision, double remboursement refusé ;
 *  - annulation d'avoir : stock ET facture inversés.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function retSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'RET'], ['email' => 'ret@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-RET'], ['name' => 'Dépôt RET', 'is_default' => true, 'is_active' => true]);
    $quar = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-QUAR'], ['name' => 'Dépôt Quarantaine', 'is_active' => true]);
    $p = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 3000]);
    $client = Client::factory()->create();
    $inv = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id,
        'number' => 'FA-RET-' . uniqid(), 'status' => 'emise', 'issued_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 50000, 'total_tax' => 0, 'total_ttc' => 50000, 'remaining_amount' => 50000,
    ]);
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'Fer vendu', 'quantity' => 10, 'unit_price' => 5000,
        'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 50000, 'line_tax' => 0, 'line_total_ttc' => 50000,
    ]);

    return [$co, $wh, $quar, $p, $client, $inv];
}

it('D-dispositions : restock vendable, quarantaine ISOLÉE, rebut rien — imputation exacte', function () {
    [$co, $wh, $quar, $p, $client, $inv] = retSetup();
    $svc = app(\App\Services\CreditNoteService::class);

    $cn = $svc->createFromInvoice($inv, [
        'reason' => 'Retour partiel mixte',
        'items' => [
            ['product_id' => $p->id, 'description' => 'OK', 'quantity' => 2, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'restock'],
            ['product_id' => $p->id, 'description' => 'Douteux', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'quarantaine'],
            ['product_id' => $p->id, 'description' => 'Cassé', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'rebut'],
        ],
    ]);
    $svc->validate($cn);

    // Stock vendable : +2 seulement ; quarantaine : +1 ISOLÉ ; rebut : rien
    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(2.0)
        ->and((float) (ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity') ?? 0))->toBe(1.0)
        ->and((int) $cn->fresh()->total_ttc)->toBe(20000); // 4 × 5 000

    // Imputation : 50 000 − 20 000 = 30 000
    $svc->applyToInvoice($cn->fresh());
    expect((int) $inv->fresh()->remaining_amount)->toBe(30000)
        ->and($cn->fresh()->status)->toBe('applique');
});

it('D-remboursement réel : caisse débitée, statut rembourse ; refus sans provision ; pas de double', function () {
    [$co, $wh, $quar, $p, $client, $inv] = retSetup();
    $svc = app(\App\Services\CreditNoteService::class);
    $cn = $svc->createFromInvoice($inv, [
        'reason' => 'Remboursement',
        'items' => [['product_id' => $p->id, 'description' => 'R', 'quantity' => 3, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'rebut']],
    ]);
    $svc->validate($cn); // avoir 15 000

    // Refus sans provision
    $cashVide = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
    try {
        $svc->refund($cn->fresh(), $cashVide->id);
        $this->fail('Le remboursement sans provision aurait dû être refusé.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('solde');
    }
    expect($cn->fresh()->status)->toBe('valide');

    // Remboursement réel : 20 000 − 15 000 = 5 000 restants en caisse
    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 20000, 'is_active' => true]);
    $svc->refund($cn->fresh(), $cash->id);

    expect($cn->fresh()->status)->toBe('rembourse')
        ->and((int) $cn->fresh()->remaining_credit)->toBe(0)
        ->and((int) $cash->fresh()->current_balance)->toBe(5000);

    // Écriture D411/C571 équilibrée
    $gl = \App\Models\JournalEntry::where('reference', 'REMB-' . $cn->number)->first();
    expect($gl)->not->toBeNull()
        ->and((int) $gl->total_debit)->toBe(15000)
        ->and((int) $gl->total_debit)->toBe((int) $gl->total_credit);

    // Double remboursement refusé
    try {
        $svc->refund($cn->fresh(), $cash->id);
        $this->fail('Le double remboursement aurait dû être refusé.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('remboursé');
    }
    // Journal d'audit
    expect(\App\Models\AuditLog::where('action', 'avoir.remboursement')->where('model_id', $cn->id)->exists())->toBeTrue();
});

it('D-annulation : annuler un avoir restock inverse le stock ET la facture', function () {
    [$co, $wh, $quar, $p, $client, $inv] = retSetup();
    $svc = app(\App\Services\CreditNoteService::class);
    $cn = $svc->createFromInvoice($inv, [
        'reason' => 'Erreur',
        'items' => [['product_id' => $p->id, 'description' => 'R', 'quantity' => 2, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'restock']],
    ]);
    $svc->validate($cn);
    $svc->applyToInvoice($cn->fresh());
    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(2.0)
        ->and((int) $inv->fresh()->remaining_amount)->toBe(40000); // 50 000 − 10 000

    app(\App\Services\CommercialWorkflowService::class)->cancel($cn->fresh(), 'Avoir émis par erreur');

    expect($cn->fresh()->status)->toBe('annule')
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(0.0)
        ->and((int) $inv->fresh()->remaining_amount)->toBe(50000); // restauré
});
