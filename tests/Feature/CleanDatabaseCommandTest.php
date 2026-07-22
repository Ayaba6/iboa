<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;

it('a3:clean-database — dry-run inoffensif puis --fix corrige espaces, vides et casse', function () {
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    Company::firstOrCreate(['name' => 'CLN'], ['email' => 'c@c.io', 'current_fiscal_year_id' => $fy->id]);

    $client = Client::factory()->create(['name' => '  Sale Espace SA  ', 'trade_name' => '']);
    $product = \App\Models\Product::factory()->create();
    \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['code_article' => 'abc123']);

    // Dry-run : détecte, ne modifie rien
    $this->artisan('a3:clean-database')->assertSuccessful();
    expect($client->fresh()->name)->toBe('  Sale Espace SA  ');

    // --fix : corrige les trois familles d'anomalies
    $this->artisan('a3:clean-database', ['--fix' => true])->assertSuccessful();
    expect($client->fresh()->name)->toBe('Sale Espace SA')
        ->and($client->fresh()->trade_name)->toBeNull()
        ->and($product->fresh()->code_article)->toBe('ABC123');
});
