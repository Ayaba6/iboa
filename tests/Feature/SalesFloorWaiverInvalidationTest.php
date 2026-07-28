<?php

/**
 * [Ventes §8 — parcours B] Invalidation de la dérogation « sous prix plancher ».
 *
 * Exigence : toute modification de PRIX, QUANTITÉ, REMISE, TAXE ou COÛT doit
 * invalider une dérogation déjà approuvée.
 *
 * Ces tests vérifient chaque axe SÉPARÉMENT. Un test global qui modifierait
 * plusieurs champs à la fois ne dirait pas lequel protège réellement — il
 * passerait même si un seul des cinq était pris en compte.
 *
 * Mécanisme sous-jacent : `pricing_signature`, un SHA-256 des champs qui
 * déterminent le respect du plancher. Une dérogation n'est valable que pour la
 * signature exacte au moment de son approbation.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesFloorWaiver;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use App\Services\SalesFloorWaiverService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{order:Order,requester:User,approver:User,product:Product} */
function waiverScenario(): array
{
    $year = FiscalYear::firstOrCreate(['label' => 'WAIVER-INV-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Waiver Inv Co'], [
        'email' => 'waiver-inv@iboa.test', 'current_fiscal_year_id' => $year->id,
    ]);
    app()->instance('current_company', $company);

    foreach (['sales.submit', 'sales_below_floor.request', 'sales_below_floor.approve'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    // Le demandeur détient VOLONTAIREMENT la permission d'approbation : c'est la
    // seule façon de prouver que le refus vient de la séparation des acteurs
    // (« on n'approuve pas sa propre demande ») et non d'un simple droit manquant.
    $requester = User::factory()->create(['company_id' => $company->id]);
    $requester->givePermissionTo(['sales.submit', 'sales_below_floor.request', 'sales_below_floor.approve']);
    $approver = User::factory()->create(['company_id' => $company->id]);
    $approver->givePermissionTo(['sales_below_floor.approve']);

    $product = Product::factory()->create([
        'type' => 'simple', 'is_manufacturable' => true, 'cout_standard' => 100,
        'weighted_avg_cost' => 90, 'margin_rate_target' => 20, 'sale_price' => 80,
        'uv_to_us_coef' => 1,
    ]);
    $client = Client::factory()->create(['credit_limit' => 10_000_000, 'payment_mode' => 'credit', 'balance' => 0]);

    test()->actingAs($requester);
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now(),
        'items' => [[
            'product_id' => $product->id, 'description' => $product->name,
            'quantity' => 2, 'unit_price' => 80, 'discount_percent' => 0, 'tax_rate_value' => 0,
        ]],
    ]);

    return ['order' => $order, 'requester' => $requester, 'approver' => $approver, 'product' => $product];
}

/** Demande + approbation par un acteur DISTINCT, puis soumission réussie. */
function waiverApproveAndSubmit(array $ctx): void
{
    test()->actingAs($ctx['requester']);
    $waiver = app(SalesFloorWaiverService::class)->request(
        $ctx['order'], $ctx['order']->items->first(), 'Offre stratégique documentée pour ce client.'
    );

    test()->actingAs($ctx['approver']);
    app(SalesFloorWaiverService::class)->approve($waiver, 'Accord financier exceptionnel');

    test()->actingAs($ctx['requester']);
    app(CommercialWorkflowService::class)->submit($ctx['order']->fresh());

    expect($ctx['order']->fresh()->status)->toBe('en_attente_validation');
}

/**
 * Remet la commande en brouillon pour tenter une nouvelle soumission.
 *
 * Écriture par le constructeur de requêtes, PAS par le modèle : le modèle en
 * mémoire porte encore `status = brouillon` (il a été soumis via une instance
 * rechargée), donc `update()` ne verrait aucun changement et n'écrirait rien.
 * L'état est ensuite VÉRIFIÉ : sans cette assertion, les tests d'invalidation
 * passeraient même si la remise en brouillon échouait — la garde « dérogation »
 * s'exécutant avant la garde de statut, l'exception attendue tomberait quand
 * même, pour la mauvaise raison.
 */
