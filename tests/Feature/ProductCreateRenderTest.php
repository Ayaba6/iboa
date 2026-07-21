<?php
use App\Models\{Company,FiscalYear,User};
use Spatie\Permission\Models\Role;
uses(\Tests\Concerns\RefreshDatabase::class);

it('products/create rend avec catégorie, sous-famille et sections pilotées', function () {
    $fy = FiscalYear::firstOrCreate(['label'=>'QC-2026'],['starts_at'=>'2026-01-01','ends_at'=>'2026-12-31','status'=>'ouvert','is_current'=>true]);
    $co = Company::firstOrCreate(['name'=>'QC Co'],['email'=>'qc@iboa.test','current_fiscal_year_id'=>$fy->id]);
    app()->instance('current_company', $co);
    (new \Database\Seeders\ItemCategorySeeder())->run();
    (new \Database\Seeders\SubFamilySeeder())->run();
    $u = User::factory()->create(['company_id'=>$co->id]);
    $u->assignRole(Role::firstOrCreate(['name'=>'super_admin','guard_name'=>'web']));
    $this->actingAs($u);

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('Catégorie de gestion')
        ->assertSee('PF_TOLE_MTO')
        ->assertSee('Sous-famille')
        ->assertSee('data-props', false)
        ->assertSee('$store.cat', false);
});
