<?php

/**
 * [Ventes §8] Parcours A à E — sessions HTTP AUTHENTIFIÉES avec de vrais rôles.
 *
 * PÉRIMÈTRE EXACT DE LA PREUVE, à ne pas surinterpréter :
 *   - PROUVÉ : la route réelle est appelée en session authentifiée, avec un rôle
 *     réel et ses permissions réelles ; la transition, le refus, le contrôle de
 *     crédit, la séparation des acteurs et l'anti-double-soumission sont
 *     constatés en base.
 *   - NON PROUVÉ : le comportement du NAVIGATEUR. Ces tests n'ouvrent aucune
 *     page, ne cliquent aucune modale de confirmation et n'exécutent aucun
 *     JavaScript. La preuve visuelle des 36 modales reste à faire.
 *
 * Aucun de ces tests ne doit être présenté comme « parcours navigateur ».
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function journeyCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'JOURNEY-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    $company = Company::firstOrCreate(
        ['name' => 'Journey Co'],
        ['email' => 'journey@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
    app()->instance('current_company', $company);

    return $company;
}

function journeyUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $user = User::factory()->create([
        'company_id' => journeyCompany()->id,
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

function journeyClient(int $creditLimit = 10_000_000): Client
{
    return Client::factory()->create([
        'is_active' => true,
        'payment_mode' => 'credit',
        'credit_limit' => $creditLimit,
        'balance' => 0,
    ]);
}

function journeyProduct(int $price = 100_000): Product
{
    return Product::factory()->create([
        'is_stockable' => false,
        'purchase_price' => 40_000,
        'last_purchase_price' => 40_000,
        'weighted_avg_cost' => 0,
        'cout_standard' => 0,
        'min_sale_price' => 0,
        'sale_price' => $price,
    ]);
}

/** @return array<string,mixed> */
function journeyLine(Product $product, int $qty = 1, ?int $unitPrice = null): array
{
    $unit = Unit::firstOrCreate(['name' => 'Pièce Journey'], ['abbreviation' => 'pcj']);
    $tax = TaxRate::firstOrCreate(['name' => 'TVA Journey'], ['short_name' => 'TVAJ', 'rate' => 18, 'is_active' => true]);

    return [
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_id' => $unit->id,
        'unit_price' => $unitPrice ?? (int) $product->sale_price,
        'tax_rate_id' => $tax->id,
        'discount_percent' => 0,
    ];
}

function journeyQuote(Client $client, Product $product, User $creator, int $qty = 1, ?int $unitPrice = null): Quote
{
    $company = journeyCompany();

    return Quote::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'DEV-JRN-'.str_pad((string) (Quote::count() + 1), 4, '0', STR_PAD_LEFT),
        'status' => 'brouillon',
        'issued_at' => now(),
        'subtotal_ht' => ($unitPrice ?? (int) $product->sale_price) * $qty,
        'total_ttc' => ($unitPrice ?? (int) $product->sale_price) * $qty,
        'created_by' => $creator->id,
    ]);
}

function journeyOrder(Client $client, User $creator, int $amount = 1_000_000): Order
{
    $company = journeyCompany();

    return Order::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'CMD-JRN-'.str_pad((string) (Order::count() + 1), 4, '0', STR_PAD_LEFT),
        'status' => 'brouillon',
        'issued_at' => now(),
        'subtotal_ht' => $amount,
        'total_ttc' => $amount,
        'invoiced_amount' => 0,
        'created_by' => $creator->id,
    ]);
}

// ---------------------------------------------------------------------------
// Parcours A — devis conforme : commercial soumet, un approbateur DISTINCT valide
// ---------------------------------------------------------------------------

it('parcours A : le commercial soumet le devis, un approbateur distinct le valide', function () {
    $commercial = journeyUser('commercial');
    $approbateur = journeyUser('responsable_commercial');
    $client = journeyClient();
    $product = journeyProduct();
    $quote = journeyQuote($client, $product, $commercial);

    // 1) Le commercial soumet — route réelle, session réelle.
    $this->actingAs($commercial)
        ->post(route('ventes.devis.submit', $quote))
        ->assertRedirect();
    expect($quote->fresh()->status)->toBe('en_attente_validation');

    // 2) Le commercial NE PEUT PAS valider son propre devis : maker ≠ checker.
    $this->actingAs($commercial)
        ->post(route('ventes.devis.validate-internal', $quote))
        ->assertForbidden();
    expect($quote->fresh()->status)->toBe('en_attente_validation');

    // 3) L'approbateur distinct valide.
    $this->actingAs($approbateur)
        ->post(route('ventes.devis.validate-internal', $quote))
        ->assertRedirect();
    expect($quote->fresh()->status)->not->toBe('en_attente_validation');

    // Les deux acteurs sont bien deux utilisateurs différents.
    expect($commercial->id)->not->toBe($approbateur->id);
});

