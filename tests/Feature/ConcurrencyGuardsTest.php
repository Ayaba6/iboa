<?php

/**
 * [R2 §8 — concurrence sur agrégats critiques] Preuve DÉTERMINISTE des gardes.
 *
 * Méthode : sous vraie concurrence, chaque agrégat est protégé par un verrou
 * pessimiste `lockForUpdate` (ou une contrainte d'unicité). Le verrou SÉRIALISE
 * les transactions : la transaction perdante ne démarre son travail qu'après le
 * commit de la gagnante, et lit donc l'état déjà modifié. Son comportement est
 * alors IDENTIQUE à une seconde exécution séquentielle. On prouve ici, en
 * séquentiel, que cette seconde exécution est refusée ou sans effet — donc
 * qu'aucun double n'est possible. La vraie parallélisation OS est prouvée par
 * ailleurs (scripts/concurrency-test.sh, 5 processus sur la réservation stock).
 *
 * Chaque test vérifie : opérations réussies, opérations refusées, données
 * finales, absence de doublon / double écriture / double mouvement.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Services\CreditNoteService;
use App\Services\DocumentSequenceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function concSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CONC'], ['email' => 'conc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-CONC'], ['name' => 'Dépôt', 'is_default' => true, 'is_active' => true]);

    return [$co, $fy, $wh, $u];
}

function concInvoiceWithCreditNote(array $ctx): array
{
    [$co, $fy, $wh] = $ctx;
    $p = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 3000]);
    $client = Client::factory()->create();
    $inv = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id,
        'number' => 'FA-CONC-' . uniqid(), 'status' => 'emise', 'issued_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 50000, 'total_tax' => 0, 'total_ttc' => 50000, 'remaining_amount' => 50000,
    ]);
    $inv->items()->create([
        'product_id' => $p->id, 'description' => 'X', 'quantity' => 10, 'unit_price' => 5000,
        'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 50000, 'line_tax' => 0, 'line_total_ttc' => 50000,
    ]);
    $svc = app(CreditNoteService::class);
    $cn = $svc->createFromInvoice($inv, [
        'reason' => 'C', 'items' => [['product_id' => $p->id, 'description' => 'R', 'quantity' => 4, 'unit_price' => 5000, 'tax_rate_value' => 0, 'disposition' => 'rebut']],
    ]);
    $svc->validate($cn); // avoir 20 000

    return [$svc, $inv, $cn, $p];
}

it('numéro de document : deux tirages ne rendent JAMAIS le même numéro', function () {
    [$co] = concSetup();
    $svc = app(DocumentSequenceService::class);

    $n1 = $svc->nextNumber($co, 'facture');
    $n2 = $svc->nextNumber($co, 'facture');

    expect($n1)->not->toBe($n2); // pas de doublon de numéro
});

it('double remboursement d\'avoir : la seconde exécution est refusée, une seule sortie caisse', function () {
    $ctx = concSetup();
    [$svc, , $cn] = concInvoiceWithCreditNote($ctx);
    $cash = CashAccount::factory()->create(['company_id' => $ctx[0]->id, 'type' => 'caisse', 'current_balance' => 100000, 'is_active' => true]);

    $svc->refund($cn->fresh(), $cash->id, 20000); // 1er : OK
    try {
        $svc->refund($cn->fresh(), $cash->id, 20000); // 2e : refus
        test()->fail('Le second remboursement aurait dû être refusé.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('restant');
    }

    // Une SEULE sortie : caisse 100 000 − 20 000 = 80 000, une seule écriture REMB
    expect((int) $cash->fresh()->current_balance)->toBe(80000)
        ->and(JournalEntry::where('reference', 'like', 'REMB-' . $cn->number . '%')->count())->toBe(1)
        ->and($cn->fresh()->status)->toBe('rembourse');
});

it('double imputation d\'avoir : une seule réduction de la facture', function () {
    $ctx = concSetup();
    [$svc, $inv, $cn] = concInvoiceWithCreditNote($ctx);

    $svc->applyToInvoice($cn->fresh()); // impute 20 000 → facture 30 000
    expect((int) $inv->fresh()->remaining_amount)->toBe(30000);

    // 2e imputation : l'avoir est épuisé (remaining_credit 0) → refus
    try {
        $svc->applyToInvoice($cn->fresh());
        test()->fail('La seconde imputation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        // Avoir épuisé (statut « applique », plus de crédit) → l'imputation est refusée.
        expect(strtolower($e->getMessage()))->toContain('valid');
    }
    expect((int) $inv->fresh()->remaining_amount)->toBe(30000); // inchangé, pas de double réduction
});

it('double annulation de remboursement : la seconde est refusée, un seul recrédit', function () {
    $ctx = concSetup();
    [$svc, , $cn] = concInvoiceWithCreditNote($ctx);
    $cash = CashAccount::factory()->create(['company_id' => $ctx[0]->id, 'type' => 'caisse', 'current_balance' => 100000, 'is_active' => true]);
    $svc->refund($cn->fresh(), $cash->id, 20000);        // caisse 80 000
    $svc->cancelRefund($cn->fresh(), 'Erreur');          // recrédit → 100 000

    try {
        $svc->cancelRefund($cn->fresh(), 'Erreur bis');  // plus rien à annuler
        test()->fail('La seconde annulation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('aucun remboursement');
    }
    expect((int) $cash->fresh()->current_balance)->toBe(100000); // un seul recrédit
});

it('double validation de facture : la seconde est refusée', function () {
    [$co, $fy] = concSetup();
    $client = Client::factory()->create();
    $inv = Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id,
        'number' => 'FA-V-' . uniqid(), 'status' => 'brouillon', 'issued_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 10000, 'total_tax' => 0, 'total_ttc' => 10000, 'remaining_amount' => 10000,
    ]);
    $svc = app(\App\Services\InvoiceService::class);

    $svc->validate($inv->fresh()); // 1er : brouillon → emise
    expect($inv->fresh()->status)->toBe('envoyee');

    try {
        $svc->validate($inv->fresh()); // 2e : refus
        test()->fail('La seconde validation aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('brouillon');
    }
    expect($inv->fresh()->status)->toBe('envoyee'); // pas de double transition
});

it('double reverse de consommation bobine : une seule restitution de poids', function () {
    [$co, $fy, $wh] = concSetup();
    $mp = Product::factory()->create(['is_stockable' => true]);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'number' => 'OF-C-' . uniqid(),
        'status' => 'en_cours', 'product_id' => $pf->id, 'quantity_requested' => 1, 'quantity_produced' => 0,
    ]);
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'BC-' . uniqid(),
        'initial_weight' => 20, 'remaining_weight' => 20, 'status' => 'disponible',
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 10000, 'received_at' => now(),
    ]);
    $svc = app(\App\Modules\Production\Services\CoilConsumptionService::class);
    $consumption = $svc->consume($of, $coil, 6.0, null, null);
    expect((float) $coil->fresh()->remaining_weight)->toBe(14.0); // 20 − 6

    $svc->reverse($consumption->fresh(), 'Erreur de saisie'); // restitue → 20
    expect((float) $coil->fresh()->remaining_weight)->toBe(20.0);

    // 2e reverse : déjà annulé → sans effet (pas de double restitution)
    try {
        $svc->reverse($consumption->fresh(), 'Rejeu');
    } catch (\Throwable $e) {
        // garde reversed_at : soit refus explicite, soit no-op — les deux acceptables
    }
    expect((float) $coil->fresh()->remaining_weight)->toBe(20.0); // toujours 20, pas 26
});
