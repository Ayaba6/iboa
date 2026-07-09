<?php

/**
 * [CDC §13] Notifications de validation — chaque étape de workflow doit
 * notifier EXACTEMENT le rôle attendu par le CDC, jamais une diffusion
 * large. Ces tests vérifient à la fois la présence de la notification
 * pour le bon rôle et son ABSENCE pour les rôles qui n'ont rien à faire
 * à cette étape (respect strict des rôles demandé).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Production\Services\ProductionService;
use App\Notifications\ValidationStepNotification;
use App\Services\CommercialWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(\Tests\Concerns\RefreshDatabase::class);

function notifCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'NOTIF-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Notif Co'], ['email' => 'notif@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function notifUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => notifCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

it('notifie Finance (comptable+daf) à la soumission d\'une commande, jamais le commercial', function () {
    Notification::fake();

    $commercial = notifUser('commercial');
    $comptable  = notifUser('comptable');
    $daf        = notifUser('daf');

    $company = notifCompany();
    $client  = Client::factory()->create(['is_active' => true]);
    $unit    = Unit::firstOrCreate(['name' => 'Pièce NOT'], ['abbreviation' => 'pcnot']);
    $tax     = TaxRate::firstOrCreate(['name' => 'TVA NOT'], ['short_name' => 'TVANOT', 'rate' => 18, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => false]);

    $order = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-NOTIF-001', 'status' => 'brouillon',
        'issued_at' => now(), 'total_ht' => 1000, 'total_ttc' => 1180,
    ]);

    $this->actingAs($commercial);
    app(CommercialWorkflowService::class)->submit($order);

    Notification::assertSentTo($comptable, ValidationStepNotification::class);
    Notification::assertSentTo($daf, ValidationStepNotification::class);
    Notification::assertNotSentTo($commercial, ValidationStepNotification::class);
});

it('notifie Chef Atelier puis Responsable Production aux 2 étapes de validation OF', function () {
    Notification::fake();

    $chefAtelier    = notifUser('chef_atelier');
    $chefProduction = notifUser('chef_production');
    $operateur      = notifUser('operateur_production');

    $company = notifCompany();
    $of = ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-NOTIF-001', 'status' => 'brouillon', 'quantity_requested' => 10,
    ]);

    $svc = app(ProductionService::class);
    $svc->submitForValidation($of->fresh());

    Notification::assertSentTo($chefAtelier, ValidationStepNotification::class);
    Notification::assertNotSentTo($operateur, ValidationStepNotification::class);

    $svc->validateByChef($of->fresh());

    Notification::assertSentTo($chefProduction, ValidationStepNotification::class);
});

it('notifie le rôle exact selon le seuil de la demande d\'achat (CDC §13.4)', function () {
    Notification::fake();

    $directeurUsine = notifUser('directeur_usine'); // chef service, <500k
    $daf            = notifUser('daf');              // <5M
    $directeur      = notifUser('directeur');        // DG, >=5M

    $company = notifCompany();

    $smallRequest = \App\Models\PurchaseRequest::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'DA-NOTIF-001', 'status' => 'brouillon', 'requested_at' => now(),
        'requested_by' => $directeurUsine->id, 'total_estimated' => 300_000,
    ]);
    app(\App\Services\PurchaseRequestService::class)->submit($smallRequest);
    Notification::assertSentTo($directeurUsine, ValidationStepNotification::class);
    Notification::assertNotSentTo($daf, ValidationStepNotification::class);
    Notification::assertNotSentTo($directeur, ValidationStepNotification::class);

    Notification::fake();
    $bigRequest = \App\Models\PurchaseRequest::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'DA-NOTIF-002', 'status' => 'brouillon', 'requested_at' => now(),
        'requested_by' => $directeurUsine->id, 'total_estimated' => 6_000_000,
    ]);
    app(\App\Services\PurchaseRequestService::class)->submit($bigRequest);
    Notification::assertSentTo($directeur, ValidationStepNotification::class);
    Notification::assertNotSentTo($directeurUsine, ValidationStepNotification::class);
});

it('fait suivre les 4 avis de modification OF au bon rôle à chaque étape (CDC §13.10)', function () {
    Notification::fake();

    $chef       = notifUser('chef_production');
    $commercial = notifUser('commercial');
    $daf        = notifUser('daf');
    $dg         = notifUser('directeur');

    $company = notifCompany();
    $of = ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-NOTIF-MOD', 'status' => 'en_cours', 'quantity_requested' => 10,
    ]);

    $svc = app(ProductionService::class);

    $svc->requestModification($of, 'Changement client', $chef);
    Notification::assertSentTo($chef, ValidationStepNotification::class);

    $svc->giveModificationChefAvis($of->fresh(), 'OK', $chef);
    Notification::assertSentTo($commercial, ValidationStepNotification::class);

    $svc->giveModificationCommercialAvis($of->fresh(), 'OK', $commercial);
    Notification::assertSentTo($daf, ValidationStepNotification::class);

    $svc->giveModificationFinanceAvis($of->fresh(), 'OK', $daf);
    Notification::assertSentTo($dg, ValidationStepNotification::class);

    $svc->approveModificationByDg($of->fresh(), 'OK', $dg);
    Notification::assertSentTo($chef, ValidationStepNotification::class); // demandeur notifié de la décision
});

it('notifie Chef Atelier au rebut déclaré puis Qualité après validation chef (CDC §13.9)', function () {
    Notification::fake();

    $chefProduction = notifUser('chef_production'); // production.update — déclare la chute
    $chefAtelier    = notifUser('chef_atelier');
    $qualite        = notifUser('responsable_qualite');

    $company = notifCompany();
    $of = ProductionOrder::create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'number' => 'OF-NOTIF-REBUT', 'status' => 'en_cours', 'quantity_requested' => 10,
    ]);

    $this->actingAs($chefProduction);
    $this->post(route('production.orders.waste', $of), ['type' => 'rebut', 'weight' => 50])
        ->assertRedirect();

    $waste = ProductionWaste::where('production_order_id', $of->id)->firstOrFail();

    Notification::assertSentTo($chefAtelier, ValidationStepNotification::class);
    Notification::assertNotSentTo($qualite, ValidationStepNotification::class);

    Notification::fake();
    $this->actingAs($chefAtelier);
    $this->post(route('production.wastes.validate-chef', $waste), ['cause' => array_key_first(ProductionWaste::CAUSES)])
        ->assertRedirect();

    Notification::assertSentTo($qualite, ValidationStepNotification::class);
    Notification::assertNotSentTo($chefProduction, ValidationStepNotification::class);
});

it('notifie le bon rôle à la soumission de chaque type de document commercial (CDC §13.1/§17)', function () {
    Notification::fake();

    $commercial = notifUser('commercial');
    $respCom    = notifUser('responsable_commercial');
    $comptable  = notifUser('comptable');
    $company    = notifCompany();

    $quote = \App\Models\Quote::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id, 'status' => 'brouillon']);
    $this->actingAs($commercial);
    app(CommercialWorkflowService::class)->submit($quote);
    Notification::assertSentTo($respCom, ValidationStepNotification::class);
    Notification::assertNotSentTo($comptable, ValidationStepNotification::class);

    Notification::fake();
    $invoice = \App\Models\Invoice::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id, 'status' => 'brouillon']);
    app(CommercialWorkflowService::class)->submit($invoice);
    Notification::assertSentTo($comptable, ValidationStepNotification::class);
    Notification::assertNotSentTo($respCom, ValidationStepNotification::class);
});

it('confirme au créateur du document que sa demande a été validée (CDC §13/§17)', function () {
    Notification::fake();

    $commercial = notifUser('commercial');
    $respCom    = notifUser('responsable_commercial');
    $company    = notifCompany();

    $quote = \App\Models\Quote::factory()->create([
        'company_id' => $company->id, 'fiscal_year_id' => $company->current_fiscal_year_id,
        'status' => 'en_attente_validation', 'created_by' => $commercial->id,
    ]);

    $this->actingAs($respCom);
    app(CommercialWorkflowService::class)->validateQuote($quote);

    Notification::assertSentTo($commercial, ValidationStepNotification::class);
    Notification::assertNotSentTo($respCom, ValidationStepNotification::class);
});

it('notifie Qualité à la validation d\'une réception (CDC §13.4)', function () {
    Notification::fake();

    $magasinier = notifUser('magasinier');
    $qualite    = notifUser('responsable_qualite');
    $company    = notifCompany();

    $supplier  = \App\Models\Supplier::create(['name' => 'Fournisseur Notif', 'code' => 'SUP-NOTIF', 'is_active' => true]);
    $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'WH-NOTIF'], ['name' => 'Dépôt Notif', 'company_id' => $company->id, 'is_active' => true]);
    $product   = \App\Models\Product::factory()->create(['is_stockable' => true]);

    $reception = \App\Models\Reception::create([
        'company_id' => $company->id, 'supplier_id' => $supplier->id,
        'number' => 'REC-NOTIF-001', 'status' => 'brouillon', 'received_at' => now(),
    ]);
    $item = $reception->items()->create([
        'product_id' => $product->id, 'description' => 'Bobine test',
        'quantity' => 100, 'received_quantity' => 0, 'unit_cost' => 500,
    ]);

    $this->actingAs($magasinier);
    $this->post(route('achats.receptions.validate', $reception), [
        'warehouse_id' => $warehouse->id,
        'items' => [$item->id => ['received_quantity' => 100]],
    ])->assertRedirect();

    Notification::assertSentTo($qualite, ValidationStepNotification::class);
    Notification::assertNotSentTo($magasinier, ValidationStepNotification::class);
});

it('notifie Qualité (+ Chef Atelier si critique) à l\'ouverture d\'une non-conformité (CDC §13)', function () {
    Notification::fake();

    $chefProduction = notifUser('chef_production'); // production.update — ouvre la NC
    $qualite        = notifUser('responsable_qualite');
    $chefAtelier    = notifUser('chef_atelier');
    $company        = notifCompany();

    $this->actingAs($chefProduction);
    $this->post(route('qualite.non-conformities.store'), [
        'title' => 'Défaut épaisseur', 'severity' => 'mineure', 'status' => 'ouverte',
    ])->assertRedirect();

    Notification::assertSentTo($qualite, ValidationStepNotification::class);
    Notification::assertNotSentTo($chefAtelier, ValidationStepNotification::class);

    Notification::fake();
    $this->post(route('qualite.non-conformities.store'), [
        'title' => 'Rupture critique', 'severity' => 'critique', 'status' => 'ouverte',
    ])->assertRedirect();

    Notification::assertSentTo($qualite, ValidationStepNotification::class);
    Notification::assertSentTo($chefAtelier, ValidationStepNotification::class);
});

it('notifie le Service Maintenance à la création d\'une intervention (CDC §13.8)', function () {
    Notification::fake();

    $chefProduction = notifUser('chef_production');
    $technicien     = notifUser('technicien_maintenance');
    $company        = notifCompany();

    $machine = \App\Modules\Production\Models\ProductionMachine::factory()->create(['company_id' => $company->id]);

    $this->actingAs($chefProduction);
    $this->post(route('production.maintenance.store'), [
        'machine_id' => $machine->id, 'type' => 'corrective', 'title' => 'Panne moteur',
        'status' => 'planifie', 'planned_at' => now()->toDateString(),
    ])->assertRedirect();

    Notification::assertSentTo($technicien, ValidationStepNotification::class);
});
