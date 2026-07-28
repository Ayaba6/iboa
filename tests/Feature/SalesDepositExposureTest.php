<?php

/**
 * [Ventes §5] Acomptes et encours — preuve d'absence de DOUBLE COMPTAGE.
 *
 * Un acompte réduit l'encours client. Il ne doit le réduire :
 *   - qu'une seule fois, quel que soit le chemin par lequel on l'observe ;
 *   - que pour sa part réellement CONFIRMÉE et encore AFFECTABLE ;
 *   - jamais tant qu'il n'est pas confirmé, jamais après annulation.
 *
 * Chaque test part de pièces réelles. Aucun champ dénormalisé n'est écrit à la
 * main : un encours posé de force prouverait seulement qu'on sait écrire.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\ClientPaymentService;
use App\Services\CustomerCreditExposureService;
use Illuminate\Support\Facades\Auth;

/** @return array{company:Company,fy:FiscalYear,user:User,client:Client} */
function depositContext(int $creditLimit = 10_000_000): array
{
    $fy = FiscalYear::create([
        'label' => 'ACO-2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::create([
        'name' => 'OA METAL ACOMPTES', 'email' => 'acomptes@oa-metal.test',
        'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    Auth::login($user);

    $client = Client::factory()->create([
        'payment_mode' => 'credit', 'credit_limit' => $creditLimit, 'balance' => 0,
    ]);

    return ['company' => $company, 'fy' => $fy, 'user' => $user, 'client' => $client];
}

function depositInvoice(array $ctx, string $number, int $amount, string $status = 'emise'): Invoice
{
    return Invoice::create([
        'company_id' => $ctx['company']->id, 'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $ctx['client']->id, 'number' => $number, 'type' => 'facture',
        'status' => $status, 'issued_at' => now(), 'subtotal_ht' => $amount,
        'total_ttc' => $amount, 'remaining_amount' => $status === 'brouillon' ? 0 : $amount,
        'currency_code' => 'XOF',
    ]);
}

function depositPayment(array $ctx, string $number, int $amount, string $status, bool $isAcompte = true, int $allocated = 0): ClientPayment
{
    return ClientPayment::create([
        'company_id' => $ctx['company']->id, 'client_id' => $ctx['client']->id,
        'number' => $number, 'amount' => $amount, 'payment_date' => now()->toDateString(),
        'status' => $status, 'is_acompte' => $isAcompte,
        'allocated_amount' => $allocated,
        'unallocated_amount' => $status === 'confirme' ? max(0, $amount - $allocated) : 0,
        'currency_code' => 'XOF',
    ]);
}

function depositExposure(array $ctx): array
{
    return app(CustomerCreditExposureService::class)
        ->assessClient($ctx['client']->fresh(), (int) $ctx['company']->id);
}

// ---------------------------------------------------------------------------

it('1. n annule rien tant que l acompte n est pas confirmé', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-1', 6_000_000);
    depositPayment($ctx, 'ACO-1', 4_000_000, 'en_attente');

    $exposure = depositExposure($ctx);

    expect($exposure['deposits'])->toBe(0)
        ->and($exposure['projected'])->toBe(6_000_000);
});

it('2. déduit un acompte confirmé et non affecté', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-2', 6_000_000);
    depositPayment($ctx, 'ACO-2', 4_000_000, 'confirme');

    $exposure = depositExposure($ctx);

    expect($exposure['deposits'])->toBe(4_000_000)
        ->and($exposure['projected'])->toBe(2_000_000);
});

it('3. ignore un acompte annulé', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-3', 6_000_000);
    $deposit = depositPayment($ctx, 'ACO-3', 4_000_000, 'confirme');

    expect(depositExposure($ctx)['deposits'])->toBe(4_000_000);

    $deposit->forceFill(['status' => 'annule'])->save();

    $exposure = depositExposure($ctx);
    expect($exposure['deposits'])->toBe(0)
        ->and($exposure['projected'])->toBe(6_000_000);
});

it('4. ne déduit rien d un acompte intégralement affecté', function () {
    $ctx = depositContext();
    // Facture partiellement réglée par l'acompte : le reste dû est déjà net de
    // l'acompte. Le compter en plus le déduirait DEUX fois.
    $invoice = depositInvoice($ctx, 'FAC-ACO-4', 6_000_000);
    $invoice->forceFill(['remaining_amount' => 2_000_000, 'status' => 'partiellement_payee'])->save();
    depositPayment($ctx, 'ACO-4', 4_000_000, 'confirme', allocated: 4_000_000);

    $exposure = depositExposure($ctx);

    expect($exposure['deposits'])->toBe(0)
        ->and($exposure['outstanding'])->toBe(2_000_000)
        ->and($exposure['projected'])->toBe(2_000_000);
});

