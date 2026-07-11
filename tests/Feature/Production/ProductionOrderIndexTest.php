<?php

/**
 * [Refonte Prod X3 §5] Liste des OF : colonnes enrichies + filtres X3
 * (statut, priorité, origine, commande liée, vues rapides en_retard / a_lancer / clotures).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function idxAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'IDX'], ['email' => 'idx@idx.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WI'], ['name' => 'WI', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function idxOf(array $attrs = []): ProductionOrder
{
    $co = Company::first();
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    return ProductionOrder::create(array_merge([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-IDX-' . uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 10, 'quantity_produced' => 0, 'product_id' => $pf->id,
    ], $attrs));
}

it('affiche les colonnes X3 de la liste des OF', function () {
    $this->actingAs(idxAdmin());
    idxOf();

    $this->get(route('production.orders.index'))
        ->assertOk()
        ->assertSee('Origine')->assertSee('Commande')->assertSee('Qté')
        ->assertSee('Produite')->assertSee('Reste')->assertSee('Métrage')
        ->assertSee('Priorité')->assertSee('Prévue')->assertSee('Responsable');
});

it('filtre par statut et priorité', function () {
    $this->actingAs(idxAdmin());
    $a = idxOf(['status' => 'en_cours', 'priorite' => 'urgente', 'launched_at' => now()]);
    $b = idxOf(['status' => 'brouillon', 'priorite' => 'basse']);

    $this->get(route('production.orders.index', ['status' => 'en_cours', 'priorite' => 'urgente']))
        ->assertOk()->assertSee($a->number)->assertDontSee($b->number);
});

it('vue rapide « OF en retard » : date fin prévue dépassée + statut actif', function () {
    $this->actingAs(idxAdmin());
    $retard = idxOf(['status' => 'en_cours', 'date_fin_prevue' => now()->subDays(3), 'launched_at' => now()->subDays(10)]);
    $ok     = idxOf(['status' => 'en_cours', 'date_fin_prevue' => now()->addDays(3), 'launched_at' => now()]);
    $fini   = idxOf(['status' => 'termine', 'date_fin_prevue' => now()->subDays(3)]);

    $this->get(route('production.orders.index', ['vue' => 'en_retard']))
        ->assertOk()->assertSee($retard->number)
        ->assertDontSee($ok->number)->assertDontSee($fini->number);
});

it('vue rapide « OF à lancer » : brouillon + attentes de validation', function () {
    $this->actingAs(idxAdmin());
    $draft  = idxOf(['status' => 'brouillon']);
    $chef   = idxOf(['status' => 'attente_chef']);
    $lance  = idxOf(['status' => 'lance', 'launched_at' => now()]);

    $this->get(route('production.orders.index', ['vue' => 'a_lancer']))
        ->assertOk()->assertSee($draft->number)->assertSee($chef->number)
        ->assertDontSee($lance->number);
});

it('filtre par commande de vente liée', function () {
    $this->actingAs(idxAdmin());
    $co = Company::first();
    $client = \App\Models\Client::factory()->create();
    $cmd = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-IDX-777', 'status' => 'confirme', 'issued_at' => now(),
    ]);
    $lie   = idxOf(['order_id' => $cmd->id, 'client_id' => $client->id]);
    $autre = idxOf();

    $this->get(route('production.orders.index', ['commande' => 'CMD-IDX-777']))
        ->assertOk()->assertSee($lie->number)->assertDontSee($autre->number);
});
