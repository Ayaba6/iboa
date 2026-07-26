<?php

/**
 * [ACHATS #9] Idempotence durable de la création de facture fournisseur +
 * [ACHATS #8] politique du numéro obligatoire à la validation.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\IdempotencyKey;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\SupplierInvoiceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function idemSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'IDEM'], ['email' => 'idem@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return [$co, Supplier::factory()->create(), Supplier::factory()->create()];
}

function idemData(int $supplierId, string $number, ?string $key = null, array $over = []): array
{
    return array_merge([
        'supplier_id'             => $supplierId,
        'supplier_invoice_number' => $number,
        'currency_code'           => 'XOF',
        'received_at'             => today(),
        'due_at'                  => today()->addDays(30),
        '_idempotency_key'        => $key,
        'items'                   => [],
    ], $over);
}

it('1. même clé + même payload → une seule facture (rejeu renvoie la même)', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $a = $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    $b = $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    expect($b->id)->toBe($a->id)
        ->and(SupplierInvoice::count())->toBe(1)
        ->and(IdempotencyKey::where('idempotency_key', 'KEY-1')->count())->toBe(1);
});

it('2. même clé + payload différent → refus explicite', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    expect(fn () => $svc->create(idemData($sup->id, 'F-1', 'KEY-1', ['currency_code' => 'EUR'])))
        ->toThrow(\RuntimeException::class, 'contenu différent');
});

it('3. même numéro + clés différentes → A1 empêche le doublon (une facture)', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(idemData($sup->id, 'F-1', 'KEY-A'));
    expect(fn () => $svc->create(idemData($sup->id, 'F-1', 'KEY-B')))
        ->toThrow(\RuntimeException::class, 'Doublon');
    expect(SupplierInvoice::count())->toBe(1);
});

it('4. même clé pour deux fournisseurs → payload différent → refus', function () {
    [, $sup, $sup2] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    expect(fn () => $svc->create(idemData($sup2->id, 'F-1', 'KEY-1')))
        ->toThrow(\RuntimeException::class, 'contenu différent');
});

it('5. retry après succès → renvoie la facture existante', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $a = $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    $again = $svc->create(idemData($sup->id, 'F-1', 'KEY-1'));
    expect($again->id)->toBe($a->id)->and(SupplierInvoice::count())->toBe(1);
});

it('6. retry après exception avant commit → clé libérée, seconde tentative réussit', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(idemData($sup->id, 'F-EXIST')); // numéro déjà pris (sans clé)

    // Tentative avec clé K sur un numéro en doublon → doCreate lève, la clé K est
    // annulée par le rollback de transaction (pas de clé « fantôme »).
    expect(fn () => $svc->create(idemData($sup->id, 'F-EXIST', 'KEY-R')))
        ->toThrow(\RuntimeException::class, 'Doublon');
    expect(IdempotencyKey::where('idempotency_key', 'KEY-R')->count())->toBe(0);

    // La même clé K est réutilisable pour une création valide.
    $ok = $svc->create(idemData($sup->id, 'F-NEW', 'KEY-R'));
    expect($ok->exists)->toBeTrue()
        ->and(IdempotencyKey::where('idempotency_key', 'KEY-R')->count())->toBe(1);
});

it('7/8/9. double-clic / job / import rejoués → une seule facture', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $a = $svc->create(idemData($sup->id, 'F-1', 'KEY-1', ['_source' => 'ui']));
    $svc->create(idemData($sup->id, 'F-1', 'KEY-1', ['_source' => 'job']));    // même clé
    $svc->create(idemData($sup->id, 'F-1', 'KEY-1', ['_source' => 'import'])); // même clé
    expect(SupplierInvoice::count())->toBe(1)
        ->and($svc->create(idemData($sup->id, 'F-1', 'KEY-1'))->id)->toBe($a->id);
});

it('10. clé vide → aucune idempotence (chemin normal, A1 reste actif)', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(idemData($sup->id, 'F-1', ''));      // sans idempotence
    $svc->create(idemData($sup->id, 'F-2', ''));      // numéro distinct
    expect(SupplierInvoice::count())->toBe(2)
        ->and(IdempotencyKey::count())->toBe(0);
});

it('11. clé trop longue → refus', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $tooLong = str_repeat('k', 129);
    expect(fn () => $svc->create(idemData($sup->id, 'F-1', $tooLong)))
        ->toThrow(\RuntimeException::class, 'trop longue');
});

it('12. création sans clé (chemin où elle est facultative) fonctionne', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $inv = $svc->create(idemData($sup->id, 'F-1')); // _idempotency_key null
    expect($inv->exists)->toBeTrue()->and(IdempotencyKey::count())->toBe(0);
});

// ── #8 politique numéro obligatoire à la validation ─────────────────────────
it('numéro obligatoire à la validation : brouillon sans numéro refusé à la validation', function () {
    [, $sup] = idemSetup();
    $svc = app(SupplierInvoiceService::class);
    $inv = $svc->create(idemData($sup->id, '')); // facture reçue sans numéro : tolérée avant validation
    expect($inv->exists)->toBeTrue()->and($inv->status)->toBe('recue');

    // Validation refusée tant que le numéro fournisseur est absent.
    expect(fn () => $svc->validate($inv->fresh()))
        ->toThrow(\RuntimeException::class, 'Numéro de facture fournisseur obligatoire');

    // Numéro renseigné → validation possible (garde levée).
    $svc->update($inv->fresh(), ['supplier_invoice_number' => 'F-OK']);
    $ok = $svc->validate($inv->fresh());
    expect($ok->status)->toBe('validee');
});
