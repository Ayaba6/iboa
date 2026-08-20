<?php

/**
 * [Ventes §17] Toute annulation de commande doit être tracée.
 *
 * Défaut corrigé : `OrderService::cancel()` était la SEULE annulation de l'ERP
 * à ne pas exiger de motif — facture, avoir, encaissement, virement, ordre de
 * fabrication et transfert de stock en demandent tous un. Une commande
 * confirmée passait donc à « annulé » sans auteur, sans date d'action et sans
 * raison.
 *
 * La cause profonde était en amont : `HasCommercialWorkflow::isCancellable()`
 * s'arrête à `brouillon` et `en_attente_validation`. Le chemin tracé
 * (`cancel-internal` → `cancelDocument()`) refusait donc toute commande
 * confirmée, et l'interface avait été construite autour du contournement — un
 * second bouton « Annuler » branché sur le chemin sans motif, affiché
 * précisément pour `@if($order->status === 'confirme')`.
 */

use App\Models\Client;
use App\Models\CommercialValidation;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function cancelTraceFixture(string $status = 'confirme'): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CANCELTRACE-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'CancelTrace Co'], [
        'email' => 'canceltrace@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $unit    = Unit::firstOrCreate(['name' => 'Kg CancelTrace'], ['abbreviation' => 'kgct']);
    $product = Product::factory()->create(['is_stockable' => true]);
    $client  = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 100_000_000, 'balance' => 0]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-CANCELTRACE-'.uniqid(), 'status' => $status, 'issued_at' => now(),
        'subtotal_ht' => 1_000_000, 'total_tax' => 180_000, 'total_ttc' => 1_180_000,
        'total_discount' => 0, 'global_discount_amount' => 0, 'invoiced_amount' => 0,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
        'description' => 'Tôle bac 0,40', 'quantity' => 100, 'delivered_quantity' => 0,
        'invoiced_quantity' => 0, 'unit_price' => 10_000, 'discount_percent' => 0,
        'tax_rate_value' => 18, 'line_total_ht' => 1_000_000, 'line_tax' => 180_000,
        'line_total_ttc' => 1_180_000,
    ]);

    return compact('company', 'fy', 'order', 'client', 'user', 'product');
}

function cancelTraceEntries(Order $order): \Illuminate\Support\Collection
{
    return CommercialValidation::query()
        ->where('document_type', Order::DOCUMENT_TYPE)
        ->where('document_id', $order->id)
        ->where('action', CommercialValidation::ACTION_ANNULATION)
        ->get();
}

// ── La garde de statut ───────────────────────────────────────────────────────

it('rend une commande confirmée annulable — le chemin tracé lui était fermé', function () {
    // C'est la correction de fond : sans elle, cancelDocument() refuse et il ne
    // reste que le chemin sans motif.
    expect(cancelTraceFixture('confirme')['order']->isCancellable())->toBeTrue();
});

it('couvre aussi les statuts intermédiaires de la chaîne de livraison', function () {
    foreach (['brouillon', 'en_attente_validation', 'en_preparation', 'partiellement_livre'] as $status) {
        expect(cancelTraceFixture($status)['order']->isCancellable())
            ->toBeTrue("Le statut {$status} devrait rester annulable.");
    }
});

it('refuse les statuts où l’annulation aurait des effets comptables ou de stock', function () {
    // `livre` et `facture` supposent une sortie de stock et/ou une écriture :
    // ils relèvent de l'avoir et du retour, pas d'une annulation sèche.
    foreach (['livre', 'facture', 'annule'] as $status) {
        expect(cancelTraceFixture($status)['order']->isCancellable())
            ->toBeFalse("Le statut {$status} ne doit pas être annulable.");
    }
});

// ── Le motif obligatoire ─────────────────────────────────────────────────────

it('refuse une annulation sans motif et laisse la commande intacte', function () {
    $f = cancelTraceFixture();

    expect(fn () => app(OrderService::class)->cancel($f['order'], ''))
        ->toThrow(RuntimeException::class, "Le motif d'annulation est obligatoire.");

    // La garde doit précéder tout effet de bord : statut inchangé, journal vide.
    expect($f['order']->fresh()->status)->toBe('confirme')
        ->and(cancelTraceEntries($f['order']))->toHaveCount(0);
});

it('refuse une annulation qu’aucun utilisateur ne peut endosser', function () {
    // `commercial_validations.user_id` est NOT NULL : sans session, l'écriture
    // d'audit est impossible. Refuser explicitement vaut mieux que planter sur
    // un accès à une propriété de null au milieu d'une transaction.
    $f = cancelTraceFixture();
    auth()->logout();

    expect(fn () => app(OrderService::class)->cancel($f['order'], 'Annulation sans session active.'))
        ->toThrow(RuntimeException::class, 'utilisateur identifié');

    expect($f['order']->fresh()->status)->toBe('confirme')
        ->and(cancelTraceEntries($f['order']))->toHaveCount(0);
});

