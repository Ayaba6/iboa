<?php

/**
 * [Ventes — TVA] Le mode de prix « exonere » doit tenir dans sa colonne.
 *
 * `price_mode` a été créée en `varchar(5)` quand elle ne connaissait que « ttc »
 * et « ht ». L'ajout du troisième mode — « exonere », sept caractères — la fait
 * déborder, et six Form Requests l'acceptent pourtant sur devis, commandes et
 * factures.
 *
 * Le défaut était INVISIBLE sur SQLite, qui n'applique pas la longueur d'un
 * VARCHAR. Seul MySQL le levait : « SQLSTATE[22001] Data too long for column
 * 'price_mode' ». En production, enregistrer un devis exonéré échouait — pas une
 * troncature silencieuse, un refus d'écriture pur et simple.
 *
 * Ces tests écrivent la valeur puis la RELISENT. Comparer après aller-retour est
 * ce qui distingue une colonne trop courte d'une colonne suffisante : sur un
 * moteur permissif, une valeur tronquée s'écrit sans erreur et ne se voit qu'à
 * la relecture.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0:Company,1:Client} */
function pmContext(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PM-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'PriceMode Co'], [
        'email' => 'pricemode@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $co);

    return [$co, Client::factory()->create(['is_active' => true])];
}

it('conserve « exonere » sur un devis, sans troncature', function () {
    [$co, $client] = pmContext();

    $quote = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'DEV-PM-'.uniqid(),
        'status' => 'brouillon', 'issued_at' => now(),
        'price_mode' => 'exonere',
        'subtotal_ht' => 100000, 'total_tax' => 0, 'total_ttc' => 100000,
    ]);

    expect($quote->fresh()->price_mode)->toBe('exonere');
});

it('conserve « exonere » sur une commande', function () {
    [$co, $client] = pmContext();

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-PM-'.uniqid(),
        'status' => 'brouillon', 'issued_at' => now(),
        'price_mode' => 'exonere',
    ]);

    expect($order->fresh()->price_mode)->toBe('exonere');
});

it('conserve « exonere » sur une facture', function () {
    [$co, $client] = pmContext();

    $invoice = Invoice::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'FAC-PM-'.uniqid(),
        'type' => 'facture', 'status' => 'brouillon', 'issued_at' => now(), 'due_at' => now()->addDays(30),
        'price_mode' => 'exonere',
        'subtotal_ht' => 100000, 'total_ttc' => 100000, 'paid_amount' => 0, 'remaining_amount' => 100000,
    ]);

    expect($invoice->fresh()->price_mode)->toBe('exonere');
});

it('conserve aussi les deux modes historiques', function () {
    // Élargir une colonne ne doit pas changer ce qu'elle stockait déjà.
    [$co, $client] = pmContext();

    foreach (['ttc', 'ht'] as $mode) {
        $q = Quote::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'client_id' => $client->id, 'number' => 'DEV-PM-'.$mode.'-'.uniqid(),
            'status' => 'brouillon', 'issued_at' => now(), 'price_mode' => $mode,
            'subtotal_ht' => 1000, 'total_tax' => 0, 'total_ttc' => 1000,
        ]);

        expect($q->fresh()->price_mode)->toBe($mode);
    }
});

it('applique toujours le mode par défaut propre à chaque cycle', function () {
    // La migration préserve `ttc` côté vente et `ht` côté achat : élargir une
    // colonne ne doit pas uniformiser des défauts métier distincts.
    [$co, $client] = pmContext();

    $quote = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'DEV-PM-DEF-'.uniqid(),
        'status' => 'brouillon', 'issued_at' => now(),
        'subtotal_ht' => 1000, 'total_tax' => 0, 'total_ttc' => 1000,
    ]);

    expect($quote->fresh()->price_mode)->toBe('ttc');
});
