<?php

/**
 * [VEN Marge] Ventilation de la marge brute par commercial et par site/dépôt.
 */

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesInsightsService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

function marginSeed(): array
{
    $co = Company::firstOrCreate(['name' => 'MRG Co'], ['email' => 'mrg@iboa.test']);
    app()->instance('current_company', $co); // currentCompany() lit ce binding

    $repA = User::factory()->create(['name' => 'Rep A']);
    $repB = User::factory()->create(['name' => 'Rep B']);
    $wh1  = Warehouse::create(['company_id' => $co->id, 'name' => 'Ouaga', 'code' => 'OUA']);
    $wh2  = Warehouse::create(['company_id' => $co->id, 'name' => 'Bobo', 'code' => 'BOB']);
    $prod = Product::factory()->create();

    $mkInvoice = function ($rep, $wh, $ca, $cost) use ($co, $prod) {
        $inv = Invoice::create([
            'company_id'   => $co->id,
            'client_id'    => \App\Models\Client::factory()->create()->id,
            'number'       => 'F'.uniqid(),
            'type'         => 'facture',
            'status'       => 'emise',
            'issued_at'    => now(),
            'sales_rep_id' => $rep->id,
            'warehouse_id' => $wh->id,
            'subtotal_ht'  => $ca,
            'total_ttc'    => $ca,
            'remaining_amount' => $ca,
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id'    => $inv->id,
            'product_id'    => $prod->id,
            'description'   => $prod->name,
            'quantity'      => 1,
            'unit_price'    => $ca,
            'unit_cost'     => $cost,
            'line_total_ht' => $ca,
            'line_tax'      => 0,
            'line_total_ttc'=> $ca,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return $inv;
    };

    $mkInvoice($repA, $wh1, 100000, 60000); // marge 40000
    $mkInvoice($repA, $wh1, 50000, 20000);  // marge 30000
    $mkInvoice($repB, $wh2, 80000, 70000);  // marge 10000

    return compact('co', 'repA', 'repB', 'wh1', 'wh2');
}

it('ventile la marge par commercial', function () {
    $ctx = marginSeed();
    $rows = app(SalesInsightsService::class)->marginBySalesRep(12);

    $a = $rows->firstWhere('rep_id', $ctx['repA']->id);
    $b = $rows->firstWhere('rep_id', $ctx['repB']->id);

    expect((float) $a['ca'])->toBe(150000.0);
    expect((float) $a['marge'])->toBe(70000.0);
    expect((int) $a['invoices'])->toBe(2);
    expect((float) $b['marge'])->toBe(10000.0);
    // Rep A meilleure marge → en tête
    expect($rows->first()['rep_id'])->toBe($ctx['repA']->id);
});

it('ventile la marge par site', function () {
    $ctx = marginSeed();
    $rows = app(SalesInsightsService::class)->marginBySite(12);

    $ouaga = $rows->firstWhere('site_id', $ctx['wh1']->id);
    $bobo  = $rows->firstWhere('site_id', $ctx['wh2']->id);

    expect((float) $ouaga['marge'])->toBe(70000.0);
    expect((float) $bobo['marge'])->toBe(10000.0);
    expect(round($bobo['taux'], 1))->toBe(12.5);
});
