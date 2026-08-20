<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Order;
use App\Services\ClientService;
use App\Services\CustomerCreditExposureService;
use App\Services\TestData\OrderTestClientsSpec as Spec;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [Données de test] Trois clients pour éprouver les modes de traitement d'une commande.
 *
 * Même doctrine que `a3:prepare-production-test-data` : `--audit` et `--dry-run`
 * n'écrivent rien, les champs structurants d'un client existant ne sont jamais
 * modifiés automatiquement, et ce qui est écarté est déclaré plutôt que tu.
 *
 * Ce que l'audit a établi et que cette commande affiche sans le maquiller : deux
 * des trois clients servent des scénarios que le logiciel ne sait pas encore
 * arbitrer. Ils sont créés quand même — le jeu de données sera prêt le jour où
 * les règles existeront — mais leurs scénarios restent marqués BLOQUÉS.
 */
class PrepareOrderTestClients extends Command
{
    protected $signature = 'a3:prepare-order-test-clients
        {--audit            : Inventaire des clients et de leurs dépendances}
        {--dry-run          : Plan complet — aucune écriture}
        {--execute          : Crée les trois clients et leur paramétrage}
        {--with-orders      : Ajoute les scénarios de commande réellement testables}
        {--reset-test-batch : Supprime les seules données du lot de test}';

    protected $description = 'Prépare trois clients de test : comptant, acompte, crédit.';

    public function handle(): int
    {
        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if ($this->option('reset-test-batch')) {
            return $this->resetTestBatch();
        }

        $rapport = $this->auditer();

        $this->rendreClients($rapport);
        $this->rendreEcarts($rapport);
        $this->rendreScenarios($rapport);

        if ($this->option('execute')) {
            return $this->executer($rapport);
        }

        $this->newLine();
        $this->line('Mode lecture seule — aucune donnée modifiée.');
        $this->afficherStatut($rapport);

        return self::SUCCESS;
    }

    /** §1 — refuse de travailler sur une base dont l'identité n'est pas établie. */
    private function preflight(): bool
    {
        $this->info('── Contrôles préalables ──────────────────────────────────────');

        $connexion = config('database.default');
        $base = (string) config("database.connections.{$connexion}.database");
        $env = (string) config('app.env');

        $lignes = [
            ['APP_NAME', config('app.name'), config('app.name') === 'A3 ERP'],
            ['APP_ENV', $env, in_array($env, ['local', 'testing', 'development'], true)],
            ['Connexion', $connexion, $connexion === 'mysql'],
            ['Base', $base, ! preg_match('/prod/i', $base)],
        ];

        $bloquant = false;
        foreach ($lignes as [$libelle, $valeur, $ok]) {
            $this->line(sprintf('  %-12s %-28s %s', $libelle, $valeur, $ok ? '[OK]' : '[REFUS]'));
            $bloquant = $bloquant || ! $ok;
        }

        $societe = DB::table('companies')->first();
        $this->line(sprintf('  Société      %-28s %s', $societe->name ?? 'INCONNUE', $societe ? '[OK]' : '[REFUS]'));
        $bloquant = $bloquant || ! $societe;

        $enAttente = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
            ->keys()->diff(app('migrator')->getRepository()->getRan())->count();
        $this->line(sprintf('  Migrations   %-28s %s', $enAttente.' en attente', $enAttente === 0 ? '[OK]' : '[REFUS]'));
        $bloquant = $bloquant || $enAttente > 0;

        if ($bloquant) {
            $this->newLine();
            $this->error('Contrôles préalables non satisfaits — exécution refusée.');

            return false;
        }

        $this->newLine();

        return true;
    }

    /** §2 + §8 — état réel de chaque client face à la spécification. */
    private function auditer(): array
    {
        $rapport = [];

        foreach (Spec::clients() as $code => $def) {
            $client = Client::withTrashed()->where('code', $code)->first();

            $ecarts = [];
            $dependances = [];

            if ($client) {
                foreach ($def['attendu'] as $champ => $attendu) {
                    $actuel = $client->{$champ} ?? null;
                    if ($this->equivalent($actuel, $attendu)) {
                        continue;
                    }
                    $structurant = in_array($champ, Spec::STRUCTURANTS, true);
                    $ecarts[] = [
                        'champ' => $champ, 'actuel' => $actuel, 'attendu' => $attendu,
                        'verdict' => $structurant ? 'conflit' : 'completable',
                    ];
                }

                $dependances = array_filter([
                    'devis' => $client->quotes()->count(),
                    'commandes' => $client->orders()->count(),
                    'factures' => $client->invoices()->count(),
                    'règlements' => $client->payments()->count(),
                    'avoirs' => $client->creditNotes()->count(),
                    'décisions de crédit' => $client->creditDecisions()->count(),
                ]);
            }

            $rapport[$code] = [
                'existe' => (bool) $client,
                'id' => $client?->id,
                'supprime' => $client?->trashed() ?? false,
                'testable' => $def['testable'],
                'blocage' => $def['blocage'],
                'attendu' => $def['attendu'],
                'scenarios' => $def['scenarios'],
                'montants' => $def['montants'] ?? [],
                'ecarts' => $ecarts,
                'dependances' => $dependances,
            ];
        }

        return $rapport;
    }