it('5. ne déduit que le reliquat d un acompte partiellement affecté', function () {
    $ctx = depositContext();
    $invoice = depositInvoice($ctx, 'FAC-ACO-5', 6_000_000);
    $invoice->forceFill(['remaining_amount' => 5_000_000, 'status' => 'partiellement_payee'])->save();
    depositPayment($ctx, 'ACO-5', 4_000_000, 'confirme', allocated: 1_000_000);

    $exposure = depositExposure($ctx);

    // 1 000 000 déjà imputés sur la facture (reste dû 5 000 000),
    // 3 000 000 encore affectables → déduits une seule fois.
    expect($exposure['deposits'])->toBe(3_000_000)
        ->and($exposure['outstanding'])->toBe(5_000_000)
        ->and($exposure['projected'])->toBe(2_000_000);
});

it('6. compte une seule fois le même reçu observé par deux chemins', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-6', 6_000_000);
    depositPayment($ctx, 'ACO-6', 4_000_000, 'confirme');

    // Chemin 1 : le service, source unique.
    $viaService = depositExposure($ctx);
    // Chemin 2 : le modèle Client, qui doit DÉLÉGUER au même service.
    $viaModel = $ctx['client']->fresh()->creditExposure((int) $ctx['company']->id);

    expect($viaModel)->toBe($viaService)
        ->and($viaService['deposits'])->toBe(4_000_000);

    // Et le disponible affiché est celui réellement appliqué au blocage.
    expect($ctx['client']->fresh()->available_credit)->toBe(10_000_000 - 2_000_000);
});

it('7. fait remonter l encours après remboursement de l acompte', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-7', 6_000_000);
    $deposit = depositPayment($ctx, 'ACO-7', 4_000_000, 'confirme');

    expect(depositExposure($ctx)['projected'])->toBe(2_000_000);

    // Remboursement : la part non affectée est rendue au client, elle ne couvre
    // plus rien. L'encours doit remonter immédiatement.
    $deposit->forceFill(['unallocated_amount' => 0, 'status' => 'rejete'])->save();

    $exposure = depositExposure($ctx);
    expect($exposure['deposits'])->toBe(0)
        ->and($exposure['projected'])->toBe(6_000_000);
});

it('8. refuse une affectation supérieure au reliquat de l acompte', function () {
    $ctx = depositContext();
    $invoice = depositInvoice($ctx, 'FAC-ACO-8', 6_000_000);
    $deposit = depositPayment($ctx, 'ACO-8', 4_000_000, 'confirme', allocated: 3_000_000);

    // Reliquat affectable : 1 000 000. Toute demande au-delà doit être refusée,
    // sinon deux affectations concurrentes sur-alloueraient le même reçu.
    expect(fn () => app(ClientPaymentService::class)->addAllocation($deposit->fresh(), $invoice->id, 2_000_000))
        ->toThrow(RuntimeException::class);

    // Aucune écriture partielle : le reçu est inchangé.
    $after = $deposit->fresh();
    expect((int) $after->allocated_amount)->toBe(3_000_000)
        ->and((int) $after->unallocated_amount)->toBe(1_000_000)
        ->and(depositExposure($ctx)['deposits'])->toBe(1_000_000);
});

it('9. un règlement ordinaire non-acompte ne réduit jamais l encours en tant qu acompte', function () {
    $ctx = depositContext();
    depositInvoice($ctx, 'FAC-ACO-9', 6_000_000);
    // Reçu confirmé mais NON marqué acompte : son affectation est indécise, il
    // ne réduit pas un encours identifié.
    depositPayment($ctx, 'ENC-9', 4_000_000, 'confirme', isAcompte: false);

    $exposure = depositExposure($ctx);

    expect($exposure['deposits'])->toBe(0)
        ->and($exposure['projected'])->toBe(6_000_000);
});

it('10. bloque une commande dont le plafond est dépassé malgré un acompte insuffisant', function () {
    $ctx = depositContext(creditLimit: 10_000_000);
    depositInvoice($ctx, 'FAC-ACO-10', 9_000_000);
    depositPayment($ctx, 'ACO-10', 1_000_000, 'confirme');

    $order = Order::create([
        'company_id' => $ctx['company']->id, 'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $ctx['client']->id, 'number' => 'CMD-ACO-10', 'status' => 'brouillon',
        'issued_at' => now(), 'subtotal_ht' => 3_000_000, 'total_ttc' => 3_000_000,
        'invoiced_amount' => 0, 'created_by' => $ctx['user']->id,
    ]);

    $service = app(CustomerCreditExposureService::class);
    $exposure = $service->assess($order);

    // 9 000 000 + 3 000 000 − 1 000 000 = 11 000 000 > 10 000 000
    expect($exposure['deposits'])->toBe(1_000_000)
        ->and($exposure['projected'])->toBe(11_000_000)
        ->and($exposure['limited'])->toBeTrue()
        ->and($exposure['available'])->toBe(0);
});
