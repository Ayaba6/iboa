<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

it('ignore les lignes comptables en brouillon dans les soldes audites', function () {
    $company = Company::factory()->create();
    $classId = DB::table('account_classes')->insertGetId([
        'company_id' => $company->id,
        'number' => 6,
        'name' => 'Charges',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $accountId = DB::table('accounts')->insertGetId([
        'company_id' => $company->id,
        'account_class_id' => $classId,
        'code' => 'AUD-661',
        'name' => 'Compte audit brouillon',
        'type' => 'charge',
        'is_detail' => true,
        'is_active' => true,
        'debit_balance' => 0,
        'credit_balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $journalTypeId = DB::table('journal_types')->insertGetId([
        'company_id' => $company->id,
        'code' => 'AUD',
        'name' => 'Audit',
        'type' => 'operations_diverses',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entryId = DB::table('journal_entries')->insertGetId([
        'company_id' => $company->id,
        'journal_type_id' => $journalTypeId,
        'number' => 'AUD-BROUILLON-001',
        'entry_date' => now()->toDateString(),
        'description' => 'Écriture non validée',
        'status' => 'brouillon',
        'total_debit' => 1000,
        'total_credit' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('journal_entry_lines')->insert([
        'journal_entry_id' => $entryId,
        'account_id' => $accountId,
        'label' => 'Brouillon à ignorer',
        'debit' => 1000,
        'credit' => 0,
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('audit:business', ['--module' => 'Compta', '--json' => true])
        ->assertSuccessful();
});

it('audite les mouvements dans leur unite de stock', function () {
    $company = Company::factory()->create();
    $product = Product::factory()->create([
        'is_stockable' => true,
    ]);
    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'code' => 'AUD-WH',
        'name' => 'Dépôt audit',
        'is_active' => true,
    ]);
    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 6,
        'reserved_quantity' => 0,
        'avg_cost' => 0,
    ]);
    StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'entree',
        'quantity' => 10,
        'uom' => 'KG',
        'quantity_in_stock_uom' => 10,
        'occurred_at' => now(),
    ]);
    StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'sortie',
        'quantity' => 24,
        'uom' => 'ML',
        'quantity_in_stock_uom' => 4,
        'occurred_at' => now(),
    ]);

    $this->artisan('audit:business', ['--module' => 'Stock', '--json' => true])
        ->assertSuccessful();
});
