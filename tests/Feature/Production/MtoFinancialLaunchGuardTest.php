<?php

/**
 * [BUG-A3-MTO-FIN-001] Garde financière au lancement d'un OF MTO — 26 cas.
 *
 * Règle unique : le lancement serveur est refusé tant que l'exigence financière
 * de la commande n'est pas remplie, et le verdict provient d'UNE seule source,
 * {@see \App\Services\Production\ProductionFinancialEligibilityService}. La
 * garde est FAIL-CLOSED : un mode de règlement non reconnu ne vaut pas
 * autorisation, il vaut refus.
 *
 * Deux invariants structurent cette suite :
 *
 *   1. ÉLIGIBILITÉ CALCULÉE ≠ DÉROGATION MANUELLE. Une couverture acquise par
 *      encaissement n'écrit RIEN. `financial_authorization` ne consigne qu'une
 *      décision humaine, et une décision sans auteur n'en est pas une.
 *   2. Un REFUS n'écrit rien du tout — ni colonne, ni journal d'audit.
 *
 * La garde ne consulte AUCUNE permission : elle porte sur des faits comptables,
 * pas sur des droits. Un `super_admin` est donc bloqué comme les autres, et les
 * cas par profil le vérifient plutôt que de le supposer.
 *
 * Moteur de référence : MySQL (`pest -c phpunit.mysql.xml`).
 */

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use App\Services\Production\ProductionFinancialRequirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

const GUARD_TTC = 236000;

function guardSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'GUARD'], ['email' => 'guard@guard.io', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WGD'], ['name' => 'WGD', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    return $co;
}

/** Crée un utilisateur du rôle demandé et l'authentifie. */
function guardUtilisateur(string $role, array $permissions = []): User
{
    $co = guardSociete();
    $r = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $r->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    test()->actingAs($u);

    return $u;
}

/**
 * Commande confirmée + OF brouillon, sans aucun encaissement.
 *
 * `$modeReglement` est écrit tel quel : les cas de configuration invalide ont
 * besoin d'injecter des valeurs que les Form Requests refusent, précisément
 * pour vérifier que la garde ne s'y laisse pas prendre.
 */
function guardScenario(string $modeReglement = Client::PAYMENT_CASH, int $plafond = 0): array
{
    $co = guardSociete();

    $client = Client::factory()->create([
        'payment_mode' => $modeReglement, 'credit_limit' => $plafond, 'is_active' => true,
    ]);

    $produit = Product::factory()->create([
        'is_active' => true, 'is_sellable' => true,
        'is_manufacturable' => false, // écarte la garde nomenclature : on éprouve la garde FINANCIÈRE
        'production_mode' => 'mto', 'sale_price' => GUARD_TTC,
    ]);

    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id, 'number' => 'CMD-GD-'.uniqid(),
        'status' => 'confirme', 'issued_at' => now(),
        'total_ttc' => GUARD_TTC, 'invoiced_amount' => 0, 'production_approved' => false,
    ]);

    OrderItem::create([
        'order_id' => $commande->id, 'product_id' => $produit->id,
        'description' => $produit->name, 'quantity' => 50, 'unit_price' => 4720,
        'line_total_ht' => GUARD_TTC, 'line_tax' => 0, 'line_total_ttc' => GUARD_TTC,
    ]);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'order_id' => $commande->id, 'client_id' => $client->id, 'product_id' => $produit->id,
        'number' => 'OF-GD-'.uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 50, 'quantity_produced' => 0, 'origin' => 'commande_client',
    ]);

    return ['of' => $of, 'commande' => $commande, 'client' => $client, 'company' => $co];
}

