<?php

/**
 * [MTO §1] Un OF portant un article MTO doit être rattaché à une commande client.
 *
 * La règle est portée par MtoOrderRequirementGuard et appelée depuis
 * ProductionService::create(), seul chemin de création métier — vérifié par
 * cartographie : le contrôleur (store), le listener de confirmation de commande
 * (TriggerMtoProductionOnOrderConfirmed) et tout futur canal y convergent.
 *
 * Deux OF antérieurs à la règle (OF-2026-0004, OF-2026-0005 en base de
 * développement) restent non conformes. Ils ne sont PAS régularisés d'office :
 * leur rattachement est une décision métier. La commande a3:audit-mto-orders les
 * signale sans les modifier — c'est le dernier test de ce fichier qui le prouve.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\MtoOrderRequirementGuard;
use App\Modules\Production\Services\ProductionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * Utilisateur portant EXACTEMENT les permissions demandées — jamais super_admin,
 * qui court-circuite toutes les vérifications via Gate::before et rendrait le
 * test « sans permission » mécaniquement faux.
 *
 * @param  list<string>  $permissions
 */
function mtoReqUser(array $permissions = ['production.create']): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTOREQ-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'MtoReq Co'], [
        'email' => 'mtoreq@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WMTOREQ'], [
        'name' => 'WMTOREQ', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    // Rôle unique par jeu de permissions : deux tests aux droits différents ne
    // doivent pas se contaminer via un rôle partagé.
    $role = Role::firstOrCreate(['name' => 'mtoreq_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

function mtoReqProduct(?string $mode): Product
{
    return Product::factory()->create([
        'production_mode' => $mode,
        'is_stockable'    => true,
        'is_manufacturable' => true,
    ]);
}

function mtoReqSalesOrder(Product $p): App\Models\Order
{
    $co = Company::first();
    $o  = App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-MTOREQ-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
    ]);
    $o->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => 100,
        'unit_price' => 1000, 'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    return $o;
}

// ── 1. Cas nominal ───────────────────────────────────────────────────────────

it('autorise un OF MTO rattaché à une commande client', function () {
    mtoReqUser();
    $p = mtoReqProduct('mto');

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id,
        'order_id'   => mtoReqSalesOrder($p)->id,
        'quantity_requested' => 10,
    ]);

    expect($of->exists)->toBeTrue()
        ->and($of->order_id)->not->toBeNull();
});

// ── 2-4. Dérogation ──────────────────────────────────────────────────────────

it('refuse un OF MTO sans commande à un utilisateur sans dérogation', function () {
    mtoReqUser(['production.create']); // pas la permission de dérogation
    $p = mtoReqProduct('mto');

    expect(fn () => app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
    ]))->toThrow(ValidationException::class, 'fabriqué à la commande');

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});

it('refuse un OF MTO sans commande quand la dérogation est accordée mais non motivée', function () {
    // La permission autorise la dérogation ; elle ne la justifie pas.
    mtoReqUser(['production.create', MtoOrderRequirementGuard::PERMISSION]);
    $p = mtoReqProduct('mto');

    expect(fn () => app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
    ]))->toThrow(ValidationException::class, 'motif de la dérogation');

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});

it('refuse aussi un motif fait de blancs — un motif vide reste vide', function () {
    mtoReqUser(['production.create', MtoOrderRequirementGuard::PERMISSION]);
    $p = mtoReqProduct('mto');

    expect(fn () => app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10, 'derogation_motif' => "   \t  ",
    ]))->toThrow(ValidationException::class, 'motif de la dérogation');
});

it('autorise un OF MTO sans commande avec dérogation et motif', function () {
    mtoReqUser(['production.create', MtoOrderRequirementGuard::PERMISSION]);
    $p = mtoReqProduct('mto');

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
        'derogation_motif' => 'Reconstitution stock tampon chantier Ouaga 2000 — accord direction.',
    ]);

    expect($of->exists)->toBeTrue()
        ->and($of->order_id)->toBeNull();
});

// ── 5. MTS et articles non qualifiés ─────────────────────────────────────────

it('laisse passer un OF MTS sans commande — c’est son fonctionnement normal', function () {
    mtoReqUser(['production.create']);
    $p = mtoReqProduct('mts');

    $of = app(ProductionService::class)->create(['product_id' => $p->id, 'quantity_requested' => 10]);

    expect($of->exists)->toBeTrue()->and($of->order_id)->toBeNull();
});

it('ne bloque pas rétroactivement un article dont le mode n’est pas renseigné', function () {
    // production_mode NULL = article historique non qualifié. La règle se
    // déclenche sur une intention explicite (« mto »), jamais sur un vide.
    mtoReqUser(['production.create']);
    $p = mtoReqProduct(null);

    expect(app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
    ])->exists)->toBeTrue();
});

// ── 6. Journalisation ────────────────────────────────────────────────────────

