<?php

/**
 * [Production] Ordonnancement — placement des OF sur une ligne, à un créneau.
 *
 * Distinct du plan de charge (WorkloadPlanningTest), qui mesure une capacité
 * agrégée. Ici on vérifie ce qu'un ordonnancement doit garantir : une ligne ne
 * produit qu'un ordre à la fois, un ordre clôturé ne se replace plus, une ligne
 * arrêtée ne reçoit rien, et dépositionner ne détruit pas l'avancement.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionSchedulingService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function ordoAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'ORDO'], ['email' => 'ordo@ordo.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WO'], ['name' => 'WO', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function ordoLigne(array $attrs = []): ProductionLine
{
    return ProductionLine::factory()->create(array_merge([
        'company_id' => Company::first()->id,
        'is_active'  => true,
        'status'     => 'active',
    ], $attrs));
}

function ordoOf(array $attrs = []): ProductionOrder
{
    $co = Company::first();

    return ProductionOrder::create(array_merge([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-ORDO-' . uniqid(), 'status' => 'lance',
        'quantity_requested' => 10, 'quantity_produced' => 0,
        'product_id' => Product::factory()->create(['is_manufacturable' => true])->id,
        'launched_at' => now(),
    ], $attrs));
}

function ordo(): ProductionSchedulingService
{
    return app(ProductionSchedulingService::class);
}

it('place un OF sur une ligne à une date et une heure', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $of    = ordoOf();

    ordo()->schedule($of, [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01',
        'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01',
        'heure_fin_prevue'   => '12:00',
    ]);

    $of->refresh();
    expect($of->production_line_id)->toBe($ligne->id);
    expect($of->date_debut_prevue->format('Y-m-d'))->toBe('2026-09-01');
    expect($of->heure_debut_prevue)->toStartWith('08:00');
    expect($of->heure_fin_prevue)->toStartWith('12:00');
    // Pont vers la colonne héritée : le plan de charge doit voir le même OF.
    expect($of->date_fabrication_prevue->format('Y-m-d'))->toBe('2026-09-01');
});

it('refuse deux OF qui se chevauchent sur la même ligne', function () {
    ordoAdmin();
    $ligne = ordoLigne();

    ordo()->schedule(ordoOf(), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ]);

    $second = ordoOf();

    // 10 h → 14 h mord sur le créneau 8 h → 12 h.
    expect(fn () => ordo()->schedule($second, [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '10:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '14:00',
    ]))->toThrow(ValidationException::class);

    expect($second->fresh()->date_debut_prevue)->toBeNull();
});

it('accepte deux OF adjacents sur la même ligne', function () {
    ordoAdmin();
    $ligne = ordoLigne();

    ordo()->schedule(ordoOf(), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ]);

    $second = ordo()->schedule(ordoOf(), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '12:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '16:00',
    ]);

    expect($second->heure_debut_prevue)->toStartWith('12:00');
});

it('autorise le même créneau sur deux lignes différentes', function () {
    ordoAdmin();
    $a = ordoLigne();
    $b = ordoLigne();

    $creneau = [
        'date_debut_prevue' => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'   => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ];

    ordo()->schedule(ordoOf(), $creneau + ['production_line_id' => $a->id]);
    $second = ordo()->schedule(ordoOf(), $creneau + ['production_line_id' => $b->id]);

    expect($second->production_line_id)->toBe($b->id);
});

it('refuse d\'ordonnancer un OF terminé ou annulé', function (string $statut) {
    ordoAdmin();
    $ligne = ordoLigne();

    expect(fn () => ordo()->schedule(ordoOf(['status' => $statut]), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01',
    ]))->toThrow(ValidationException::class);
})->with(['termine', 'annule']);

it('refuse une ligne indisponible', function (string $statut) {
    ordoAdmin();

    expect(fn () => ordo()->schedule(ordoOf(), [
        'production_line_id' => ordoLigne(['status' => $statut])->id,
        'date_debut_prevue'  => '2026-09-01',
    ]))->toThrow(ValidationException::class);
})->with(['indisponible', 'arretee', 'en_panne']);

it('refuse une fin antérieure au début', function () {
    ordoAdmin();

    expect(fn () => ordo()->schedule(ordoOf(), [
        'production_line_id' => ordoLigne()->id,
        'date_debut_prevue'  => '2026-09-10',
        'date_fin_prevue'    => '2026-09-01',
    ]))->toThrow(ValidationException::class);
});

it('détecte le chevauchement même sans date de fin déclarée', function () {
    ordoAdmin();
    $ligne = ordoLigne();

    // Sans fin, l'OF occupe sa journée entière — sinon il traverserait la
    // détection sans jamais entrer en conflit avec personne.
    ordo()->schedule(ordoOf(), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
    ]);

    expect(fn () => ordo()->schedule(ordoOf(), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '15:00',
    ]))->toThrow(ValidationException::class);
});

it('fait apparaître au planning un OF ne portant que la date de fabrication héritée', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $of    = ordoOf([
        'production_line_id'      => $ligne->id,
        'date_fabrication_prevue' => '2026-09-02',
        'date_debut_prevue'       => null,
    ]);

    $board = ordo()->board('2026-09-01', '2026-09-30');

    expect($board['places']->pluck('id'))->toContain($of->id);
    expect($board['a_placer']->pluck('id'))->not->toContain($of->id);
});

it('classe à placer un OF sans ligne et un OF sans date', function () {
    ordoAdmin();
    $sansLigne = ordoOf(['date_debut_prevue' => '2026-09-03']);
    $sansDate  = ordoOf(['production_line_id' => ordoLigne()->id]);

    $board = ordo()->board('2026-09-01', '2026-09-30');

    expect($board['a_placer']->pluck('id'))
        ->toContain($sansLigne->id)
        ->toContain($sansDate->id);
});

it('exclut du planning les OF terminés et annulés', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $clos  = ordoOf(['status' => 'termine', 'production_line_id' => $ligne->id, 'date_debut_prevue' => '2026-09-04']);

    $board = ordo()->board('2026-09-01', '2026-09-30');

    expect($board['places']->pluck('id'))->not->toContain($clos->id);
    expect($board['a_placer']->pluck('id'))->not->toContain($clos->id);
});

it('dépositionne un OF sans toucher à son avancement', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $of    = ordoOf(['status' => 'en_cours', 'quantity_produced' => 4]);

    ordo()->schedule($of, [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ]);

    ordo()->unschedule($of);
    $of->refresh();

    expect($of->date_debut_prevue)->toBeNull();
    expect($of->heure_debut_prevue)->toBeNull();
    expect($of->date_fin_prevue)->toBeNull();
    expect((float) $of->quantity_produced)->toBe(4.0);
    expect($of->status)->toBe('en_cours');
    // OF démarré : il EST physiquement sur cette ligne, on ne le nie pas.
    expect($of->production_line_id)->toBe($ligne->id);
});

it('libère la ligne quand on dépositionne un OF non démarré', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $of    = ordoOf(['status' => 'brouillon', 'launched_at' => null, 'date_fabrication_prevue' => '2026-09-01']);

    ordo()->schedule($of, [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
    ]);
    ordo()->unschedule($of);

    // Sans libération de la ligne, le repli sur date_fabrication_prevue le
    // laisserait affiché comme placé : le bouton « Retirer » semblerait inerte.
    expect($of->fresh()->production_line_id)->toBeNull();
    expect(ordo()->board('2026-09-01', '2026-09-30')['places']->pluck('id'))->not->toContain($of->id);
});

it('libère le créneau pour un autre OF après dépositionnement', function () {
    ordoAdmin();
    $ligne   = ordoLigne();
    $creneau = [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ];

    $premier = ordoOf(['status' => 'brouillon', 'launched_at' => null]);
    ordo()->schedule($premier, $creneau);
    ordo()->unschedule($premier);

    $second = ordo()->schedule(ordoOf(), $creneau);

    expect($second->heure_debut_prevue)->toStartWith('08:00');
});

it('ne salit pas les OF renvoyés par le tableau', function () {
    ordoAdmin();
    $ligne = ordoLigne();
    $of = ordoOf(['date_fabrication_prevue' => '2026-09-05', 'production_line_id' => $ligne->id]);

    $board = ordo()->board('2026-09-01', '2026-09-30');
    $lu = $board['places']->firstWhere('id', $of->id) ?? $board['a_placer']->firstWhere('id', $of->id);

    expect($lu)->not->toBeNull();
    // Les dates effectives étaient POSÉES sur le modèle : Eloquent les rangeait
    // dans le sac d'attributs, marquant l'OF modifié sur deux colonnes qui
    // n'existent pas. Tout save() ultérieur échouait — une mine pour la
    // première action groupée qu'on ajouterait au tableau.
    expect($lu->debut_effectif)->not->toBeNull();
    expect($lu->isDirty())->toBeFalse();

    $lu->save(); // ne doit rien lever
    expect($lu->fresh())->not->toBeNull();
});

it('affiche l\'écran d\'ordonnancement', function () {
    $this->actingAs(ordoAdmin());
    $of = ordoOf();

    $this->get(route('production.schedule'))
        ->assertOk()
        ->assertSee('Ordonnancement')
        ->assertSee($of->number);
});

it('place un OF depuis l\'écran et refuse le chevauchement en HTTP', function () {
    $this->actingAs(ordoAdmin());
    $ligne = ordoLigne();

    $this->post(route('production.schedule.assign', ordoOf()), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '08:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '12:00',
    ])->assertRedirect()->assertSessionHas('success');

    $this->post(route('production.schedule.assign', ordoOf()), [
        'production_line_id' => $ligne->id,
        'date_debut_prevue'  => '2026-09-01', 'heure_debut_prevue' => '09:00',
        'date_fin_prevue'    => '2026-09-01', 'heure_fin_prevue'   => '11:00',
    ])->assertSessionHasErrors('date_debut_prevue');
});

it('interdit l\'ordonnancement sans la permission production.update', function () {
    $co = Company::firstOrCreate(['name' => 'ORDO'], ['email' => 'ordo@ordo.io']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'lecteur_prod', 'guard_name' => 'web']));

    $this->actingAs($u)->get(route('production.schedule'))->assertForbidden();
});
