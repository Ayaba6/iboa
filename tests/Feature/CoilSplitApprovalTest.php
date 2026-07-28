<?php

/**
 * [Division #1/#3/#4] Acteur d'exécution EXPLICITE + proposition PERSISTÉE avec
 * machine d'états, seuils figés et invalidation après modification.
 *
 * Règle centrale prouvée ici : « absence d'utilisateur ≠ autorisation ».
 */

use App\Models\CoilSplitProposal;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\CoilSplitProposalService;
use App\Services\ExecutionContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function splitSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SPL'], ['email' => 'spl@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $wh  = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-SPL'], ['name' => 'Dépôt', 'is_default' => true, 'is_active' => true]);
    $sup = Supplier::factory()->create();
    $p   = Product::factory()->create(['is_stockable' => true]);
    $rec = Reception::create([
        'company_id' => $co->id, 'supplier_id' => $sup->id, 'number' => 'R-SPL-' . uniqid(),
        'status' => 'valide', 'received_at' => now(),
    ]);
    $mother = Coil::create([
        'company_id' => $co->id, 'product_id' => $p->id, 'reception_id' => $rec->id,
        'reference' => 'BOB-SP-' . uniqid(), 'initial_weight' => 200, 'remaining_weight' => 200,
        'status' => 'disponible', 'quality_status' => Coil::QUALITY_QUARANTINED,
        'qty_released' => 0, 'qty_quarantine' => 200, 'qty_rejected' => 0,
        'warehouse_id' => $wh->id, 'cost_per_kg' => 500, 'purchase_price' => 100000, 'received_at' => now(),
    ]);

    return [$co, $mother];
}

function userWith(array $perms, int $companyId): User
{
    $u = User::factory()->create(['company_id' => $companyId, 'email_verified_at' => now()]);
    if ($perms !== []) {
        $role = Role::firstOrCreate(['name' => 'r_' . md5(implode(',', $perms)), 'guard_name' => 'web']);
        foreach ($perms as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $u->assignRole($role);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $u;
}

afterEach(fn () => ExecutionContext::clear());

// ── [#1] Acteur d'exécution explicite ───────────────────────────────────────

it('AUCUN ACTEUR : exécution REFUSÉE (absence d\'utilisateur n\'autorise rien)', function () {
    [$co, $mother] = splitSetup();
    auth()->logout();

    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 200]], 0.0))
        ->toThrow(\RuntimeException::class, 'Aucun acteur');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

it('ACTEUR SYSTÈME AUTORISÉ : exécution acceptée sous contexte explicite', function () {
    [$co, $mother] = splitSetup();
    auth()->logout();

    // Acteur système PLEINEMENT habilité : exécution + dérogation technique
    // (l'exécution hors proposition reste une procédure exceptionnelle).
    $children = ExecutionContext::asSystem(
        'import-bobines', ['coils.split.execute', 'coils.split.technical_override'],
        origin: 'artisan:coils:import', reason: 'Import quotidien',
        callback: fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
            ->split($mother, [['weight' => 200]], 0.0, 'Reprise technique')
    );

    expect($children)->toHaveCount(1)
        ->and((float) $children[0]->initial_weight)->toBe(200.0);
});

it('ACTEUR SYSTÈME SANS PERMISSION : exécution refusée', function () {
    [$co, $mother] = splitSetup();
    auth()->logout();

    expect(fn () => ExecutionContext::asSystem(
        'job-sans-droit', ['autre.permission'],
        origin: 'queue:job', reason: 'Test',
        callback: fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
            ->split($mother, [['weight' => 200]], 0.0, 'Reprise technique')
    ))->toThrow(\RuntimeException::class, 'non accordée');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

it('le contexte système est bien restauré après exécution (pas de fuite)', function () {
    [$co, $mother] = splitSetup();
    auth()->logout();
    expect(ExecutionContext::systemActor())->toBeNull();

    ExecutionContext::asSystem('x', ['coils.split.execute'], 'o', 'r', fn () => null);

    expect(ExecutionContext::systemActor())->toBeNull(); // restauré
});

// ── [#2] Exécution directe interdite hors proposition approuvée ─────────────

it('EXÉCUTION DIRECTE sans proposition ni motif technique : REFUSÉE', function () {
    [$co, $mother] = splitSetup();
    // Utilisateur habilité à exécuter, mais pas de proposition ni de motif.
    test()->actingAs(userWith(['coils.split.execute'], $co->id));

    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 200]], 0.0))
        ->toThrow(\RuntimeException::class, 'proposition approuvée est requise');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

