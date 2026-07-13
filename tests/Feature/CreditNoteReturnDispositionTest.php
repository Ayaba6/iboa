<?php

/**
 * [VEN Retour] Retours rebut / remplacement sur avoir.
 *
 *  - une ligne 'rebut' ne réintègre PAS le stock vendable (pas de mouvement retour_client) ;
 *  - une ligne 'restock' génère bien un retour_client ;
 *  - la génération d'un remplacement crée un BL brouillon lié reprenant les articles.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CreditNoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cnrAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CNR'], ['email' => 'cnr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

function cnrCreditNote(): App\Models\CreditNote
{
    $co = currentCompany();
    Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEF'], ['name' => 'Dépôt', 'is_default' => true]);

    $pA = Product::factory()->create(['is_stockable' => true, 'purchase_price' => 500]);
    $pB = Product::factory()->create(['is_stockable' => true, 'purchase_price' => 800]);

    $invoice = Invoice::create([
        'company_id'   => $co->id,
        'client_id'    => Client::factory()->create()->id,
        'number'       => 'F-'.uniqid(),
        'type'         => 'facture',
        'status'       => 'emise',
        'issued_at'    => now(),
        'currency_code' => 'XOF',
        'subtotal_ht'  => 0, 'total_ttc' => 0, 'remaining_amount' => 0,
    ]);

    return app(CreditNoteService::class)->createFromInvoice($invoice, [
        'reason' => 'Retour marchandise',
        'items'  => [
            ['product_id' => $pA->id, 'description' => 'A restock', 'quantity' => 3, 'unit_price' => 1000, 'tax_rate_value' => 0, 'disposition' => 'restock'],
            ['product_id' => $pB->id, 'description' => 'B rebut',   'quantity' => 2, 'unit_price' => 1500, 'tax_rate_value' => 0, 'disposition' => 'rebut'],
        ],
    ]);
}

it('persiste la disposition des lignes', function () {
    cnrAdmin();
    $cn = cnrCreditNote();

    $dispos = $cn->items()->pluck('disposition', 'description');
    expect($dispos['A restock'])->toBe('restock');
    expect($dispos['B rebut'])->toBe('rebut');
});

it('ne réintègre pas le stock pour une ligne rebut', function () {
    cnrAdmin();
    $cn = cnrCreditNote();

    app(CreditNoteService::class)->validate($cn);

    $movements = StockMovement::where('reference_type', 'credit_note')
        ->where('reference_id', $cn->id)
        ->where('type', 'retour_client')
        ->get();

    // Seule la ligne 'restock' génère un retour_client.
    expect($movements)->toHaveCount(1);
    expect((float) $movements->first()->quantity)->toBe(3.0);
});

it('génère un BL de remplacement brouillon lié reprenant les articles', function () {
    cnrAdmin();
    $cn = cnrCreditNote();
    app(CreditNoteService::class)->validate($cn);

    $dn = app(CreditNoteService::class)->createReplacementDelivery($cn->fresh());

    expect($dn->status)->toBe('brouillon');
    expect($dn->order_id)->toBeNull();
    expect($dn->items()->count())->toBe(2);
    expect($cn->fresh()->replacement_delivery_id)->toBe($dn->id);
    expect((bool) $cn->fresh()->is_replacement)->toBeTrue();

    // Un second appel est bloqué (idempotence métier).
    expect(fn () => app(CreditNoteService::class)->createReplacementDelivery($cn->fresh()))
        ->toThrow(RuntimeException::class);
});
