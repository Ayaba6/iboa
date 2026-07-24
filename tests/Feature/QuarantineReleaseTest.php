<?php

/**
 * [R2 §2 — sortie de quarantaine par décision qualité] Attendus INDÉPENDANTS.
 * La quarantaine ne se vide JAMAIS toute seule : seule une décision qualité
 * explicite (décideur + motif) via QuarantineService peut libérer (→ vendable)
 * ou rebuter (→ sortie définitive). Stock quarantaine initial : 5 u, CMP 3 000.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QuarantineService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function quarSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QUAR-CO'], ['email' => 'quar@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $sellable = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-VEND'], ['name' => 'Dépôt vente', 'is_default' => true, 'is_active' => true]);
    $quar     = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'DEP-QUAR'], ['name' => 'Dépôt Quarantaine', 'is_active' => true]);
    $p = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $sellable->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 3000]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $quar->id, 'quantity' => 5, 'reserved_quantity' => 0, 'avg_cost' => 3000]);

    return [$co, $sellable, $quar, $p];
}

it('libère 3 u de quarantaine vers le dépôt vendable : vendable +3, quarantaine 5−3=2, tracé', function () {
    [$co, $sellable, $quar, $p] = quarSetup();
    app(QuarantineService::class)->release($p->id, $co->id, 3, $sellable->id, 'Contrôle conforme', auth()->id());

    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $sellable->id)->value('quantity'))->toBe(3.0)
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity'))->toBe(2.0)
        ->and(\App\Models\AuditLog::where('action', 'quarantaine.liberation')->exists())->toBeTrue();
});

it('rebute 2 u depuis la quarantaine : sortie définitive, quarantaine 5−2=3, PAS de retour vendable', function () {
    [$co, $sellable, $quar, $p] = quarSetup();
    app(QuarantineService::class)->scrap($p->id, $co->id, 2, 'Non conforme irrécupérable', auth()->id());

    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity'))->toBe(3.0)
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $sellable->id)->value('quantity'))->toBe(0.0)
        ->and(\App\Models\AuditLog::where('action', 'quarantaine.rebut')->exists())->toBeTrue();
});

it('refuse toute libération sans décision qualité complète et au-delà du disponible', function () {
    [$co, $sellable, $quar, $p] = quarSetup();
    $svc = app(QuarantineService::class);

    // Motif obligatoire (décision qualité incomplète)
    expect(fn () => $svc->release($p->id, $co->id, 1, $sellable->id, '   ', auth()->id()))
        ->toThrow(\RuntimeException::class, 'Motif');

    // Au-delà du disponible (5 en quarantaine)
    try {
        $svc->release($p->id, $co->id, 99, $sellable->id, 'Trop', auth()->id());
        $this->fail('Libération au-delà du stock quarantaine aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('insuffisante');
    }

    // Destination = un autre dépôt de quarantaine → interdit
    $quar2 = Warehouse::create(['company_id' => $co->id, 'code' => 'QUAR-2', 'name' => 'Quarantaine 2', 'is_active' => true]);
    try {
        $svc->release($p->id, $co->id, 1, $quar2->id, 'Vers quarantaine', auth()->id());
        $this->fail('Libération vers un dépôt de quarantaine aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect(strtolower($e->getMessage()))->toContain('quarantaine');
    }

    // Rien n'a bougé : quarantaine toujours 5, vendable toujours 0
    expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $quar->id)->value('quantity'))->toBe(5.0)
        ->and((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $sellable->id)->value('quantity'))->toBe(0.0);
});
