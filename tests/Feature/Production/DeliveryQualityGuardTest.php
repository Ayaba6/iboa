<?php

/**
 * [MTO §15] Une production ne se livre que contrôlée, et à hauteur de ce qui est
 * réellement conforme.
 *
 * Le garde initial ne refusait que le statut `non_conforme` du dernier contrôle
 * et comparait des sommes globales. Ces tests couvrent les quatre trous fermés :
 * absence de contrôle, statut « à reprendre », déclarations non visées ou non
 * libérées, et comparaison article par article.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Services\BonPreparationService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function dqUser(array $permissions = [], bool $superAdmin = true): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DQ-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'DQ Co'], ['email' => 'dq@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WDQ'], ['name' => 'WDQ', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    $role = $superAdmin
        ? Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
        : Role::firstOrCreate(['name' => 'dq_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);

    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/**
 * Scénario complet. `$output` décrit l'état de la déclaration de production :
 * 'complete' (visée + libérée), 'sans_visa', 'sans_liberation', ou 'aucune'.
 */
function dqScenario(
    int $commande = 10,
    int $produit = 10,
    ?string $qc = 'conforme',
    string $output = 'complete',
    float $rejete = 0,
): array {
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-DQ-'.uniqid(),
        'status' => 'confirme', 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => 'PF', 'quantity' => $commande, 'unit_price' => 1000,
        'line_total_ht' => $commande * 1000, 'line_tax' => 0, 'line_total_ttc' => $commande * 1000,
    ]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-DQ-'.uniqid(), 'status' => 'termine', 'order_id' => $order->id,
        'product_id' => $p->id, 'quantity_requested' => $commande, 'quantity_produced' => $produit,
        'finished_at' => now(),
    ]);

    if ($produit > 0 && $output !== 'aucune') {
        $of->outputs()->create([
            'company_id' => $co->id, 'product_id' => $p->id, 'length' => 6,
            'quantity' => $produit, 'total_meters' => $produit * 6, 'produced_at' => now(),
            'status'              => $output === 'sans_visa' ? 'declaree' : 'validee',
            'validated_at'        => $output === 'sans_visa' ? null : now(),
            'quality_released_at' => $output === 'complete' ? now() : null,
        ]);
    }

    if ($qc) {
        ProductionQualityControl::create([
            'company_id' => $co->id, 'production_order_id' => $of->id,
            'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
            'status' => $qc, 'rejected_quantity' => $rejete, 'controlled_at' => now(),
        ]);
    }

    $dn = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'order_id' => $order->id,
        'number' => 'BL-DQ-'.uniqid(), 'issued_at' => now(), 'status' => 'brouillon', 'warehouse_id' => $wh->id,
    ]);
    $dn->items()->create(['product_id' => $p->id, 'description' => 'PF', 'quantity' => $commande, 'unit_price' => 1000]);

    return compact('co', 'wh', 'p', 'order', 'of', 'dn');
}

function dqValidate(DeliveryNote $dn, ?string $derogation = null): DeliveryNote
{
    return app(DeliveryNoteService::class)->validate($dn, $derogation);
}

// ── 1-3. Barrière qualité ────────────────────────────────────────────────────

it('refuse la livraison quand aucun contrôle qualité n’existe', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(qc: null);

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, 'aucun contrôle qualité');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('refuse la livraison sur un contrôle « non conforme »', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(qc: 'non_conforme');

    expect(fn () => dqValidate($dn))->toThrow(RuntimeException::class, 'non conforme');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('refuse la livraison sur un contrôle « à reprendre »', function () {
    // Ce statut existe dans l'énumération en base et s'affiche en ambre à
    // l'écran ; il passait pourtant sans obstacle.
    dqUser();
    ['dn' => $dn] = dqScenario(qc: 'a_reprendre');

    expect(fn () => dqValidate($dn))->toThrow(RuntimeException::class, 'à reprendre');
    expect($dn->fresh()->status)->toBe('brouillon');
});

// ── 4-9. Quantité conforme ───────────────────────────────────────────────────

it('exclut une déclaration de production non visée par le chef d’équipe', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(output: 'sans_visa');

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, 'réellement conforme et disponible');
});