    private function rendreClients(array $rapport): void
    {
        $this->info('── Clients ───────────────────────────────────────────────────');

        $this->table(['Code', 'Raison sociale', 'Base', 'Mode', 'Ligne de crédit', 'Écarts', 'Scénarios'],
            collect($rapport)->map(fn ($r, $code) => [
                $code,
                $r['attendu']['name'],
                $r['existe'] ? '#'.$r['id'].($r['supprime'] ? ' (archivé)' : '') : 'à créer',
                $r['attendu']['payment_mode'],
                number_format((float) $r['attendu']['credit_limit'], 0, ',', ' ').' FCFA',
                count($r['ecarts']),
                $r['testable'] ? 'testables' : 'BLOQUÉS',
            ])->values()->all());

        $this->line('  Lot de test : '.Spec::BATCH);
        $this->line('  `clients` ne porte ni is_test ni test_batch : la référence va dans `notes`,');
        $this->line('  sans toucher à l’identité commerciale imprimée sur les documents.');
        $this->newLine();
    }

    private function rendreEcarts(array $rapport): void
    {
        $lignes = [];
        foreach ($rapport as $code => $r) {
            foreach ($r['ecarts'] as $e) {
                $lignes[] = [
                    $code,
                    Spec::LIBELLES[$e['champ']] ?? $e['champ'],
                    $this->valeur($e['actuel']),
                    $this->valeur($e['attendu']),
                    $r['dependances'] ? $this->resumer($r['dependances']) : 'aucune',
                    $e['verdict'] === 'conflit' ? 'PRÉSERVER' : 'Complétable',
                ];
            }
        }

        $this->info('── Écarts de paramétrage ─────────────────────────────────────');
        if (! $lignes) {
            $this->line('  Aucun écart : les codes sont libres ou déjà conformes.');
            $this->newLine();

            return;
        }
        $this->table(['Client', 'Champ', 'Actuel', 'Attendu', 'Dépendances', 'Décision'], $lignes);
        $this->newLine();
    }

    /** §12 — ce qui est réalisable et ce qui ne l'est pas, sans arrondir. */
    private function rendreScenarios(array $rapport): void
    {
        $this->info('── Scénarios ─────────────────────────────────────────────────');

        foreach ($rapport as $code => $r) {
            if ($r['testable']) {
                $this->line(sprintf('  <fg=green>%s</> — %d scénario(s) testable(s)', $code, count($r['scenarios'])));
                foreach ($r['scenarios'] as $s) {
                    $this->line('     ✓ '.$s);
                }

                continue;
            }

            $this->warn(sprintf('  %s — %d scénario(s) BLOQUÉS', $code, count($r['scenarios'])));
            $this->line('     Cause : '.$r['blocage']);
            foreach ($r['scenarios'] as $s) {
                $this->line('     · '.$s);
            }
        }
        $this->newLine();
    }

    /** §9 --execute : crée les clients par le SERVICE métier, jamais en SQL direct. */
    private function executer(array $rapport): int
    {
        $this->info('── Exécution ─────────────────────────────────────────────────');

        $service = app(ClientService::class);
        $cree = 0;
        $preserves = 0;

        foreach ($rapport as $code => $r) {
            if ($r['existe']) {
                // Doctrine : on ne réécrit pas un client existant. Les écarts ont
                // été affichés ; leur arbitrage n'appartient pas à un script de
                // préparation de jeu d'essai.
                $this->line(sprintf('  %-20s existant #%d — préservé', $code, $r['id']));
                $preserves++;

                continue;
            }

            $donnees = $r['attendu'] + [
                'code' => $code,
                'notes' => 'DONNÉE DE TEST — lot '.Spec::BATCH
                    ."\nCréé pour éprouver les modes de traitement d'une commande."
                    .($r['testable'] ? '' : "\nScénarios BLOQUÉS : ".$r['blocage']),
            ];

            $client = $service->create($donnees);
            $this->line(sprintf('  %-20s créé #%d', $code, $client->id));
            $cree++;
        }

        $this->newLine();
        $this->line(sprintf('  %d client(s) créé(s), %d préservé(s).', $cree, $preserves));

        if ($this->option('with-orders')) {
            $this->evaluerScenariosCredit($rapport);
        }

        $this->afficherStatut($rapport);

        return self::SUCCESS;
    }

