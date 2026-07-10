<?php

/**
 * [CDC §9.2/§9.3] La fiche de traçabilité d'un lot doit permettre de retrouver :
 * fournisseur, poids, certificat qualité, OF ayant consommé le lot, et clients
 * livrés à partir de ce lot (StockController::lotTraceability).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\QualityCertificate;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ltAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = ltCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function ltCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'LT-2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'LotTrace Co'],
        ['email' => 'lt@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

it('affiche fournisseur, certificat, OF et clients impactés sur la fiche de traçabilité', function () {
    $user    = ltAdmin();
    $company = ltCompany();
    $this->actingAs($user);

    $supplier = Supplier::create([
        'code' => 'FOUR-LT', 'type' => 'entreprise', 'name' => 'Fournisseur Acier LT',
        'is_active' => true, 'country' => 'Burkina Faso', 'balance' => 0,
    ]);

    $rawMaterial = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $finished    = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'production_mode' => 'mto']);

    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-LT'],
        ['name' => 'Dépôt LT', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]
    );

    $lotNumber = 'LOT-LT-001';

    $lot = StockLot::create([
        'product_id'   => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'lot_number'   => $lotNumber,
        'quantity'     => 500,
        'unit_cost'    => 1200,
        'received_at'  => now()->subDays(5),
        'status'       => 'consomme',
    ]);

    $coil = Coil::create([
        'company_id'       => $company->id,
        'product_id'       => $rawMaterial->id,
        'supplier_id'      => $supplier->id,
        'lot_number'       => $lotNumber,
        'reference'        => 'BOB-LT-001',
        'color'            => 'RAL 9006',
        'thickness'        => 0.5,
        'width'            => 1000,
        'initial_weight'   => 500,
        'remaining_weight' => 0,
        'received_at'      => now()->subDays(5),
        'status'           => 'epuisee',
        'created_by'       => $user->id,
    ]);

    $certificate = QualityCertificate::create([
        'company_id'      => $company->id,
        'number'          => 'CERT-LT-001',
        'type'            => 'reception_bobine',
        'lot_number'      => $lotNumber,
        'fournisseur'     => $supplier->name,
        'date_reception'  => now()->subDays(5),
        'date_certificat' => now()->subDays(5),
        'poids_reel'      => 500,
        'largeur_mm'      => 1000,
        'epaisseur_mm'    => 0.5,
        'couleur'         => 'RAL 9006',
        'resultat'        => 'conforme',
        'controleur_id'   => $user->id,
    ]);

    $client  = Client::factory()->create(['is_active' => true]);
    $unit    = \App\Models\Unit::firstOrCreate(['name' => 'Pièce LT'], ['abbreviation' => 'pclt']);
    $order   = app(\App\Services\OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'status'    => 'confirme',
        'items'     => [[
            'product_id' => $finished->id, 'description' => 'Tôle bac',
            'quantity' => 100, 'unit_price' => 5_000, 'discount_percent' => 0, 'unit_id' => $unit->id,
        ]],
    ]);

    $bom = BillOfMaterial::create([
        'company_id' => $company->id, 'product_id' => $finished->id, 'name' => 'BOM LT',
        'is_active'  => true, 'sheet_type' => 'bac', 'thickness' => 0.5, 'usable_width' => 1000,
    ]);

    $productionOrder = ProductionOrder::create([
        'company_id'         => $company->id,
        'client_id'          => $client->id,
        'order_id'           => $order->id,
        'product_id'         => $finished->id,
        'bill_of_material_id'=> $bom->id,
        'number'             => 'OF-LT-001',
        'quantity_requested' => 100,
        'status'             => 'termine',
    ]);

    ProductionConsumption::create([
        'company_id'          => $company->id,
        'production_order_id' => $productionOrder->id,
        'coil_id'              => $coil->id,
        'weight_consumed'      => 500,
        'consumed_at'          => now()->subDays(3),
        'created_by'           => $user->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id'  => $client->id,
        'order_id'   => $order->id,
        'status'     => 'emise',
    ]);

    $response = $this->get(route('stocks.lots.traceability', $lot));

    $response->assertOk()
        ->assertSee($supplier->name)
        ->assertSee($certificate->number)
        ->assertSee('Conforme')
        ->assertSee($productionOrder->number)
        ->assertSee($client->name)
        ->assertSee($invoice->number);
});

it('affiche un message clair quand aucun certificat n\'existe pour le lot', function () {
    $user      = ltAdmin();
    $company   = ltCompany();
    $this->actingAs($user);

    $product   = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-LT2'],
        ['name' => 'Dépôt LT2', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]
    );

    $lot = StockLot::create([
        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'lot_number' => 'LOT-LT-002', 'quantity' => 10, 'unit_cost' => 500,
        'received_at' => now(), 'status' => 'disponible',
    ]);

    $this->get(route('stocks.lots.traceability', $lot))
        ->assertOk()
        ->assertSee('Aucun certificat qualité enregistré pour ce lot.')
        ->assertSee('Aucun client impacté trouvé pour ce lot.');
});
