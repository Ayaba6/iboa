<?php

/**
 * [CDC §13.10] Modification OF exceptionnelle — workflow à 4 étapes séquentielles :
 * Chef Production → Commercial → Finance → DG. Chaque étape exige la précédente ;
 * la validation DG débloque l'édition de l'OF une seule fois (consommée à la
 * sauvegarde suivante).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Database\Seeders\RolesAndPermissionsSeeder;

uses(\Tests\Concerns\RefreshDatabase::class);

function modCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'MOD-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Mod Co'], ['email' => 'mod@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

/**
 * Utilise le VRAI seeder de rôles/permissions plutôt qu'une réplication manuelle —
 * garantit que ces tests reflètent exactement les droits réels (et auraient
 * détecté le bug "commercial sans production.view" trouvé pendant l'écriture
 * de ce fichier).
 */
function modUser(string $role): User
{
    // [RefreshDatabase] Chaque test roule dans sa propre transaction annulée
    // ensuite — pas de cache statique possible, on reseed à chaque appel
    // (Role::firstOrCreate + syncPermissions sont idempotents). Artisan::call
    // fournit le contexte $this->command attendu par le seeder (->info()).
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

    $u = User::factory()->create(['company_id' => modCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    return $u;
}

function modOrder(string $number, string $status = 'en_cours'): ProductionOrder
{
    $co = modCompany();
    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => $number, 'status' => $status, 'quantity_requested' => 10,
    ]);
}

it('parcourt les 4 étapes séquentielles et débloque l\'édition une seule fois', function () {
    $chef       = modUser('chef_production');
    $commercial = modUser('commercial');
    $finance    = modUser('daf');
    $dg         = modUser('directeur');

    $order = modOrder('OF-MOD-001');
    $svc   = app(ProductionService::class);

    // Étape 0 : demande
    $this->actingAs($chef);
    $svc->requestModification($order, 'Changer la teinte demandée par le client', $chef);
    $order->refresh();
    expect($order->modification_status)->toBe('en_attente');

    // Étape 1 : chef production
    $this->actingAs($chef);
    $this->post(route('production.orders.modification-avis-chef', $order))->assertRedirect();
    $order->refresh();
    expect($order->modification_chef_avis_at)->not->toBeNull()
        ->and($order->modification_chef_avis_by)->toBe($chef->id);

    // Étape 2 : commercial — bloquée si chef n'a pas (déjà passé), OK maintenant
    $this->actingAs($commercial);
    $this->post(route('production.orders.modification-avis-commercial', $order))->assertRedirect();
    $order->refresh();
    expect($order->modification_commercial_avis_at)->not->toBeNull();

    // Étape 3 : finance
    $this->actingAs($finance);
    $this->post(route('production.orders.modification-avis-finance', $order))->assertRedirect();
    $order->refresh();
    expect($order->modification_finance_avis_at)->not->toBeNull();

    // Étape 4 : DG
    $this->actingAs($dg);
    $this->post(route('production.orders.modification-approve-dg', $order))->assertRedirect();
    $order->refresh();
    expect($order->modification_status)->toBe('approuvee')
        ->and($order->modification_dg_approved_by)->toBe($dg->id);

    // L'OF en_cours est maintenant éditable
    expect($order->isEditableViaModification())->toBeTrue();

    // Une modification appliquée consomme l'autorisation
    $svc->update($order, ['notes' => 'Teinte changée en RAL 9006']);
    $order->refresh();
    expect($order->modification_status)->toBe('aucune')
        ->and($order->isEditableViaModification())->toBeFalse();
});

it('refuse de sauter une étape (commercial avant chef)', function () {
    $chef       = modUser('chef_production');
    $commercial = modUser('commercial');

    $order = modOrder('OF-MOD-002');
    $svc   = app(ProductionService::class);

    $this->actingAs($chef);
    $svc->requestModification($order, 'Motif', $chef);

    $this->actingAs($commercial);
    $this->post(route('production.orders.modification-avis-commercial', $order))
        ->assertSessionHas('error');

    $order->refresh();
    expect($order->modification_commercial_avis_at)->toBeNull();
});

it('refuse l\'avis DG sans avis finance préalable', function () {
    $chef       = modUser('chef_production');
    $commercial = modUser('commercial');
    $dg         = modUser('directeur');

    $order = modOrder('OF-MOD-003');
    $svc   = app(ProductionService::class);
    $svc->requestModification($order, 'Motif', $chef);
    $svc->giveModificationChefAvis($order, null, $chef);
    $svc->giveModificationCommercialAvis($order, null, $commercial);

    $this->actingAs($dg);
    $this->post(route('production.orders.modification-approve-dg', $order))
        ->assertSessionHas('error');

    $order->refresh();
    expect($order->modification_status)->toBe('en_attente');
});

it('un acteur sans le bon rôle ne peut pas donner son avis (403)', function () {
    $chef = modUser('chef_production');
    $operateur = modUser('operateur_production'); // aucune permission modification.*

    $order = modOrder('OF-MOD-004');
    app(ProductionService::class)->requestModification($order, 'Motif', $chef);

    $this->actingAs($operateur);
    $this->post(route('production.orders.modification-avis-chef', $order))
        ->assertForbidden();
});

it('rejette une demande de modification à n\'importe quelle étape en attente', function () {
    $chef = modUser('chef_production');

    $order = modOrder('OF-MOD-005');
    app(ProductionService::class)->requestModification($order, 'Motif initial', $chef);

    $this->actingAs($chef);
    $this->post(route('production.orders.modification-reject', $order), ['reason' => 'Finalement pas nécessaire'])
        ->assertRedirect();

    $order->refresh();
    expect($order->modification_status)->toBe('refusee');
});

it('bloque une nouvelle demande tant qu\'une précédente est en attente', function () {
    $chef = modUser('chef_production');
    $order = modOrder('OF-MOD-006');
    $svc = app(ProductionService::class);

    $svc->requestModification($order, 'Première demande', $chef);

    expect(fn () => $svc->requestModification($order, 'Deuxième demande', $chef))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('un OF en_cours reste non éditable sans approbation DG', function () {
    $order = modOrder('OF-MOD-007');
    expect($order->isEditable())->toBeFalse()
        ->and($order->isEditableViaModification())->toBeFalse();

    expect(fn () => app(ProductionService::class)->update($order, ['notes' => 'tentative']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
