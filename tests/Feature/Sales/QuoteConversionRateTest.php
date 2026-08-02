<?php

/**
 * [Ventes — tableau de bord] Le taux de transformation compte des devis qui
 * n'ont jamais pu être convertis.
 *
 * `SalesProductionService::dashboardKpis()` calculait :
 *
 *     taux = devis convertis / TOUS les devis
 *
 * Le dénominateur retenait donc les brouillons, les devis en attente de
 * validation interne et les devis annulés. Un devis encore en brouillon n'a
 * jamais été soumis à un client : le compter comme une transformation manquée
 * fait baisser le taux mécaniquement, sans qu'aucune vente ait été perdue.
 *
 * Constaté sur la base de développement : 5 convertis sur 6 devis, le sixième
 * étant un brouillon — 83,3 % affichés là où tout ce qui a été proposé au
 * client a été converti.
 *
 * PÉRIMÈTRE RETENU, et sa limite : ne sont exclus que les états dont la
 * conversion est IMPOSSIBLE par construction — `brouillon`,
 * `en_attente_validation` et `annule`. Les états `envoye`, `valide`, `accepte`,
 * `refuse`, `expire` et `converti` restent au dénominateur : ils correspondent
 * tous à une offre qui a existé et dont l'issue est mesurable. Départager plus
 * finement — un devis `valide` mais jamais envoyé compte-t-il ? — relève d'une
 * décision commerciale, pas d'une correction technique.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Quote;
use App\Models\User;
use App\Modules\Production\Services\SalesProductionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qcrSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QCR-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QCR Co'], ['email' => 'qcr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function qcrDevis(string $statut): Quote
{
    $co = qcrSociete();

    return Quote::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => \App\Models\Client::factory()->create(['is_active' => true])->id,
        'number' => 'DEV-QCR-'.uniqid(), 'status' => $statut,
        'issued_at' => now()->toDateString(), 'expires_at' => now()->addDays(30)->toDateString(),
        'currency_code' => 'XOF', 'exchange_rate' => 1,
        'subtotal_ht' => 100000, 'total_tax' => 18000, 'total_ttc' => 118000,
    ]);
}

function qcrTaux(): float
{
    return (float) app(SalesProductionService::class)->dashboardKpis()['taux_transfo'];
}

it('ignore un brouillon : il n\'a jamais été proposé au client', function () {
    qcrSociete();
    qcrDevis('converti');
    qcrDevis('brouillon');

    // Ancien calcul : 1 / 2 = 50 %. Or une seule offre est sortie, et elle a
    // été convertie.
    expect(qcrTaux())->toBe(100.0);
});

it('ignore un devis en attente de validation interne', function () {
    qcrSociete();
    qcrDevis('converti');
    qcrDevis('en_attente_validation');

    expect(qcrTaux())->toBe(100.0);
});

it('ignore un devis annulé : retiré, pas perdu', function () {
    qcrSociete();
    qcrDevis('converti');
    qcrDevis('annule');

    expect(qcrTaux())->toBe(100.0);
});

it('compte bien les offres réellement perdues', function () {
    qcrSociete();
    qcrDevis('converti');
    qcrDevis('refuse');
    qcrDevis('expire');
    qcrDevis('envoye');

    // 1 converti sur 4 offres sorties : le taux DOIT chuter. La correction ne
    // consiste pas à embellir l'indicateur, mais à lui donner le bon périmètre.
    expect(qcrTaux())->toBe(25.0);
});

it('reproduit le cas réel : 5 convertis et 1 brouillon', function () {
    qcrSociete();
    foreach (range(1, 5) as $i) {
        qcrDevis('converti');
    }
    qcrDevis('brouillon');

    // Affiché avant correction : 83,3 %.
    expect(qcrTaux())->toBe(100.0);
});

it('rend 0 plutôt qu\'une division par zéro quand aucune offre n\'est sortie', function () {
    qcrSociete();
    qcrDevis('brouillon');

    expect(qcrTaux())->toBe(0.0);
});