/** Acompte client libre — une des sources retenues par confirmedReceipts(). */
function guardAcompte(Company $co, Client $client, int $montant, string $statut = 'confirme'): int
{
    return (int) DB::table('client_payments')->insertGetId([
        'company_id' => $co->id, 'client_id' => $client->id,
        'number' => 'PAY-GD-'.uniqid(), 'amount' => $montant,
        'status' => $statut, 'is_acompte' => 1,
        'allocated_amount' => 0, 'unallocated_amount' => $montant,
        'payment_date' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Facture ouverte échue — impayé bloquant pour un client à crédit. */
function guardFactureEchue(Company $co, Client $client, int $reste): void
{
    DB::table('invoices')->insert([
        'company_id' => $co->id, 'client_id' => $client->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'FAC-GD-'.uniqid(), 'status' => 'en_retard',
        'issued_at' => now()->subDays(60)->toDateString(),
        'due_at' => now()->subDays(30)->toDateString(),
        'total_ttc' => $reste, 'paid_amount' => 0, 'remaining_amount' => $reste,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function guardLancer(ProductionOrder $of): void
{
    app(ProductionService::class)->launch($of);
}

/** Photographie des colonnes financières — sert à prouver qu'un refus n'écrit rien. */
function guardEmpreinteFinanciere(ProductionOrder $of): array
{
    $l = DB::table('production_orders')->where('id', $of->getKey())->first();

    return [
        'status' => $l->status,
        'financial_authorization' => $l->financial_authorization,
        'financial_authorized_at' => $l->financial_authorized_at,
        'financial_authorized_by' => $l->financial_authorized_by,
        'financial_notes' => $l->financial_notes,
        'financial_authorization_expires_at' => $l->financial_authorization_expires_at,
        'financial_authorization_unpaid' => $l->financial_authorization_unpaid,
        'payment_mode' => $l->payment_mode,
        'payment_rate' => $l->payment_rate,
        'launched_at' => $l->launched_at,
    ];
}

// ═════════════════════════════════════════════════════════════════════════════
// 1 à 5 · COMPTANT
// ═════════════════════════════════════════════════════════════════════════════

it('1. refuse le lancement d\'un comptant sans aucun paiement, quel que soit le profil', function (string $role) {
    guardUtilisateur($role, ['production.validate', 'production.approve_financial']);
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
})->with([
    'operateur_production',
    'chef_production',
    'daf',
    'super_admin', // ne bénéficie d'AUCUN contournement : la garde ignore les droits
]);

it('2. refuse le lancement d\'un comptant partiellement payé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC - 1); // un franc manquant reste un manque

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('3. autorise le lancement d\'un comptant intégralement payé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC);

    guardLancer($of);

    expect($of->fresh()->status)->toBe('lance');
});

it('4. ne compte pas un paiement non confirmé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC, 'en_attente');

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('5. retire l\'éligibilité quand le paiement est annulé avant le lancement', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'commande' => $commande, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    $paiementId = guardAcompte($co, $client, GUARD_TTC);

    // L'éligibilité est acquise à cet instant...
    expect($commande->fresh()->isFinanciallyEligibleForProduction())->toBeTrue();

    // ...puis le règlement est annulé. Une éligibilité affichée n'est pas un
    // laissez-passer : la garde relit l'état au moment du lancement, sous verrou.
    DB::table('client_payments')->where('id', $paiementId)->update(['status' => 'annule']);

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

// ═════════════════════════════════════════════════════════════════════════════
// 6 à 8 · CONFIGURATION INCONNUE, NULLE OU INCOMPLÈTE — fail-closed
// ═════════════════════════════════════════════════════════════════════════════

it('6. refuse un mode de règlement inconnu, même intégralement payé', function () {
    guardUtilisateur('chef_production');
    // Valeur qu'aucun formulaire n'accepte : elle simule un mode ajouté plus tard
    // sans mise à jour de la garde. L'ancienne version l'aurait laissée passer,
    // faute de branche de refus correspondante.
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario('mobile_money');
    guardAcompte($co, $client, GUARD_TTC);

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('7. refuse un mode de règlement vide', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario('');
    guardAcompte($co, $client, GUARD_TTC);

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('8. refuse une commande dont le client n\'est plus résoluble', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC);

    // `orders.client_id` est NOT NULL : la commande orpheline n'existe pas.
    // L'état réellement atteignable est la fiche client supprimée après coup —
    // la relation, soumise au SoftDeletes, retourne alors null et le mode de
    // règlement devient indéterminable. Payée ou non, la commande ne part pas.
    $client->delete();

    expect($of->fresh()->order->client)->toBeNull();
    expect(fn () => guardLancer($of->fresh()))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

// ═════════════════════════════════════════════════════════════════════════════
// 9 à 12 · ACOMPTE — le mode n'existe pas, et cette suite le prouve
//
// Ces quatre cas ne testent pas un mode « acompte » : ils établissent qu'il n'y
// en a pas. Les tests précédents de l'application créaient des clients
// `payment_mode => 'acompte'` et concluaient au bon fonctionnement du seuil ;
// aucune fiche client ne pouvait porter cette valeur. C'est cette couverture
// fictive qui a laissé BUG-A3-MTO-FIN-001 survivre. Voir BUG-A3-SALES-DEPOSIT-004.
// ═════════════════════════════════════════════════════════════════════════════

it('9. n\'expose que deux modes de règlement, et le formulaire ne valide qu\'eux', function () {
    expect(Client::PAYMENT_MODES)->toBe(['cash', 'credit']);

    // La contrainte de saisie est la preuve utile : un mode absent des règles de
    // validation ne peut entrer dans la base par le chemin applicatif.
    $regles = (new \App\Http\Requests\Client\StoreClientRequest)->rules()['payment_mode'];
    expect($regles)->toContain('in:cash,credit');
});

it('10. refuse un « acompte » forcé en base au lieu de lui appliquer un seuil', function () {
    guardUtilisateur('chef_production');
    SalesSetting::current()->update(['deposit_required_rate' => 50]);
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario('acompte');
    guardAcompte($co, $client, (int) (GUARD_TTC * 0.5)); // 50 % : « suffirait » si le mode existait

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('11. ne dispose d\'aucune source structurée reliant un client à un taux d\'acompte', function () {
    // Le support existe — `payment_terms.deposit_required` / `deposit_rate` —
    // mais rien ne le rattache à un client : `clients.payment_terms` est un
    // varchar libre, il n'y a pas de clé étrangère.
    expect(\Illuminate\Support\Facades\Schema::hasColumn('payment_terms', 'deposit_rate'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('clients', 'payment_term_id'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('clients', 'payment_terms_id'))->toBeFalse();
});

it('12. ne fait plus dépendre aucune décision de production du taux global d\'acompte', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'commande' => $commande, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, (int) (GUARD_TTC * 0.7));

    // Le taux global pilotait auparavant l'exigence. Le faire varier ne doit plus
    // rien changer : un comptant doit 100 %, quel que soit ce paramètre.
    foreach ([10, 50, 70, 100] as $taux) {
        SalesSetting::current()->update(['deposit_required_rate' => $taux]);
        expect($commande->fresh()->productionFinancialRequirement()->requiredAmount)->toBe(GUARD_TTC);
    }

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
});

// ═════════════════════════════════════════════════════════════════════════════
// 13 à 16 · CRÉDIT
// ═════════════════════════════════════════════════════════════════════════════

it('13. refuse un client à crédit sans plafond accordé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of] = guardScenario(Client::PAYMENT_CREDIT, plafond: 0);

    // Plafond nul ≠ plafond illimité. `CustomerCreditExposureService` pose
    // `limited = false` dans ce cas pour l'affichage commercial ; transposé à une
    // garde de production, cela ouvrirait la production à tout client non paramétré.
    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('14. autorise un client à crédit dont l\'encours prévisionnel reste sous le plafond', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'commande' => $commande] = guardScenario(Client::PAYMENT_CREDIT, plafond: 5_000_000);

    $exigence = $commande->fresh()->productionFinancialRequirement($of);
    expect($exigence->type)->toBe(ProductionFinancialRequirement::TYPE_CREDIT);
    expect($exigence->satisfied)->toBeTrue();

    guardLancer($of);
    expect($of->fresh()->status)->toBe('lance');
});

it('15. refuse un client à crédit dont cette commande ferait dépasser le plafond', function () {
    guardUtilisateur('chef_production');
    // Plafond inférieur au TTC de la commande : l'encours prévisionnel le dépasse
    // du seul fait de cette commande.
    ['of' => $of] = guardScenario(Client::PAYMENT_CREDIT, plafond: GUARD_TTC - 1);

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('16. refuse un client à crédit portant une facture échue impayée', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CREDIT, plafond: 50_000_000);
    guardFactureEchue($co, $client, 1_000);

    // Le plafond est largement suffisant : c'est l'impayé échu qui bloque.
    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

// ═════════════════════════════════════════════════════════════════════════════
// 17 à 21 · DÉROGATION
// ═════════════════════════════════════════════════════════════════════════════

it('17. autorise le lancement sur dérogation DAF complète', function () {
    $daf = guardUtilisateur('daf', ['production.approve_financial']);
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    $of->update([
        'financial_authorization' => 'approved',
        'financial_authorized_at' => now(),
        'financial_authorized_by' => $daf->id,
        'financial_notes' => 'Accord DG du 12/07, règlement bancaire en compensation.',
        'financial_authorization_unpaid' => GUARD_TTC,
    ]);

    guardLancer($of->fresh());
    expect($of->fresh()->status)->toBe('lance');
});

it('18. ne retient pas une autorisation sans auteur — la trace laissée par l\'anomalie', function () {
    guardUtilisateur('chef_production');
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    // Empreinte exacte d'OF-2026-0007 : date d'autorisation renseignée, auteur
    // NULL. C'est ce que l'ancienne garde écrivait elle-même, et que l'écran
    // présentait comme « ✔ Approuvée ». Une décision sans décideur n'en est pas une.
    $of->update([
        'financial_authorization' => 'approved',
        'financial_authorized_at' => now(),
        'financial_authorized_by' => null,
    ]);

    expect(fn () => guardLancer($of->fresh()))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('19. ne retient pas une dérogation expirée', function () {
    $daf = guardUtilisateur('daf', ['production.approve_financial']);
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    $of->update([
        'financial_authorization' => 'approved',
        'financial_authorized_at' => now()->subDays(30),
        'financial_authorized_by' => $daf->id,
        'financial_authorization_expires_at' => today()->subDay(),
        'financial_notes' => 'Dérogation ponctuelle du mois dernier.',
    ]);

    expect(fn () => guardLancer($of->fresh()))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('20. autorise le lancement sur approbation gérant portée par la commande', function () {
    $daf = guardUtilisateur('daf', ['production.approve_financial']);
    ['of' => $of, 'commande' => $commande] = guardScenario(Client::PAYMENT_CASH);

    $commande->update([
        'production_approved' => true,
        'production_approved_at' => now(),
        'production_approved_by' => $daf->id,
        'production_approval_reason' => 'Client historique, accord DG.',
        'production_approval_expires_at' => today()->addDays(7),
    ]);

    guardLancer($of->fresh());
    expect($of->fresh()->status)->toBe('lance');
});

it('21. ne retient pas une approbation gérant expirée', function () {
    $daf = guardUtilisateur('daf', ['production.approve_financial']);
    ['of' => $of, 'commande' => $commande] = guardScenario(Client::PAYMENT_CASH);

    $commande->update([
        'production_approved' => true,
        'production_approved_at' => now()->subDays(30),
        'production_approved_by' => $daf->id,
        'production_approval_reason' => 'Accord ponctuel du mois dernier.',
        'production_approval_expires_at' => today()->subDay(),
    ]);

    expect(fn () => guardLancer($of->fresh()))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

// ═════════════════════════════════════════════════════════════════════════════
// 22 à 26 · AUDIT ET CONCURRENCE
// ═════════════════════════════════════════════════════════════════════════════

it('22. n\'écrit strictement rien lorsqu\'un lancement est refusé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    $avant = guardEmpreinteFinanciere($of);
    $auditsAvant = AuditLog::where('action', 'production.of.lancement')->count();

    expect(fn () => guardLancer($of))->toThrow(ValidationException::class);

    expect(guardEmpreinteFinanciere($of))->toBe($avant);
    expect(AuditLog::where('action', 'production.of.lancement')->count())->toBe($auditsAvant);
});

it('23. journalise le fondement financier du lancement réussi', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC);

    guardLancer($of);

    // Le lancement d'OF-2026-0007 n'avait laissé aucune entrée : l'incident n'a pu
    // être reconstitué que par recoupement de colonnes.
    $log = AuditLog::where('action', 'production.of.lancement')
        ->where('model_id', $of->getKey())->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['financier']['type'])->toBe(ProductionFinancialRequirement::TYPE_FULL_PAYMENT);
    expect($log->new_values['financier']['satisfied'])->toBeTrue();
    expect((int) $log->new_values['financier']['covered'])->toBe(GUARD_TTC);
});

it('24. n\'inscrit aucune autorisation financière quand la couverture vient du paiement', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC);

    guardLancer($of);
    $of->refresh();

    // Cœur de la correction : payer n'est pas être autorisé. Le lancement est
    // acquis, l'éligibilité est calculée — et aucune piste d'audit ne prétend
    // qu'une personne a approuvé quoi que ce soit.
    expect($of->status)->toBe('lance');
    expect($of->financial_authorization)->toBeNull();
    expect($of->financial_authorized_at)->toBeNull();
    expect($of->financial_authorized_by)->toBeNull();

    // L'éligibilité reste calculée à la demande, et reflète le paiement.
    $exigence = $of->order->productionFinancialRequirement($of);
    expect($exigence->type)->toBe(ProductionFinancialRequirement::TYPE_FULL_PAYMENT);
    expect($exigence->label())->toBe('Éligibilité acquise — Paiement intégral confirmé');
});

it('25. consigne auteur, motif, validité et risque accepté lors d\'une dérogation', function () {
    $daf = guardUtilisateur('daf', ['production.approve_financial', 'production.view']);
    ['of' => $of] = guardScenario(Client::PAYMENT_CASH);

    $reponse = test()->post(route('production.orders.authorize-finance', $of), [
        'financial_notes' => 'Accord DG du 12/07 : virement client en cours de compensation.',
        'expires_at' => today()->addDays(5)->toDateString(),
    ]);
    $reponse->assertRedirect();

    $of->refresh();
    expect($of->financial_authorization)->toBe('approved');
    expect($of->financial_authorized_by)->toBe($daf->id);
    expect($of->financial_authorized_at)->not->toBeNull();
    expect($of->financial_authorization_expires_at->toDateString())->toBe(today()->addDays(5)->toDateString());
    expect((int) $of->financial_authorization_unpaid)->toBe(GUARD_TTC);
    expect($of->financial_notes)->toContain('Accord DG du 12/07');

    expect(AuditLog::where('action', 'production.of.derogation_financiere')
        ->where('model_id', $of->getKey())->exists())->toBeTrue();

    // Un motif vide n'ouvre aucune dérogation : sans justification, la trace ne
    // vaudrait pas mieux que l'autorisation automatique supprimée.
    ['of' => $autre] = guardScenario(Client::PAYMENT_CASH);
    test()->post(route('production.orders.authorize-finance', $autre), ['financial_notes' => ''])
        ->assertSessionHasErrors('financial_notes');
    expect($autre->fresh()->financial_authorization)->toBeNull();
});

it('26. ne relance pas un OF déjà lancé', function () {
    guardUtilisateur('chef_production');
    ['of' => $of, 'client' => $client, 'company' => $co] = guardScenario(Client::PAYMENT_CASH);
    guardAcompte($co, $client, GUARD_TTC);

    guardLancer($of);
    expect($of->fresh()->status)->toBe('lance');
    $empreinte = guardEmpreinteFinanciere($of);

    // Le statut est revérifié SOUS VERROU, après acquisition de celui-ci : deux
    // requêtes concurrentes sérialisent sur la même ligne, et la seconde voit
    // l'état committé par la première.
    expect(fn () => guardLancer($of->fresh()))->toThrow(ValidationException::class);
    expect(guardEmpreinteFinanciere($of))->toBe($empreinte);
});
