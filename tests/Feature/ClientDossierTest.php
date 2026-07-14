<?php

/**
 * [Dossier client] La synthèse affiche le statut PROPRE de chaque document
 * (devis / commande / OF / BL / facture / avoir) — jamais un statut « payé »
 * global. Répond au point 8 du rapport d'évaluation.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('affiche le statut propre de chaque document dans le dossier client', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'DOS'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'DOS Co'], ['email' => 'dos@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    $this->actingAs($u);

    $client  = Client::factory()->create(['name' => 'BTP Sahel', 'credit_limit' => 5000000, 'balance' => 0]);
    $product = Product::factory()->create();

    $quote = Quote::create(['company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id, 'number' => 'DEV-001', 'status' => 'converti', 'issued_at' => now(), 'subtotal_ht' => 100000, 'total_tax' => 18000, 'total_ttc' => 118000]);
    $order = Order::create(['company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id, 'quote_id' => $quote->id, 'number' => 'CMD-001', 'status' => 'livre', 'issued_at' => now()]);
    ProductionOrder::factory()->create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id, 'number' => 'OF-001', 'status' => 'termine', 'quantity_produced' => 100]);
    DeliveryNote::create(['company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id, 'number' => 'BL-001', 'status' => 'valide', 'issued_at' => now()]);
    Invoice::create(['company_id' => $co->id, 'client_id' => $client->id, 'order_id' => $order->id, 'number' => 'FAC-001', 'type' => 'facture', 'status' => 'payee', 'issued_at' => now(), 'subtotal_ht' => 100000, 'total_ttc' => 118000, 'paid_amount' => 118000, 'remaining_amount' => 0]);
    CreditNote::create(['company_id' => $co->id, 'client_id' => $client->id, 'number' => 'AV-001', 'status' => 'valide', 'issued_at' => now(), 'subtotal_ht' => 10000, 'total_ttc' => 11800, 'remaining_credit' => 11800]);

    $resp = $this->get(route('clients.dossier', $client));
    $resp->assertOk();

    // Chaque document porte SON statut, distinct
    $resp->assertSee('Converti');   // devis
    $resp->assertSee('Livrée');     // commande
    $resp->assertSee('Terminé');    // OF
    $resp->assertSee('Livré');      // BL
    $resp->assertSee('Payée');      // facture
    $resp->assertSee('Validé');     // avoir
    $resp->assertSee('Parcours des commandes');
    // Le statut « payé » n'est jamais collé à la commande / OF / BL
    $resp->assertDontSee('CMD-001</td><td class="px-3 py-2">Payé', false);
});
