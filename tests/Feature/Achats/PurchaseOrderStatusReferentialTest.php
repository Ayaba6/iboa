<?php

/**
 * [Achats] Les statuts de commande fournisseur sont au MASCULIN.
 *
 * `PurchaseInsightsService` filtrait sur « envoyee », « confirmee » et
 * « partiellement_recue ». L'énumération de `purchase_orders.status` est
 * `brouillon, envoye, confirme, partiellement_recu, recu, facture, annule` :
 * aucune de ces valeurs ne pouvait correspondre à une ligne. Les indicateurs
 * « commandes en cours » et « à recevoir » du tableau de bord achats
 * affichaient donc ZÉRO en permanence, sans jamais lever d'erreur — et le
 * regroupement par statut rendait des libellés vides, ses six clés étant
 * fausses elles aussi.
 *
 * La confusion s'explique : `invoices`, `supplier_invoices` et `rfqs` portent
 * bien des statuts au féminin. Seule la commande fournisseur est au masculin.
 * D'où un référentiel nommé sur le modèle, et ces tests qui l'ancrent.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseInsightsService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function poStatContext(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'POSTAT-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'PoStat Co'], [
        'email' => 'postat@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $co);
    test()->actingAs(User::factory()->create(['company_id' => $co->id]));

    return $co;
}

/** Commande fournisseur avec une ligne partiellement reçue. */
function poStatOrder(string $status, float $ttc = 100000, float $commande = 10, float $recu = 0): int
{
    $co = Company::first();
    $id = DB::table('purchase_orders')->insertGetId([
        'company_id' => $co->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'CF-STAT-'.uniqid(), 'status' => $status,
        'ordered_at' => now()->toDateString(),
        'total_ttc' => $ttc, 'subtotal_ht' => $ttc, 'total_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $id, 'product_id' => Product::factory()->create()->id,
        'description' => 'Ligne', 'quantity' => $commande, 'received_quantity' => $recu,
        'unit_price' => 1000, 'line_total_ht' => 1000 * $commande, 'line_tax' => 0,
        'line_total_ttc' => 1000 * $commande,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

// ── Le référentiel colle à la base ───────────────────────────────────────────

it('n’expose que des statuts réellement acceptés par la colonne', function () {
    // Le test le plus important : il écrit CHAQUE constante en base. Une valeur
    // qui n'existe pas dans l'énumération y échouerait — c'est exactement ce que
    // les anciennes chaînes au féminin n'ont jamais eu à subir, faute d'être
    // écrites quelque part.
    poStatContext();

    foreach (PurchaseOrder::STATUS_LABELS as $statut => $libelle) {
        $id = poStatOrder($statut);

        expect(DB::table('purchase_orders')->where('id', $id)->value('status'))->toBe($statut)
            ->and($libelle)->not->toBeEmpty();
    }
});

it('couvre tous les statuts de l’énumération, sans en inventer', function () {
    poStatContext();

    $enum = DB::select("SHOW COLUMNS FROM purchase_orders LIKE 'status'")[0]->Type ?? '';
    preg_match_all("/'([a-z_]+)'/", $enum, $m);

    expect(array_keys(PurchaseOrder::STATUS_LABELS))
        ->toEqualCanonicalizing($m[1]);
})->skip(fn () => DB::getDriverName() !== 'mysql', 'SHOW COLUMNS est propre à MySQL');

// ── Les indicateurs comptent enfin ───────────────────────────────────────────

it('compte les commandes en cours au lieu de rendre zéro', function () {
    poStatContext();
    poStatOrder(PurchaseOrder::STATUS_BROUILLON, ttc: 50000);
    poStatOrder(PurchaseOrder::STATUS_ENVOYE, ttc: 100000);
    poStatOrder(PurchaseOrder::STATUS_CONFIRME, ttc: 200000);
    poStatOrder(PurchaseOrder::STATUS_PARTIELLEMENT_RECU, ttc: 300000);
    // Ni soldée ni annulée : hors en-cours.
    poStatOrder(PurchaseOrder::STATUS_RECU, ttc: 999999);
    poStatOrder(PurchaseOrder::STATUS_ANNULE, ttc: 999999);

    $kpis = app(PurchaseInsightsService::class)->dashboardKpis();

    expect($kpis['open_po_count'])->toBe(4)
        ->and((int) $kpis['open_po_value'])->toBe(650000);
});

it('compte les commandes dont de la marchandise reste à recevoir', function () {
    poStatContext();
    poStatOrder(PurchaseOrder::STATUS_CONFIRME, commande: 10, recu: 0);
    poStatOrder(PurchaseOrder::STATUS_PARTIELLEMENT_RECU, commande: 10, recu: 4);
    // Entièrement reçue sur la ligne : plus rien à attendre.
    poStatOrder(PurchaseOrder::STATUS_CONFIRME, commande: 10, recu: 10);
    // Le brouillon n'engage rien, il n'est pas « attendu ».
    poStatOrder(PurchaseOrder::STATUS_BROUILLON, commande: 10, recu: 0);

    expect(app(PurchaseInsightsService::class)->dashboardKpis()['awaiting_receipt'])->toBe(2);
});

it('rend zéro quand il n’y a réellement aucune commande en cours', function () {
    // Le zéro doit rester possible : c'est un zéro CONSTATÉ, pas un zéro dû à
    // des valeurs de filtre introuvables.
    poStatContext();
    poStatOrder(PurchaseOrder::STATUS_RECU);
    poStatOrder(PurchaseOrder::STATUS_ANNULE);

    $kpis = app(PurchaseInsightsService::class)->dashboardKpis();

    expect($kpis['open_po_count'])->toBe(0)
        ->and($kpis['awaiting_receipt'])->toBe(0);
});

// ── Le regroupement par statut affiche enfin ses libellés ────────────────────

it('libelle chaque statut du pipeline au lieu de laisser un vide', function () {
    poStatContext();
    poStatOrder(PurchaseOrder::STATUS_CONFIRME, ttc: 120000);
    poStatOrder(PurchaseOrder::STATUS_PARTIELLEMENT_RECU, ttc: 80000);

    $pipeline = app(PurchaseInsightsService::class)->purchaseOrdersPipeline();
    $libelles = collect($pipeline)->pluck('label')->filter()->all();

    expect($libelles)->toContain('Confirmée')
        ->and($libelles)->toContain('Partiellement reçue');
});
