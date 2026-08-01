<?php

/**
 * [CDC §13.2] Garde financière au lancement OF — le service bloquait déjà
 * `launch()` correctement (ProductionService::checkFinancialGate), mais
 * AUCUN bouton UI n'exposait l'action de déblocage (DAF/DG). Un DAF qui
 * tombait sur l'erreur n'avait aucun moyen d'agir depuis l'écran OF.
 * Ce test couvre le bouton ajouté à resources/views/production/orders/show.blade.php.
 *
 * [BUG-A3-MTO-FIN-001] Le montage éprouvait un OF SANS commande de vente, en
 * lui posant à la main `payment_mode` et `financial_authorization`. Un tel OF
 * relève du stock (MTS) : il ne porte aucun engagement client, donc aucune
 * exigence financière, et le bandeau n'a pas lieu de s'afficher. Le scénario est
 * remonté sur une vraie commande comptant impayée — le cas où le bandeau doit
 * effectivement apparaître.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\Concerns\RefreshDatabase::class);

function finAuthCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'FINAUTH-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'FinAuth Co'], ['email' => 'finauth@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function finAuthUser(string $role): \App\Models\User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = \App\Models\User::factory()->create(['company_id' => finAuthCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

/** OF rattaché à une commande comptant de 236 000 FCFA, sans le moindre encaissement. */
function finAuthOf(string $numero): ProductionOrder
{
    $company = finAuthCompany();
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0]);
    $produit = Product::factory()->create(['is_manufacturable' => false, 'production_mode' => 'mto', 'sale_price' => 236000]);

    $commande = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-'.$numero,
        'status' => 'confirme', 'issued_at' => now(),
        'total_ttc' => 236000, 'invoiced_amount' => 0, 'production_approved' => false,
    ]);
    OrderItem::create([
        'order_id' => $commande->id, 'product_id' => $produit->id, 'description' => $produit->name,
        'quantity' => 50, 'unit_price' => 4720,
        'line_total_ht' => 236000, 'line_tax' => 0, 'line_total_ttc' => 236000,
    ]);

    return ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'order_id' => $commande->id, 'client_id' => $client->id, 'product_id' => $produit->id,
        'number' => 'OF-'.$numero, 'status' => 'brouillon', 'quantity_requested' => 50,
        'origin' => 'commande_client',
    ]);
}

it('affiche le bouton de dérogation financière au DAF et permet de débloquer l\'OF', function () {
    $daf        = finAuthUser('daf');
    $commercial = finAuthUser('commercial');
    $of         = finAuthOf('FINAUTH-001');

    // Le DAF voit le bandeau bloquant et le bouton de dérogation.
    $this->actingAs($daf);
    $this->get(route('production.orders.show', $of))
        ->assertOk()
        ->assertSee('Éligibilité financière')
        ->assertSee('Bloquant')
        ->assertSee(route('production.orders.authorize-finance', $of), false);

    // Le commercial (pas de production.approve_financial) ne voit pas le bouton.
    $this->actingAs($commercial);
    $this->get(route('production.orders.show', $of))
        ->assertOk()
        ->assertDontSee(route('production.orders.authorize-finance', $of), false);

    // Le commercial ne peut pas non plus poster l'action directement (403).
    $this->post(route('production.orders.authorize-finance', $of), [
        'bypass' => true, 'financial_notes' => 'Tentative sans habilitation.',
    ])->assertForbidden();

    // Le DAF déroge.
    $this->actingAs($daf);
    $this->post(route('production.orders.authorize-finance', $of), [
        'bypass' => true, 'financial_notes' => 'Dérogation accordée par téléphone, confirmée par écrit.',
    ])->assertRedirect();

    $of->refresh();
    expect($of->financial_authorization)->toBe('bypassed')
        ->and($of->financial_authorized_by)->toBe($daf->id)
        ->and((int) $of->financial_authorization_unpaid)->toBe(236000);

    // OF lançable maintenant — la garde reconnaît la dérogation.
    app(\App\Modules\Production\Services\ProductionService::class)->launch($of->fresh());
    expect($of->fresh()->status)->toBe('lance');
});

it('exige un motif explicite pour toute dérogation', function () {
    $daf = finAuthUser('daf');
    $of  = finAuthOf('FINAUTH-003');

    $this->actingAs($daf);
    $this->post(route('production.orders.authorize-finance', $of), ['bypass' => true])
        ->assertSessionHasErrors('financial_notes');

    $this->post(route('production.orders.authorize-finance', $of), ['financial_notes' => 'urgent'])
        ->assertSessionHasErrors('financial_notes');

    expect($of->fresh()->financial_authorization)->toBeNull();
});

it('refuse une seconde dérogation si déjà accordée', function () {
    $daf = finAuthUser('daf');
    $of  = finAuthOf('FINAUTH-002');

    $of->update([
        'financial_authorization' => 'approved',
        'financial_authorized_at' => now(),
        'financial_authorized_by' => $daf->id,
        'financial_notes' => 'Première dérogation, motivée et tracée.',
    ]);

    $this->actingAs($daf);
    $this->post(route('production.orders.authorize-finance', $of), [
        'bypass' => true, 'financial_notes' => 'Seconde tentative sur le même OF.',
    ])->assertStatus(422);
});
