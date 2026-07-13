<?php

/**
 * [QUA-05] Actions correctives (CAPA) — cycle de vie + clôture NC sur efficacité.
 */

use App\Models\Company;
use App\Modules\Quality\Models\CorrectiveAction;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Services\CorrectiveActionService;

uses(\Tests\Concerns\RefreshDatabase::class);

function capaNc(): NonConformity
{
    $co = Company::firstOrCreate(['name' => 'CAPA Co'], ['email' => 'capa@iboa.test']);

    return NonConformity::create([
        'company_id' => $co->id, 'reference' => 'NC-2026-001',
        'title' => 'Épaisseur tôle hors tolérance', 'severity' => 'majeure', 'status' => 'ouverte',
    ]);
}

function makeAction(NonConformity $nc): CorrectiveAction
{
    return CorrectiveAction::create([
        'company_id' => $nc->company_id, 'non_conformity_id' => $nc->id,
        'reference' => 'NC-2026-001/A01', 'type' => 'corrective',
        'action_plan' => 'Recalibrer la profileuse', 'status' => 'a_faire',
    ]);
}

it('déroule le cycle a_faire → en_cours → faite', function () {
    $svc = app(CorrectiveActionService::class);
    $a = makeAction(capaNc());

    $svc->changeStatus($a, 'en_cours');
    expect($a->fresh()->status)->toBe('en_cours');

    $svc->changeStatus($a, 'faite');
    expect($a->fresh()->status)->toBe('faite');
    expect($a->fresh()->completed_at)->not->toBeNull();
});

it('clôture la NC quand l’unique action est vérifiée efficace', function () {
    $nc = capaNc();
    $svc = app(CorrectiveActionService::class);
    $a = makeAction($nc);
    $svc->changeStatus($a, 'faite');

    $svc->verify($a, true, 'Contrôle 3 lots conformes', null);

    expect($a->fresh()->status)->toBe('verifiee');
    expect($a->fresh()->is_effective)->toBeTrue();
    expect($nc->fresh()->status)->toBe('cloturee');
    expect($nc->fresh()->closed_at)->not->toBeNull();
});

it('remet l’action en cours et ne clôture pas la NC si inefficace', function () {
    $nc = capaNc();
    $svc = app(CorrectiveActionService::class);
    $a = makeAction($nc);
    $svc->changeStatus($a, 'faite');

    $svc->verify($a, false, 'NC réapparue au lot suivant', null);

    expect($a->fresh()->status)->toBe('en_cours');
    expect($a->fresh()->is_effective)->toBeFalse();
    expect($nc->fresh()->status)->not->toBe('cloturee');
});

it('ne clôture pas la NC tant qu’une action reste non vérifiée', function () {
    $nc = capaNc();
    $svc = app(CorrectiveActionService::class);
    $a1 = makeAction($nc);
    $a2 = makeAction($nc);
    $svc->changeStatus($a1, 'faite');
    $svc->verify($a1, true, 'ok', null);

    // a2 encore ouverte → NC pas clôturée
    expect($nc->fresh()->status)->not->toBe('cloturee');
    expect($nc->capaComplete())->toBeFalse();
});
