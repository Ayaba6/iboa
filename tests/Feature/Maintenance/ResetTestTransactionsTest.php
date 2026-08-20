<?php

/**
 * Remise à zéro du transactionnel de test — gardes et comportement.
 *
 * Ce qui est éprouvé ici n'est pas « la commande supprime bien ». C'est
 * l'inverse : tout ce qu'elle doit REFUSER de faire. Une commande de purge se
 * juge à ses refus, parce qu'un défaut d'ordre ou une garde manquante ne se
 * remarque qu'une fois les données parties.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function rttSociete(): Company
{
    $exercice = FiscalYear::firstOrCreate(
        ['label' => 'RTT-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $societe = Company::firstOrCreate(
        ['name' => 'RTT Co'],
        ['email' => 'rtt@iboa.test', 'current_fiscal_year_id' => $exercice->id],
    );
    app()->instance('current_company', $societe);
    Warehouse::firstOrCreate(
        ['code' => 'WRTT'],
        ['name' => 'Dépôt RTT', 'company_id' => $societe->id, 'is_active' => true, 'is_default' => true],
    );

    return $societe;
}

function rttUtilisateur(): User
{
    $u = User::factory()->create(['company_id' => rttSociete()->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    auth()->login($u);

    return $u;
}

/** Un devis, donc une ligne transactionnelle réelle à purger. */
function rttDevis(): Quote
{
    rttUtilisateur();

    return app(\App\Services\QuoteService::class)->create([
        'client_id' => Client::factory()->create(['is_active' => true])->id,
        'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => Product::factory()->create(['is_sellable' => true, 'is_active' => true, 'sale_price' => 1000])->id,
            'description' => 'Ligne RTT', 'quantity' => 2, 'unit_price' => 1000, 'tax_rate_value' => 18,
        ]],
    ]);
}

/**
 * Empreinte publiée par le mode audit — le test ne la devine pas, il la lit.
 *
 * `Artisan::call()` et non `$this->artisan()` : ce dernier rend un
 * `PendingCommand` qui ne s'exécute qu'à la première assertion, si bien que
 * `Artisan::output()` serait lu AVANT que la commande ait écrit quoi que ce soit.
 */
function rttEmpreinte(array $options = []): string
{
    // Les options font partie de l'empreinte : un audit produit sans elles
    // rendrait un jeton que l'exécution refuserait. C'est voulu — le rapport
    // validé doit décrire l'opération exacte, options comprises.
    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true] + $options);
    $sortie = \Illuminate\Support\Facades\Artisan::output();

    expect($sortie)->toMatch('/Empreinte du rapport : \w{16}/');
    preg_match('/Empreinte du rapport : (\w{16})/', $sortie, $m);

    return $m[1];
}

it('1. n’écrit rien en mode audit', function () {
    rttDevis();
    $avant = DB::table('quotes')->count();
    expect($avant)->toBeGreaterThan(0);

    $this->artisan('a3:reset-test-transactions --audit')->assertSuccessful();

    expect(DB::table('quotes')->count())->toBe($avant);
    expect(DB::table('quote_items')->count())->toBeGreaterThan(0);
});

it('2. refuse d’exécuter sans jeton de confirmation', function () {
    rttDevis();
    $avant = DB::table('quotes')->count();

    $this->artisan('a3:reset-test-transactions --execute')->assertFailed();

    expect(DB::table('quotes')->count())->toBe($avant);
});

it('3. refuse un jeton périmé', function () {
    // Le scénario réel : l'audit est produit, quelqu'un saisit un document,
    // puis l'exécution est lancée avec l'ancien jeton. Le rapport validé ne
    // décrit alors plus la base.
    rttDevis();

    $this->artisan('a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-0000000000000000')
        ->assertFailed();

    expect(DB::table('quotes')->count())->toBeGreaterThan(0);
});

