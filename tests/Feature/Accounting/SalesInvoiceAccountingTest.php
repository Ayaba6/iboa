<?php

/**
 * Comptabilisation d'une facture de vente.
 *
 * Point métier vérifié :
 *  - la validation d'une facture génère une écriture comptable équilibrée (SYSCOHADA) ;
 *  - la TVA collectée est comptabilisée ;
 *  - relancer la validation ne crée pas de doublon d'écriture (facture verrouillée) ;
 *  - une facture validée n'est plus re-validable sans repasser en brouillon.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function siaAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SIA'], ['email' => 'sia@sia.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function siaInvoice(): App\Models\Invoice
{
    $product = Product::factory()->create(['is_sellable' => true]);
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 SIA'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article facturé',
            'quantity' => 10, 'unit_price' => 1000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    // [Ventes §21.2] La facturation est limitée aux quantités LIVRÉES : facturer
    // une commande dont rien n'est sorti constaterait un produit (compte 70) et une
    // TVA collectée exigible avant tout transfert de propriété. Le sujet de ce test
    // est l'écriture comptable, pas le circuit de livraison — on marque donc la
    // ligne comme livrée intégralement pour placer la commande dans l'état où une
    // facture est légitime.
    $order->items()->update(['delivered_quantity' => 10]);

    return app(InvoiceService::class)->createFromOrder($order->fresh());
}

it('validation facture → écriture comptable équilibrée avec TVA collectée', function () {
    $this->actingAs(siaAdmin());
    $invoice = siaInvoice();

    expect($invoice->status)->toBe('brouillon');
    // 10 × 1000 = 10 000 HT ; TVA 1 800 ; TTC 11 800.
    expect((int) $invoice->total_tax)->toBe(1800);
    expect((int) $invoice->total_ttc)->toBe(11800);

    $validated = app(InvoiceService::class)->validate($invoice);
    expect($validated->status)->toBeIn(['emise', 'envoyee']);

    // Écriture générée et rattachée à la facture.
    $entry = JournalEntry::where('reference', $validated->number)->first();
    expect($entry)->not->toBeNull();
    expect((int) $validated->fresh()->journal_entry_id)->toBe((int) $entry->id);

    // Équilibrée : total débit = total crédit = TTC.
    expect((int) $entry->total_debit)->toBe((int) $entry->total_credit);
    expect((int) $entry->total_debit)->toBe(11800);

    // TVA collectée comptabilisée : une ligne au crédit exactement égale à la TVA.
    $tvaLine = $entry->lines->firstWhere('credit', 1800);
    expect($tvaLine)->not->toBeNull();
});

it('relancer la validation ne crée pas de doublon d’écriture (facture verrouillée)', function () {
    $this->actingAs(siaAdmin());
    $invoice = siaInvoice();

    $validated = app(InvoiceService::class)->validate($invoice);
    expect(JournalEntry::where('reference', $validated->number)->count())->toBe(1);

    // Seconde validation refusée (facture déjà émise).
    expect(fn () => app(InvoiceService::class)->validate($validated->fresh()))
        ->toThrow(\RuntimeException::class);

    // Toujours une seule écriture — pas de doublon comptable.
    expect(JournalEntry::where('reference', $validated->number)->count())->toBe(1);
    expect($validated->fresh()->status)->toBeIn(['emise', 'envoyee']);
});
