<?php

/**
 * [Ventes §17] La commande `a3:audit-orphans` détecte les parents disparus.
 *
 * Motif : neuf bons de préparation pointaient vers des commandes absentes de la
 * table `orders`, malgré une clé étrangère. Rien dans l'ERP ne le signalait —
 * l'anomalie n'est apparue que dans un inventaire manuel, la colonne
 * « commande » restant simplement vide à l'écran.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function orphanFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'ORPHAN-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Orphan Co'], [
        'email' => 'orphan@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    $client = Client::factory()->create(['payment_mode' => 'credit', 'credit_limit' => 50_000_000, 'balance' => 0]);
    $order  = Order::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'client_id' => $client->id,
        'number' => 'CMD-ORPHAN-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 100_000, 'total_tax' => 18_000, 'total_ttc' => 118_000,
        'total_discount' => 0, 'global_discount_amount' => 0, 'invoiced_amount' => 0,
    ]);
    $bp = BonPreparation::create([
        'company_id' => $company->id, 'order_id' => $order->id, 'fiscal_year_id' => $fy->id,
        'number' => 'BP-ORPHAN-'.uniqid(), 'payment_mode' => 'credit', 'status' => 'en_attente',
        'created_by' => $user->id,
    ]);

    return compact('company', 'fy', 'order', 'client', 'user', 'bp');
}

/**
 * Fabrique l'état exact rencontré en base : un enfant désignant un parent qui
 * n'existe pas.
 *
 * On n'y arrive PAS en supprimant la commande : `bon_preparations_order_id_foreign`
 * est en ON DELETE CASCADE, le bon partirait avec elle. Les vrais orphelins
 * viennent d'un `migrate:fresh` — un DROP TABLE n'exécute aucune cascade et
 * laisse les enfants derrière lui. On reproduit donc directement le résultat :
 * une ligne pointant vers un identifiant inexistant.
 *
 * Les deux moteurs diffèrent sur la levée des contraintes en transaction :
 *  - MySQL honore `SET FOREIGN_KEY_CHECKS=0` à l'intérieur d'une transaction ;
 *  - SQLite IGNORE `PRAGMA foreign_keys=OFF` dès qu'une transaction est ouverte
 *    — ce que fait RefreshDatabase — mais accepte `defer_foreign_keys`, qui
 *    reporte la vérification au commit. Le test étant annulé, elle n'a jamais lieu.
 */