function waiverReopen(array $ctx): void
{
    Order::whereKey($ctx['order']->id)->update(['status' => 'brouillon']);

    expect($ctx['order']->fresh()->status)->toBe('brouillon');
}

// ---------------------------------------------------------------------------

it('parcours B : bloque sous le plancher, exige un approbateur distinct, puis laisse passer', function () {
    $ctx = waiverScenario();

    // 1) Blocage initial : prix 80 sous le minimum calculé.
    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']))
        ->toThrow(RuntimeException::class, 'dérogation');

    // 2) Le demandeur ne peut pas approuver sa propre dérogation.
    test()->actingAs($ctx['requester']);
    $waiver = app(SalesFloorWaiverService::class)->request(
        $ctx['order'], $ctx['order']->items->first(), 'Offre stratégique documentée.'
    );
    expect(fn () => app(SalesFloorWaiverService::class)->approve($waiver))
        ->toThrow(RuntimeException::class, 'propre');

    // 3) Approbateur distinct → la soumission passe.
    test()->actingAs($ctx['approver']);
    app(SalesFloorWaiverService::class)->approve($waiver, 'Accord financier exceptionnel');
    test()->actingAs($ctx['requester']);
    app(CommercialWorkflowService::class)->submit($ctx['order']->fresh());

    expect($ctx['order']->fresh()->status)->toBe('en_attente_validation')
        ->and(SalesFloorWaiver::where('status', 'approuvee')->count())->toBe(1);
});

it('axe PRIX : baisser le prix unitaire invalide la dérogation approuvée', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    $ctx['order']->items()->first()->update(['unit_price' => 70]);

    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']->fresh()))
        ->toThrow(RuntimeException::class, 'dérogation');
});

it('axe QUANTITÉ : changer la quantité invalide la dérogation approuvée', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    $ctx['order']->items()->first()->update(['quantity' => 3]);

    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']->fresh()))
        ->toThrow(RuntimeException::class, 'dérogation');
});

it('axe REMISE : ajouter une remise de ligne invalide la dérogation approuvée', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    $ctx['order']->items()->first()->update(['discount_percent' => 10]);

    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']->fresh()))
        ->toThrow(RuntimeException::class, 'dérogation');
});

it('axe COÛT : modifier le coût de revient de l article invalide la dérogation approuvée', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    // Le coût entre dans la signature : un coût qui monte relève le plancher,
    // la dérogation accordée sur l'ancien coût ne couvre plus rien.
    $ctx['product']->update(['cout_standard' => 150]);

    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']->fresh()))
        ->toThrow(RuntimeException::class, 'dérogation');
});

it('axe TAXE : la taxe ne participe pas à la signature — comportement constaté, à trancher', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    // Le contrôle du plancher porte sur le prix HT NET. Un changement de taux de
    // TVA ne modifie ni le prix HT ni le coût, donc ne peut pas rendre une ligne
    // conforme non conforme. La signature ne l'inclut pas et la dérogation reste
    // valable — c'est cohérent avec la règle du plancher, mais cela s'écarte de
    // l'exigence « toute modification de taxe invalide la dérogation ».
    //
    // Ce test FIGE le comportement réel plutôt que de le maquiller. Si la règle
    // doit changer, il échouera et devra être mis à jour en même temps que le
    // service — pas l'inverse.
    $item = $ctx['order']->items()->first();
    $item->update(['tax_rate_value' => 18]);

    app(CommercialWorkflowService::class)->submit($ctx['order']->fresh());

    expect($ctx['order']->fresh()->status)->toBe('en_attente_validation');
});

it('une dérogation expirée ne couvre plus rien', function () {
    $ctx = waiverScenario();
    waiverApproveAndSubmit($ctx);
    waiverReopen($ctx);

    SalesFloorWaiver::where('status', 'approuvee')->update(['expires_at' => now()->subDay()]);

    expect(fn () => app(CommercialWorkflowService::class)->submit($ctx['order']->fresh()))
        ->toThrow(RuntimeException::class, 'dérogation');
});
