<?php

/**
 * [MRP] Propositions de transfert entre dépôts.
 *
 * BASE : la SUR-RÉSERVATION. Un dépôt dont les réservations dépassent le stock
 * physique a promis une marchandise qu'il ne détient pas ; un autre dépôt
 * disposant d'un excédent réel peut la couvrir.
 *
 * Les seuils par dépôt auraient été la base attendue — ils sont inutilisables :
 * `product_sites` porte bien des seuils par site, mais `warehouses` n'a aucune
 * colonne `site_id`. Le chaînage article → site → dépôt est rompu au dernier
 * maillon, et la table est vide. Bâtir dessus reviendrait à inventer un
 * rattachement.
 *
 * GARDE : quarantaine, rebuts et chutes ne sont JAMAIS source. Sortir de la
 * marchandise bloquée par la qualité vers un dépôt de vente contournerait la
 * barrière posée sur la livraison. Le drapeau `can_transfer` ne peut pas
 * l'assurer : il vaut 1 sur les dix dépôts réels, quarantaine comprise.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\TransferProposalService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param list<string> $permissions */
function trUser(array $permissions = ['production.view', 'production.update']): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TR-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'Transfer Co'], [
        'email' => 'transfer@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'tr_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

function trWarehouse(string $code, ?string $type = null, bool $active = true, int $canTransfer = 1): Warehouse
{
    return Warehouse::create([
        'company_id' => Company::first()->id, 'code' => $code, 'name' => 'Dépôt '.$code,
        'type' => $type, 'is_active' => $active, 'can_transfer' => $canTransfer,
    ]);
}

function trStock(Product $p, Warehouse $w, float $physique, float $reserve = 0): void
{
    ProductStock::create([
        'product_id' => $p->id, 'warehouse_id' => $w->id,
        'quantity' => $physique, 'reserved_quantity' => $reserve, 'avg_cost' => 500,
    ]);
}

function trProposals()
{
    return app(TransferProposalService::class)->proposals();
}

// ── Cas nominal ──────────────────────────────────────────────────────────────

it('propose un transfert quand un dépôt a réservé plus qu’il ne détient', function () {
    trUser();
    $p = Product::factory()->create();
    $manque   = trWarehouse('W-MANQUE');   // 10 réservés, 2 détenus → manque 8
    $excedent = trWarehouse('W-EXCEDENT'); // 50 détenus, 0 réservé  → dispo 50

    trStock($p, $manque, physique: 2, reserve: 10);
    trStock($p, $excedent, physique: 50);

    $props = trProposals();

    expect($props)->toHaveCount(1)
        ->and($props[0]['from']->id)->toBe($excedent->id)
        ->and($props[0]['to']->id)->toBe($manque->id)
        ->and($props[0]['quantite'])->toBe(8.0)
        ->and($props[0]['deficit'])->toBe(8.0);
});

it('ne propose rien quand aucun dépôt n’est sur-réservé', function () {
    trUser();
    $p = Product::factory()->create();
    trStock($p, trWarehouse('W-A'), physique: 50, reserve: 10);
    trStock($p, trWarehouse('W-B'), physique: 30);

    expect(trProposals())->toBeEmpty();
});

it('ne propose rien quand le manque existe mais qu’aucun dépôt n’a d’excédent', function () {
    trUser();
    $p = Product::factory()->create();
    trStock($p, trWarehouse('W-A'), physique: 2, reserve: 10);
    trStock($p, trWarehouse('W-B'), physique: 5, reserve: 5); // dispo 0

    expect(trProposals())->toBeEmpty();
});

it('plafonne la quantité à l’excédent réellement disponible', function () {
    trUser();
    $p = Product::factory()->create();
    $manque   = trWarehouse('W-MANQUE');
    $excedent = trWarehouse('W-PETIT');

    trStock($p, $manque, physique: 0, reserve: 100); // manque 100
    trStock($p, $excedent, physique: 30, reserve: 25); // dispo 5 seulement

    $props = trProposals();

    expect($props)->toHaveCount(1)
        ->and($props[0]['quantite'])->toBe(5.0)
        ->and($props[0]['deficit'])->toBe(100.0);   // le manque reste affiché en entier
});

it('répartit un manque sur plusieurs dépôts excédentaires', function () {
    trUser();
    $p = Product::factory()->create();
    $manque = trWarehouse('W-MANQUE');
    $gros   = trWarehouse('W-GROS');
    $petit  = trWarehouse('W-PETIT');

    trStock($p, $manque, physique: 0, reserve: 100);
    trStock($p, $gros, physique: 70);
    trStock($p, $petit, physique: 40);

    $props = trProposals();

    // Le plus gros excédent sert en premier : moins de transferts à volume égal.
    expect($props)->toHaveCount(2)
        ->and($props[0]['from']->id)->toBe($gros->id)
        ->and($props[0]['quantite'])->toBe(70.0)
        ->and($props[1]['from']->id)->toBe($petit->id)
        ->and($props[1]['quantite'])->toBe(30.0);
});

// ── Gardes de sécurité ───────────────────────────────────────────────────────

it('ne sort jamais de marchandise d’un dépôt de quarantaine', function () {
    // Transférer du stock en quarantaine vers un dépôt de vente contournerait la
    // barrière qualité posée sur la livraison.
    trUser();
    $p = Product::factory()->create();
    $manque = trWarehouse('W-VENTE', type: 'vente');
    trStock($p, $manque, physique: 0, reserve: 20);
    trStock($p, trWarehouse('W-QUAR', type: 'quarantaine'), physique: 500);

    expect(trProposals())->toBeEmpty();
});

it('ne sort jamais de marchandise des rebuts ni des chutes', function () {
    trUser();
    foreach (['rebuts', 'chutes'] as $i => $type) {
        $p = Product::factory()->create();
        trStock($p, trWarehouse('W-DEST-'.$i, type: 'vente'), physique: 0, reserve: 20);
        trStock($p, trWarehouse('W-SRC-'.$i, type: $type), physique: 500);
    }

    expect(trProposals())->toBeEmpty();
});

it('accepte en revanche la quarantaine comme DESTINATION', function () {
    // Y faire entrer de la marchandise est légitime — c'est en sortir qui ne l'est pas.
    trUser();
    $p = Product::factory()->create();
    $quarantaine = trWarehouse('W-QUAR', type: 'quarantaine');
    trStock($p, $quarantaine, physique: 0, reserve: 15);
    trStock($p, trWarehouse('W-PF', type: 'produit_fini'), physique: 100);

    $props = trProposals();

    expect($props)->toHaveCount(1)
        ->and($props[0]['to']->id)->toBe($quarantaine->id);
});

it('ignore un dépôt inactif, en source comme en destination', function () {
    trUser();
    $p = Product::factory()->create();
    trStock($p, trWarehouse('W-MANQUE'), physique: 0, reserve: 20);
    trStock($p, trWarehouse('W-INACTIF', active: false), physique: 500);

    expect(trProposals())->toBeEmpty();
});

it('honore can_transfer lorsqu’il devient restrictif', function () {
    // Le drapeau vaut 1 partout aujourd'hui, donc il ne garantit rien seul —
    // mais s'il est un jour mis à 0, il doit être respecté.
    trUser();
    $p = Product::factory()->create();
    trStock($p, trWarehouse('W-MANQUE'), physique: 0, reserve: 20);
    trStock($p, trWarehouse('W-BLOQUE', canTransfer: 0), physique: 500);

    expect(trProposals())->toBeEmpty();
});

it('ne propose jamais un transfert d’un dépôt vers lui-même', function () {
    trUser();
    $p = Product::factory()->create();
    // Un seul dépôt, sur-réservé : aucune source possible ailleurs.
    trStock($p, trWarehouse('W-SEUL'), physique: 2, reserve: 10);

    expect(trProposals())->toBeEmpty();
});

it('traite chaque article séparément, sans compensation entre eux', function () {
    trUser();
    $manquant  = Product::factory()->create();
    $abondant  = Product::factory()->create();
    $wManque   = trWarehouse('W-M');
    $wExcedent = trWarehouse('W-E');

    trStock($manquant, $wManque, physique: 0, reserve: 30);
    trStock($abondant, $wExcedent, physique: 500);      // autre article : ne couvre rien

    expect(trProposals())->toBeEmpty();
});

// ── Écran ────────────────────────────────────────────────────────────────────

it('affiche l’écran des propositions de transfert', function () {
    trUser();
    $p = Product::factory()->create();
    trStock($p, trWarehouse('W-MANQUE'), physique: 0, reserve: 20);
    trStock($p, trWarehouse('W-EXCEDENT'), physique: 100);

    $this->get(route('production.mrp.transfers'))->assertOk()
        ->assertSee($p->name)
        ->assertSee('Dépôt W-EXCEDENT')
        ->assertSee('Dépôt W-MANQUE');
});

it('affiche un écran vide et explicite quand rien n’est à transférer', function () {
    trUser();

    $this->get(route('production.mrp.transfers'))->assertOk()
        ->assertSee('Aucune proposition');
});

it('refuse l’écran à qui n’a pas le droit de piloter la production', function () {
    trUser(['production.view']);

    $this->get(route('production.mrp.transfers'))->assertForbidden();
});