function orphanWithoutForeignKeys(callable $callback): void
{
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $callback();

        return;
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        $callback();
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

/** Identifiant de commande garanti absent de la table. */
function orphanMissingOrderId(): int
{
    return ((int) DB::table('orders')->max('id')) + 10_000;
}

/** Rend un bon de préparation orphelin en le rattachant à une commande inexistante. */
function orphanDetachBp(int $bpId): int
{
    $missing = orphanMissingOrderId();
    orphanWithoutForeignKeys(fn () => DB::table('bon_preparations')
        ->where('id', $bpId)->update(['order_id' => $missing]));

    return $missing;
}

// ── Le cas sain ──────────────────────────────────────────────────────────────

it('sort en succès quand tous les rattachements pointent vers un parent existant', function () {
    orphanFixture();

    $this->artisan('a3:audit-orphans')
        ->expectsOutputToContain('AUCUN ORPHELIN')
        ->assertExitCode(0);
});

it('ne compte pas un parent en suppression logique comme disparu', function () {
    // Une commande annulée puis retirée des listes reste consultable : sa ligne
    // existe. Le signaler serait un faux positif qui noierait les vrais.
    $f = orphanFixture();
    $f['order']->delete();

    expect(Order::withTrashed()->find($f['order']->id))->not->toBeNull();

    $this->artisan('a3:audit-orphans')
        ->expectsOutputToContain('AUCUN ORPHELIN')
        ->assertExitCode(0);
});

// ── La détection ─────────────────────────────────────────────────────────────

it('détecte un bon de préparation dont la commande a été effacée', function () {
    $f = orphanFixture();
    orphanDetachBp($f['bp']->id);

    $this->artisan('a3:audit-orphans')
        ->expectsOutputToContain('Bons de préparation sans commande')
        ->assertExitCode(1);
});

it('cesse de signaler un orphelin lui-même RETIRÉ, sans le détruire', function () {
    // Neuf bons orphelins réels ont été retirés en suppression LOGIQUE : leur
    // commande parente avait été effacée contraintes désactivées, ils n'étaient
    // pas annulables (statut « chargé ») et les annuler n'aurait recréé aucune
    // commande. Sans ce filtre, l'audit les aurait signalés indéfiniment et ne
    // serait jamais revenu au vert — un retrait assumé et tracé doit pouvoir
    // clore l'alerte.
    $f = orphanFixture();
    orphanDetachBp($f['bp']->id);

    // Encore signalé tant qu'il circule…
    $this->artisan('a3:audit-orphans')->assertExitCode(1);

    $f['bp']->delete(); // suppression LOGIQUE

    // …plus signalé une fois retiré…
    $this->artisan('a3:audit-orphans')->assertExitCode(0);

    // …mais la ligne existe TOUJOURS : rien n'a été détruit.
    expect(DB::table('bon_preparations')->where('id', $f['bp']->id)->exists())->toBeTrue()
        ->and(DB::table('bon_preparations')->where('id', $f['bp']->id)->value('deleted_at'))->not->toBeNull();
});

it('signale à nouveau un orphelin restauré', function () {
    // Le filtre porte sur l'état COURANT, pas sur un marqueur définitif : un
    // document remis en circulation redevient un orphelin actif.
    $f = orphanFixture();
    orphanDetachBp($f['bp']->id);
    $f['bp']->delete();
    $this->artisan('a3:audit-orphans')->assertExitCode(0);

    DB::table('bon_preparations')->where('id', $f['bp']->id)->update(['deleted_at' => null]);

    $this->artisan('a3:audit-orphans')->assertExitCode(1);
});

it('sort en échec pour qu’une intégration continue s’arrête', function () {
    // Le code de sortie est le seul signal exploitable par un ordonnanceur.
    $f = orphanFixture();
    orphanDetachBp($f['bp']->id);

    $this->artisan('a3:audit-orphans')->assertExitCode(1);
});

it('nomme la table, la colonne et les identifiants concernés', function () {
    $f = orphanFixture();
    $bpId    = $f['bp']->id;
    $missing = orphanDetachBp($bpId);

    // Assertions portées sur la table, la colonne et les deux identifiants pris
    // séparément : le rapport les relie par une flèche « → » dont la comparaison
    // multi-octets n'est pas fiable selon la console, et ce n'est pas elle que
    // ce test doit prouver.
    $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
    $code   = \Illuminate\Support\Facades\Artisan::call('a3:audit-orphans', [], $buffer);
    $output = $buffer->fetch();

    expect($code)->toBe(1)
        ->and($output)->toContain('bon_preparations.order_id')
        ->and($output)->toContain("#{$bpId}")
        ->and($output)->toContain((string) $missing);
});

it('ne répare rien — l’orphelin est laissé en l’état', function () {
    $f = orphanFixture();
    $missing = orphanDetachBp($f['bp']->id);

    $this->artisan('a3:audit-orphans')->assertExitCode(1);

    // Ni suppression, ni rattachement inventé : le sort d'un orphelin est une
    // décision métier, pas le rôle d'un audit.
    $row = DB::table('bon_preparations')->find($f['bp']->id);
    expect($row)->not->toBeNull()
        ->and((int) $row->order_id)->toBe($missing);
});

it('détecte aussi une facture dont la commande a disparu', function () {
    $f = orphanFixture();
    $missing = orphanMissingOrderId();

    orphanWithoutForeignKeys(fn () => DB::table('invoices')->insert([
        'company_id' => $f['company']->id, 'fiscal_year_id' => $f['fy']->id,
        'client_id' => $f['client']->id, 'order_id' => $missing,
        'number' => 'FA-ORPHAN-'.uniqid(), 'status' => 'brouillon',
        'issued_at' => now(), 'due_at' => now()->addDays(30),
        'subtotal_ht' => 100_000, 'total_tax' => 18_000, 'total_ttc' => 118_000,
        'remaining_amount' => 118_000, 'paid_amount' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));

    $this->artisan('a3:audit-orphans')
        ->expectsOutputToContain('Factures sans commande')
        ->assertExitCode(1);
});

// ── Le périmètre ─────────────────────────────────────────────────────────────

it('restreint la recherche à la société demandée', function () {
    $f = orphanFixture();
    orphanDetachBp($f['bp']->id);

    // Une autre société : l'orphelin ne la concerne pas.
    $other = Company::firstOrCreate(['name' => 'Orphan Autre'], [
        'email' => 'orphan2@oa-metal.test', 'current_fiscal_year_id' => $f['fy']->id,
    ]);

    $this->artisan('a3:audit-orphans', ['--company' => $other->id])
        ->expectsOutputToContain('AUCUN ORPHELIN')
        ->assertExitCode(0);

    $this->artisan('a3:audit-orphans', ['--company' => $f['company']->id])
        ->assertExitCode(1);
});

it('ne prétend pas sains les liens qu’il n’a pas pu vérifier', function () {
    // Le rapport distingue « vérifié et sain » de « non vérifiable ». Confondre
    // les deux transformerait une lacune de couverture en certificat de santé.
    $source = file_get_contents(app_path('Console/Commands/AuditOrphanDocuments.php'));

    expect($source)->toContain('NON VÉRIFIÉS')
        ->and($source)->toContain('ni sains, ni fautifs');
});
