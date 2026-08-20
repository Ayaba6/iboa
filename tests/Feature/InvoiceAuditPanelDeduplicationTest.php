<?php

/**
 * [UI] La fiche facture n'affiche plus l'historique d'audit en double.
 *
 * Défaut constaté sur `/ventes/factures/12` :
 *
 *   - `InvoiceController::show()` interrogeait `audit_logs` (20 dernières opérations)
 *     et passait `$audits` à la vue, qui rendait une section « 7. Historique » ;
 *   - la même vue appelait ensuite `<x-audit.timeline>`, composant qui refait
 *     EXACTEMENT la même requête et rend un second panneau.
 *
 * Résultat : deux panneaux identiques sur une seule page, et deux fois le même
 * SELECT — visible dans le journal de requêtes.
 *
 * Arbitrage : le composant est conservé. Il sert ONZE fiches — devis, commandes,
 * avoirs, bons de livraison, achats, réceptions, transferts, journaux — et affiche
 * davantage que la section supprimée : adresse IP, écart avant/après, référence du
 * document. Les quatre actions qu'il ne nommait pas encore — `sent`, `cancelled`,
 * `partially_paid`, `overdue` — y ont été ajoutées AVANT la suppression, pour ne
 * rien perdre au passage.
 */

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function auditDedupFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'AUDITDEDUP-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'AuditDedup Co'], [
        'email' => 'auditdedup@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    $role = Role::firstOrCreate(['name' => 'auditdedup_lecteur', 'guard_name' => 'web']);
    foreach (['invoices.view'] as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $user->assignRole($role);
    test()->actingAs($user);

    $client  = Client::factory()->create(['is_active' => true]);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'FA-AUDITDEDUP-'.uniqid(), 'status' => 'brouillon',
        'issued_at' => now(), 'due_at' => now()->addDays(30),
        'subtotal_ht' => 100_000, 'total_tax' => 18_000, 'total_ttc' => 118_000,
        'remaining_amount' => 118_000, 'paid_amount' => 0,
    ]);

    // Trois entrées de journal, pour que les deux panneaux auraient eu de quoi
    // s'afficher : un test sur une facture sans historique ne prouverait rien.
    foreach (['created', 'sent', 'partially_paid'] as $action) {
        AuditLog::create([
            'model_type' => Invoice::class,
            'model_id'   => $invoice->id,
            'action'     => $action,
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return compact('company', 'fy', 'user', 'client', 'invoice');
}

it('n’interroge le journal d’audit qu’UNE seule fois', function () {
    $f = auditDedupFixture();

    // Une première visite amorce les caches (permissions, paramètres) : mesurer la
    // seconde évite de compter des requêtes d'amorçage comme des doublons.
    $this->get(route('ventes.factures.show', $f['invoice']))->assertOk();

    DB::enableQueryLog();
    $this->get(route('ventes.factures.show', $f['invoice']))->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $auditQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'audit_logs'));

    expect(count($auditQueries))->toBe(1);
});

it('ne rend qu’UN seul panneau d’historique', function () {
    $f = auditDedupFixture();

    $html = $this->get(route('ventes.factures.show', $f['invoice']))->assertOk()->getContent();

    // La section supprimée portait ce titre ; le composant porte le sien.
    expect($html)->not->toContain('7. Historique')
        ->and(substr_count($html, 'Historique d&#039;activit'))->toBe(1);
});

it('conserve le composant, source unique pour onze fiches', function () {
    $f = auditDedupFixture();

    $html = $this->get(route('ventes.factures.show', $f['invoice']))->assertOk()->getContent();

    // Le composant reste appelé et rend bien les entrées du journal.
    expect($html)->toContain('Historique d&#039;activit')
        ->and($html)->toContain('Création');
});

it('nomme les quatre actions reprises de la section supprimée', function () {
    // Sans cet ajout, `sent`, `cancelled`, `partially_paid` et `overdue` seraient
    // retombées sur le libellé générique du composant : on aurait perdu une
    // information en supprimant le doublon.
    $source = file_get_contents(resource_path('views/components/audit/timeline.blade.php'));

    foreach (['sent', 'cancelled', 'partially_paid', 'overdue'] as $action) {
        expect($source)->toContain("'".$action."'");
    }

    $f = auditDedupFixture();
    $html = $this->get(route('ventes.factures.show', $f['invoice']))->assertOk()->getContent();

    expect($html)->toContain('Envoyée au client')
        ->and($html)->toContain('Paiement partiel');
});

it('ne passe plus $audits à la vue', function () {
    // Laisser la variable aurait gardé la requête du contrôleur, donc le doublon
    // de SELECT, même sans le second panneau.
    $source = file_get_contents(app_path('Http/Controllers/Sales/InvoiceController.php'));

    expect($source)->not->toContain("compact('invoice', 'audits')")
        ->and($source)->toContain("compact('invoice')");
});
