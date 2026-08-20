<?php

/**
 * [Production] Le bandeau de la liste des OF compte ce que les lignes montrent.
 *
 * Le KPI « Mètres produits » ne sommait que les OF au statut `termine`, alors
 * que son propre commentaire annonçait « mètres réellement produits ». Un mètre
 * déclaré sur un ordre en cours est pourtant du métal sorti de la ligne : le
 * bandeau affichait 26 m au-dessus de lignes qui en totalisaient 27.
 *
 * Les ordres ANNULÉS restent hors du compte : leurs déclarations sont censées
 * avoir été contre-passées.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ofKpiAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'KPI'], ['email' => 'kpi@kpi.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WK'], ['name' => 'WK', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

function ofKpiCreer(string $statut, float $metres): ProductionOrder
{
    $co = Company::first();
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-KPI-'.uniqid(), 'status' => $statut,
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => Product::factory()->create(['is_manufacturable' => true])->id,
    ]);

    ProductionOutput::create([
        'company_id' => $co->id,
        'production_order_id' => $of->id,
        'total_meters' => $metres,
        'quantity' => 1,
    ]);

    return $of;
}

it('compte les mètres déclarés sur un ordre encore en cours', function () {
    $this->actingAs(ofKpiAdmin());
    ofKpiCreer('termine', 24);
    ofKpiCreer('en_cours', 3);

    $stats = $this->get(route('production.orders.index'))->assertOk()->viewData('stats');

    // 24 + 3 : le métal produit hier sur un ordre non clos existe autant que
    // celui d'un ordre clos.
    expect((float) $stats['metres'])->toBe(27.0);
});

it('exclut les mètres d\'un ordre annulé', function () {
    $this->actingAs(ofKpiAdmin());
    ofKpiCreer('termine', 10);
    ofKpiCreer('annule', 99);

    $stats = $this->get(route('production.orders.index'))->assertOk()->viewData('stats');

    expect((float) $stats['metres'])->toBe(10.0);
});

it('fait dire la même chose au bandeau et aux lignes', function () {
    $this->actingAs(ofKpiAdmin());
    ofKpiCreer('termine', 12);
    ofKpiCreer('en_cours', 5);
    ofKpiCreer('lance', 2);

    $reponse = $this->get(route('production.orders.index'))->assertOk();

    // Les lignes affichent la somme des déclarations de chaque OF ; le bandeau
    // doit être leur total, hors annulés. L'égalité est la propriété testée,
    // pas la valeur : elle survivra au changement des données.
    $sommeLignes = (float) $reponse->viewData('orders')
        ->reject(fn ($o) => $o->status === 'annule')
        ->sum('total_meters');

    expect((float) $reponse->viewData('stats')['metres'])->toBe($sommeLignes);
    expect($sommeLignes)->toBeGreaterThan(0);
});
