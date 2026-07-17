<?php

use App\Models\PayrollParameterVersion;
use App\Services\PayrollRegulationService;

uses(\Tests\Concerns\RefreshDatabase::class);

function regSvc(): PayrollRegulationService
{
    return app(PayrollRegulationService::class);
}

function regPublishSmig(string $debut, ?string $fin = null, int $montant = 45_000): PayrollParameterVersion
{
    return regSvc()->publish([
        'code'        => PayrollParameterVersion::CODE_SMIG,
        'libelle'     => 'SMIG',
        'type_valeur' => 'montant',
        'valeur'      => ['montant' => $montant],
        'date_debut'  => $debut,
        'date_fin'    => $fin,
    ]);
}

describe('Versionnement réglementaire', function () {

    it('publie une version et l\'active à sa date', function () {
        $v = regPublishSmig('2026-01-01');

        expect($v->statut)->toBe('actif')
            ->and($v->version)->toBe(1)
            ->and(PayrollParameterVersion::activeFor('SMIG', now()->setDate(2026, 6, 1))?->id)->toBe($v->id);
    });

    it('clôture automatiquement la version ouverte lors d\'une succession', function () {
        $v1 = regPublishSmig('2026-01-01');           // ouverte (date_fin NULL)
        $v2 = regPublishSmig('2026-07-01', null, 50_000);

        $v1->refresh();
        expect($v1->statut)->toBe('archive')
            ->and($v1->date_fin->format('Y-m-d'))->toBe('2026-06-30')
            ->and($v2->version)->toBe(2)
            ->and(PayrollParameterVersion::activeFor('SMIG', now()->setDate(2026, 8, 1))?->id)->toBe($v2->id);
    });

    it('refuse deux versions actives qui se chevauchent', function () {
        regPublishSmig('2026-01-01', '2026-12-31');

        expect(fn() => regPublishSmig('2026-06-01', '2026-09-30'))
            ->toThrow(\RuntimeException::class, 'Chevauchement');
    });

    it('permet une activation à une date future sans conflit', function () {
        regPublishSmig('2026-01-01', '2026-12-31');
        $future = regPublishSmig('2027-01-01', null, 50_000);

        expect($future->statut)->toBe('actif')
            ->and(PayrollParameterVersion::activeFor('SMIG', now()->setDate(2027, 3, 1))?->id)->toBe($future->id);
    });

    it('refuse un barème avec chevauchement de bornes', function () {
        expect(fn() => regSvc()->assertBracketsValid([
            [0, 30_000, 0],
            [25_000, 50_000, 12.1],   // chevauche la tranche 1
        ]))->toThrow(\RuntimeException::class, 'chevauchement');
    });

    it('refuse un barème avec un trou entre deux tranches', function () {
        expect(fn() => regSvc()->assertBracketsValid([
            [0, 30_000, 0],
            [40_000, 50_000, 12.1],   // trou 30 001 → 39 999
        ]))->toThrow(\RuntimeException::class, 'trou');
    });

    it('refuse une borne maximale inférieure à la borne minimale', function () {
        expect(fn() => regSvc()->assertBracketsValid([
            [0, 30_000, 0],
            [30_001, 20_000, 12.1],
        ]))->toThrow(\RuntimeException::class, 'supérieure à la borne minimale');
    });

    it('refuse une tranche illimitée qui n\'est pas la dernière', function () {
        expect(fn() => regSvc()->assertBracketsValid([
            [0, null, 0],
            [30_001, 50_000, 12.1],
        ]))->toThrow(\RuntimeException::class, 'dernière');
    });

    it('accepte le barème IUTS officiel BF complet', function () {
        regSvc()->assertBracketsValid([
            [0,        30_000,   0],
            [30_001,   50_000, 12.1],
            [50_001,   80_000, 13.9],
            [80_001,  120_000, 15.7],
            [120_001, 170_000, 18.4],
            [170_001, 250_000, 21.7],
            [250_001, null,      25],
        ]);
        expect(true)->toBeTrue();
    });

    it('refuse la suppression d\'une version couvrant une paie validée', function () {
        $company = bfCompany();
        bfSettings($company);
        bfAccountClasses($company);
        $this->actingAs(bfAdmin());
        bfEmployee($company, 200_000);

        $v = regPublishSmig('2020-01-01'); // couvre la date du run

        $svc = app(\App\Services\PayrollService::class);
        $run = $svc->createRun(['period_month' => 9, 'period_year' => 2025, 'notes' => '']);
        $svc->calculate($run);
        $svc->validate($run);

        expect(fn() => regSvc()->delete($v->fresh()))
            ->toThrow(\RuntimeException::class, 'suppression interdite');
    });

    it('supprime une version future jamais utilisée', function () {
        $v = regPublishSmig('2030-01-01');
        regSvc()->delete($v);

        expect(PayrollParameterVersion::find($v->id))->toBeNull();
    });
});
