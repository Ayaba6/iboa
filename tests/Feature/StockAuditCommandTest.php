<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('erp check stock uses stock uom quantities for converted movements', function () {
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'STK-AUD'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'Stock Audit Co'],
        ['email' => 'stock-audit@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    app()->instance('current_company', $company);

    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $warehouse = Warehouse::firstOrCreate(
        ['company_id' => $company->id, 'code' => 'WH-AUD-STK'],
        ['name' => 'Audit stock', 'is_active' => true, 'is_default' => true]
    );
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $stock = app(StockService::class);
    $stock->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'entree',
        'quantity' => 10,
        'unit_cost' => 1000,
        'reference_type' => 'seed',
        'reference_id' => 1,
    ]);
    $stock->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'sortie',
        'quantity' => 24,
        'uom' => 'ML',
        'conversion_factor' => 0.1667,
        'quantity_in_stock_uom' => 4,
        'stock_uom' => 'KG',
        'reference_type' => 'seed',
        'reference_id' => 2,
    ]);

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'))
        ->toBe(6.0);

    $this->artisan('erp:check-stock')->assertSuccessful();
});