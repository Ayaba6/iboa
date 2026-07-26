<?php

/**
 * [ACHATS A1 — durci] Anti-doublon facture fournisseur.
 * Règle : même fournisseur + même numéro NORMALISÉ = doublon. Numéro RÉSERVÉ
 * dans l'historique (annulée / soft-deleted comprises). Numéro fournisseur
 * facultatif : sans lui, aucun contrôle. Deux fournisseurs distincts peuvent
 * partager un numéro.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\SupplierInvoiceService;
use App\Support\SupplierInvoiceNumber;
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

function siData(int $supplierId, ?string $number): array
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

// ── Normaliseur (politique documentée) ──────────────────────────────────────
it('normalise casse, espaces, tirets Unicode et caractères invisibles', function () {
    $ref = SupplierInvoiceNumber::normalize('FAC-2026-001');
    expect(SupplierInvoiceNumber::normalize('fac-2026-001'))->toBe($ref)      // casse
        ->and(SupplierInvoiceNumber::normalize('  FAC-2026-001  '))->toBe($ref) // trim
        ->and(SupplierInvoiceNumber::normalize("FAC-2026-001\u{200B}"))->toBe($ref) // zero-width
        ->and(SupplierInvoiceNumber::normalize('FAC–2026–001'))->toBe($ref)    // en-dash → hyphen
        ->and(SupplierInvoiceNumber::normalize('FAC—2026—001'))->toBe($ref);   // em-dash → hyphen
    // Décision documentée : espace ≠ tiret (ne PAS fusionner des refs distinctes)
    expect(SupplierInvoiceNumber::normalize('FAC 2026 001'))->not->toBe($ref);
    // Espaces seuls / vide → null (pas de contrôle)
    expect(SupplierInvoiceNumber::normalize('   '))->toBeNull()
        ->and(SupplierInvoiceNumber::normalize(''))->toBeNull()
        ->and(SupplierInvoiceNumber::normalize(null))->toBeNull();
});

// ── Portée fournisseur ──────────────────────────────────────────────────────
it('refuse un doublon (même fournisseur, même numéro normalisé)', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, 'F-2026-001'));

    expect(fn () => $svc->create(siData($sup->id, ' f-2026-001 ')))   // variante normalisée
        ->toThrow(\RuntimeException::class, 'Doublon');
    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(1);
});

it('autorise le même numéro pour deux fournisseurs différents', function () {
    [, $sup, $sup2] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, 'F-2026-001'));
    $svc->create(siData($sup2->id, 'F-2026-001'));
    expect(SupplierInvoice::where('supplier_invoice_number', 'F-2026-001')->count())->toBe(2);
});

it('autorise deux numéros distincts pour le même fournisseur', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, 'F-2026-001'));
    $svc->create(siData($sup->id, 'F-2026-002'));
    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(2);
});

// ── Numéro facultatif ───────────────────────────────────────────────────────
it('n\'applique aucun contrôle quand le numéro est absent, vide ou espaces seuls', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, null));
    $svc->create(siData($sup->id, ''));
    $svc->create(siData($sup->id, '   '));
    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(3);
});

// ── Réservation dans l'historique ───────────────────────────────────────────
it('réserve le numéro même après ANNULATION de la facture', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $inv = $svc->create(siData($sup->id, 'F-RES-001'));
    $inv->update(['status' => 'annulee']); // facture annulée

    expect(fn () => $svc->create(siData($sup->id, 'F-RES-001')))
        ->toThrow(\RuntimeException::class, 'réservé');
});

it('réserve le numéro même après SUPPRESSION LOGIQUE (soft delete)', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $inv = $svc->create(siData($sup->id, 'F-SD-001'));
    $inv->delete(); // soft delete

    expect(fn () => $svc->create(siData($sup->id, 'F-SD-001')))
        ->toThrow(\RuntimeException::class, 'Doublon');
    expect(SupplierInvoice::withTrashed()->where('supplier_id', $sup->id)->count())->toBe(1);
});

// ── Modification ────────────────────────────────────────────────────────────
it('refuse la mise à jour d\'une facture vers le numéro d\'une autre facture du même fournisseur', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, 'F-A'));
    $b = $svc->create(siData($sup->id, 'F-B'));

    expect(fn () => $svc->update($b->fresh(), ['supplier_invoice_number' => 'F-A']))
        ->toThrow(\RuntimeException::class, 'Doublon');
    expect($b->fresh()->supplier_invoice_number)->toBe('F-B'); // inchangé
});

it('autorise le changement de fournisseur sur un brouillon (numéro libre sous le nouveau fournisseur)', function () {
    [, $sup, $sup2] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $svc->create(siData($sup->id, 'F-X'));          // sup : F-X
    $b = $svc->create(siData($sup2->id, 'F-Y'));    // sup2 : F-Y

    // b passe chez sup en gardant F-Y : F-Y libre chez sup → autorisé
    $svc->update($b->fresh(), ['supplier_id' => $sup->id]);
    expect((int) $b->fresh()->supplier_id)->toBe($sup->id);

    // mais b→F-X chez sup = doublon → refus
    expect(fn () => $svc->update($b->fresh(), ['supplier_invoice_number' => 'F-X']))
        ->toThrow(\RuntimeException::class, 'Doublon');
});

// ── Robustesse entrée ───────────────────────────────────────────────────────
it('accepte un numéro Unicode et un numéro très long sans collision indue', function () {
    [, $sup] = siSetup();
    $svc = app(SupplierInvoiceService::class);
    $long = 'FAC-' . str_repeat('9', 40);
    $svc->create(siData($sup->id, $long));
    $svc->create(siData($sup->id, 'FÄC-2026-Ω-001')); // unicode distinct
    expect(SupplierInvoice::where('supplier_id', $sup->id)->count())->toBe(2);

    // Le très long numéro est bien réservé (doublon normalisé)
    expect(fn () => $svc->create(siData($sup->id, $long)))
        ->toThrow(\RuntimeException::class, 'Doublon');
});

it('barrière DB : l\'index unique rejette un doublon inséré hors service (preuve concurrence)', function () {
    [, $sup] = siSetup();
    $first = app(SupplierInvoiceService::class)->create(siData($sup->id, 'F-DB-001'));

    // Réplique une ligne physique valide avec un numéro interne différent mais la
    // MÊME clé normalisée : c'est exactement ce que tente la transaction perdante
    // sous vraie concurrence. La contrainte d'unicité DB doit la rejeter.
    $row = $first->fresh()->getAttributes();
    unset($row['id']);
    $row['number'] = 'INT-DUP-' . uniqid();

    expect(fn () => \Illuminate\Support\Facades\DB::table('supplier_invoices')->insert($row))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    // Une seule facture subsiste réellement.
    expect(SupplierInvoice::withTrashed()->where('supplier_id', $sup->id)->count())->toBe(1);
});

it('persiste la clé normalisée en base', function () {
    [, $sup] = siSetup();
    $inv = app(SupplierInvoiceService::class)->create(siData($sup->id, ' fac-2026-050 '));
    expect($inv->fresh()->supplier_invoice_number_normalized)->toBe('FAC-2026-050');
});
