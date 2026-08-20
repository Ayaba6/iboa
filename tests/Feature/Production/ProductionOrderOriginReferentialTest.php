<?php

/**
 * [MTS §2] Référentiel des origines d'OF — une seule source.
 *
 * Il était recopié à TROIS endroits, chacun avec sa graphie : la règle de
 * validation du contrôleur, le sélecteur du formulaire, celui de la liste.
 * Ajouter une origine obligeait à penser aux trois, et en oublier un suffisait
 * pour qu'une valeur soit acceptée en base sans jamais s'afficher — ou l'inverse.
 *
 * Ce n'est pas une crainte théorique : c'est exactement la dérive qui a laissé
 * `PurchaseInsightsService` filtrer sur des statuts au féminin absents de
 * l'énumération, mettant deux indicateurs achats à zéro en permanence.
 *
 * Deux origines du cahier des charges manquaient au passage — « prévision » et
 * « besoin interne » — pourtant citées comme déclencheurs de production pour
 * stock. Elles sont ajoutées ici.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function refUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'REFORIG-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'RefOrig Co'], [
        'email' => 'reforig@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WREFO'], [
        'name' => 'WREFO', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'reforig_prod', 'guard_name' => 'web']);
    foreach (['production.view', 'production.create'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('couvre les cinq origines du cahier des charges', function () {
    // MTS §2 : MRP, prévision, stock minimum, besoin interne, décision manuelle.
    // Les deux dernières manquaient au référentiel.
    expect(ProductionOrder::origins())
        ->toContain('mrp')
        ->toContain('prevision')
        ->toContain('stock_minimum')
        ->toContain('besoin_interne')
        ->toContain('manuel')
        ->toContain('commande_client');
});

it('accepte en base chaque origine du référentiel', function () {
    // Une valeur exposée par le référentiel mais refusée par la colonne serait
    // un piège : le formulaire la proposerait, l'enregistrement échouerait.
    refUser();
    $co = Company::first();

    foreach (ProductionOrder::origins() as $origine) {
        $of = ProductionOrder::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'number' => 'OF-REFO-'.uniqid(), 'status' => 'brouillon', 'origin' => $origine,
        ]);

        expect($of->fresh()->origin)->toBe($origine);
    }
});

it('libelle chaque origine, en version longue comme en abrégée', function () {
    // Une clé sans libellé s'afficherait brute à l'écran (« besoin_interne »).
    foreach (ProductionOrder::origins() as $origine) {
        expect(ProductionOrder::ORIGIN_LABELS[$origine] ?? null)->not->toBeNull()
            ->and(ProductionOrder::ORIGIN_LABELS_SHORT[$origine] ?? null)->not->toBeNull();
    }

    // Les deux jeux portent EXACTEMENT les mêmes clés : c'est ce qui garantit
    // qu'aucune origine n'est visible d'un écran et absente de l'autre.
    expect(array_keys(ProductionOrder::ORIGIN_LABELS))
        ->toBe(array_keys(ProductionOrder::ORIGIN_LABELS_SHORT));
});

it('propose toutes les origines dans le formulaire de création', function () {
    refUser();

    $html = $this->get(route('production.orders.create'))->assertOk()->getContent();

    foreach (ProductionOrder::ORIGIN_LABELS as $cle => $libelle) {
        expect($html)->toContain('value="'.$cle.'"');
    }
    expect($html)->toContain('Prévision de vente')
        ->and($html)->toContain('Besoin interne');
});

it('propose toutes les origines au filtre de la liste', function () {
    refUser();

    $html = $this->get(route('production.orders.index'))->assertOk()->getContent();

    foreach (ProductionOrder::ORIGIN_LABELS_SHORT as $cle => $libelle) {
        expect($html)->toContain('value="'.$cle.'"');
    }
});

it('accepte une origine nouvelle au formulaire HTTP', function () {
    // La règle de validation lit désormais le référentiel : une origine ajoutée
    // au modèle est acceptée sans toucher au contrôleur.
    refUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $this->post(route('production.orders.store'), [
        'product_id' => $p->id,
        'quantity_requested' => 10,
        'origin' => ProductionOrder::ORIGIN_BESOIN_INTERNE,
    ])->assertSessionHasNoErrors();

    expect(ProductionOrder::where('product_id', $p->id)->value('origin'))
        ->toBe(ProductionOrder::ORIGIN_BESOIN_INTERNE);
});

it('refuse une origine étrangère au référentiel', function () {
    refUser();
    $p = Product::factory()->create(['production_mode' => 'mts', 'is_manufacturable' => true]);

    $this->post(route('production.orders.store'), [
        'product_id' => $p->id,
        'quantity_requested' => 10,
        'origin' => 'inventee_de_toutes_pieces',
    ])->assertSessionHasErrors('origin');

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});

it('filtre la liste sur une origine donnée', function () {
    refUser();
    $co = Company::first();

    foreach ([ProductionOrder::ORIGIN_PREVISION, ProductionOrder::ORIGIN_MANUEL] as $origine) {
        ProductionOrder::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'number' => 'OF-FILTRE-'.$origine, 'status' => 'brouillon', 'origin' => $origine,
        ]);
    }

    $html = $this->get(route('production.orders.index', ['origin' => ProductionOrder::ORIGIN_PREVISION]))
        ->assertOk()->getContent();

    expect($html)->toContain('OF-FILTRE-'.ProductionOrder::ORIGIN_PREVISION)
        ->and($html)->not->toContain('OF-FILTRE-'.ProductionOrder::ORIGIN_MANUEL);
});
