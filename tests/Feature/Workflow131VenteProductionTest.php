<?php

/**
 * [CDC §13.1] Workflow Vente → Production Tôles Bac.
 *
 * Règles vérifiées :
 *  - Commercial : crée/modifie devis, crée commande — PAS de validation
 *    financière, PAS de génération d'OF, PAS de modification prix après validation.
 *  - Finance (comptable/daf) : valide → débloque fabrication (gate §13.2 testée ailleurs).
 *  - Chef Production : génère l'OF (production.create).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function w131Company(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'W131-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'W131 Co'], ['email' => 'w131@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function w131User(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => w131Company()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);

    return $u;
}

function w131Order(User $creator, int $unitPrice = 50_000): Order
{
    $company = w131Company();
    $client = Client::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'is_stockable' => false,
        'purchase_price' => 10_000,
        'last_purchase_price' => 10_000,
        'weighted_avg_cost' => 0,
        'cout_standard' => 0,
        'min_sale_price' => 0,
    ]);
    $unit = Unit::firstOrCreate(['name' => 'Pièce W131'], ['abbreviation' => 'pc131']);
    $tax = TaxRate::firstOrCreate(['name' => 'TVA W131'], ['short_name' => 'TVA131', 'rate' => 18, 'is_active' => true]);

    return app(OrderService::class)->create([
        'company_id' => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => $unitPrice,
            'unit_id' => $unit->id,
            'tax_rate_id' => $tax->id,
            'tax_rate_value' => 18,
        ]],
    ]);
}

it('interdit au commercial de valider financièrement une commande', function () {
    $commercial = w131User('commercial');
    $this->actingAs($commercial);

    $order = w131Order($commercial);
    app(CommercialWorkflowService::class)->submit($order);

    expect(fn () => app(CommercialWorkflowService::class)->validateOrder($order->fresh()))
        ->toThrow(RuntimeException::class, 'sales.validate');
});

it('permet au comptable (Finance) de valider une commande soumise', function () {
    $commercial = w131User('commercial');
    $comptable = w131User('comptable');

    $this->actingAs($commercial);
    $order = w131Order($commercial);
    app(CommercialWorkflowService::class)->submit($order);

    $this->actingAs($comptable);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    expect($order->fresh()->status)->toBe('confirme');
});

it('interdit au commercial de modifier les prix après validation', function () {
    $commercial = w131User('commercial');
    $comptable = w131User('comptable');

    $this->actingAs($commercial);
    $order = w131Order($commercial, 50_000);
    app(CommercialWorkflowService::class)->submit($order);

    $this->actingAs($comptable);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    // Le commercial tente de changer le prix unitaire sur la commande confirmée
    $this->actingAs($commercial);
    $order = $order->fresh();
    $item = $order->items->first();

    expect(fn () => app(OrderService::class)->update($order, [
        'items' => [[
            'product_id' => $item->product_id,
            'quantity' => (float) $item->quantity,
            'unit_price' => 99_000, // ← changement de prix interdit
            'unit_id' => $item->unit_id,
            'tax_rate_id' => $item->tax_rate_id,
            'tax_rate_value' => (float) $item->tax_rate_value,
        ]],
    ]))->toThrow(RuntimeException::class, 'verrouillés');

    // La policy bloque aussi l'accès HTTP à l'édition
    expect($commercial->can('update', $order))->toBeFalse();
});

it('permet au responsable commercial de modifier une commande validée', function () {
    $commercial = w131User('commercial');
    $responsable = w131User('responsable_commercial');
    $comptable = w131User('comptable');

    $this->actingAs($commercial);
    $order = w131Order($commercial, 50_000);
    app(CommercialWorkflowService::class)->submit($order);

    $this->actingAs($comptable);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $this->actingAs($responsable);
    expect($responsable->can('update', $order->fresh()))->toBeTrue();
});

it('interdit au commercial de générer un OF (production.create)', function () {
    $commercial = w131User('commercial');
    expect($commercial->can('production.create'))->toBeFalse();
});

it('permet au chef production de générer un OF avec machine et responsable', function () {
    $chef = w131User('chef_production');
    expect($chef->can('production.create'))->toBeTrue()
        ->and($chef->can('production.launch'))->toBeTrue();
});

it('laisse le commercial libre de modifier une commande en brouillon', function () {
    $commercial = w131User('commercial');
    $this->actingAs($commercial);

    $order = w131Order($commercial, 50_000);
    expect($order->status)->toBe('brouillon')
        ->and($commercial->can('update', $order))->toBeTrue();

    $item = $order->items->first();
    app(OrderService::class)->update($order, [
        'items' => [[
            'product_id' => $item->product_id,
            'quantity' => 3.0,
            'unit_price' => 60_000,
            'unit_id' => $item->unit_id,
            'tax_rate_id' => $item->tax_rate_id,
            'tax_rate_value' => (float) $item->tax_rate_value,
        ]],
    ]);

    expect((int) $order->fresh()->items->first()->unit_price)->toBe(60_000);
});
