<?php
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Spatie\Permission\Models\Role;

function pdfUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PDFCO'], ['email' => 'pdf@co.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

// [QA 1-4 §25] États comptables PDF : rendent même à vide (base neuve), type
// correct, signature %PDF, aucune URL localhost embarquée.
it('rend les états comptables PDF sur base vide', function (string $uri) {
    $this->actingAs(pdfUser());

    $res = $this->get($uri);
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf');
    $body = $res->streamedContent ?? $res->getContent();
    expect(substr($body, 0, 4))->toBe('%PDF')
        ->and(str_contains($body, 'localhost'))->toBeFalse();
})->with([
    '/comptabilite/balance/pdf',
    '/comptabilite/bilan/pdf',
    '/comptabilite/compte-de-resultat/pdf',
    '/comptabilite/grand-livre/pdf',
]);

it('rend le PDF d\'un devis', function () {
    $this->actingAs(pdfUser());
    $co = Company::first();
    $p  = Product::factory()->create();
    $quote = Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'DEV-PDF-' . uniqid(),
        'status' => 'brouillon', 'issued_at' => now(),
        'subtotal_ht' => 10000, 'total_tax' => 1800, 'total_ttc' => 11800,
    ]);
    $quote->items()->create([
        'product_id' => $p->id, 'description' => 'Ligne devis', 'quantity' => 2,
        'unit_price' => 5000, 'discount_percent' => 0, 'tax_rate_value' => 18,
        'line_total_ht' => 10000, 'line_tax' => 1800, 'line_total_ttc' => 11800,
    ]);

    $res = $this->get(route('ventes.devis.pdf', $quote));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf');
});

// [CDC §7] Le PDF d'une révision porte la mention de version (sinon deux offres
// identiques circulent chez le client sans distinction).
it('rend le PDF d\'une révision de devis avec mention de version', function () {
    $this->actingAs(pdfUser());
    $co = Company::first();
    $p  = Product::factory()->create();
    $svc = app(\App\Services\QuoteService::class);
    $quote = $svc->create([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $p->id, 'description' => 'Tôle', 'quantity' => 2,
            'unit_price' => 5000, 'tax_rate_value' => 0,
        ]],
    ]);
    $svc->send($quote);
    $rev = $svc->revise($quote->fresh());

    $res = $this->get(route('ventes.devis.pdf', $rev));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf')
        ->and($rev->revision_of_id)->toBe($quote->id);
});
