<?php

/**
 * [ACHATS A1] Anti-doublon facture fournisseur : un même numéro fournisseur ne
 * peut être saisi deux fois pour le MÊME fournisseur (risque de double paiement).
 * Le même numéro reste possible pour deux fournisseurs distincts.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\SupplierInvoiceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function siSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'ACH'], ['email' => 'ach@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return [$co, Supplier::factory()->create(), Supplier::factory()->create()];
}

function siData(int $supplierId, string $number): array
{
    return [
        'supplier_id'             => $supplierId,
        'supplier_invoice_number' => $number,
        'currency_code'           => 'XOF',
        'received_at'             => today(),
        'due_at'                  => today()->addDays(30),
        'items'                   => [],
    ];
}

it('refuse un doublon de numéro fournisseur pour le même fournisseur', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);

    $svc->create(siData($sup->id, 'F-2026-001')); // 1re : OK

    // 2e saisie du MÊME numéro pour le MÊME fournisseur → refus
    expect(fn () => $svc->create(siData($sup->id, 'F-2026-001')))
        ->toThrow(\RuntimeException::class, 'Doublon');

    expect(SupplierInvoice::where('supplier_id', $sup->id)->where('supplier_invoice_number', 'F-2026-001')->count())->toBe(1);
});

it('autorise le même numéro pour deux fournisseurs différents', function () {
    [, $sup, $sup2] = siSetup();
    $svc = app(SupplierInvoiceService::class);

    $svc->create(siData($sup->id, 'F-2026-001'));
    $svc->create(siData($sup2->id, 'F-2026-001')); // autre fournisseur → autorisé

    expect(SupplierInvoice::where('supplier_invoice_number', 'F-2026-001')->count())->toBe(2);
});

it('autorise deux numéros différents pour le même fournisseur', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);

    $svc->create(siData($sup->id, 'F-2026-001'));
    $svc->create(siData($sup->id, 'F-2026-002'));

    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(2);
});

it('n\'applique aucun contrôle d\'unicité quand le numéro fournisseur est absent', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);

    $d = siData($sup->id, '');
    $svc->create($d);
    $svc->create($d); // deux factures sans numéro fournisseur → tolérées

    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(2);
});
