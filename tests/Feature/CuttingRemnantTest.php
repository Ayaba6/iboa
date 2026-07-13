<?php

/**
 * [PRO-08] Ré-entrée stock des chutes réutilisables à la clôture de la découpe.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\CuttingOptimization;
use App\Modules\Production\Services\CuttingRemnantService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function remnantAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'REM'], ['email' => 'rem@x.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function remnantOpt(int $companyId, int $productId): CuttingOptimization
{
    return CuttingOptimization::create([
        'company_id' => $companyId, 'code' => 'OPT-R', 'status' => 'optimisee',
        'standard_length' => 12, 'cut_tolerance_mm' => 0,
        'valorize_offcuts' => true, 'min_reusable_offcut' => 6, 'reusable_offcut_m' => 7,
        'product_id' => $productId,
    ]);
}

it('clôture la découpe et ré-entre la chute au PMP dans le dépôt Chutes', function () {
    $this->actingAs(remnantAdmin());
    $cid = currentCompany()->id;
    $product = Product::factory()->create(['weighted_avg_cost' => 1500]);
    $opt = remnantOpt($cid, $product->id);

    $this->post(route('production.cutting.close', $opt))->assertRedirect();

    $opt->refresh();
    expect($opt->status)->toBe('cloturee');

    $mv = StockMovement::where('reference_type', 'cutting_optimization')->where('reference_id', $opt->id)->first();
    expect($mv)->not->toBeNull();
    expect($mv->type)->toBe('entree');
    expect((float) $mv->quantity)->toBe(7.0);
    expect((float) $mv->unit_cost)->toBe(1500.0);
    expect($mv->product_id)->toBe($product->id);

    $wh = Warehouse::withoutGlobalScopes()->where('company_id', $cid)->where('code', 'CHUTES')->first();
    expect($wh)->not->toBeNull();
    expect($mv->warehouse_id)->toBe($wh->id);
});

it('est idempotent : pas de double ré-entrée', function () {
    $this->actingAs(remnantAdmin());
    $cid = currentCompany()->id;
    $product = Product::factory()->create(['weighted_avg_cost' => 1000]);
    $opt = remnantOpt($cid, $product->id);

    $svc = app(CuttingRemnantService::class);
    $m1 = $svc->reenter($opt);
    $m2 = $svc->reenter($opt->fresh());

    expect($m1->id)->toBe($m2->id);
    expect(StockMovement::where('reference_type', 'cutting_optimization')->where('reference_id', $opt->id)->count())->toBe(1);
});

it('ne ré-entre rien si la valorisation est désactivée', function () {
    $this->actingAs(remnantAdmin());
    $cid = currentCompany()->id;
    $product = Product::factory()->create(['weighted_avg_cost' => 1000]);
    $opt = remnantOpt($cid, $product->id);
    $opt->update(['valorize_offcuts' => false]);

    $this->post(route('production.cutting.close', $opt))->assertRedirect();

    expect($opt->fresh()->status)->toBe('cloturee');
    expect(StockMovement::where('reference_type', 'cutting_optimization')->count())->toBe(0);
});

it('refuse de clôturer deux fois', function () {
    $this->actingAs(remnantAdmin());
    $cid = currentCompany()->id;
    $product = Product::factory()->create(['weighted_avg_cost' => 1000]);
    $opt = remnantOpt($cid, $product->id);

    $this->post(route('production.cutting.close', $opt));
    $this->post(route('production.cutting.close', $opt))->assertSessionHas('error');

    expect(StockMovement::where('reference_type', 'cutting_optimization')->where('reference_id', $opt->id)->count())->toBe(1);
});