it('refuse un motif composé uniquement d’espaces', function () {
    $f = cancelTraceFixture();

    expect(fn () => app(OrderService::class)->cancel($f['order'], "   \n\t  "))
        ->toThrow(RuntimeException::class, "Le motif d'annulation est obligatoire.");

    expect($f['order']->fresh()->status)->toBe('confirme');
});

// ── L'écriture au journal ────────────────────────────────────────────────────

it('écrit motif, auteur, ancien et nouveau statut au journal d’audit', function () {
    $f = cancelTraceFixture();

    app(OrderService::class)->cancel($f['order'], 'Commande annulée par le client — rupture d\'approvisionnement.');

    $entries = cancelTraceEntries($f['order']);
    expect($entries)->toHaveCount(1);

    $entry = $entries->first();
    expect($entry->ancien_statut)->toBe('confirme')
        ->and($entry->nouveau_statut)->toBe('annule')
        ->and($entry->user_id)->toBe($f['user']->id)
        ->and($entry->motif)->toContain('rupture d\'approvisionnement')
        ->and($f['order']->fresh()->status)->toBe('annule');
});

it('conserve l’ancien statut réel, pas une valeur figée', function () {
    $f = cancelTraceFixture('partiellement_livre');

    app(OrderService::class)->cancel($f['order'], 'Solde de commande abandonné à la demande du client.');

    expect(cancelTraceEntries($f['order'])->first()->ancien_statut)->toBe('partiellement_livre');
});

// ── L'unicité de l'implémentation ────────────────────────────────────────────

it('produit une seule écriture, quel que soit le service appelé', function () {
    // CommercialWorkflowService délègue à OrderService : si les deux écrivaient,
    // le journal porterait deux annulations pour un seul événement.
    $f = cancelTraceFixture();

    $role = Role::firstOrCreate(['name' => 'canceltrace_annuleur', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'sales.cancel', 'guard_name' => 'web']));
    $f['user']->assignRole($role);

    app(CommercialWorkflowService::class)->cancel($f['order'], 'Annulation via le workflow commercial.');

    expect(cancelTraceEntries($f['order']))->toHaveCount(1)
        ->and($f['order']->fresh()->status)->toBe('annule');
});

it('libère les réservations de stock quel que soit le chemin emprunté', function () {
    $f = cancelTraceFixture();

    \DB::table('stock_reservations')->insert([
        'company_id'  => $f['company']->id,
        'order_id'    => $f['order']->id,
        'product_id'  => $f['product']->id,
        'quantity'    => 100,
        'status'      => 'reserved',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    app(OrderService::class)->cancel($f['order'], 'Annulation — les réservations doivent être rendues.');

    expect(\DB::table('stock_reservations')
        ->where('order_id', $f['order']->id)->where('status', 'reserved')->count())->toBe(0);
});

// ── La route HTTP ────────────────────────────────────────────────────────────

/**
 * La route exige DEUX permissions : `orders.validate` par le middleware du
 * groupe, `orders.delete` par la policy appelée dans le contrôleur. Les trois
 * rôles porteurs de `orders.delete` (super_admin, directeur,
 * responsable_commercial) possèdent aussi `orders.validate`, donc la redondance
 * ne ferme la porte à personne — mais un test qui n'en accorderait qu'une
 * recevrait un 403 avant même d'atteindre la validation du motif.
 */
function cancelTraceGrantHttp(User $user, string $suffix): void
{
    $role = Role::firstOrCreate(['name' => 'canceltrace_'.$suffix, 'guard_name' => 'web']);
    foreach (['orders.validate', 'orders.delete'] as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
}

it('rejette la requête HTTP d’annulation dépourvue de motif', function () {
    $f = cancelTraceFixture();
    cancelTraceGrantHttp($f['user'], 'sansmotif');

    $this->from(route('ventes.commandes.show', $f['order']))
        ->post(route('ventes.commandes.cancel', $f['order']), [])
        ->assertSessionHasErrors('motif');

    expect($f['order']->fresh()->status)->toBe('confirme')
        ->and(cancelTraceEntries($f['order']))->toHaveCount(0);
});

it('accepte la requête HTTP munie d’un motif et la journalise', function () {
    $f = cancelTraceFixture();
    cancelTraceGrantHttp($f['user'], 'avecmotif');

    $this->post(route('ventes.commandes.cancel', $f['order']), [
        'motif' => 'Doublon de la commande CMD-2026-014 saisie par erreur.',
    ])->assertSessionHasNoErrors();

    expect($f['order']->fresh()->status)->toBe('annule')
        ->and(cancelTraceEntries($f['order'])->first()->motif)->toContain('Doublon');
});
