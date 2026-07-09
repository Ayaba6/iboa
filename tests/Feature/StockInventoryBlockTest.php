<?php

/**
 * [CDC §8.5] Un mouvement de stock (entrée/sortie/transfert) ne doit jamais
 * être appliqué sur un dépôt dont une session d'inventaire est en cours —
 * sinon le comptage physique diverge du stock théorique pendant le comptage.
 *
 * Couvre les deux points d'entrée qui modifient ProductStock.quantity :
 *   - StockService::recordMovement()      (entrées/sorties/ajustements directs)
 *   - StockTransferService::ship/receive  (transferts inter-dépôts)
 *
 * InventoryService::validate() (clôture de l'inventaire) n'est PAS concerné :
 * il manipule ProductStock directement sans passer par ces deux services.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockService;
use App\Services\StockTransferService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function invAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = invCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function invCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'IV-2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'InventoryFlow Co'],
        ['email' => 'iv@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function invWarehouse(string $code): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'company_id' => invCompany()->id, 'is_active' => true, 'is_default' => false]
    );
}

describe('Blocage mouvements pendant inventaire — StockService', function () {

    it('bloque une entrée de stock sur un dépôt en cours d\'inventaire', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh      = invWarehouse('WH-INV-A');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        InventorySession::create([
            'company_id'   => invCompany()->id,
            'warehouse_id' => $wh->id,
            'number'       => 'INV-TEST-001',
            'type'         => 'complet',
            'status'       => 'en_cours',
            'started_at'   => now(),
            'created_by'   => $user->id,
        ]);

        expect(fn () => app(StockService::class)->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $wh->id,
            'type'         => 'entree',
            'quantity'     => 10,
            'unit_cost'    => 1000,
        ]))->toThrow(\RuntimeException::class);
    });

    it('autorise les mouvements sur un autre dépôt non concerné par l\'inventaire', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $whCounted   = invWarehouse('WH-INV-B1');
        $whUntouched = invWarehouse('WH-INV-B2');
        $product     = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        InventorySession::create([
            'company_id'   => invCompany()->id,
            'warehouse_id' => $whCounted->id,
            'number'       => 'INV-TEST-002',
            'type'         => 'complet',
            'status'       => 'en_cours',
            'started_at'   => now(),
            'created_by'   => $user->id,
        ]);

        $movement = app(StockService::class)->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $whUntouched->id,
            'type'         => 'entree',
            'quantity'     => 5,
            'unit_cost'    => 2000,
        ]);

        expect($movement)->not->toBeNull();
        expect((float) ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $whUntouched->id)->value('quantity'))->toBe(5.0);
    });

    it('autorise de nouveau les mouvements une fois l\'inventaire validé', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh      = invWarehouse('WH-INV-C');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        $session = InventorySession::create([
            'company_id'   => invCompany()->id,
            'warehouse_id' => $wh->id,
            'number'       => 'INV-TEST-003',
            'type'         => 'complet',
            'status'       => 'en_cours',
            'started_at'   => now(),
            'created_by'   => $user->id,
        ]);

        // InventoryService::validate() manipule ProductStock directement —
        // pas affecté par le garde-fou de StockService.
        app(InventoryService::class)->validate($session);
        $session->refresh();
        expect($session->status)->toBe('valide');

        $movement = app(StockService::class)->recordMovement([
            'product_id'   => $product->id,
            'warehouse_id' => $wh->id,
            'type'         => 'entree',
            'quantity'     => 8,
            'unit_cost'    => 1500,
        ]);

        expect($movement)->not->toBeNull();
    });
});

describe('Mouvement manuel bloqué — le stock n\'est impacté qu\'au déblocage', function () {

    it('n\'applique pas les lignes au stock quand is_blocked est coché, puis les applique au déblocage', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh = invWarehouse('WH-INV-J');
        $p  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
        ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 92, 'reserved_quantity' => 0, 'avg_cost' => 100]);

        // Création BLOQUÉE : le stock ne bouge pas.
        $this->post(route('stocks.movement.store'), [
            'occurred_at'     => now()->toDateString(),
            'warehouse_to_id' => $wh->id,
            'reason'          => 'ajustement',
            'is_blocked'      => 1,
            'items'           => [['product_id' => $p->id, 'quantity' => 5, 'unit_cost' => 100]],
        ])->assertRedirect();

        expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(92.0);

        $mvt = \App\Models\ManualStockMovement::latest('id')->first();
        expect($mvt->is_blocked)->toBeTrue()
            ->and($mvt->status)->toBe('bloque')
            ->and($mvt->lines)->toHaveCount(1);

        // Déblocage : les lignes sont appliquées.
        $this->post(route('stocks.movement.unblock', $mvt))->assertRedirect();
        expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(97.0)
            ->and($mvt->fresh()->is_blocked)->toBeFalse()
            ->and($mvt->fresh()->status)->toBe('saisi');
    });

    it('applique immédiatement quand non bloqué', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh = invWarehouse('WH-INV-K');
        $p  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
        ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 10, 'reserved_quantity' => 0, 'avg_cost' => 100]);

        $this->post(route('stocks.movement.store'), [
            'occurred_at'     => now()->toDateString(),
            'warehouse_to_id' => $wh->id,
            'reason'          => 'ajustement',
            'items'           => [['product_id' => $p->id, 'quantity' => 3, 'unit_cost' => 100]],
        ])->assertRedirect();

        expect((float) ProductStock::where('product_id', $p->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(13.0);
    });
});

describe('Sauvegarde comptage inventaire — ne remet pas à zéro les articles non saisis', function () {

    it('laisse counted_quantity = null pour un article non saisi (pas d\'écart fantôme)', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh = invWarehouse('WH-INV-G');
        $p1 = Product::factory()->create(['is_stockable' => true]);
        $p2 = Product::factory()->create(['is_stockable' => true]);

        $session = InventorySession::create([
            'company_id' => invCompany()->id, 'warehouse_id' => $wh->id,
            'number' => 'INV-CNT-001', 'type' => 'complet', 'status' => 'en_cours',
            'started_at' => now(), 'created_by' => $user->id,
        ]);
        $i1 = $session->items()->create(['product_id' => $p1->id, 'theoretical_quantity' => 100, 'counted_quantity' => null, 'unit_cost' => 1000]);
        $i2 = $session->items()->create(['product_id' => $p2->id, 'theoretical_quantity' => 88,  'counted_quantity' => null, 'unit_cost' => 500]);

        // L'opérateur ne saisit QUE l'article 1 (95) ; l'article 2 reste vide ('').
        app(InventoryService::class)->saveCount($session, [
            ['id' => $i1->id, 'counted_quantity' => '95'],
            ['id' => $i2->id, 'counted_quantity' => ''],
        ]);

        // Article 1 : compté 95, écart -5. Article 2 : NON compté (null), pas d'écart.
        expect((float) $i1->fresh()->counted_quantity)->toBe(95.0)
            ->and((float) $i1->fresh()->variance_quantity)->toBe(-5.0)
            ->and($i2->fresh()->counted_quantity)->toBeNull()
            ->and((float) $i2->fresh()->variance_quantity)->toBe(0.0);
    });

    it('un 0 explicitement saisi est bien compté comme zéro', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $wh = invWarehouse('WH-INV-H');
        $p  = Product::factory()->create(['is_stockable' => true]);
        $session = InventorySession::create([
            'company_id' => invCompany()->id, 'warehouse_id' => $wh->id,
            'number' => 'INV-CNT-002', 'type' => 'complet', 'status' => 'en_cours',
            'started_at' => now(), 'created_by' => $user->id,
        ]);
        $item = $session->items()->create(['product_id' => $p->id, 'theoretical_quantity' => 20, 'counted_quantity' => null, 'unit_cost' => 100]);

        app(InventoryService::class)->saveCount($session, [['id' => $item->id, 'counted_quantity' => '0']]);

        expect((float) $item->fresh()->counted_quantity)->toBe(0.0)
            ->and((float) $item->fresh()->variance_quantity)->toBe(-20.0);
    });
});

describe('Blocage transferts pendant inventaire — StockTransferService', function () {

    it('bloque l\'expédition si le dépôt source est en cours d\'inventaire', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $source = invWarehouse('WH-INV-D1');
        $dest   = invWarehouse('WH-INV-D2');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

        InventorySession::create([
            'company_id' => invCompany()->id, 'warehouse_id' => $source->id,
            'number' => 'INV-TEST-004', 'type' => 'complet', 'status' => 'en_cours',
            'started_at' => now(), 'created_by' => $user->id,
        ]);

        $transferSvc = app(StockTransferService::class);
        $transfer = $transferSvc->create([
            'from_warehouse_id' => $source->id,
            'to_warehouse_id'   => $dest->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1000]],
        ]);

        expect(fn () => $transferSvc->ship($transfer))->toThrow(\RuntimeException::class);
    });

    it('bloque la réception si le dépôt destination est en cours d\'inventaire', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $source = invWarehouse('WH-INV-E1');
        $dest   = invWarehouse('WH-INV-E2');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

        $transferSvc = app(StockTransferService::class);
        $transfer = $transferSvc->create([
            'from_warehouse_id' => $source->id,
            'to_warehouse_id'   => $dest->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1000]],
        ]);
        $transferSvc->ship($transfer);
        $transfer->refresh();
        expect($transfer->status)->toBe('en_transit');

        // L'inventaire démarre APRES l'expédition, sur le dépôt destination.
        InventorySession::create([
            'company_id' => invCompany()->id, 'warehouse_id' => $dest->id,
            'number' => 'INV-TEST-005', 'type' => 'complet', 'status' => 'en_cours',
            'started_at' => now(), 'created_by' => $user->id,
        ]);

        expect(fn () => $transferSvc->receive($transfer))->toThrow(\RuntimeException::class);
    });

    it('reporte le CMP source à la destination quand aucun coût n\'est saisi sur la ligne', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $source = invWarehouse('WH-INV-I1');
        $dest   = invWarehouse('WH-INV-I2');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        // Source valorisée à 3048 F/u ; la destination ne possède pas l'article.
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 3048]);

        $transferSvc = app(StockTransferService::class);
        $transfer = $transferSvc->create([
            'from_warehouse_id' => $source->id,
            'to_warehouse_id'   => $dest->id,
            'items' => [['product_id' => $product->id, 'quantity' => 20]], // AUCUN unit_cost saisi
        ]);
        $transferSvc->ship($transfer);
        $transferSvc->receive($transfer->fresh());

        // La ligne a hérité du CMP source, la destination est valorisée à 3048, pas 0.
        expect((float) $transfer->fresh()->items->first()->unit_cost)->toBe(3048.0)
            ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $dest->id)->value('avg_cost'))->toBe(3048.0);

        // Les deux mouvements (sortie + entrée) portent le coût réel, pas 0.
        $movements = \App\Models\StockMovement::where('reference_type', \App\Models\StockTransfer::class)
            ->where('reference_id', $transfer->id)->get();
        expect($movements)->toHaveCount(2)
            ->and((float) $movements->firstWhere('type', 'sortie')->unit_cost)->toBe(3048.0)
            ->and((float) $movements->firstWhere('type', 'entree')->unit_cost)->toBe(3048.0);
    });

    it('transfert complet (ship+receive) réussit sans inventaire en cours', function () {
        $user = invAdmin();
        $this->actingAs($user);

        $source = invWarehouse('WH-INV-F1');
        $dest   = invWarehouse('WH-INV-F2');
        $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $source->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

        $transferSvc = app(StockTransferService::class);
        $transfer = $transferSvc->create([
            'from_warehouse_id' => $source->id,
            'to_warehouse_id'   => $dest->id,
            'items' => [['product_id' => $product->id, 'quantity' => 15, 'unit_cost' => 1000]],
        ]);
        $transferSvc->ship($transfer);
        $transferSvc->receive($transfer->fresh());
        $transfer->refresh();

        expect($transfer->status)->toBe('recu')
            ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $source->id)->value('quantity'))->toBe(35.0)
            ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $dest->id)->value('quantity'))->toBe(15.0);
    });
});