it('exclut une déclaration visée mais non libérée par la qualité', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(output: 'sans_liberation');

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, 'réellement conforme et disponible');
});

it('déduit la quantité rejetée par le contrôle qualité', function () {
    // 10 produites, 4 rejetées : 6 livrables, la commande en demande 10.
    dqUser();
    ['dn' => $dn] = dqScenario(rejete: 4);

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, '10 à livrer pour 6 réellement conforme');
});

it('autorise la livraison quand la quantité conforme suffit', function () {
    dqUser();
    ['dn' => $dn] = dqScenario();

    dqValidate($dn);

    expect($dn->fresh()->status)->toBe('valide');
});

it('refuse la livraison quand la quantité conforme est insuffisante', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(commande: 10, produit: 4);

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, '10 à livrer pour 4 réellement conforme');
});

it('déduit les quantités déjà livrées — deux partielles ne dépassent pas la conforme', function () {
    dqUser();
    ['co' => $co, 'wh' => $wh, 'p' => $p, 'order' => $order] = dqScenario(commande: 10, produit: 10);

    // Le BL de la fixture porte 10 : on le remplace par deux BL de 6.
    DeliveryNote::where('order_id', $order->id)->delete();

    $bl = fn (int $q) => tap(DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'order_id' => $order->id,
        'number' => 'BL-PART-'.uniqid(), 'issued_at' => now(), 'status' => 'brouillon', 'warehouse_id' => $wh->id,
    ]), fn ($d) => $d->items()->create([
        'product_id' => $p->id, 'description' => 'PF', 'quantity' => $q, 'unit_price' => 1000,
    ]));

    dqValidate($bl(6));                       // 6 livrés sur 10 conformes
    expect(fn () => dqValidate($bl(6)))       // 6 + 6 = 12 > 10
        ->toThrow(RuntimeException::class, 'déjà livré 6');
});

// ── 10-11. Comparaison par article ───────────────────────────────────────────

it('contrôle un BL mixte article par article, sans compensation entre eux', function () {
    // Deux articles fabriqués : A produit 10, B produit 2. Le BL demande 5 de
    // chaque. Une somme globale (12 produits ≥ 10 demandés) aurait laissé passer.
    dqUser();
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $client = Client::factory()->create();

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-MIX-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
    ]);

    $creerOf = function (int $produit) use ($co, $order, $wh) {
        $p = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
        ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0, 'avg_cost' => 100]);
        $order->items()->create([
            'product_id' => $p->id, 'description' => 'PF', 'quantity' => 5, 'unit_price' => 1000,
            'line_total_ht' => 5000, 'line_tax' => 0, 'line_total_ttc' => 5000,
        ]);
        $of = ProductionOrder::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'number' => 'OF-MIX-'.uniqid(), 'status' => 'termine', 'order_id' => $order->id,
            'product_id' => $p->id, 'quantity_requested' => 5, 'quantity_produced' => $produit, 'finished_at' => now(),
        ]);
        $of->outputs()->create([
            'company_id' => $co->id, 'product_id' => $p->id, 'length' => 6, 'quantity' => $produit,
            'total_meters' => $produit * 6, 'produced_at' => now(), 'status' => 'validee',
            'validated_at' => now(), 'quality_released_at' => now(),
        ]);
        ProductionQualityControl::create([
            'company_id' => $co->id, 'production_order_id' => $of->id,
            'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
            'status' => 'conforme', 'rejected_quantity' => 0, 'controlled_at' => now(),
        ]);

        return $p;
    };

    $abondant = $creerOf(10);
    $rare     = $creerOf(2);

    $dn = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id,
        'number' => 'BL-MIX-'.uniqid(), 'issued_at' => now(), 'status' => 'brouillon', 'warehouse_id' => $wh->id,
    ]);
    foreach ([$abondant, $rare] as $p) {
        $dn->items()->create(['product_id' => $p->id, 'description' => 'PF', 'quantity' => 5, 'unit_price' => 1000]);
    }

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, $rare->name);
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('refuse un article du BL qu’aucun OF de la commande ne fabrique', function () {
    dqUser();
    ['co' => $co, 'wh' => $wh, 'order' => $order, 'dn' => $dn] = dqScenario();
    $intrus = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $intrus->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 100]);
    $dn->items()->create(['product_id' => $intrus->id, 'description' => 'Intrus', 'quantity' => 3, 'unit_price' => 500]);

    expect(fn () => dqValidate($dn))
        ->toThrow(RuntimeException::class, 'n’est fabriqué par aucun OF');
});

