<?php

/**
 * [PRO-08] Intégration : le contrôleur run() applique bien l'optimisation 2D
 * et persiste refente + valorisation des chutes.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\CuttingOptimization;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cutAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CUT'], ['email' => 'cut@x.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('exécute run() en mode 2D et persiste refente + chutes', function () {
    $this->actingAs(cutAdmin());

    $opt = CuttingOptimization::create([
        'company_id' => currentCompany()->id, 'code' => 'OPT-1', 'status' => 'brouillon',
        'standard_length' => 12, 'coil_width' => 1250, 'useful_width' => 600, 'cut_tolerance_mm' => 0,
        'valorize_offcuts' => true, 'min_reusable_offcut' => 6,
    ]);
    $opt->lines()->create(['requested_length_m' => 5, 'quantity' => 3]);

    $this->post(route('production.cutting.run', $opt))->assertRedirect();

    $opt->refresh();
    expect($opt->status)->toBe('optimisee');
    expect((int) $opt->strips_per_coil)->toBe(2);         // 1250 / 600
    expect((float) $opt->width_yield)->toBe(96.0);        // 1200 / 1250
    expect((int) $opt->coils_used)->toBe(1);              // 2 barres / 2 bandes
    expect((float) $opt->material_yield)->toBe(60.0);     // rendement combiné
    expect((float) $opt->reusable_offcut_m)->toBe(7.0);   // valorisation active
    expect($opt->plan['strips_per_coil'])->toBe(2);       // plan JSON enrichi
});

it('reste en 1D si les largeurs ne sont pas saisies', function () {
    $this->actingAs(cutAdmin());

    $opt = CuttingOptimization::create([
        'company_id' => currentCompany()->id, 'code' => 'OPT-2', 'status' => 'brouillon',
        'standard_length' => 12, 'cut_tolerance_mm' => 0,
    ]);
    $opt->lines()->create(['requested_length_m' => 5, 'quantity' => 3]);

    $this->post(route('production.cutting.run', $opt))->assertRedirect();

    $opt->refresh();
    expect((int) $opt->strips_per_coil)->toBe(0);
    expect((int) $opt->coils_used)->toBe(2);          // = nombre de barres 1D
    expect((float) $opt->material_yield)->toBe(62.5); // rendement longueur seul
});
