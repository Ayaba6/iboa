<?php

/**
 * [CDC — Critère d'acceptation Comptabilité SYSCOHADA]
 *
 * « Le module Comptabilité sera accepté lorsque les opérations principales pourront
 *   être exécutées de bout en bout avec contrôles, droits, statuts, historique,
 *   documents et impacts automatiques sur les modules liés. »
 *
 *   - STATUTS + IMPACTS : écriture brouillon → validée → soldes des comptes mis à jour
 *   - CONTRÔLES : écriture déséquilibrée refusée ; période verrouillée refusée ;
 *     écriture validée non supprimable (contre-passation obligatoire)
 *   - LETTRAGE : compensation débit/crédit sur un compte tiers
 *   - CONTRE-PASSATION : annulation d'une écriture validée par écriture miroir
 *   - DROITS : accès aux états refusé sans permission
 *   - DOCUMENTS : balance générale (écran + PDF)
 */

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\AccountingPeriodLock;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Models\User;
use App\Services\JournalEntryService;
use App\Services\LettrageService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function comptaSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CPT'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CPT Co'], ['email' => 'cpt@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    $jt = JournalType::firstOrCreate(['company_id' => $co->id, 'code' => 'OD'], ['name' => 'Opérations diverses']);
    $classes = [];
    foreach ([4 => 'Tiers', 5 => 'Trésorerie', 7 => 'Produits'] as $n => $lbl) {
        $classes[$n] = AccountClass::firstOrCreate(['company_id' => $co->id, 'number' => $n], ['name' => $lbl]);
    }
    $acc = fn ($code, $name, $class) => Account::firstOrCreate(
        ['company_id' => $co->id, 'code' => $code],
        ['account_class_id' => $classes[$class]->id, 'name' => $name]
    );
    $accounts = [
        '411' => $acc('411000', 'Clients', 4),
        '701' => $acc('701000', 'Ventes', 7),
        '521' => $acc('521000', 'Banque', 5),
    ];

    return compact('co', 'u', 'jt', 'accounts');
}

function comptaEntry(array $ctx, string $date, array $lines): JournalEntry
{
    return app(JournalEntryService::class)->create([
        'journal_type_id' => $ctx['jt']->id,
        'entry_date'      => $date,
        'description'     => 'Écriture test',
        'lines'           => $lines,
    ]);
}

it('valide une écriture équilibrée et met à jour les soldes des comptes', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];

    $entry = comptaEntry($ctx, '2026-06-15', [
        ['account_id' => $a['411']->id, 'label' => 'Client', 'debit' => 118000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'Vente',  'debit' => 0, 'credit' => 100000],
        ['account_id' => $a['701']->id, 'label' => 'TVA',    'debit' => 0, 'credit' => 18000],
    ]);
    expect($entry->status)->toBe('brouillon')
        ->and($entry->total_debit)->toBe($entry->total_credit);

    app(JournalEntryService::class)->validate($entry);
    $entry->refresh();
    expect($entry->status)->toBe('valide');

    // Impact : soldes des comptes incrémentés
    expect((float) $a['411']->fresh()->debit_balance)->toBe(118000.0)
        ->and((float) $a['701']->fresh()->credit_balance)->toBe(118000.0);
});

it('refuse de valider une écriture déséquilibrée (contrôle)', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];

    $entry = comptaEntry($ctx, '2026-06-15', [
        ['account_id' => $a['411']->id, 'label' => 'Client', 'debit' => 100000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'Vente',  'debit' => 0, 'credit' => 90000], // déséquilibre
    ]);

    expect(fn () => app(JournalEntryService::class)->validate($entry))->toThrow(\RuntimeException::class);
});

it('refuse toute écriture sur une période verrouillée (contrôle)', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];

    AccountingPeriodLock::create([
        'company_id' => $ctx['co']->id, 'year' => 2026, 'month' => 5,
        'locked_at' => now(), 'locked_by' => $ctx['u']->id, 'reason' => 'Clôture mensuelle',
    ]);

    expect(fn () => comptaEntry($ctx, '2026-05-20', [
        ['account_id' => $a['411']->id, 'label' => 'x', 'debit' => 1000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'y', 'debit' => 0, 'credit' => 1000],
    ]))->toThrow(\RuntimeException::class);
});