    /**
     * §9 --with-orders : éprouve les scénarios de crédit par le service RÉEL.
     *
     * On n'enregistre aucune commande : `compute()` évalue l'encours prévisionnel
     * pour un montant hypothétique. Les trois cas de la mission se vérifient donc
     * sans laisser de documents commerciaux derrière eux, et sans risquer qu'un
     * jeu d'essai se retrouve un jour dans un état comptable.
     */
    private function evaluerScenariosCredit(array $rapport): void
    {
        $r = $rapport['CLT-TEST-CREDIT'] ?? null;
        $client = Client::where('code', 'CLT-TEST-CREDIT')->first();

        if (! $r || ! $client) {
            return;
        }

        $this->newLine();
        $this->info('── Scénarios de crédit évalués par CustomerCreditExposureService ──');

        $service = app(CustomerCreditExposureService::class);
        $societe = (int) DB::table('companies')->value('id');
        $lignes = [];

        foreach ($r['montants'] as $montant) {
            $e = $service->compute(
                companyId: $societe,
                clientId: $client->id,
                limit: (int) $client->credit_limit,
                isCredit: true,
                newOrderAmount: $montant,
            );

            $lignes[] = [
                number_format($montant, 0, ',', ' ').' FCFA',
                number_format($e['outstanding'], 0, ',', ' '),
                number_format($e['open_orders'], 0, ',', ' '),
                number_format($e['deposits'], 0, ',', ' '),
                number_format($e['projected'], 0, ',', ' '),
                number_format($e['limit'], 0, ',', ' '),
                $e['projected'] > $e['limit'] ? 'BLOQUÉE' : 'éligible',
            ];
        }

        $this->table(
            ['Nouvelle commande', 'Factures', 'Cmd ouvertes', 'Acomptes', 'Encours prévu', 'Plafond', 'Verdict'],
            $lignes
        );
        $this->line('  Aucune commande enregistrée : l’évaluation est faite sur des montants hypothétiques.');
    }

    /** §9 --reset-test-batch : purge du seul lot, et jamais d'un client réel. */
    private function resetTestBatch(): int
    {
        $this->info('── Purge du lot '.Spec::BATCH.' ──');

        $clients = Client::where('notes', 'like', '%'.Spec::BATCH.'%')->get();
        $this->line(sprintf('  %d client(s) portent la référence du lot.', $clients->count()));

        if ($clients->isEmpty()) {
            $this->line('  Rien à purger.');

            return self::SUCCESS;
        }

        $bloques = [];
        foreach ($clients as $c) {
            $deps = array_filter([
                'devis' => $c->quotes()->count(),
                'commandes' => $c->orders()->count(),
                'factures' => $c->invoices()->count(),
                'règlements' => $c->payments()->count(),
                'avoirs' => $c->creditNotes()->count(),
            ]);
            $this->line(sprintf('  %-20s #%-4d %s', $c->code, $c->id, $deps ? $this->resumer($deps) : 'aucune dépendance'));
            if ($deps) {
                $bloques[] = $c->code;
            }
        }

        if ($bloques) {
            $this->newLine();
            $this->error('Purge refusée — documents commerciaux rattachés : '.implode(', ', $bloques));
            $this->line('  Supprimer un client qui porte des devis, commandes, factures ou règlements');
            $this->line('  laisserait ces documents sans tiers. Traitez-les d’abord.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->error('Purge refusée — non ouverte tant que le plan n’a pas été validé.');

        return self::FAILURE;
    }

    private function afficherStatut(array $rapport): void
    {
        $conflits = collect($rapport)->sum(fn ($r) => collect($r['ecarts'])->where('verdict', 'conflit')->count());
        $bloques = collect($rapport)->where('testable', false)->count();

        $this->newLine();
        if ($conflits > 0) {
            $this->error('NO-GO — Conflit structurel ou paramétrage financier incomplet');

            return;
        }
        if ($bloques > 0) {
            $this->warn('GO PARTIEL — Certains scénarios nécessitent un paramétrage complémentaire');
            $this->line(sprintf('  %d client(s) sur %d attendent une règle métier qui n’existe pas encore.', $bloques, count($rapport)));

            return;
        }
        $this->info('GO — Trois clients prêts pour les tests de commande');
    }

    private function equivalent(mixed $a, mixed $b): bool
    {
        if (is_bool($b)) {
            return (bool) $a === $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-6;
        }

        return (string) $a === (string) $b;
    }

    private function valeur(mixed $v): string
    {
        return match (true) {
            $v === null => '—',
            is_bool($v) => $v ? 'oui' : 'non',
            $v === '' => '(vide)',
            default => (string) $v,
        };
    }

    private function resumer(array $deps): string
    {
        return collect($deps)->map(fn ($n, $k) => "{$n} {$k}")->implode(', ');
    }
}
