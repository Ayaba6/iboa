<?php

/**
 * [CDC — Critère d'acceptation Trésorerie]
 *
 * « Le module Trésorerie sera accepté lorsque les opérations principales pourront être
 *   exécutées de bout en bout avec contrôles, droits, statuts, historique, documents
 *   et impacts automatiques sur les modules liés. »
 *
 * Les paiements (encaissement/décaissement), virements inter-comptes, seuils
 * d'approbation et clôture de caisse sont déjà couverts par ClientPaymentTest,
 * TreasuryWorkflowTest et TreasuryAccessControlTest. Ce test complète les
 * dimensions restantes du critère :
 *   - RAPPROCHEMENT BANCAIRE : création → pointage d'une ligne relevé sur une écriture → validation
 *   - PRÉVISION DE TRÉSORERIE : création (brouillon) → validation (valide)
 *   - CONTRÔLES : rapprochement déjà validé non re-validable
 *   - DROITS : accès au module refusé sans permission
 *   - DOCUMENTS : état de trésorerie (PDF)
 */

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\BankReconciliation;
use App\Models\CashAccount;
use App\Models\CashFlowForecast;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalType;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\CashFlowForecastService;
use App\Services\JournalEntryService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function tresoSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TRZ'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TRZ Co'], ['email' => 'trz@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'banque', 'current_balance' => 500000, 'is_active' => true]);

    return compact('co', 'u', 'cash');
}

it('rapproche une ligne de relevé bancaire avec une écriture puis valide le rapprochement', function () {
    $ctx = tresoSetup();
    $co  = $ctx['co'];

    // Écriture validée touchant un compte 521 (banque) → fournit une ligne à pointer
    $jt = JournalType::firstOrCreate(['company_id' => $co->id, 'code' => 'BQ'], ['name' => 'Banque']);
    $class5 = AccountClass::firstOrCreate(['company_id' => $co->id, 'number' => 5], ['name' => 'Trésorerie']);
    $class7 = AccountClass::firstOrCreate(['company_id' => $co->id, 'number' => 7], ['name' => 'Produits']);
    $acc521 = Account::firstOrCreate(['company_id' => $co->id, 'code' => '521000'], ['account_class_id' => $class5->id, 'name' => 'Banque']);
    $acc701 = Account::firstOrCreate(['company_id' => $co->id, 'code' => '701000'], ['account_class_id' => $class7->id, 'name' => 'Ventes']);

    $entry = app(JournalEntryService::class)->create([
        'journal_type_id' => $jt->id, 'entry_date' => '2026-05-05', 'description' => 'Encaissement client',
        'lines' => [
            ['account_id' => $acc521->id, 'label' => 'Banque', 'debit' => 500000, 'credit' => 0],
            ['account_id' => $acc701->id, 'label' => 'Vente',  'debit' => 0, 'credit' => 500000],
        ],
    ]);
    app(JournalEntryService::class)->validate($entry);
    $journalLine = $entry->fresh()->lines->firstWhere('account_id', $acc521->id);

    // Rapprochement + ligne de relevé correspondante
    $rec = app(BankReconciliationService::class)->create([
        'cash_account_id' => $ctx['cash']->id,
        'period_start'    => '2026-05-01', 'period_end' => '2026-05-31', 'statement_date' => '2026-05-31',
        'lines' => [['value_date' => '2026-05-05', 'label' => 'Virement client', 'reference' => 'VIR-1', 'debit' => 500000, 'credit' => 0]],
    ]);
    $bankLine = $rec->lines->first();

    app(BankReconciliationService::class)->matchLine($bankLine, $journalLine->id);

    expect($bankLine->fresh()->is_matched)->toBeTrue()
        ->and($journalLine->fresh()->reconciliation_ref)->toStartWith('RAPPR-');

    $validated = app(BankReconciliationService::class)->validate($rec->fresh());
    expect($validated->status)->toBe('valide');

    // Contrôle : un rapprochement validé n'est plus re-validable
    expect(fn () => app(BankReconciliationService::class)->validate($validated->fresh()))
        ->toThrow(\RuntimeException::class);
});

it('crée et valide une prévision de trésorerie (statuts)', function () {
    tresoSetup();

    $forecast = app(CashFlowForecastService::class)->create([
        'label'        => 'Prévision Q2 2026',
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
    ]);
    expect($forecast->status)->toBe('brouillon');

    $validated = app(CashFlowForecastService::class)->validateForecast($forecast);
    expect($validated->status)->toBe('valide');
});

it('refuse l’accès au module Trésorerie sans la permission (droits)', function () {
    $ctx = tresoSetup();
    Permission::firstOrCreate(['name' => 'treasury.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'sans_droits_treso', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $ctx['co']->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/tresorerie')->assertForbidden();
});

it('génère le document état de trésorerie en PDF (documents)', function () {
    tresoSetup();

    $resp = $this->get('/tresorerie/etat/pdf');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toContain('pdf');
});