it('EXCEPTION TECHNIQUE : refusée sans coils.split.technical_override', function () {
    [$co, $mother] = splitSetup();
    test()->actingAs(userWith(['coils.split.execute'], $co->id));

    expect(fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 200]], 0.0, 'Reprise incident atelier'))
        ->toThrow(\RuntimeException::class, 'coils.split.technical_override');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

it('EXCEPTION TECHNIQUE : autorisée avec permission + motif, et JOURNALISÉE', function () {
    [$co, $mother] = splitSetup();
    test()->actingAs(userWith(['coils.split.execute', 'coils.split.technical_override'], $co->id));

    $children = app(\App\Modules\Production\Services\CoilSplitService::class)
        ->split($mother, [['weight' => 200]], 0.0, 'Reprise incident atelier');

    expect($children)->toHaveCount(1)
        ->and(\App\Models\AuditLog::where('action', 'bobine.division.derogation_technique')->exists())->toBeTrue();
});

it('ACTEUR SYSTÈME : ne contourne PAS la proposition sans droit de dérogation', function () {
    [$co, $mother] = splitSetup();
    auth()->logout();

    // Acteur système autorisé à exécuter, mais SANS technical_override.
    expect(fn () => ExecutionContext::asSystem(
        'job-division', ['coils.split.execute'],
        origin: 'queue:job', reason: 'Traitement automatique',
        callback: fn () => app(\App\Modules\Production\Services\CoilSplitService::class)
            ->split($mother, [['weight' => 200]], 0.0, 'Automatique')
    ))->toThrow(\RuntimeException::class, 'coils.split.technical_override');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

// ── [#3] Proposition persistée + machine d'états ────────────────────────────

it('PROPOSITION : soumission refusée sans coils.split.propose', function () {
    [$co, $mother] = splitSetup();
    test()->actingAs(userWith([], $co->id));

    expect(fn () => app(CoilSplitProposalService::class)->submit($mother, [['weight' => 200]]))
        ->toThrow(\RuntimeException::class, 'coils.split.propose');
});

it('PROPOSITION : soumise → approuvée (approbateur distinct) → exécutée', function () {
    config(['security.maker_checker.enabled' => true]);
    [$co, $mother] = splitSetup();
    $svc = app(CoilSplitProposalService::class);

    // Perte 100 kg (50 000 F) : au-dessus du seuil → approbation distincte requise.
    // Le proposeur détient AUSSI approve_loss : c'est donc le maker-checker
    // (et non l'absence de permission) qui doit bloquer son auto-approbation.
    $proposeur   = userWith(['coils.split.propose', 'coils.split.approve_loss'], $co->id);
    $approbateur = userWith(['coils.split.approve_loss', 'coils.split.execute'], $co->id);

    test()->actingAs($proposeur);
    $prop = $svc->submit($mother, [['weight' => 100]], ['loss' => 100]);
    expect($prop->status)->toBe(CoilSplitProposal::STATUS_SUBMITTED)
        ->and($prop->requires_loss_approval)->toBeTrue()
        // [#2] Seuils FIGÉS sur la proposition.
        ->and((int) $prop->threshold_loss_value)->toBe(50000)
        ->and((float) $prop->threshold_loss_qty)->toBe(50.0)
        ->and((int) $prop->loss_value)->toBe(50000)
        ->and((int) $prop->residual_cost)->toBe(100000);

    // Auto-approbation par le proposeur → refusée.
    expect(fn () => $svc->approve($prop->fresh()))
        ->toThrow(\RuntimeException::class, 'Séparation des tâches');

    // Approbateur distinct → OK, puis exécution.
    test()->actingAs($approbateur);
    $approved = $svc->approve($prop->fresh());
    expect($approved->status)->toBe(CoilSplitProposal::STATUS_APPROVED)
        ->and((int) $approved->approved_by)->toBe($approbateur->id);

    $children = $svc->execute($approved, [['weight' => 100]], ['loss' => 100]);
    expect($children)->toHaveCount(1)
        ->and($prop->fresh()->status)->toBe(CoilSplitProposal::STATUS_EXECUTED);
});

it('APPROBATION : refusée sans coils.split.approve_loss (défense en profondeur)', function () {
    config(['security.maker_checker.enabled' => true]);
    [$co, $mother] = splitSetup();
    $svc = app(CoilSplitProposalService::class);

    $proposeur = userWith(['coils.split.propose'], $co->id);   // sans approve_loss
    test()->actingAs($proposeur);
    $prop = $svc->submit($mother, [['weight' => 100]], ['loss' => 100]);

    // Un tiers sans la permission ne peut pas approuver non plus.
    test()->actingAs(userWith(['coils.split.execute'], $co->id));
    expect(fn () => $svc->approve($prop->fresh()))
        ->toThrow(\RuntimeException::class, 'coils.split.approve_loss');

    expect($prop->fresh()->status)->toBe(CoilSplitProposal::STATUS_SUBMITTED);
});

it('EXÉCUTION SANS APPROBATION : refusée', function () {
    [$co, $mother] = splitSetup();
    $u = userWith(['coils.split.propose', 'coils.split.execute'], $co->id);
    test()->actingAs($u);
    $svc = app(CoilSplitProposalService::class);

    $prop = $svc->submit($mother, [['weight' => 200]]);   // reste « soumise »
    expect(fn () => $svc->execute($prop, [['weight' => 200]]))
        ->toThrow(\RuntimeException::class, 'sans approbation');

    expect(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);
});

// ── [#4] Invalidation après modification ────────────────────────────────────

it('INVALIDATION : payload modifié après approbation → exécution refusée, nouvelle soumission requise', function () {
    [$co, $mother] = splitSetup();
    $u = userWith(['coils.split.propose', 'coils.split.execute'], $co->id);
    test()->actingAs($u);
    $svc = app(CoilSplitProposalService::class);

    // Sans perte → pas d'approbation distincte requise.
    $prop = $svc->submit($mother, [['weight' => 120], ['weight' => 80]]);
    $prop = $svc->approve($prop);
    expect($prop->status)->toBe(CoilSplitProposal::STATUS_APPROVED);

    // Exécution avec une répartition DIFFÉRENTE de celle approuvée.
    expect(fn () => $svc->execute($prop, [['weight' => 150], ['weight' => 50]]))
        ->toThrow(\RuntimeException::class, 'INVALIDÉE');

    // Proposition invalidée, aucune fille créée.
    expect($prop->fresh()->status)->toBe(CoilSplitProposal::STATUS_INVALIDATED)
        ->and(Coil::where('parent_coil_id', $mother->id)->count())->toBe(0);

    // Une proposition invalidée ne peut plus être exécutée, même au bon payload.
    expect(fn () => $svc->execute($prop->fresh(), [['weight' => 120], ['weight' => 80]]))
        ->toThrow(\RuntimeException::class, 'sans approbation');
});

it('exécution du payload EXACT approuvé : acceptée', function () {
    [$co, $mother] = splitSetup();
    $u = userWith(['coils.split.propose', 'coils.split.execute'], $co->id);
    test()->actingAs($u);
    $svc = app(CoilSplitProposalService::class);

    $prop = $svc->approve($svc->submit($mother, [['weight' => 120], ['weight' => 80]]));
    $children = $svc->execute($prop, [['weight' => 120], ['weight' => 80]]);

    expect($children)->toHaveCount(2)
        ->and((float) $children[0]->initial_weight)->toBe(120.0)
        ->and((float) $children[1]->initial_weight)->toBe(80.0)
        ->and($prop->fresh()->status)->toBe(CoilSplitProposal::STATUS_EXECUTED);
});