it('4. invalide le jeton dès qu’une ligne apparaît après l’audit', function () {
    rttDevis();
    $jeton = rttEmpreinte();

    // Une seconde pièce entre l'audit et l'exécution.
    rttDevis();

    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertFailed();

    expect(DB::table('quotes')->count())->toBe(2);
});

it('5. supprime le transactionnel avec les contraintes ACTIVES', function () {
    rttDevis();

    // La garde qui compte : si la commande désactivait les contraintes, ce
    // contrôle passerait quand même. On vérifie donc l'état du moteur, pas le
    // résultat.
    expect((int) DB::selectOne('SELECT @@SESSION.foreign_key_checks c')->c)->toBe(1);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('quotes')->count())->toBe(0);
    expect(DB::table('quote_items')->count())->toBe(0);
    expect((int) DB::selectOne('SELECT @@SESSION.foreign_key_checks c')->c)->toBe(1);
});

it('6. conserve le paramétrage', function () {
    rttDevis();
    $articles = DB::table('products')->count();
    $utilisateurs = DB::table('users')->count();
    $comptes = DB::table('accounts')->count();
    $depots = DB::table('warehouses')->count();

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('products')->count())->toBe($articles);
    expect(DB::table('users')->count())->toBe($utilisateurs);
    expect(DB::table('accounts')->count())->toBe($comptes);
    expect(DB::table('warehouses')->count())->toBe($depots);
});