// ---------------------------------------------------------------------------
// Parcours C — crédit dépassé : la commande est bloquée, rien n'est écrit
// ---------------------------------------------------------------------------

it('parcours C : une commande au-dessus du plafond est bloquée et reste en brouillon', function () {
    $commercial = journeyUser('commercial');
    $company = journeyCompany();
    $client = journeyClient(creditLimit: 1_000_000);

    // Encours réel de 900 000 : la commande de 500 000 porterait le total à
    // 1 400 000, au-dessus du plafond de 1 000 000.
    Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'FAC-JRN-C1', 'type' => 'facture', 'status' => 'emise',
        'issued_at' => now(), 'subtotal_ht' => 900_000,
        'total_ttc' => 900_000, 'remaining_amount' => 900_000,
    ]);
    $order = journeyOrder($client, $commercial, 500_000);

    $response = $this->actingAs($commercial)->post(route('ventes.commandes.submit', $order));

    // Refus métier : la commande n'est PAS soumise, et le motif est restitué.
    expect($order->fresh()->status)->toBe('brouillon');
    $response->assertRedirect();
    expect(session('error') ?? '')->toContain('plafond');
});

it('parcours C bis : sous le plafond, la même commande passe', function () {
    $commercial = journeyUser('commercial');
    $client = journeyClient(creditLimit: 10_000_000);
    $order = journeyOrder($client, $commercial, 500_000);

    $this->actingAs($commercial)
        ->post(route('ventes.commandes.submit', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('en_attente_validation');
});

// ---------------------------------------------------------------------------
// Parcours D — appel direct de la route sans permission
// ---------------------------------------------------------------------------

it('parcours D : un utilisateur sans permission ne peut pas appeler la route directement', function () {
    $lecteur = journeyUser('lecture_seule');
    $commercial = journeyUser('commercial');
    $client = journeyClient();
    $order = journeyOrder($client, $commercial);

    // Aucune interface ne lui propose ce bouton — il appelle l'URL à la main.
    $this->actingAs($lecteur)
        ->post(route('ventes.commandes.submit', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('brouillon');
});

it('parcours D bis : la validation financière est refusée au commercial', function () {
    $commercial = journeyUser('commercial');
    $client = journeyClient();
    $order = journeyOrder($client, $commercial);

    $this->actingAs($commercial)->post(route('ventes.commandes.submit', $order));
    expect($order->fresh()->status)->toBe('en_attente_validation');

    $this->actingAs($commercial)
        ->post(route('ventes.commandes.validate-internal', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('en_attente_validation');
});

it('parcours D ter : une suppression d échéancier est refusée à une permission de lecture', function () {
    $lecteur = journeyUser('lecture_seule');
    $commercial = journeyUser('commercial');
    $company = journeyCompany();
    $client = journeyClient();

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'FAC-JRN-D3', 'type' => 'facture', 'status' => 'emise',
        'issued_at' => now(), 'subtotal_ht' => 100_000,
        'total_ttc' => 100_000, 'remaining_amount' => 100_000,
    ]);

    // Avant correction, `invoices.view` suffisait à effacer tout l'échéancier.
    $this->actingAs($lecteur)
        ->delete(route('ventes.factures.schedules.destroy-all', $invoice))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Parcours E — double soumission rapide
// ---------------------------------------------------------------------------

it('parcours E : deux soumissions rapides ne produisent qu une seule transition', function () {
    $commercial = journeyUser('commercial');
    $client = journeyClient();
    $order = journeyOrder($client, $commercial, 500_000);

    $this->actingAs($commercial)->post(route('ventes.commandes.submit', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe('en_attente_validation');

    // Second envoi immédiat : la garde d'état doit refuser, sans second passage
    // du workflow ni seconde notification.
    $this->actingAs($commercial)->post(route('ventes.commandes.submit', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe('en_attente_validation')
        ->and(session('error') ?? '')->toContain('déjà été soumise');
});

it('parcours E bis : une double conversion de devis est refusée', function () {
    $commercial = journeyUser('commercial');
    $approbateur = journeyUser('responsable_commercial');
    $client = journeyClient();
    $product = journeyProduct();
    $quote = journeyQuote($client, $product, $commercial);

    $this->actingAs($commercial)->post(route('ventes.devis.submit', $quote));
    $this->actingAs($approbateur)->post(route('ventes.devis.validate-internal', $quote));
    $quote->fresh()->update(['status' => 'accepte']);

    $ordersBefore = Order::count();
    $this->actingAs($approbateur)->post(route('ventes.devis.convert', $quote));
    $ordersAfterFirst = Order::count();

    // Deuxième conversion immédiate : aucune commande supplémentaire.
    $this->actingAs($approbateur)->post(route('ventes.devis.convert', $quote));
    $ordersAfterSecond = Order::count();

    expect($ordersAfterFirst)->toBe($ordersBefore + 1)
        ->and($ordersAfterSecond)->toBe($ordersAfterFirst);
});