// ── 12-13. Préparation et chargement ─────────────────────────────────────────

it('bloque le démarrage de la préparation sans contrôle qualité', function () {
    dqUser();
    ['co' => $co, 'order' => $order] = dqScenario(qc: null);
    $bp = BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'BP-DQ-'.uniqid(), 'payment_mode' => 'credit', 'status' => 'en_attente',
    ]);

    expect(fn () => app(BonPreparationService::class)->startLoading($bp))
        ->toThrow(RuntimeException::class, 'aucun contrôle qualité');
    expect($bp->fresh()->status)->toBe('en_attente');
});

it('bloque la clôture du chargement quand la qualité bascule pendant l’opération', function () {
    dqUser();
    ['co' => $co, 'of' => $of, 'order' => $order] = dqScenario();
    $bp = BonPreparation::create([
        'company_id' => $co->id, 'order_id' => $order->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'BP-DQ-'.uniqid(), 'payment_mode' => 'credit', 'status' => 'en_attente',
    ]);

    app(BonPreparationService::class)->startLoading($bp);   // qualité conforme : démarrage accepté
    expect($bp->fresh()->status)->toBe('en_cours');

    // Le contrôle bascule pendant le chargement.
    ProductionQualityControl::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'thickness_ok' => false, 'length_ok' => true, 'color_ok' => false, 'visual_ok' => false,
        'status' => 'non_conforme', 'rejected_quantity' => 0, 'controlled_at' => now(),
    ]);

    expect(fn () => app(BonPreparationService::class)->finishLoading($bp->fresh()))
        ->toThrow(RuntimeException::class, 'non conforme');
    expect($bp->fresh()->status)->toBe('en_cours'); // jamais confirmé « chargé »
});

// ── 14-17. Chemins de validation et de facturation ───────────────────────────

it('bloque la validation directe du bon de livraison', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(qc: null);

    expect(fn () => app(DeliveryNoteService::class)->validate($dn))
        ->toThrow(RuntimeException::class, 'Livraison bloquée');
});

it('bloque aussi la validation par le workflow interne', function () {
    dqUser();
    ['dn' => $dn] = dqScenario(qc: null);
    $dn->update(['status' => 'en_attente_validation']);

    expect(fn () => app(\App\Services\CommercialWorkflowService::class)->validateDeliveryNote($dn->fresh()))
        ->toThrow(RuntimeException::class, 'Livraison bloquée');
    expect($dn->fresh()->status)->toBe('en_attente_validation');
});

it('interdit de facturer un bon de livraison non validé', function () {
    // Sans cette garde, facturer un brouillon contournait entièrement le contrôle
    // qualité posé sur la validation.
    dqUser();
    ['dn' => $dn] = dqScenario(qc: null);

    expect(fn () => app(InvoiceService::class)->createFromDeliveryNote($dn))
        ->toThrow(RuntimeException::class, 'doit être validé avant d’être facturé');
});

