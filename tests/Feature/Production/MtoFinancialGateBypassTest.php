<?php

/**
 * [BUG-A3-MTO-FIN-001] La garde financière laisse passer un client comptant.
 *
 * `ProductionService::checkFinancialGate()` compare le mode de règlement aux
 * chaînes 'comptant', 'acompte' et 'credit'. La table `clients` stocke 'cash'
 * et 'credit'. Aucune des trois branches de refus ne s'applique donc à un
 * client comptoir : l'exécution atteint la ligne finale, qui écrit
 * `financial_authorization = 'approved'` sans auteur ni motif.
 *
 * Le défaut est ASYMÉTRIQUE, et c'est ce qui le rend durable :
 *   - un client 'credit' est bien bloqué ;
 *   - un client sans mode défini l'est aussi (`?? 'credit'`) ;
 *   - seul le comptant — celui qui doit payer d'avance — est exempté.
 *
 * Constaté en exploitation sur CMD-2026-006 / OF-2026-0007 : 236 000 FCFA dus,
 * 0 encaissé, aucune dérogation, OF passé au statut « lancé ».
 *
 * CE TEST EST ROUGE TANT QUE L'ANOMALIE EST OUVERTE. Il assert le comportement
 * ATTENDU, conformément à la méthode imposée : écrire un test qui échoue avant
 * correction. Il passera au vert le jour où la garde reconnaîtra 'cash'.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/** Monte une commande confirmée, sans le moindre encaissement. */
function gateContexte(string $modeReglement): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'GATE'], ['email' => 'gate@gate.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WG'], ['name' => 'WG', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $client = Client::factory()->create([
        'name' => 'Client '.$modeReglement,
        'payment_mode' => $modeReglement,
        'credit_limit' => 0,
        'is_active' => true,
    ]);

    $produit = Product::factory()->create([
        'is_active' => true, 'is_sellable' => true,
        'is_manufacturable' => false, // écarte la garde nomenclature : on éprouve la garde FINANCIÈRE
        'production_mode' => 'mto',
        'sale_price' => 236000,
    ]);

    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-GATE-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'total_ttc' => 236000, 'invoiced_amount' => 0,
        'production_approved' => false,
    ]);

    // `order_items` impose description, line_tax et line_total_ttc sans défaut.
    OrderItem::create([
        'order_id' => $commande->id, 'product_id' => $produit->id,
        'description' => $produit->name,
        'quantity' => 50, 'unit_price' => 4720,
        'line_total_ht' => 236000, 'line_tax' => 0, 'line_total_ttc' => 236000,
    ]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $fy->id,
        'order_id' => $commande->id, 'client_id' => $client->id,
        'product_id' => $produit->id,
        'number' => 'OF-GATE-'.uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 50, 'quantity_produced' => 0,
        'origin' => 'commande_client',
    ]);

    return ['of' => $of, 'commande' => $commande, 'client' => $client];
}

it('refuse de lancer un OF pour un client comptant qui n\'a rien payé', function () {
    ['of' => $of, 'commande' => $commande] = gateContexte('cash');

    // Rien n'a été encaissé : la garde financière DOIT refuser.
    expect((float) $commande->confirmedReceipts())->toBe(0.0);

    app(ProductionService::class)->launch($of);
})->throws(ValidationException::class);

it('n\'inscrit aucune autorisation financière sans paiement ni dérogation', function () {
    ['of' => $of] = gateContexte('cash');

    try {
        app(ProductionService::class)->launch($of);
    } catch (\Throwable) {
        // Le refus est éprouvé par le test précédent ; ici on examine la trace.
    }

    $of->refresh();

    // Une date d'autorisation sans auteur est le symptôme observé en
    // exploitation : l'écran affiche « Approuvée » sur la foi de cette date.
    expect($of->financial_authorization)->not->toBe('approved');
    expect($of->financial_authorized_at)->toBeNull();
    expect($of->status)->toBe('brouillon');
});

it('bloque bien un client à crédit — la garde n\'est pas absente, elle est aveugle', function () {
    ['of' => $of] = gateContexte('credit');

    // Contrôle en miroir, et cœur du diagnostic : le même code REFUSE le
    // crédit. La garde existe et fonctionne ; elle ne reconnaît simplement pas
    // la valeur que la base emploie pour le comptant.
    expect(fn () => app(ProductionService::class)->launch($of))
        ->toThrow(ValidationException::class);

    expect($of->fresh()->status)->toBe('brouillon');
});