it('interdit la suppression d’une écriture validée — contre-passation obligatoire (contrôle)', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];
    $entry = comptaEntry($ctx, '2026-06-15', [
        ['account_id' => $a['411']->id, 'label' => 'x', 'debit' => 1000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'y', 'debit' => 0, 'credit' => 1000],
    ]);
    app(JournalEntryService::class)->validate($entry);

    expect(fn () => app(JournalEntryService::class)->delete($entry->fresh()))->toThrow(\RuntimeException::class);
});

it('lettre une compensation débit/crédit sur un compte tiers', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];

    // Facture : débit 411 / crédit 701
    $inv = comptaEntry($ctx, '2026-06-01', [
        ['account_id' => $a['411']->id, 'label' => 'Facture', 'debit' => 50000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'Vente',   'debit' => 0, 'credit' => 50000],
    ]);
    app(JournalEntryService::class)->validate($inv);

    // Règlement : débit 521 / crédit 411
    $pay = comptaEntry($ctx, '2026-06-10', [
        ['account_id' => $a['521']->id, 'label' => 'Banque',    'debit' => 50000, 'credit' => 0],
        ['account_id' => $a['411']->id, 'label' => 'Règlement', 'debit' => 0, 'credit' => 50000],
    ]);
    app(JournalEntryService::class)->validate($pay);

    $line411Debit  = $inv->fresh()->lines->firstWhere('account_id', $a['411']->id);
    $line411Credit = $pay->fresh()->lines->firstWhere('account_id', $a['411']->id);

    $lettre = app(LettrageService::class)->lettre([$line411Debit->id, $line411Credit->id], $ctx['co']->id);

    expect($lettre)->not->toBeEmpty()
        ->and($line411Debit->fresh()->reconciliation_ref)->toBe($lettre)
        ->and($line411Credit->fresh()->reconciliation_ref)->toBe($lettre);
});

it('contre-passe une écriture validée par une écriture miroir', function () {
    $ctx = comptaSetup();
    $a = $ctx['accounts'];
    $entry = comptaEntry($ctx, '2026-06-15', [
        ['account_id' => $a['411']->id, 'label' => 'x', 'debit' => 30000, 'credit' => 0],
        ['account_id' => $a['701']->id, 'label' => 'y', 'debit' => 0, 'credit' => 30000],
    ]);
    app(JournalEntryService::class)->validate($entry);

    $reversal = app(JournalEntryService::class)->reverse($entry->fresh(), 'Erreur de saisie');

    expect($reversal->id)->not->toBe($entry->id);
    // Débit et crédit inversés au total
    expect($reversal->total_debit)->toBe($entry->total_debit)
        ->and($reversal->total_credit)->toBe($entry->total_credit);
    // Le compte 411 débité à l'origine est crédité dans la contre-passation
    $rev411 = $reversal->fresh()->lines->firstWhere('account_id', $a['411']->id);
    expect((float) $rev411->credit)->toBe(30000.0);
});

it('affiche la balance générale et refuse l’accès sans permission (droits + documents)', function () {
    $ctx = comptaSetup();
    Permission::firstOrCreate(['name' => 'accounting.view', 'guard_name' => 'web']);

    // Utilisateur avec droit → 200 (écran + PDF)
    $this->get('/comptabilite/balance')->assertOk();
    $pdf = $this->get('/comptabilite/balance/pdf');
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf');

    // Utilisateur sans droit → 403
    $role = Role::firstOrCreate(['name' => 'sans_droits_compta', 'guard_name' => 'web']);
    $u2 = User::factory()->create(['company_id' => $ctx['co']->id, 'email_verified_at' => now()]);
    $u2->assignRole($role);
    $this->actingAs($u2)->get('/comptabilite/balance')->assertForbidden();
});
