<?php

/**
 * [FIX-OF-ORDER] Un ordre de fabrication ne se rattache qu'à une commande
 * réellement lançable.
 *
 * Le formulaire de création proposait TOUTES les commandes — annulées,
 * facturées, déjà pourvues d'un ordre, ou ne portant aucun article fabriqué à
 * la commande — et la validation se contentait de `exists:orders,id`. Le même
 * contrôleur disposait pourtant de `Order::eligibleForProduction()`, qui définit
 * la règle.
 *
 * Les deux volets comptent, mais inégalement : la liste évite l'erreur de bonne
 * foi, la garde serveur est la seule qui tienne devant une requête forgée. Un
 * `<select>` ne protège rien.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ofSociete(): Company
{
    $exercice = FiscalYear::firstOrCreate(
        ['label' => 'OFE-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $societe = Company::firstOrCreate(
        ['name' => 'OFE Co'],
        ['email' => 'ofe@iboa.test', 'current_fiscal_year_id' => $exercice->id],
    );
    app()->instance('current_company', $societe);

    return $societe;
}

function ofUtilisateur(): User
{
    $u = User::factory()->create(['company_id' => ofSociete()->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $u;
}

/** Commande MTO approuvée pour la production : le cas lançable. */
function ofCommandeLancable(string $numero = 'CMD-OFE-1'): Order
{
    $societe = ofSociete();

    $article = Product::factory()->create([
        'production_mode' => 'mto', 'is_manufacturable' => true,
        'is_sellable' => true, 'is_active' => true, 'sale_price' => 10000,
    ]);

    $commande = Order::create([
        'company_id' => $societe->id,
        'fiscal_year_id' => $societe->current_fiscal_year_id,
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'number' => $numero, 'status' => 'confirme',
        'issued_at' => now(), 'total_ttc' => 10000,
        'production_approved' => true,
    ]);

    DB::table('order_items')->insert([
        'order_id' => $commande->id, 'product_id' => $article->id,
        'description' => 'Ligne OFE', 'quantity' => 1, 'unit_price' => 10000,
        // Totaux de ligne obligatoires en base : l'insertion directe
        // court-circuite le service qui les calcule d'ordinaire.
        'line_total_ht' => 10000, 'line_tax' => 1800, 'line_total_ttc' => 11800,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $commande->fresh();
}

it('1. propose une commande lançable dans le formulaire', function () {
    $commande = ofCommandeLancable();

    // Garde du test : sans elle, un scope trop strict rendrait tout vert en ne
    // proposant jamais rien.
    expect(Order::eligibleForProduction()->count())->toBe(1);

    $this->actingAs(ofUtilisateur())
        ->get(route('production.orders.create'))
        ->assertOk()
        ->assertSee($commande->number);
});

it('2. ne propose PAS une commande facturée', function () {
    $commande = ofCommandeLancable('CMD-OFE-FACTUREE');
    $commande->update(['status' => 'facture']);

    $this->actingAs(ofUtilisateur())
        ->get(route('production.orders.create'))
        ->assertOk()
        ->assertDontSee($commande->number);
});

it('3. ne propose PAS une commande annulée', function () {
    $commande = ofCommandeLancable('CMD-OFE-ANNULEE');
    $commande->update(['status' => 'annule']);

    $this->actingAs(ofUtilisateur())
        ->get(route('production.orders.create'))
        ->assertOk()
        ->assertDontSee($commande->number);
});

it('4. REFUSE côté serveur une commande non lançable', function () {
    // Le test qui compte. Une requête forgée ignore le contenu du `<select>` ;
    // seule la validation protège réellement.
    $commande = ofCommandeLancable('CMD-OFE-FORGEE');
    $commande->update(['status' => 'annule']);

    $article = Product::factory()->create([
        'production_mode' => 'mto', 'is_manufacturable' => true, 'is_active' => true,
    ]);

    $this->actingAs(ofUtilisateur())
        ->from(route('production.orders.create'))
        ->post(route('production.orders.store'), [
            'order_id'   => $commande->id,
            'product_id' => $article->id,
            'quantity'   => 1,
        ])
        ->assertSessionHasErrors('order_id');

    expect(ProductionOrder::where('order_id', $commande->id)->count())->toBe(0);
});

it('5. refuse une commande qui porte déjà un ordre de fabrication actif', function () {
    // Le scope exclut ces commandes ; la garde doit en faire autant, sans quoi
    // deux ordres viseraient la même commande.
    $commande = ofCommandeLancable('CMD-OFE-DEJA-OF');

    ProductionOrder::create([
        'company_id' => ofSociete()->id,
        'order_id'   => $commande->id,
        'product_id' => DB::table('order_items')->where('order_id', $commande->id)->value('product_id'),
        'number'     => 'OF-OFE-EXISTANT',
        'status'     => 'lance',
        'quantity'   => 1,
    ]);

    expect(Order::eligibleForProduction()->whereKey($commande->id)->exists())->toBeFalse();

    $this->actingAs(ofUtilisateur())
        ->from(route('production.orders.create'))
        ->post(route('production.orders.store'), [
            'order_id'   => $commande->id,
            'product_id' => Product::factory()->create([
                'production_mode' => 'mto', 'is_manufacturable' => true, 'is_active' => true,
            ])->id,
            'quantity'   => 1,
        ])
        ->assertSessionHasErrors('order_id');
});

it('6. accepte sans commande — un OF peut être lancé sur stock', function () {
    // `order_id` reste `nullable` : la production pour stock (MTS) n'a pas de
    // commande, et la garde ne doit pas l'interdire.
    $article = Product::factory()->create([
        'production_mode' => 'mts', 'is_manufacturable' => true, 'is_active' => true,
    ]);

    $this->actingAs(ofUtilisateur())
        ->from(route('production.orders.create'))
        ->post(route('production.orders.store'), [
            'product_id' => $article->id,
            'quantity'   => 5,
        ])
        ->assertSessionDoesntHaveErrors('order_id');
});

it('7. laisse modifier un OF sans perdre sa commande d’origine', function () {
    // Piège de l'édition : dès qu'un OF existe, sa propre commande cesse d'être
    // éligible — c'est lui qui la rend inéligible. Refuser cette commande
    // rendrait tout enregistrement impossible.
    $commande = ofCommandeLancable('CMD-OFE-EDITION');
    $article = DB::table('order_items')->where('order_id', $commande->id)->value('product_id');

    $of = ProductionOrder::create([
        'company_id' => ofSociete()->id,
        'order_id'   => $commande->id,
        'product_id' => $article,
        'number'     => 'OF-OFE-EDIT',
        'status'     => 'brouillon',
        'quantity'   => 1,
    ]);

    expect(Order::eligibleForProduction()->whereKey($commande->id)->exists())->toBeFalse();

    // Le formulaire d'édition doit toujours afficher la commande rattachée.
    $this->actingAs(ofUtilisateur())
        ->get(route('production.orders.edit', $of))
        ->assertOk()
        ->assertSee($commande->number);

    $this->actingAs(ofUtilisateur())
        ->from(route('production.orders.edit', $of))
        ->put(route('production.orders.update', $of), [
            'order_id'   => $commande->id,
            'product_id' => $article,
            'quantity'   => 2,
        ])
        ->assertSessionDoesntHaveErrors('order_id');

    expect((int) $of->fresh()->order_id)->toBe($commande->id);
});
