<?php

/**
 * [BUG-A3-QUALITY-DELETE-006] Un contrôle qualité s'efface sans laisser de trace.
 *
 * `ProductionQualityController::destroy()` appelle `$qualityControl->delete()`
 * sur un modèle qui n'utilise pas SoftDeletes, sur une table sans `deleted_at`,
 * sans observer d'audit, sans motif, et sous la seule permission
 * `production.update` — pas une permission qualité.
 *
 * Ce n'est pas une suppression anodine, parce que TOUTES les gardes qualité
 * lisent le DERNIER contrôle enregistré :
 *
 *   QualityReleaseService:31       `qualityControls()->latest('id')->value('status') !== 'conforme'`
 *   ProductionService:764          `$lastQc = $order->qualityControls()->latest('id')->first()`
 *   ProductionDeliveryGuard:139    `$qc = $of->qualityControls->sortByDesc('id')->first()`
 *   SalesProductionService:24      idem
 *
 * D'où le scénario : poser un `non_conforme` qui bloque la libération et la
 * livraison, puis le supprimer. Le `conforme` antérieur redevient le dernier,
 * la marchandise repart, et rien n'indique qu'un refus a existé.
 *
 * `ProductionDeliveryGuard:248` additionne par ailleurs `rejected_quantity` sur
 * les contrôles : supprimer un contrôle portant un rejet AUGMENTE mécaniquement
 * la quantité livrable.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qcdSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QCD-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QCD Co'], ['email' => 'qcd@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WQCD'], ['name' => 'WQCD', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    return $co;
}

function qcdUtilisateur(array $permissions = ['production.view', 'production.update']): User
{
    $co = qcdSociete();
    $role = Role::firstOrCreate(['name' => 'chef_production', 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    // Spatie met en cache la table des permissions : sans purge, un droit
    // accorde dans le meme processus reste invisible et la requete part en 403.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/** OF avec contrôle qualité obligatoire, déjà déclaré conforme une première fois. */
function qcdOrdre(): ProductionOrder
{
    $co = qcdSociete();

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'product_id' => Product::factory()->create()->id,
        'number' => 'OF-QCD-'.uniqid(), 'status' => 'en_cours',
        'quantity_requested' => 10, 'quantity_produced' => 10,
        'controle_qualite_obligatoire' => true,
    ]);
}

function qcdControle(ProductionOrder $of, string $statut, float $rejete = 0): ProductionQualityControl
{
    return $of->qualityControls()->create([
        'company_id' => $of->company_id,
        'thickness_ok' => $statut === 'conforme', 'length_ok' => $statut === 'conforme',
        'color_ok' => $statut === 'conforme', 'visual_ok' => $statut === 'conforme',
        'status' => $statut, 'rejected_quantity' => $rejete,
        'controlled_at' => now(), 'created_by' => auth()->id(),
    ]);
}

it('conserve la trace d\'un contrôle qualité supprimé', function () {
    qcdUtilisateur();
    $of = qcdOrdre();
    $controle = qcdControle($of, 'non_conforme', rejete: 4);

    test()->delete(route('production.quality.destroy', $controle), [
        'reason' => 'Contrôle saisi sur le mauvais ordre de fabrication.',
    ])->assertRedirect();

    // La ligne doit SURVIVRE en base, marquée supprimée. Une décision qualité qui
    // disparaît sans laisser d'empreinte rend l'historique irréfutable — et faux.
    $ligne = DB::table('production_quality_controls')->where('id', $controle->id)->first();
    expect($ligne)->not->toBeNull();
    expect($ligne->deleted_at)->not->toBeNull();
    expect($ligne->deletion_reason)->toContain('mauvais ordre de fabrication');
    expect($ligne->deleted_by)->toBe(auth()->id());

    // Elle ne compte plus pour les gardes, ce qui est bien l'effet recherché.
    expect($of->fresh()->qualityControls()->count())->toBe(0);
});

it('exige un motif pour supprimer un contrôle qualité', function () {
    qcdUtilisateur();
    $of = qcdOrdre();
    $controle = qcdControle($of, 'non_conforme');

    test()->delete(route('production.quality.destroy', $controle))
        ->assertSessionHasErrors('reason');

    test()->delete(route('production.quality.destroy', $controle), ['reason' => 'erreur'])
        ->assertSessionHasErrors('reason');

    expect(ProductionQualityControl::whereKey($controle->id)->exists())->toBeTrue();
});

it('journalise la suppression d\'un contrôle qualité', function () {
    $u = qcdUtilisateur();
    $of = qcdOrdre();
    $controle = qcdControle($of, 'non_conforme', rejete: 4);

    test()->delete(route('production.quality.destroy', $controle), [
        'reason' => 'Doublon de saisie confirmé par le chef d\'atelier.',
    ]);

    $log = DB::table('audit_logs')
        ->where('action', 'production.qc.suppression')
        ->where('model_id', $controle->id)
        ->latest('id')->first();

    expect($log)->not->toBeNull();
    expect((int) $log->user_id)->toBe($u->id);

    $valeurs = json_decode($log->old_values ?? '{}', true);
    expect($valeurs['status'] ?? null)->toBe('non_conforme');
    expect((float) ($valeurs['rejected_quantity'] ?? 0))->toBe(4.0);
});

it('ne rend pas un refus qualité réversible en silence', function () {
    qcdUtilisateur();
    $of = qcdOrdre();

    // Chronologie réelle : la production passe, puis un défaut est constaté.
    qcdControle($of, 'conforme');
    $refus = qcdControle($of, 'non_conforme', rejete: 10);

    // Toutes les gardes lisent le DERNIER contrôle : la marchandise est bloquée.
    expect($of->fresh()->qualityControls()->latest('id')->value('status'))->toBe('non_conforme');

    test()->delete(route('production.quality.destroy', $refus), [
        'reason' => 'Suppression du refus pour débloquer la livraison.',
    ]);

    // Le « conforme » antérieur redevient le dernier — c'est inhérent à la règle
    // du dernier contrôle, et ce n'est pas ce que ce test conteste. Ce qui doit
    // être vrai, c'est qu'il RESTE une trace du refus : sinon l'historique
    // affirme qu'aucun défaut n'a jamais été constaté.
    expect($of->fresh()->qualityControls()->latest('id')->value('status'))->toBe('conforme');

    $trace = DB::table('production_quality_controls')->where('id', $refus->id)->first();
    expect($trace)->not->toBeNull();
    expect($trace->status)->toBe('non_conforme');
    expect($trace->deleted_at)->not->toBeNull();
});

it('déclare la table apte à porter une suppression tracée', function () {
    // Garde de schéma : sans ces colonnes, les cas ci-dessus n'ont aucun support
    // et la suppression redeviendrait physique au premier oubli.
    expect(Schema::hasColumn('production_quality_controls', 'deleted_at'))->toBeTrue();
    expect(Schema::hasColumn('production_quality_controls', 'deletion_reason'))->toBeTrue();
    expect(Schema::hasColumn('production_quality_controls', 'deleted_by'))->toBeTrue();
});