it('ne s’applique pas aux commandes sans production', function () {
    dqUser();
    $co = Company::first();
    $wh = Warehouse::where('company_id', $co->id)->first();
    $p  = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-STOCK-'.uniqid(),
        'status' => 'confirme', 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => 'X', 'quantity' => 5, 'unit_price' => 1000,
        'line_total_ht' => 5000, 'line_tax' => 0, 'line_total_ttc' => 5000,
    ]);
    $dn = DeliveryNote::create([
        'company_id' => $co->id, 'client_id' => $order->client_id, 'order_id' => $order->id,
        'number' => 'BL-STOCK-'.uniqid(), 'issued_at' => now(), 'status' => 'brouillon', 'warehouse_id' => $wh->id,
    ]);
    $dn->items()->create(['product_id' => $p->id, 'description' => 'X', 'quantity' => 5, 'unit_price' => 1000]);

    dqValidate($dn);

    expect($dn->fresh()->status)->toBe('valide');
});

// ── 18-20. Dérogation ────────────────────────────────────────────────────────

it('refuse la dérogation à un utilisateur qui n’a pas la permission dédiée', function () {
    // Utilisateur pourvu de tous les droits de vente, mais PAS du droit d'accepter
    // un risque qualité.
    dqUser(['sales.validate', 'deliveries.validate'], superAdmin: false);
    ['dn' => $dn] = dqScenario(qc: null);

    expect(fn () => dqValidate($dn, 'Le client accepte le lot en l’état.'))
        ->toThrow(RuntimeException::class, 'Dérogation refusée');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('n’accorde pas la dérogation au super administrateur par simple bypass', function () {
    // Gate::before donne tout au super_admin. Le garde interroge donc
    // hasPermissionTo(), qui ne passe pas par Gate — une permission générale
    // d'administration ne vaut pas acceptation d'un risque qualité.
    dqUser(); // super_admin, sans la permission nommée
    ['dn' => $dn] = dqScenario(qc: null);

    expect(fn () => dqValidate($dn, 'Accord verbal direction.'))
        ->toThrow(RuntimeException::class, 'Dérogation refusée');
});

it('refuse la dérogation sans motif, même avec la permission', function () {
    dqUser([ProductionDeliveryGuard::PERMISSION], superAdmin: false);
    ['dn' => $dn] = dqScenario(qc: null);

    // Motif vide ou fait de blancs : le blocage reste un blocage.
    expect(fn () => dqValidate($dn, '   '))
        ->toThrow(RuntimeException::class, 'Livraison bloquée');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('autorise et journalise une dérogation correctement motivée', function () {
    $user = dqUser([ProductionDeliveryGuard::PERMISSION, 'sales.validate'], superAdmin: false);
    ['dn' => $dn] = dqScenario(qc: null);

    dqValidate($dn, 'Lot accepté par le client sur constat contradictoire du 30/07.');

    expect($dn->fresh()->status)->toBe('valide');

    $log = App\Models\AuditLog::where('action', ProductionDeliveryGuard::ACTION)->latest('id')->first();
    expect($log)->not->toBeNull()->and($log->user_id)->toBe($user->id);

    $new = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);
    expect($new['motif_accepte'])->toContain('constat contradictoire')
        ->and($new['bon_livraison'])->toBe($dn->number)
        ->and($new['motifs'][0])->toContain('aucun contrôle qualité')
        ->and($new['risque_accepte'])->not->toBeEmpty();
});

// ── 21-22. Livraisons répétées ───────────────────────────────────────────────

it('ne permet pas la surlivraison en rejouant la validation du même BL', function () {
    // Double-clic : la seconde validation doit être refusée sur le statut, sans
    // jamais produire une seconde sortie de stock.
    dqUser();
    ['dn' => $dn] = dqScenario();

    dqValidate($dn);
    expect($dn->fresh()->status)->toBe('valide');

    expect(fn () => dqValidate($dn->fresh()))
        ->toThrow(RuntimeException::class, 'brouillon');
});
