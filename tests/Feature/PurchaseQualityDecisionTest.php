<?php

/**
 * [ACHATS Qualité — segment 3] Machine d'états qualité : quarantaine → contrôle →
 * libération / refus / dérogation. Décisions HISTORISÉES, libération ATOMIQUE,
 * maker-checker, idempotence. Données exclusivement créées en base de test (#18).
 *
 * Attendus indépendants : réception ventilée 100 = 60 accepté + 40 quarantaine.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseQualityDecision;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseQualityService;
use App\Services\PurchaseReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qualSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QUAL'], ['email' => 'qual@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $wh   = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-QUAL'], ['name' => 'Dépôt', 'is_default' => true, 'is_active' => true, 'can_purchase' => true]);
    $quar = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-QUAR'], ['name' => 'Dépôt Quarantaine', 'is_active' => true]);
    $sup  = Supplier::factory()->create();
    $p    = Product::factory()->create(['is_stockable' => true, 'controle_qualite' => true]);

    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'fiscal_year_id' => $fy->id,
        'number' => 'BC-Q-' . uniqid(), 'status' => 'confirme', 'ordered_at' => now(), 'currency_code' => 'XOF',
        'subtotal_ht' => 100000, 'total_tax' => 0, 'total_ttc' => 100000,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'product_id' => $p->id, 'description' => 'Matière QC',
        'quantity' => 100, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_rate_value' => 0,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
        'received_quantity' => 0, 'invoiced_quantity' => 0,
    ]);
    $rec = Reception::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'purchase_order_id' => $po->id,
        'number' => 'RCP-Q-' . uniqid(), 'status' => 'brouillon', 'received_at' => now(), 'created_by' => $u->id,
    ]);
    $recItem = $rec->items()->create([
        'purchase_order_item_id' => $poItem->id, 'product_id' => $p->id, 'description' => 'Matière QC',
        'expected_quantity' => 100, 'received_quantity' => 0, 'rejected_quantity' => 0, 'unit_cost' => 1000,
    ]);

    // Réception ventilée : 60 accepté, 40 quarantaine.
    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $recItem->id => ['received_quantity' => 100, 'accepted_quantity' => 60, 'quarantine_quantity' => 40, 'refused_quantity' => 0],
    ]);

    return [$co, $wh, $quar, $p, $poItem, $recItem->fresh(), $u];
}

function stockQty(int $productId, int $whId): float
{
    return (float) (ProductStock::where('product_id', $productId)->where('warehouse_id', $whId)->value('quantity') ?? 0);
}

it('libération TOTALE : quarantaine 40 → utilisable, décision historisée, invariant tenu', function () {
    [, $wh, $quar, $p, $poItem, $item] = qualSetup();
    expect(stockQty($p->id, $wh->id))->toBe(60.0)->and(stockQty($p->id, $quar->id))->toBe(40.0);

    $d = app(PurchaseQualityService::class)->release($item, 40.0, ['criteria' => ['poids' => 'conforme'], 'reason' => 'Contrôle conforme']);

    // Quantités : quarantaine 0, accepté 60+40=100 ; reçu physique INCHANGÉ.
    $ri = $item->fresh();
    expect((float) $ri->quarantine_quantity)->toBe(0.0)
        ->and((float) $ri->accepted_quantity)->toBe(100.0)
        ->and((float) $ri->received_quantity)->toBe(100.0)
        // Stock : DEP-QUAR 40−40=0, utilisable 60+40=100
        ->and(stockQty($p->id, $quar->id))->toBe(0.0)
        ->and(stockQty($p->id, $wh->id))->toBe(100.0)
        // Décision historisée avec avant/après
        ->and($d->type)->toBe('release')
        ->and((float) $d->quarantine_before)->toBe(40.0)
        ->and((float) $d->quarantine_after)->toBe(0.0)
        ->and((float) $d->accepted_after)->toBe(100.0)
        // Agrégat BC
        ->and((float) $poItem->fresh()->accepted_quantity)->toBe(100.0);
    // Journal d'audit
    expect(\App\Models\AuditLog::where('action', 'qualite.decision.release')->exists())->toBeTrue();
});

it('libération PARTIELLE 25/40 : quarantaine restante 15, invariant post-décision', function () {
    [, $wh, $quar, $p, , $item] = qualSetup();

    app(PurchaseQualityService::class)->release($item, 25.0, ['reason' => 'Lot conforme partiel']);

    $ri = $item->fresh();
    // quarantaine initiale 40 = libéré 25 + refusé 0 + retourné 0 + restante 15
    expect((float) $ri->quarantine_quantity)->toBe(15.0)
        ->and((float) $ri->accepted_quantity)->toBe(85.0)  // 60 + 25
        ->and(stockQty($p->id, $quar->id))->toBe(15.0)
        ->and(stockQty($p->id, $wh->id))->toBe(85.0)
        ->and($ri->quality_status)->toBe('en_attente');    // quarantaine restante
});

it('REFUS après contrôle 40 : va en DEP-REFUS (jamais utilisable), motif obligatoire', function () {
    [$co, $wh, $quar, $p, , $item] = qualSetup();

    // Sans motif → refus.
    expect(fn () => app(PurchaseQualityService::class)->rejectAfterControl($item, 40.0))
        ->toThrow(\RuntimeException::class, 'Motif obligatoire');

    app(PurchaseQualityService::class)->rejectAfterControl($item->fresh(), 40.0, ['reason' => 'Épaisseur hors tolérance']);

    $ri = $item->fresh();
    $refusWh = Warehouse::where('company_id', $co->id)->where('code', 'DEP-REFUS')->first();
    expect((float) $ri->quarantine_quantity)->toBe(0.0)
        ->and((float) $ri->accepted_quantity)->toBe(60.0)          // inchangé
        ->and((float) $ri->rejected_quantity)->toBe(40.0)
        ->and(stockQty($p->id, $quar->id))->toBe(0.0)
        ->and(stockQty($p->id, $refusWh->id))->toBe(40.0)           // matière localisée, hors utilisable
        ->and(stockQty($p->id, $wh->id))->toBe(60.0);               // utilisable inchangé
});

it('décision supérieure à la quarantaine restante : refusée, rien ne bouge', function () {
    [, $wh, $quar, $p, , $item] = qualSetup();

    expect(fn () => app(PurchaseQualityService::class)->release($item, 50.0, ['reason' => 'Trop']))
        ->toThrow(\RuntimeException::class, 'supérieure à la quarantaine');
    expect(stockQty($p->id, $quar->id))->toBe(40.0)->and(stockQty($p->id, $wh->id))->toBe(60.0);
});

it('DÉROGATION : maker-checker — le contrôleur constatant la NC ne s\'auto-approuve pas', function () {
    config(['security.maker_checker.enabled' => true]);
    [$co, $wh, $quar, $p, , $item, $admin] = qualSetup();

    // Contrôleur simple (sans rôle super_admin).
    $controleur = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);

    // Le contrôleur tente d'approuver SA propre dérogation → refus.
    test()->actingAs($controleur);
    expect(fn () => app(PurchaseQualityService::class)->derogationAcceptance($item, 10.0, [
        'reason' => 'Usage interne toléré', 'controlled_by' => $controleur->id,
    ]))->toThrow(\RuntimeException::class, 'Séparation des tâches');

    // Un approbateur DISTINCT approuve → OK (10 sous dérogation → utilisable).
    test()->actingAs($admin);
    $d = app(PurchaseQualityService::class)->derogationAcceptance($item->fresh(), 10.0, [
        'reason' => 'Usage interne toléré', 'controlled_by' => $controleur->id,
    ]);
    expect($d->type)->toBe('derogation_acceptance')
        ->and((float) $item->fresh()->accepted_quantity)->toBe(70.0)   // 60 + 10
        ->and((float) $item->fresh()->quarantine_quantity)->toBe(30.0)
        ->and(stockQty($p->id, $wh->id))->toBe(70.0)
        ->and((int) $d->controlled_by)->toBe($controleur->id)
        ->and((int) $d->approved_by)->toBe($admin->id);
});

it('IDEMPOTENCE : rejeu même clé → même décision, une seule libération ; contenu différent → refus', function () {
    [, $wh, $quar, $p, , $item] = qualSetup();
    $svc = app(PurchaseQualityService::class);

    $d1 = $svc->release($item, 20.0, ['reason' => 'OK', 'idempotency_key' => 'QK-1']);
    $d2 = $svc->release($item->fresh(), 20.0, ['reason' => 'OK', 'idempotency_key' => 'QK-1']); // rejeu

    expect($d2->id)->toBe($d1->id)
        ->and(PurchaseQualityDecision::count())->toBe(1)          // une seule décision
        ->and(stockQty($p->id, $wh->id))->toBe(80.0)              // 60 + 20, PAS 100
        ->and(stockQty($p->id, $quar->id))->toBe(20.0);           // 40 − 20 une seule fois

    // Même clé, quantité différente → refus explicite.
    expect(fn () => $svc->release($item->fresh(), 5.0, ['reason' => 'OK', 'idempotency_key' => 'QK-1']))
        ->toThrow(\RuntimeException::class, 'contenu différent');
});

it('CONCURRENCE (sérialisée par verrou) : deux libérations de 15 sur 20 → une seule complète, jamais > 20 libéré', function () {
    [, $wh, $quar, $p, , $item] = qualSetup();
    $svc = app(PurchaseQualityService::class);
    // Ramène la quarantaine à 20 pour le scénario exigé (libère 20 des 40 d'abord).
    $svc->release($item, 20.0, ['reason' => 'pré-libération']);
    expect((float) $item->fresh()->quarantine_quantity)->toBe(20.0);

    // Processus A : libère 15 → OK. Processus B (le perdant du verrou lit l'état
    // commité) : libère 15 → refus (5 restants).
    $svc->release($item->fresh(), 15.0, ['reason' => 'A']);
    expect(fn () => $svc->release($item->fresh(), 15.0, ['reason' => 'B']))
        ->toThrow(\RuntimeException::class, 'supérieure à la quarantaine');

    // Libéré total ≤ 20, quarantaine jamais négative, pas de double mouvement.
    $ri = $item->fresh();
    expect((float) $ri->quarantine_quantity)->toBe(5.0)
        ->and(stockQty($p->id, $quar->id))->toBe(5.0)
        ->and(stockQty($p->id, $wh->id))->toBe(95.0)   // 60 + 20 + 15
        ->and(PurchaseQualityDecision::where('type', 'release')->count())->toBe(2);
});

it('CONCURRENCE : libération 40 puis refus 40 sur la même quarantaine → une seule décision gagne', function () {
    [$co, $wh, $quar, $p, , $item] = qualSetup();
    $svc = app(PurchaseQualityService::class);

    $svc->release($item, 40.0, ['reason' => 'A libère tout']);
    expect(fn () => $svc->rejectAfterControl($item->fresh(), 40.0, ['reason' => 'B refuse tout']))
        ->toThrow(\RuntimeException::class, 'supérieure à la quarantaine');

    expect((float) $item->fresh()->quarantine_quantity)->toBe(0.0)
        ->and(stockQty($p->id, $wh->id))->toBe(100.0)                    // tout libéré
        ->and((float) $item->fresh()->rejected_quantity)->toBe(0.0);     // aucun refus passé
});

it('ligne historique UNKNOWN : le nouveau workflow qualité la refuse (certification manuelle requise)', function () {
    [, $wh, $quar, $p, , $item] = qualSetup();
    // Simule l'héritage : disposition inconnue.
    $item->update(['accepted_quantity' => null, 'quarantine_quantity' => null, 'disposition_origin' => 'legacy_unclassified']);

    expect(fn () => app(PurchaseQualityService::class)->release($item->fresh(), 10.0, ['reason' => 'x']))
        ->toThrow(\RuntimeException::class, 'certification');
});