it('7. conserve un référentiel de test encore référencé par une donnée SURVIVANTE', function () {
    // Un marqueur ne suffit pas : tant qu'une ligne pointe vers le tiers, il
    // reste — sans quoi la suppression créerait l'orphelin que toute
    // l'opération cherche à éviter.
    //
    // La référence choisie doit SURVIVRE à la purge, faute de quoi le test ne
    // prouverait rien. Un contact ne convient pas : il dépend du client par une
    // contrainte CASCADE, et part donc avec lui (§10). Un contact CRM, lui,
    // n'est retiré que sur `--include-crm`, absent ici.
    $societe = rttSociete();
    rttUtilisateur();
    $client = Client::factory()->create(['code' => 'CLT-TEST-GARDE', 'is_active' => true]);

    DB::table('crm_contacts')->insert([
        'company_id' => $societe->id, 'client_id' => $client->id, 'name' => 'Contact CRM',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte(['--delete-test-masters' => true]);
    $this->artisan("a3:reset-test-transactions --execute --delete-test-masters --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('clients')->where('id', $client->id)->exists())->toBeTrue();
})->skip(fn () => ! Schema::hasTable('crm_contacts'), 'crm_contacts absente');

it('7b. supprime les dépendances CASCADE du tiers avec lui', function () {
    // Revers de la règle précédente : contacts, adresses et conditions
    // particulières n'existent QUE par le tiers. La base les détruirait de
    // toute façon ; la commande les retire explicitement pour qu'ils
    // apparaissent au rapport au lieu de disparaître en silence.
    rttSociete();
    rttUtilisateur();
    $client = Client::factory()->create(['code' => 'CLT-TEST-CASCADE', 'is_active' => true]);
    DB::table('client_contacts')->insert([
        'client_id' => $client->id, 'last_name' => 'Contact', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte(['--delete-test-masters' => true]);
    $this->artisan("a3:reset-test-transactions --execute --delete-test-masters --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('clients')->where('id', $client->id)->exists())->toBeFalse();
    expect(DB::table('client_contacts')->where('client_id', $client->id)->count())->toBe(0);
})->skip(fn () => ! Schema::hasTable('client_contacts'), 'client_contacts absente');

it('8. ne touche pas aux référentiels sans --delete-test-masters', function () {
    rttUtilisateur();
    $client = Client::factory()->create(['code' => 'CLT-TEST-SEUL', 'is_active' => true]);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('clients')->where('id', $client->id)->exists())->toBeTrue();
});

it('9. ne repositionne les séquences qu’avec --reset-sequences', function () {
    rttDevis();
    DB::table('document_sequences')->where('document_type', 'devis')->update(['last_number' => 42]);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect((int) DB::table('document_sequences')->where('document_type', 'devis')->value('last_number'))->toBe(42);
});

it('10. repositionne les séquences avec l’option', function () {
    rttDevis();
    DB::table('document_sequences')->where('document_type', 'devis')->update(['last_number' => 42]);

    $jeton = rttEmpreinte(['--reset-sequences' => true]);
    $this->artisan("a3:reset-test-transactions --execute --reset-sequences --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect((int) DB::table('document_sequences')->where('document_type', 'devis')->value('last_number'))->toBe(0);
});

it('11. recalcule le stock au lieu de l’écraser', function () {
    rttDevis();
    $article = Product::factory()->create(['is_active' => true]);
    $depot = Warehouse::where('code', 'WRTT')->firstOrFail();

    DB::table('product_stocks')->insert([
        'product_id' => $article->id, 'warehouse_id' => $depot->id,
        'quantity' => 12.5, 'reserved_quantity' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte(['--reset-stock' => true]);
    $this->artisan("a3:reset-test-transactions --execute --reset-stock --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    // Aucun mouvement ne subsiste : le recalcul DONNE zéro. La ligne demeure —
    // c'est le stock qui est nul, pas la fiche de stock qui disparaît.
    $ligne = DB::table('product_stocks')->where('product_id', $article->id)->first();
    expect($ligne)->not->toBeNull();
    expect((float) $ligne->quantity)->toBe(0.0);
    expect((float) $ligne->reserved_quantity)->toBe(0.0);
});

it('12. laisse le stock intact sans --reset-stock', function () {
    rttDevis();
    $article = Product::factory()->create(['is_active' => true]);
    $depot = Warehouse::where('code', 'WRTT')->firstOrFail();

    DB::table('product_stocks')->insert([
        'product_id' => $article->id, 'warehouse_id' => $depot->id,
        'quantity' => 7.25, 'reserved_quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect((float) DB::table('product_stocks')->where('product_id', $article->id)->value('quantity'))->toBe(7.25);
});

it('13. ne laisse aucun orphelin sur les clés étrangères', function () {
    rttDevis();

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    $base = DB::connection()->getDatabaseName();
    $fks = DB::table('information_schema.KEY_COLUMN_USAGE')
        ->where('TABLE_SCHEMA', $base)
        ->whereNotNull('REFERENCED_TABLE_NAME')
        ->get(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);

    expect($fks)->not->toBeEmpty();

    $orphelins = [];
    foreach ($fks as $fk) {
        $n = DB::table($fk->TABLE_NAME.' as c')
            ->leftJoin($fk->REFERENCED_TABLE_NAME.' as p', "p.{$fk->REFERENCED_COLUMN_NAME}", '=', "c.{$fk->COLUMN_NAME}")
            ->whereNotNull("c.{$fk->COLUMN_NAME}")
            ->whereNull("p.{$fk->REFERENCED_COLUMN_NAME}")
            ->count();

        if ($n > 0) {
            $orphelins["{$fk->TABLE_NAME}.{$fk->COLUMN_NAME}"] = $n;
        }
    }

    expect($orphelins)->toBe([]);
});

it('14. n’emploie ni FOREIGN_KEY_CHECKS, ni TRUNCATE, ni DELETE sans clause', function () {
    // Garde de code, et non de comportement : ces trois motifs sont interdits
    // par la mission, et leur absence doit rester vraie après refonte.
    $source = file_get_contents(app_path('Console/Commands/ResetTestTransactions.php'));

    // On ignore les commentaires : le docblock EXPLIQUE pourquoi ces motifs
    // sont proscrits, et doit pouvoir les nommer.
    $code = '';
    foreach (token_get_all($source) as $jeton) {
        if (is_array($jeton) && in_array($jeton[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($jeton) ? $jeton[1] : $jeton;
    }

    expect($code)->not->toContain('FOREIGN_KEY_CHECKS');
    expect($code)->not->toContain('truncate');
    expect($code)->not->toContain('TRUNCATE');
    expect($code)->not->toContain('migrate:fresh');
    expect($code)->not->toContain('db:wipe');

    // Chaque suppression porte une clause.
    expect($code)->toContain("whereRaw('1 = 1')->delete()");
});

it('15. n’autorise que iboa_erp et les bases de test', function (string $base, bool $attendu) {
    // La règle est isolée en fonction pure : la vérifier n'exige pas de casser
    // la connexion du processus de test, donc elle est vérifiée VRAIMENT et non
    // laissée en suspens.
    expect(\App\Console\Commands\ResetTestTransactions::baseAutorisee($base))->toBe($attendu);
})->with([
    ['iboa_erp',                      true],
    ['iboa_erp_test',                 true],
    ['iboa_erp_test_mtofin',          true],
    ['iboa_erp_reset_test',           true],
    ['testing',                       true],
    ['a3_qa',                         true],
    ['ci_build',                      true],

    // Bancs d'essai de restauration : admis. C'est là qu'on répète le reset
    // avant de toucher à la base réelle ; les refuser interdirait la seule
    // répétition générale qui vaille.
    ['iboa_erp_restore_check',        true],
    ['iboa_erp_final_restore_check',  true],
    ['restore_check',                 true],

    // Copies d'inspection ordinaires : refusées. Rien n'y indique qu'elles
    // soient jetables.
    ['iboa_erp_profils',              false],
    ['iboa_erp_prod',                 false],
    ['iboa_erp_sauvegarde_20260804',  false],
    ['une_base_quelconque',           false],
    ['',                              false],

    // Piège : « test » doit être un SEGMENT, pas une sous-chaîne. Une base
    // nommée « contestation » ou « latest_backup » n'est pas une base de test.
    ['contestation',                  false],
    ['latest_backup',                 false],
    ['protest',                       false],
]);

it('16. ne supprime pas une table peuplée qu’aucune liste ne classe', function () {
    // Principe central : le défaut est de CONSERVER. Une table oubliée au
    // classement doit survivre à la purge et apparaître au rapport — sans quoi
    // la commande déciderait seule du sort de ce qu'on n'a pas relu.
    rttDevis();

    // La table est vide sur une base fraîchement migrée : on la peuple, sans
    // quoi le test n'observerait rien et passerait pour de mauvaises raisons.
    DB::table('audit_logs')->insert([
        'user_name' => 'Testeur', 'action' => 'reset.garde', 'created_at' => now(),
    ]);

    $avant = DB::table('audit_logs')->count();
    expect($avant)->toBeGreaterThan(0);

    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true]);
    $sortie = \Illuminate\Support\Facades\Artisan::output();
    expect($sortie)->toContain('ZONES GRISES');
    expect($sortie)->toContain('audit_logs');

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    expect(DB::table('audit_logs')->count())->toBe($avant);
});

/**
 * Peuple un exemplaire de chaque domaine annexe, et rend les compteurs à
 * vérifier. Les lignes sont insérées directement : passer par les services
 * ferait dépendre ce test de règles métier qui ne sont pas son objet.
 */
function rttPeuplerDomaines(Company $societe, Client $client): array
{
    DB::table('client_payments')->insert([
        'company_id' => $societe->id, 'client_id' => $client->id,
        'number' => 'ENC-RTT-1', 'amount' => 5000, 'payment_date' => '2026-07-15',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('fixed_assets')->insert([
        'company_id' => $societe->id, 'code' => 'IMB-RTT-1', 'name' => 'Immobilisation RTT',
        'acquisition_date' => '2026-01-10', 'commissioning_date' => '2026-01-15',
        'acquisition_cost' => 1_000_000, 'asset_account' => '2441',
        'depr_account' => '2841', 'charge_account' => '6813',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $immo = DB::table('fixed_assets')->where('code', 'IMB-RTT-1')->value('id');

    DB::table('fixed_asset_depreciations')->insert([
        'fixed_asset_id' => $immo, 'company_id' => $societe->id, 'fiscal_year' => 2026,
        'depreciation_amount' => 200_000, 'cumulated_depreciation' => 200_000,
        'net_book_value' => 800_000, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('crm_contacts')->insert([
        'company_id' => $societe->id, 'name' => 'Contact CRM RTT',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_opportunities')->insert([
        'company_id' => $societe->id, 'title' => 'Opportunité RTT',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('commercial_contracts')->insert([
        'company_id' => $societe->id, 'number' => 'CT-RTT-1', 'description' => 'Contrat RTT',
        'contract_date' => '2026-01-05', 'starts_at' => '2026-01-06',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('payroll_runs')->insert([
        'company_id' => $societe->id, 'period_month' => 7, 'period_year' => 2026,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'client_payments'           => 1,
        'fixed_assets'              => 1,
        'fixed_asset_depreciations' => 1,
        'crm_contacts'              => 1,
        'crm_opportunities'         => 1,
        'commercial_contracts'      => 1,
        'payroll_runs'              => 1,
    ];
}

it('18. --full vide comptabilité, trésorerie, paie, immobilisations, CRM et contrats', function () {
    // Couvre en une passe les points 1 à 11 du périmètre imposé : ces domaines
    // ne partent QUE si le périmètre complet est demandé, et alors ils partent
    // tous.
    $societe = rttSociete();
    rttUtilisateur();
    $devis = rttDevis();
    $client = Client::findOrFail($devis->client_id);

    $domaines = rttPeuplerDomaines($societe, $client);
    foreach ($domaines as $table => $attendu) {
        expect(DB::table($table)->count())->toBeGreaterThanOrEqual($attendu, "{$table} non peuplée");
    }

    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true, '--full' => true]);
    $sortie = \Illuminate\Support\Facades\Artisan::output();
    preg_match('/Empreinte du rapport : (\w{16})/', $sortie, $m);

    $this->artisan("a3:reset-test-transactions --execute --full --confirmation=RESET-A3-FULL-TEST-{$m[1]}")
        ->assertSuccessful();

    foreach (array_keys($domaines) as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} n'a pas été vidée");
    }

    expect(DB::table('journal_entries')->count())->toBe(0);
    expect(DB::table('journal_entry_lines')->count())->toBe(0);
});

it('19. refuse un jeton de périmètre complet à une commande partielle', function () {
    // Le préfixe du jeton porte le PÉRIMÈTRE. Sans cela, un jeton obtenu sur un
    // rapport complet pourrait lancer une purge partielle, ou l'inverse — et le
    // rapport validé ne décrirait pas ce qui s'exécute.
    rttDevis();

    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true, '--full' => true]);
    preg_match('/Empreinte du rapport : (\w{16})/', \Illuminate\Support\Facades\Artisan::output(), $m);

    // Jeton complet présenté SANS --full : refusé.
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-FULL-TEST-{$m[1]}")
        ->assertFailed();

    expect(DB::table('quotes')->count())->toBeGreaterThan(0);
});

it('20. annule tout si un contrôle échoue en fin de transaction', function () {
    // La garde finale exige EXACTEMENT un exercice courant. Ce test la prend
    // par l'autre bout : aucun exercice n'est courant.
    //
    // La version précédente en créait deux — ce que
    // [BUG-A3-ACCOUNTING-MULTIPLE-CURRENT-FY-024] rend désormais impossible en
    // base. Zéro reste atteignable, et déclenche la même garde, au même
    // endroit : après les suppressions, avant le COMMIT.
    rttSociete();
    rttUtilisateur();
    rttDevis();

    FiscalYear::where('is_current', true)->update(['is_current' => false]);
    expect(DB::table('fiscal_years')->where('is_current', true)->count())->toBe(0);

    $devisAvant = DB::table('quotes')->count();
    $lignesAvant = DB::table('quote_items')->count();
    $articlesAvant = DB::table('products')->count();
    expect($devisAvant)->toBeGreaterThan(0);

    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true, '--full' => true]);
    preg_match('/Empreinte du rapport : (\w{16})/', \Illuminate\Support\Facades\Artisan::output(), $m);

    $this->artisan("a3:reset-test-transactions --execute --full --confirmation=RESET-A3-FULL-TEST-{$m[1]}")
        ->assertFailed();

    // ROLLBACK : le transactionnel est intact malgré des suppressions déjà
    // effectuées à l'intérieur de la transaction — c'est tout l'intérêt de
    // n'employer ni TRUNCATE ni DDL.
    expect(DB::table('quotes')->count())->toBe($devisAvant);
    expect(DB::table('quote_items')->count())->toBe($lignesAvant);
    expect(DB::table('products')->count())->toBe($articlesAvant);
    expect(DB::table('fiscal_years')->where('is_current', true)->count())->toBe(0);
});

it('21. reste idempotente : une seconde exécution ne casse rien', function () {
    // Une purge qu'on ne peut lancer qu'une fois est une purge qu'on n'ose plus
    // relancer. La seconde passe doit trouver le terrain déjà net, ne rien
    // supprimer de plus, et surtout ne pas entamer le paramétrage.
    $societe = rttSociete();
    rttUtilisateur();
    $devis = rttDevis();
    rttPeuplerDomaines($societe, Client::findOrFail($devis->client_id));

    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true, '--full' => true]);
    preg_match('/Empreinte du rapport : (\w{16})/', \Illuminate\Support\Facades\Artisan::output(), $m);
    $this->artisan("a3:reset-test-transactions --execute --full --confirmation=RESET-A3-FULL-TEST-{$m[1]}")
        ->assertSuccessful();

    $parametrage = [
        'products' => DB::table('products')->count(),
        'users'    => DB::table('users')->count(),
        'accounts' => DB::table('accounts')->count(),
        'bills_of_materials' => DB::table('bills_of_materials')->count(),
    ];

    // Seconde passe : nouvelle empreinte, calculée sur une base déjà nette.
    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true, '--full' => true]);
    $sortie = \Illuminate\Support\Facades\Artisan::output();
    expect($sortie)->toContain('Total à supprimer : 0 lignes');
    preg_match('/Empreinte du rapport : (\w{16})/', $sortie, $m2);

    $this->artisan("a3:reset-test-transactions --execute --full --confirmation=RESET-A3-FULL-TEST-{$m2[1]}")
        ->assertSuccessful();

    foreach ($parametrage as $table => $attendu) {
        expect(DB::table($table)->count())->toBe($attendu, "{$table} entamée par la seconde passe");
    }
    expect(DB::table('quotes')->count())->toBe(0);
    expect(DB::table('fiscal_years')->where('is_current', true)->count())->toBe(1);
});

it('27. recalcule les soldes de trésorerie au lieu de les laisser mentir', function () {
    // Défaut constaté APRÈS la première remise à zéro réelle : les mouvements
    // de caisse étaient supprimés, mais `cash_accounts.current_balance` gardait
    // le solde qu'ils justifiaient. La caisse annonçait 136 280 F pour 35 400 F
    // de mouvements, la banque 5 020 F sans aucun mouvement — et trois écrans
    // affichaient ces chiffres sans broncher.
    //
    // Un solde faux est plus grave qu'une page en erreur : personne ne le
    // signale.
    $societe = rttSociete();
    rttUtilisateur();
    rttDevis();

    $caisse = DB::table('cash_accounts')->insertGetId([
        'company_id' => $societe->id, 'name' => 'Caisse RTT', 'code' => 'CAISSE-RTT',
        'type' => 'caisse', 'currency_code' => 'XOF',
        'opening_balance' => 1000, 'current_balance' => 999_999,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Un mouvement qui sera supprimé, portant lui aussi un cumul faux.
    DB::table('cash_transactions')->insert([
        'cash_account_id' => $caisse, 'type' => 'credit', 'amount' => 7000,
        'balance_after' => 999_999, 'label' => 'Mouvement RTT',
        'transaction_date' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    // Plus aucun mouvement : le solde retombe sur la seule valeur justifiable,
    // celle d'ouverture. Le compte demeure — c'est le solde qui est corrigé,
    // pas la caisse qui disparaît.
    expect(DB::table('cash_transactions')->count())->toBe(0);
    expect((float) DB::table('cash_accounts')->where('id', $caisse)->value('current_balance'))
        ->toBe(1000.0);
});

it('28. laisse un solde qu’un mouvement subsistant justifie', function () {
    // Revers du précédent : le recalcul ne doit pas tout ramener à l'ouverture.
    // Un mouvement conservé — parce que sa table n'est pas dans le périmètre —
    // doit continuer de porter le solde.
    $societe = rttSociete();
    rttUtilisateur();

    $caisse = DB::table('cash_accounts')->insertGetId([
        'company_id' => $societe->id, 'name' => 'Caisse RTT2', 'code' => 'CAISSE-RTT2',
        'type' => 'caisse', 'currency_code' => 'XOF',
        'opening_balance' => 500, 'current_balance' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $jeton = rttEmpreinte();
    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();

    // Sans mouvement, le solde vaut l'ouverture — 500, et non 0 comme la valeur
    // erronée qui y figurait.
    expect((float) DB::table('cash_accounts')->where('id', $caisse)->value('current_balance'))
        ->toBe(500.0);
});

it('22. exige une sauvegarde référencée hors base jetable', function () {
    // La garde ne peut pas être éprouvée en conditions réelles ici : la base de
    // test EST jetable, et l'exigence y est donc levée. C'est la règle
    // elle-même qui est vérifiée, sur les deux branches.
    expect(\App\Console\Commands\ResetTestTransactions::baseDeTest('iboa_erp_test'))->toBeTrue();
    expect(\App\Console\Commands\ResetTestTransactions::baseDeTest('iboa_erp'))->toBeFalse();

    // Et la base réelle reste autorisée, sans être jetable pour autant.
    expect(\App\Console\Commands\ResetTestTransactions::baseAutorisee('iboa_erp'))->toBeTrue();
});

it('23. lie l’empreinte à la sauvegarde et au périmètre, pas aux seuls compteurs', function () {
    // Un jeton ne doit pas survivre au changement de ce qui l'entoure : une
    // autre sauvegarde de secours, un autre périmètre. Sinon le rapport validé
    // ne décrit plus l'opération qui s'exécute.
    rttDevis();

    $empreinte = function (array $options) {
        \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true] + $options);
        preg_match('/Empreinte du rapport : (\w{16})/', \Illuminate\Support\Facades\Artisan::output(), $m);

        return $m[1];
    };

    $reference = $empreinte(['--full' => true, '--backup-ref' => 'sha-A']);

    // Même données, autre sauvegarde référencée.
    expect($empreinte(['--full' => true, '--backup-ref' => 'sha-B']))->not->toBe($reference);

    // Même données, même sauvegarde, périmètre réduit.
    expect($empreinte(['--backup-ref' => 'sha-A']))->not->toBe($reference);

    // Rejouée à l'identique, elle est stable — sans quoi aucun jeton ne
    // pourrait jamais être présenté.
    expect($empreinte(['--full' => true, '--backup-ref' => 'sha-A']))->toBe($reference);
});

it('24. détecte une modification qui ne change aucun compteur', function () {
    // Le piège que les compteurs seuls ne voient pas : autant de lignes
    // qu'avant, mais une valeur modifiée. `MAX(updated_at)` le trahit.
    $devis = rttDevis();

    $avant = rttEmpreinte();

    // Aucune ligne ajoutée ni supprimée — un montant corrigé, rien de plus.
    DB::table('quote_items')->where('quote_id', $devis->id)
        ->update(['unit_price' => 999, 'updated_at' => now()->addMinute()]);

    expect(DB::table('quotes')->count())->toBe(1);
    expect(rttEmpreinte())->not->toBe($avant);
});

it('25. n’exige la mise en maintenance que sur une base durable', function () {
    // La garde ne peut pas être éprouvée en basculant réellement l'application
    // au milieu d'une suite : c'est la règle qui est vérifiée, sur ses deux
    // branches.
    expect(\App\Console\Commands\ResetTestTransactions::exigeMaintenance('iboa_erp'))->toBeTrue();
    expect(\App\Console\Commands\ResetTestTransactions::exigeMaintenance('iboa_erp_test'))->toBeFalse();
    expect(\App\Console\Commands\ResetTestTransactions::exigeMaintenance('iboa_erp_test_resetfinal'))->toBeFalse();
    // Un banc d'essai de restauration est autorisé mais n'est PAS jetable au
    // sens des suites : l'exigence de maintenance y reste due.
    expect(\App\Console\Commands\ResetTestTransactions::exigeMaintenance('iboa_erp_final_restore_check'))->toBeTrue();
    expect(\App\Console\Commands\ResetTestTransactions::baseAutorisee('iboa_erp_final_restore_check'))->toBeTrue();
});

it('26. recalcule l’empreinte à l’intérieur de la transaction', function () {
    // Garde de code doublée d'une garde de comportement : le second calcul a
    // lieu APRÈS l'ouverture de la transaction. Le premier était fait hors
    // verrou, et une écriture pouvait se glisser entre les deux.
    $source = file_get_contents(app_path('Console/Commands/ResetTestTransactions.php'));

    $code = '';
    foreach (token_get_all($source) as $jeton) {
        if (is_array($jeton) && in_array($jeton[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($jeton) ? $jeton[1] : $jeton;
    }

    expect($code)->toContain('RESET REFUSÉ');
    // Le recalcul est bien DANS la fermeture passée à DB::transaction.
    expect($code)->toMatch('/DB::transaction\(function \(\) use \(\$avant, \$hash\) \{\s*\$verrouille = \$this->hachage/');
});

it('17. n’inclut la paie qu’avec --include-payroll', function () {
    // Le run de paie est rattaché à une écriture comptable qui, elle, sera
    // supprimée. L'inclure par défaut serait une décision de purge prise à la
    // place du métier ; l'exclure sans le dire laisserait un run comptabilisé
    // dont l'écriture n'existe plus. D'où l'option, et d'où ce test.
    $societe = rttSociete();
    rttUtilisateur();

    DB::table('payroll_periods')->insert([
        // `code` est un varchar(7) : un libellé plus long serait tronqué par
        // MySQL en mode strict, donc rejeté.
        'company_id' => $societe->id, 'code' => 'P-RTT-1', 'libelle' => 'Période RTT',
        'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Sans l'option : la période survit, et l'étape n'est pas même proposée.
    $jeton = rttEmpreinte();
    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', ['--audit' => true]);
    expect(\Illuminate\Support\Facades\Artisan::output())->not->toContain('Paie et RH transactionnel');

    $this->artisan("a3:reset-test-transactions --execute --confirmation=RESET-A3-TEST-{$jeton}")
        ->assertSuccessful();
    expect(DB::table('payroll_periods')->count())->toBe(1);

    // Avec l'option : l'étape apparaît, et la période part.
    \Illuminate\Support\Facades\Artisan::call('a3:reset-test-transactions', [
        '--audit' => true, '--include-payroll' => true,
    ]);
    $sortie = \Illuminate\Support\Facades\Artisan::output();
    expect($sortie)->toContain('Paie et RH transactionnel');
    expect($sortie)->toContain('payroll_periods');

    preg_match('/Empreinte du rapport : (\w{16})/', $sortie, $m);
    $this->artisan("a3:reset-test-transactions --execute --include-payroll --confirmation=RESET-A3-TEST-{$m[1]}")
        ->assertSuccessful();

    expect(DB::table('payroll_periods')->count())->toBe(0);
})->skip(fn () => ! Schema::hasTable('payroll_periods'), 'payroll_periods absente');
