<?php

/**
 * [Ventes §17] Le bon de préparation dispose d'un chemin d'annulation tracé.
 *
 * Défaut corrigé. Le module n'exposait que `index`, `show`, `start-loading`,
 * `finish-loading` et `pdf` : aucune annulation, aucune suppression. Un bon créé
 * par erreur restait affiché indéfiniment et la seule issue était une écriture
 * directe en base — hors règle métier et sans trace. Le modèle ne portait pas
 * non plus `SoftDeletes` : toute suppression y était définitive.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use App\Services\BonPreparationService;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function bpCancelFixture(string $status = 'en_attente'): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'BPCANCEL-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'BpCancel Co'], [
        'email' => 'bpcancel@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 50_000_000, 'balance' => 0]);
    $order  = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-BPCANCEL-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 500_000, 'total_tax' => 90_000, 'total_ttc' => 590_000,
        'total_discount' => 0, 'global_discount_amount' => 0, 'invoiced_amount' => 0,
    ]);

    $bp = BonPreparation::create([
        'company_id' => $company->id, 'order_id' => $order->id, 'fiscal_year_id' => $fy->id,
        'number' => 'BP-BPCANCEL-'.uniqid(), 'payment_mode' => 'credit', 'status' => $status,
        'created_by' => $user->id,
    ]);

    return compact('company', 'fy', 'order', 'client', 'user', 'bp');
}

function bpCancelSvc(): BonPreparationService
{
    return app(BonPreparationService::class);
}

function bpCancelGrant(User $user, string $suffix, array $abilities): void
{
    $role = Role::firstOrCreate(['name' => 'bpcancel_'.$suffix, 'guard_name' => 'web']);
    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
}

// ── Le schéma ────────────────────────────────────────────────────────────────

it('porte désormais la suppression logique et les colonnes d’annulation', function () {
    foreach (['deleted_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason'] as $column) {
        expect(Schema::hasColumn('bon_preparations', $column))
            ->toBeTrue("La colonne {$column} devrait exister.");
    }

    expect(in_array(
        \Illuminate\Database\Eloquent\SoftDeletes::class,
        class_uses_recursive(BonPreparation::class),
        true
    ))->toBeTrue();
});

it('retire le bon supprimé des listes sans effacer la ligne', function () {
    $f = bpCancelFixture();
    $id = $f['bp']->id;

    $f['bp']->delete();

    expect(BonPreparation::find($id))->toBeNull()
        ->and(BonPreparation::withTrashed()->find($id))->not->toBeNull();
});

// ── La garde de statut ───────────────────────────────────────────────────────

it('autorise l’annulation tant que la marchandise n’est pas sortie', function () {
    foreach (['en_attente', 'en_cours'] as $status) {
        expect(bpCancelFixture($status)['bp']->isCancellable())->toBeTrue();
    }
});

it('refuse l’annulation une fois le chargement terminé', function () {
    $f = bpCancelFixture('charge');

    expect($f['bp']->isCancellable())->toBeFalse();
    expect(fn () => bpCancelSvc()->cancel($f['bp'], 'Tentative après chargement.'))
        ->toThrow(RuntimeException::class, 'retour client');

    expect($f['bp']->fresh()->status)->toBe('charge');
});

it('refuse une seconde annulation', function () {
    $f = bpCancelFixture();
    bpCancelSvc()->cancel($f['bp'], 'Première annulation, légitime.');

    expect(fn () => bpCancelSvc()->cancel($f['bp']->fresh(), 'Seconde tentative.'))
        ->toThrow(RuntimeException::class, 'déjà annulé');
});

// ── Le motif ─────────────────────────────────────────────────────────────────

it('refuse un motif vide et laisse le bon intact', function () {
    $f = bpCancelFixture();

    expect(fn () => bpCancelSvc()->cancel($f['bp'], '  '))
        ->toThrow(RuntimeException::class, "Le motif d'annulation est obligatoire.");

    expect($f['bp']->fresh()->status)->toBe('en_attente')
        ->and($f['bp']->fresh()->cancelled_at)->toBeNull();
});

it('enregistre motif, auteur et date dans des colonnes dédiées', function () {
    $f = bpCancelFixture();

    bpCancelSvc()->cancel($f['bp'], 'Bon créé en double — celui-ci est écarté.');

    $bp = $f['bp']->fresh();
    expect($bp->status)->toBe('annule')
        ->and($bp->cancellation_reason)->toContain('créé en double')
        ->and($bp->cancelled_by)->toBe($f['user']->id)
        ->and($bp->cancelled_at)->not->toBeNull();
});

it('conserve le document et son numéro — pas de suppression', function () {
    $f = bpCancelFixture();
    $number = $f['bp']->number;

    bpCancelSvc()->cancel($f['bp'], 'Annulation — le document doit rester consultable.');

    expect(BonPreparation::where('number', $number)->exists())->toBeTrue();
});

it('n’écrase pas les notes existantes avec le motif', function () {
    // Le motif a sa propre colonne : une note métier antérieure doit survivre.
    $f = bpCancelFixture();
    $f['bp']->update(['notes' => 'Camion 11 AB 1234 — chauffeur Ouédraogo.']);

    bpCancelSvc()->cancel($f['bp'], 'Commande annulée par le client.');

    expect($f['bp']->fresh()->notes)->toBe('Camion 11 AB 1234 — chauffeur Ouédraogo.');
});

// ── L'enchaînement avec le chargement ────────────────────────────────────────

it('empêche de démarrer le chargement d’un bon annulé', function () {
    $f = bpCancelFixture();
    bpCancelSvc()->cancel($f['bp'], 'Annulé avant tout chargement.');

    expect(fn () => bpCancelSvc()->startLoading($f['bp']->fresh()))
        ->toThrow(RuntimeException::class);
});

// ── Les permissions ──────────────────────────────────────────────────────────

it('rejette la requête HTTP dépourvue de motif', function () {
    $f = bpCancelFixture();
    bpCancelGrant($f['user'], 'ok', ['bon_preparations.view', 'bon_preparations.cancel']);

    $this->from(route('ventes.bons-preparation.show', $f['bp']))
        ->post(route('ventes.bons-preparation.cancel', $f['bp']), [])
        ->assertSessionHasErrors('motif');

    expect($f['bp']->fresh()->status)->toBe('en_attente');
});

it('accepte la requête HTTP munie d’un motif', function () {
    $f = bpCancelFixture();
    bpCancelGrant($f['user'], 'ok2', ['bon_preparations.view', 'bon_preparations.cancel']);

    $this->post(route('ventes.bons-preparation.cancel', $f['bp']), [
        'motif' => 'Client injoignable — bon écarté.',
    ])->assertSessionHasNoErrors();

    expect($f['bp']->fresh()->status)->toBe('annule');
});

it('refuse l’annulation à qui ne détient que le droit de faire avancer le chargement', function () {
    // Séparation des tâches : le magasinier exécute le chargement (`update`) mais
    // ne doit pas pouvoir écarter le document qui l'autorise.
    $f = bpCancelFixture();
    bpCancelGrant($f['user'], 'magasinier', ['bon_preparations.view', 'bon_preparations.update']);

    $this->post(route('ventes.bons-preparation.cancel', $f['bp']), [
        'motif' => 'Tentative sans la permission requise.',
    ])->assertForbidden();

    expect($f['bp']->fresh()->status)->toBe('en_attente');
});

it('n’accorde pas la permission d’annuler au rôle magasinier du référentiel', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $magasinier = Role::where('name', 'magasinier')->first();
    expect($magasinier)->not->toBeNull()
        ->and($magasinier->permissions->pluck('name')->contains('bon_preparations.cancel'))->toBeFalse();

    // …et la donne bien aux rôles qui portent la responsabilité du document.
    foreach (['responsable_commercial', 'responsable_stock', 'directeur'] as $roleName) {
        expect(Role::where('name', $roleName)->first()?->permissions->pluck('name')->contains('bon_preparations.cancel'))
            ->toBeTrue("Le rôle {$roleName} devrait pouvoir annuler un bon.");
    }
});
