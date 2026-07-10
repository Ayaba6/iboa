<?php

/**
 * [Audit création OF — parité X3] Contrôles bloquants à la création,
 * bouton « Enregistrer + soumettre validation » et payload nomenclature/gamme
 * (composants + opérations affichés en vrais tableaux sur le formulaire).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function creAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CRE'], ['email' => 'cre@cre.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WC1'], ['name' => 'WC1', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('bloque la création d\'un OF sans article à lancer', function () {
    $this->actingAs(creAdmin());

    $this->post(route('production.orders.store'), [
        'quantity_requested' => 10,
    ])->assertSessionHasErrors('product_id');

    expect(ProductionOrder::count())->toBe(0);
});

it('bloque la création d\'un OF avec quantité demandée à zéro', function () {
    $this->actingAs(creAdmin());
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    $this->post(route('production.orders.store'), [
        'product_id' => $pf->id,
        'quantity_requested' => 0,
    ])->assertSessionHasErrors('quantity_requested');

    // Une quantité portée par les lignes de coupe suffit.
    $this->post(route('production.orders.store'), [
        'product_id' => $pf->id,
        'lines' => [['label' => 'Bac 6m', 'length' => 6, 'quantity' => 5]],
    ])->assertSessionDoesntHaveErrors();

    expect(ProductionOrder::count())->toBe(1);
});

it('« Enregistrer + soumettre validation » crée l\'OF et l\'envoie au Chef Atelier', function () {
    $this->actingAs(creAdmin());
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    $this->post(route('production.orders.store'), [
        'product_id' => $pf->id, 'quantity_requested' => 8, 'save_and_submit' => 1,
    ])->assertRedirect();

    $of = ProductionOrder::latest('id')->first();
    expect($of->status)->toBe('attente_chef');
});

it('le formulaire expose composants (stock/coût) et gamme de la nomenclature', function () {
    $this->actingAs(creAdmin());
    $co = Company::first();
    $wh = Warehouse::first();
    $pf   = Product::factory()->create();
    $comp = Product::factory()->create(['name' => 'Composant Payload Test']);
    ProductStock::create(['product_id' => $comp->id, 'warehouse_id' => $wh->id, 'quantity' => 77, 'reserved_quantity' => 2, 'avg_cost' => 450]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM Payload', 'is_active' => true]);
    BomLine::create(['bill_of_material_id' => $bom->id, 'product_id' => $comp->id, 'quantity_per_meter' => 2.5, 'sort_order' => 1]);

    $resp = $this->get(route('production.orders.create'));
    $resp->assertOk()
        ->assertSee('Composant Payload Test')  // payload JSON boms embarqué
        ->assertSee('Prévisionnel')
        ->assertSee('Stock disponible');

    // Stock dispo = 77 − 2 = 75, CMP 450 — présents dans le payload.
    expect($resp->getContent())->toContain('75')->toContain('450');
});
