<?php

/**
 * [CDC §7 — versionnement devis]
 *  - un devis figé se révise : nouvelle version liée (revision_of_id, n° incrémenté),
 *    l'original reste intact et devient non convertible ;
 *  - un devis expiré n'est pas convertible ;
 *  - une seule révision active à la fois ; une révision annulée libère l'original.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

/**
 * Marque un devis « envoyé » pour les besoins du test.
 *
 * `QuoteService::send()` a été retirée : elle permettait cette transition sans
 * aucun contrôle commercial [BUG-A3-SALES-ZERO-PRICE-026]. Ces tests portent sur
 * la RÉVISION, pas sur le workflow — l'état de départ est donc posé
 * directement, comme un fixture, sans prétendre emprunter un chemin métier.
 */
function qrvMarquerEnvoye(\App\Models\Quote $devis): \App\Models\Quote
{
    \App\Models\Quote::whereKey($devis->id)->update(['status' => 'envoye']);

    return $devis->refresh();
}


function qrvAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QRV'], ['email' => 'qrv@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

function qrvQuote(array $overrides = []): Quote
{
    $p = Product::factory()->create();

    return app(QuoteService::class)->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'issued_at' => now()->toDateString(),
        'expires_at' => now()->addDays(30)->toDateString(),
        'items' => [[
            'product_id' => $p->id, 'description' => 'Tôle', 'quantity' => 5,
            'unit_price' => 10000, 'tax_rate_value' => 0,
        ]],
    ], $overrides));
}

it('révise un devis envoyé : version liée, original figé et non convertible', function () {
    qrvAdmin();
    $svc = app(QuoteService::class);
    $quote = qrvQuote();
    qrvMarquerEnvoye($quote);

    $rev = $svc->revise($quote->fresh());

    expect($rev->revision_of_id)->toBe($quote->id)
        ->and((int) $rev->revision_number)->toBe(2)
        ->and($rev->status)->toBe('brouillon')
        ->and($rev->items()->count())->toBe(1)
        ->and((int) $rev->total_ttc)->toBe((int) $quote->total_ttc);

    // Original intact (snapshot consultable)
    expect($quote->fresh()->status)->toBe('envoye')
        ->and((int) $quote->fresh()->total_ttc)->toBe(50000);

    // Original non convertible tant que la révision est active
    $quote->fresh()->update(['status' => 'accepte']);
    try {
        $svc->convertToOrder($quote->fresh());
        $this->fail('La conversion aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('révision');
    }

    // Une seule révision active à la fois
    expect(fn () => $svc->revise($quote->fresh()))->toThrow(\RuntimeException::class);
});

it('refuse la conversion d\'un devis expiré', function () {
    qrvAdmin();
    $svc = app(QuoteService::class);
    $quote = qrvQuote(['expires_at' => now()->subDays(3)->toDateString()]);
    $quote->update(['status' => 'accepte']);

    try {
        $svc->convertToOrder($quote->fresh());
        $this->fail('La conversion aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('expiré');
    }
    // acceptAndConvert refuse AVANT de changer le statut
    $q2 = qrvQuote(['expires_at' => now()->subDay()->toDateString()]);
    qrvMarquerEnvoye($q2);
    try {
        $svc->acceptAndConvert($q2->fresh());
        $this->fail('La conversion aurait dû être refusée.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('expiré');
    }
    expect($q2->fresh()->status)->toBe('envoye'); // état inchangé
});

it('libère l\'original quand la révision est annulée, et la révision se convertit', function () {
    qrvAdmin();
    $svc = app(QuoteService::class);
    $quote = qrvQuote();
    qrvMarquerEnvoye($quote);
    $rev = $svc->revise($quote->fresh());

    // Révision annulée → l'original redevient convertible
    $rev->update(['status' => 'envoye']);
    $svc->cancel($rev->fresh());
    $quote->fresh()->update(['status' => 'accepte']);
    $order = $svc->convertToOrder($quote->fresh());
    expect($order->quote_id)->toBe($quote->id);

    // Une révision d'un devis converti est refusée
    expect(fn () => $svc->revise($quote->fresh()))->toThrow(\RuntimeException::class);
});

// [Automation] Le batch quotidien expire les offres émises (envoye + valide),
// pas les brouillons ni les devis acceptés avant expiration.
it('automation:daily expire les devis envoyés ET validés à échéance dépassée', function () {
    qrvAdmin();
    $svc = app(QuoteService::class);

    $envoye = qrvQuote(['expires_at' => now()->subDays(2)->toDateString()]);
    qrvMarquerEnvoye($envoye);
    $valide = qrvQuote(['expires_at' => now()->subDays(2)->toDateString()]);
    $valide->update(['status' => 'valide']);
    $brouillon = qrvQuote(['expires_at' => now()->subDays(2)->toDateString()]);
    $accepte = qrvQuote(['expires_at' => now()->subDays(2)->toDateString()]);
    $accepte->update(['status' => 'accepte']);
    $enCours = qrvQuote(['expires_at' => now()->addDays(10)->toDateString()]);
    qrvMarquerEnvoye($enCours);

    $this->artisan('automation:daily')->assertExitCode(0);

    expect($envoye->fresh()->status)->toBe('expire')
        ->and($valide->fresh()->status)->toBe('expire')
        ->and($brouillon->fresh()->status)->toBe('brouillon')
        ->and($accepte->fresh()->status)->toBe('accepte')
        ->and($enCours->fresh()->status)->toBe('envoye');
});