it('journalise la dérogation avec utilisateur, produit, mode, motif et canal', function () {
    $user = mtoReqUser(['production.create', MtoOrderRequirementGuard::PERMISSION]);
    $p = mtoReqProduct('mto');

    $of = app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
        'derogation_motif' => 'Commande verbale client historique, régularisation sous 48 h.',
    ], [], 'import');

    $log = App\Models\AuditLog::where('action', MtoOrderRequirementGuard::ACTION)
        ->where('model_id', $of->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id);

    $new = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);

    expect($new['motif'])->toContain('régularisation sous 48 h')
        ->and($new['canal'])->toBe('import')          // le canal est bien propagé
        ->and($new['production_mode'])->toBe('mto')
        ->and($new['produit'])->toBe($p->name)
        ->and($new['order_id'])->toBeNull()
        ->and($new['of'])->toBe($of->number);
});

it('ne journalise aucune dérogation quand l’OF est régulièrement rattaché', function () {
    mtoReqUser();
    $p = mtoReqProduct('mto');

    app(ProductionService::class)->create([
        'product_id' => $p->id, 'order_id' => mtoReqSalesOrder($p)->id, 'quantity_requested' => 10,
    ]);

    expect(App\Models\AuditLog::where('action', MtoOrderRequirementGuard::ACTION)->exists())->toBeFalse();
});

// ── 7-8. Autres points d'entrée ──────────────────────────────────────────────

it('ne se contourne pas en dupliquant les données d’un OF MTO existant', function () {
    // L'ERP n'expose pas de bouton « dupliquer » ; la duplication réelle consiste
    // à rejouer les données d'un OF existant. Elle repasse par le même service,
    // donc par la même garde — y compris quand l'OF source était dérogatoire.
    mtoReqUser(['production.create', MtoOrderRequirementGuard::PERMISSION]);
    $p = mtoReqProduct('mto');

    $source = app(ProductionService::class)->create([
        'product_id' => $p->id, 'quantity_requested' => 10,
        'derogation_motif' => 'Dérogation initiale motivée.',
    ]);

    // Copie fidèle des données métier de l'OF source, motif NON repris : une
    // dérogation ne se recopie pas, elle se re-motive.
    $copie = ['product_id' => $source->product_id, 'quantity_requested' => $source->quantity_requested];

    expect(fn () => app(ProductionService::class)->create($copie))
        ->toThrow(ValidationException::class, 'motif de la dérogation');
});

it('refuse également par le formulaire HTTP, pas seulement par le service', function () {
    mtoReqUser(['production.view', 'production.create']);
    $p = mtoReqProduct('mto');

    $this->post(route('production.orders.store'), [
        'product_id' => $p->id,
        'quantity_requested' => 10,
    ])->assertSessionHasErrors('order_id');

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});

it('accepte par le formulaire HTTP quand la commande est rattachée', function () {
    mtoReqUser(['production.view', 'production.create']);
    $p = mtoReqProduct('mto');

    $this->post(route('production.orders.store'), [
        'product_id' => $p->id,
        'order_id'   => mtoReqSalesOrder($p)->id,
        'quantity_requested' => 10,
    ])->assertSessionHasNoErrors();

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeTrue();
});

// ── 9. Audit des OF antérieurs ───────────────────────────────────────────────

it('signale les OF MTO historiques sans commande sans les modifier', function () {
    mtoReqUser();
    $co = Company::first();
    $p  = mtoReqProduct('mto');

    // OF antérieurs à la règle : créés SANS passer par le service, comme l'ont
    // été OF-2026-0004 et OF-2026-0005 en base de développement.
    $legacy = collect(['OF-LEGACY-A', 'OF-LEGACY-B'])->map(fn ($n) => ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => $n, 'status' => 'termine', 'product_id' => $p->id,
        'quantity_requested' => 5, 'quantity_produced' => 5,
    ]));

    $avant = ProductionOrder::whereIn('number', ['OF-LEGACY-A', 'OF-LEGACY-B'])
        ->orderBy('number')->get(['number', 'status', 'order_id', 'quantity_produced'])->toArray();

    $code = Artisan::call('a3:audit-mto-orders');
    $sortie = Artisan::output();

    // Signalés…
    expect($code)->toBe(1)
        ->and($sortie)->toContain('OF-LEGACY-A')
        ->and($sortie)->toContain('OF-LEGACY-B');

    // …et rigoureusement intacts : l'audit ne répare rien.
    $apres = ProductionOrder::whereIn('number', ['OF-LEGACY-A', 'OF-LEGACY-B'])
        ->orderBy('number')->get(['number', 'status', 'order_id', 'quantity_produced'])->toArray();

    expect($apres)->toBe($avant)
        ->and($legacy->every(fn ($of) => $of->fresh()->order_id === null))->toBeTrue();
});

it('ne signale rien quand tous les OF MTO portent une commande', function () {
    mtoReqUser();
    $p = mtoReqProduct('mto');
    app(ProductionService::class)->create([
        'product_id' => $p->id, 'order_id' => mtoReqSalesOrder($p)->id, 'quantity_requested' => 10,
    ]);

    expect(Artisan::call('a3:audit-mto-orders'))->toBe(0);
});
