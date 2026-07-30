<?php

/**
 * [Perf] Les compteurs de l'écran « Ordres de fabrication » tiennent en une requête.
 *
 * `ProductionOrderController::index()` lançait QUATRE `COUNT` séparés — brouillon,
 * lancé/en cours, en retard, terminé — soit quatre parcours de la même table pour
 * un bandeau d'indicateurs. Trois d'entre eux ne portaient que sur `status` : ils
 * sont désormais obtenus par un seul `COUNT` groupé, idiome déjà en place dans
 * PurchaseRequestController et DeliveryNoteController.
 *
 * `en_retard` reste une requête à part, et c'est délibéré : son critère est une
 * DATE d'échéance dépassée, pas un statut. Le fondre dans le regroupement
 * produirait un compteur faux — le test ci-dessous le vérifie explicitement.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function ofStatsFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'OFSTATS-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'OfStats Co'], [
        'email' => 'ofstats@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    $role = Role::firstOrCreate(['name' => 'ofstats_lecteur', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $user->assignRole($role);
    test()->actingAs($user);

    return compact('company', 'fy', 'user');
}

/** @param array<string,mixed> $f */
function ofStatsCreate(array $f, string $status, ?string $dueAt = null): ProductionOrder
{
    return ProductionOrder::create([
        'company_id'  => $f['company']->id,
        'number'      => 'OF-OFSTATS-'.uniqid(),
        'status'      => $status,
        'date_fin_prevue' => $dueAt,
    ]);
}

/** @param array<string,mixed> $f */
function ofStatsRead(array $f): array
{
    $response = test()->get(route('production.orders.index'))->assertOk();

    return $response->original->getData()['stats'] ?? [];
}

it('compte chaque statut exactement, y compris ceux à zéro', function () {
    $f = ofStatsFixture();

    ofStatsCreate($f, 'brouillon');
    ofStatsCreate($f, 'brouillon');
    ofStatsCreate($f, 'termine');
    // Aucun OF « lance » ni « en_cours » : le compteur doit valoir 0 et non manquer.
    // Un `??` absent aurait produit une clé indéfinie plutôt qu'un zéro.

    $stats = ofStatsRead($f);

    expect($stats['brouillon'])->toBe(2)
        ->and($stats['termine'])->toBe(1)
        ->and($stats['en_cours'])->toBe(0);
});

it('additionne « lancé » et « en cours » dans un seul indicateur', function () {
    // Le regroupement rend une ligne par statut : les deux doivent être RÉUNIES,
    // pas juste l'une des deux prise au hasard.
    $f = ofStatsFixture();

    ofStatsCreate($f, 'lance');
    ofStatsCreate($f, 'lance');
    ofStatsCreate($f, 'en_cours');

    expect(ofStatsRead($f)['en_cours'])->toBe(3);
});

it('garde « en retard » séparé : c’est une date dépassée, pas un statut', function () {
    $f = ofStatsFixture();

    // Deux OF lancés : l'un en retard, l'autre non. Si « en_retard » était dérivé du
    // regroupement par statut, il compterait les deux.
    ofStatsCreate($f, 'lance', now()->subDays(5)->toDateString());
    ofStatsCreate($f, 'lance', now()->addDays(5)->toDateString());

    $stats = ofStatsRead($f);

    expect($stats['en_retard'])->toBe(1)
        ->and($stats['en_cours'])->toBe(2);
});

it('ne parcourt plus la table une fois par indicateur', function () {
    $f = ofStatsFixture();
    foreach (['brouillon', 'lance', 'en_cours', 'termine'] as $status) {
        ofStatsCreate($f, $status);
    }

    // Première visite : amorce les caches. Mesurer la seconde évite de compter des
    // requêtes d'amorçage comme des compteurs.
    test()->get(route('production.orders.index'))->assertOk();

    DB::enableQueryLog();
    test()->get(route('production.orders.index'))->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $counts = array_filter(
        $queries,
        fn ($q) => str_contains($q['query'], 'count(*)') && str_contains($q['query'], 'production_orders')
    );

    // Deux au plus : le total du paginateur, et « en retard ». Les trois compteurs
    // de statut sont fondus dans un unique GROUP BY, qui n'est pas un `count(*)`
    // isolé et n'apparaît donc pas ici.
    expect(count($counts))->toBeLessThanOrEqual(2);
});

it('reste exact sur un statut absent du référentiel des indicateurs', function () {
    // Un OF « suspendu » ne doit gonfler aucun des quatre compteurs affichés.
    $f = ofStatsFixture();

    ofStatsCreate($f, 'suspendu');
    ofStatsCreate($f, 'termine');

    $stats = ofStatsRead($f);

    expect($stats['termine'])->toBe(1)
        ->and($stats['brouillon'])->toBe(0)
        ->and($stats['en_cours'])->toBe(0);
});
