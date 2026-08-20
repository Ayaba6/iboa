<?php

namespace App\Console\Commands;

use App\Services\TestData\ProductionTestDataAuditor;
use App\Services\TestData\ProductionTestDataSpec;
use App\Services\TestData\ProductionTestDataWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Données de test MTS/MTO] Prépare le paramétrage articles et les stocks de test.
 *
 * DOCTRINE — arbitrée par le métier, appliquée sans exception :
 *
 *   « Une donnée métier existante ne doit jamais être remplacée uniquement pour
 *     faire passer un scénario de test. »
 *
 * D'où : `preserve` par défaut, champs structurants jamais écrits automatiquement,
 * et blocage de l'écriture dès qu'un conflit rend un scénario impossible. Mieux
 * vaut un test qui ne démarre pas qu'un historique réécrit pour qu'il démarre.
 *
 * `--audit` et `--dry-run` n'écrivent RIEN, dans aucune branche.
 */
class PrepareProductionTestData extends Command
{
    protected $signature = 'a3:prepare-production-test-data
        {--audit           : Inventaire de l’existant, sans comparaison de plan}
        {--dry-run         : Plan complet — aucune écriture}
        {--execute         : Applique le plan (soumis aux garde-fous)}
        {--module=all      : mts | mto | all}
        {--reset-test-batch : Supprime les seules données du lot de test}
        {--conflict=preserve : preserve | update | clone}
        {--exclude=         : Périmètres exclus, séparés par des virgules. Seule valeur reconnue : finished-goods-lots}';

    protected $description = 'Audite et prépare les données de test MTS (fer à béton) et MTO (tôle bac).';

    public function handle(ProductionTestDataAuditor $auditor): int
    {
        $module = (string) $this->option('module');
        if (! in_array($module, ['all', 'mts', 'mto'], true)) {
            $this->error('--module doit valoir mts, mto ou all.');

            return self::FAILURE;
        }

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if ($this->option('reset-test-batch')) {
            return $this->resetTestBatch();
        }

        $rapport = $auditor->audit($module);

        $this->rendreArticles($rapport);
        $this->rendreConflits($rapport);
        $this->rendreStocks($rapport);
        $this->rendreNomenclatures($rapport);
        $this->rendreHypotheses();

        $statut = $this->statutGlobal($rapport);

        if ($this->option('execute')) {
            $exclusions = $this->exclusions();
            if ($exclusions === null) {
                return self::FAILURE;
            }

            return $this->executer($rapport, $module, $exclusions);
        }

        $this->newLine();
        $this->line('Mode lecture seule — aucune donnée modifiée.');
        $this->afficherStatut($statut);

        return $statut === 'NO-GO' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * §1 — Refuse de travailler sur une base dont l'identité n'est pas établie.
     *
     * Ces contrôles ne coûtent rien et évitent la seule erreur qu'on ne peut pas
     * rattraper : écrire des données de test dans une base de production.
     */
    private function preflight(): bool
    {
        $this->info('── Contrôles préalables ──────────────────────────────────────');

        $connexion = config('database.default');
        $base = config("database.connections.{$connexion}.database");
        $env = config('app.env');

        $lignes = [
            ['APP_NAME', config('app.name'), config('app.name') === 'A3 ERP'],
            ['APP_ENV', $env, in_array($env, ['local', 'testing', 'development'], true)],
            ['Connexion', $connexion, $connexion === 'mysql'],
            ['Base', $base, ! preg_match('/prod/i', (string) $base)],
        ];

        $bloquant = false;
        foreach ($lignes as [$libelle, $valeur, $ok]) {
            $this->line(sprintf('  %-12s %-28s %s', $libelle, $valeur, $ok ? '[OK]' : '[REFUS]'));
            $bloquant = $bloquant || ! $ok;
        }

        try {
            DB::connection()->getPdo();
            $this->line('  Connexion    établie                      [OK]');
        } catch (\Throwable $e) {
            $this->error('  Connexion MySQL indisponible : '.$e->getMessage());

            return false;
        }

        // Une migration en attente signifie que le schéma lu n'est pas le schéma
        // cible : tout plan bâti dessus serait périmé avant d'être appliqué.
        $enAttente = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
            ->keys()
            ->diff(app('migrator')->getRepository()->getRan())
            ->count();
        $this->line(sprintf('  Migrations   %d en attente %s', $enAttente, $enAttente === 0 ? '                [OK]' : '[REFUS]'));
        $bloquant = $bloquant || $enAttente > 0;

        $societes = DB::table('companies')->count();
        $societe = DB::table('companies')->first();
        $this->line(sprintf('  Société      %s %s',
            $societe->name ?? 'INCONNUE',
            $societes === 1 ? '                     [OK]' : "({$societes} sociétés) [ATTENTION]"));
        $bloquant = $bloquant || ! $societe;

        if ($bloquant) {
            $this->newLine();
            $this->error('Contrôles préalables non satisfaits — exécution refusée.');

            return false;
        }

        $this->newLine();

        return true;
    }

    /** §2/§9 — Ce que la base contient face à ce que la campagne attend. */
    private function rendreArticles(array $rapport): void
    {
        $this->info('── Articles ──────────────────────────────────────────────────');

        $rows = [];
        foreach ($rapport as $code => $a) {
            $rows[] = [
                $code,
                $a['existe'] ? '#'.$a['id'] : 'ABSENT',
                $a['module'],
                $a['existe'] ? ($a['transactionne'] ? 'oui' : 'non') : '-',
                count($a['ecarts']),
                $this->badge($a['statut']),
            ];
        }
        $this->table(['Code', 'Base', 'Module', 'Transactionné', 'Écarts', 'Statut'], $rows);

        $absents = array_keys(array_filter($rapport, fn ($a) => ! $a['existe']));
        $this->line(sprintf('  %d article(s) analysé(s), %d absent(s)%s',
            count($rapport), count($absents), $absents ? ' : '.implode(', ', $absents) : ''));
        $this->newLine();
    }

    /** §8 du protocole de conflit — le tableau exigé, colonne par colonne. */
    private function rendreConflits(array $rapport): void
    {
        $rows = [];
        foreach ($rapport as $code => $a) {
            foreach ($a['ecarts'] as $e) {
                $deps = $a['dependances'] ?? [];
                $rows[] = [
                    $code,
                    ProductionTestDataSpec::LIBELLES[$e['champ']] ?? $e['champ'],
                    $this->valeur($e['actuel']),
                    $this->valeur($e['attendu']),
                    $deps ? $this->resumerDependances($deps) : 'aucune',
                    strtoupper($e['risque']),
                    $this->decision($e),
                ];
            }
        }

        $this->info('── Écarts de paramétrage ─────────────────────────────────────');
        if (! $rows) {
            $this->line('  Aucun écart : la base correspond déjà à la campagne.');
            $this->newLine();

            return;
        }

        $this->table(['Article', 'Champ', 'Actuel', 'Attendu', 'Dépendances', 'Risque', 'Décision'], $rows);
        $this->newLine();
    }

    /** §9 — stocks actuels et stocks que le plan créerait. */
    private function rendreStocks(array $rapport): void
    {
        $this->info('── Stocks ────────────────────────────────────────────────────');

        $rows = [];
        foreach ($rapport as $code => $a) {
            if (! $a['existe']) {
                continue;
            }
            $cible = $a['stock_cible'] ?? [];
            $actuel = $a['stock_actuel'] ?? ['total' => 0, 'lots' => [], 'bobines' => []];

            $aCreer = match (true) {
                ! empty($cible['lots']) => count($cible['lots']).' lot(s) — '.$cible['quantite'].' '.($cible['unite'] ?? ''),
                ! empty($cible['bobines']) => count($cible['bobines']).' bobine(s) — '.$cible['quantite'].' ML'
                    .' ('.number_format((float) ($cible['poids_attendu'] ?? 0), 3, ',', ' ').' kg)',
                default => 'aucun (stock nul volontaire)',
            };

            $rows[] = [
                $code,
                number_format($actuel['total'], 4, ',', ' '),
                count($actuel['lots']),
                count($actuel['bobines']),
                $a['depot_cible'].($a['depot_existe'] ? '' : ' [A CREER]'),
                $aCreer,
            ];
        }
        $this->table(['Article', 'Stock actuel', 'Lots', 'Bobines', 'Dépôt cible', 'Plan'], $rows);

        // Le stock préexistant est le seul endroit où test et réel peuvent se
        // mélanger sans qu'on s'en aperçoive : on le détaille toujours.
        foreach ($rapport as $code => $a) {
            $actuel = $a['stock_actuel'] ?? null;
            if (! $actuel || ($actuel['total'] <= 0 && ! $actuel['lots'] && ! $actuel['bobines'])) {
                continue;
            }
            $this->warn("  {$code} porte déjà du stock :");
            foreach ($actuel['lots'] as $l) {
                $this->line(sprintf('     lot %-24s %8s %-4s source=%s', $l['lot'], $l['quantite'], $l['uom'] ?? '?', $l['source'] ?? '-'));
            }
            foreach ($actuel['bobines'] as $b) {
                $this->line(sprintf('     bobine %-24s %8s kg / %s ML  couleur=%s ep=%s statut=%s',
                    $b['reference'], $b['poids'], $b['metres'], $b['couleur'] ?: 'VIDE', $b['epaisseur'] ?: '-', $b['statut']));
            }
        }
        $this->newLine();
    }

    private function rendreNomenclatures(array $rapport): void
    {
        $this->info('── Nomenclatures ─────────────────────────────────────────────');
        $rows = [];
        foreach ($rapport as $code => $a) {
            $n = $a['nomenclature'] ?? null;
            if (! $n) {
                continue;
            }
            if (! $n['existe']) {
                $p = $n['propose'] ?? null;
                // Une matière première n'a pas de nomenclature : l'annoncer
                // « à créer » inventerait un manque là où il n'y a rien à créer.
                if (! $p) {
                    continue;
                }
                $rows[] = [$code, 'ABSENTE', $p['composant'].' × '.$p['quantite'], 'à créer'];

                continue;
            }
            $l = $n['lignes'][0] ?? null;
            $rows[] = [
                $code, $n['code'],
                $l ? $l['composant'].' × '.$l['quantite'].' (perte '.$l['perte'].' %)' : '-',
                $n['ecart_vs_propose'] ?? 'conforme au mapping',
            ];
        }
        $rows ? $this->table(['Produit', 'Nomenclature', 'Composant', 'Écart vs mapping proposé'], $rows)
              : $this->line('  Aucun produit fabriqué dans le périmètre retenu.');
        $this->newLine();
    }

    private function rendreHypotheses(): void
    {
        $this->info('── Hypothèses appliquées ─────────────────────────────────────');
        $this->line(sprintf('  Largeur bobine    %s mm — absente de l’article ET des nomenclatures.', ProductionTestDataSpec::LARGEUR_HYPOTHESE_MM));
        $this->line('                    Valeur de test, à ne jamais propager au réel sans validation.');
        $this->line('  Nuance SAE 1008   aucune colonne sur products : invérifiable côté article.');
        $this->line('                    Elle n’existe que sur coils, donc portée par la bobine seule.');
        $this->line('  Diamètre          stocké dans `thickness`, colonne nommée « épaisseur ».');
        $this->line('                    Détournement constaté en base, non introduit ici.');
        $this->line(sprintf('  Lot de test       %s', ProductionTestDataSpec::BATCH));
        $this->line(sprintf('  Dépôt de test     %s — sépare le test du stock réel.', ProductionTestDataSpec::TEST_WAREHOUSE_CODE));
        $this->newLine();
    }

    private function statutGlobal(array $rapport): string
    {
        $bloquants = 0;
        $conflits = 0;
        foreach ($rapport as $a) {
            if ($a['statut'] === 'conflit_bloquant') {
                $bloquants++;
            }
            if ($a['statut'] === 'conflit') {
                $conflits++;
            }
        }

        return match (true) {
            $bloquants > 0 => 'NO-GO',
            $conflits > 0 => 'GO PARTIEL',
            default => 'GO',
        };
    }

    private function afficherStatut(string $statut): void
    {
        match ($statut) {
            'GO' => $this->info('GO — Aucun conflit bloquant'),
            'GO PARTIEL' => $this->warn('GO PARTIEL — Données créables sauf articles en conflit'),
            default => $this->error('NO-GO — Paramétrages structurants à arbitrer avant les tests'),
        };
    }

    /**
     * Lit --exclude et refuse toute valeur inconnue.
     *
     * Une exclusion mal orthographiée doit faire échouer bruyamment : silencieuse,
     * elle écrirait le périmètre qu'on croyait avoir écarté.
     *
     * @return list<string>|null  null en cas de valeur refusée
     */
    private function exclusions(): ?array
    {
        $brut = trim((string) $this->option('exclude'));
        if ($brut === '') {
            return [];
        }

        $demandees = array_filter(array_map('trim', explode(',', $brut)));
        $inconnues = array_diff($demandees, ProductionTestDataWriter::EXCLUSIONS);

        if ($inconnues) {
            $this->error('Exclusion inconnue : '.implode(', ', $inconnues));
            $this->line('  Valeurs reconnues : '.implode(', ', ProductionTestDataWriter::EXCLUSIONS));

            return null;
        }

        return array_values($demandees);
    }

    /**
     * Exécution du périmètre autorisé.
     *
     * Les produits finis MTS restent à zéro dans TOUS les cas : `--exclude` rend
     * l'intention explicite dans la trace, il ne débloque rien qui serait ouvert
     * sans lui. Créer un lot de produit fini sans sortie de production réelle
     * fabriquerait une traçabilité que le logiciel ne sait pas encore produire.
     */
    private function executer(array $rapport, string $module, array $exclusions): int
    {
        $this->info('── Exécution ─────────────────────────────────────────────────');
        $this->line('  Module    : '.$module);
        $this->line('  Conflits  : '.$this->option('conflict').' (les structurants sont préservés)');
        $this->line('  Exclusions: '.($exclusions ? implode(', ', $exclusions) : 'aucune déclarée'));
        $this->newLine();

        $writer = app(ProductionTestDataWriter::class);
        $journal = $writer->executer($rapport, $module, $exclusions);

        $this->rendreJournal($journal);
        $this->rendreControles($writer->controles());

        $this->newLine();
        $this->warn('GO PARTIEL — Matières, bobines, référentiels et paramètres préparés.');
        $this->error('BLOQUÉ — Lots de produits finis MTS en attente de l’implémentation R12.');

        return self::SUCCESS;
    }

    private function rendreJournal(array $j): void
    {
        if ($j['depot']) {
            $this->line(sprintf('  Dépôt %s #%d — %s', $j['depot']['code'], $j['depot']['id'], $j['depot']['action']));
        }
        if ($j['emplacements']) {
            $this->line('  Emplacements créés : '.implode(', ', $j['emplacements']));
        }
        $this->newLine();

        if ($j['sous_produits']) {
            $this->info('  Sous-produits — dépendances et décision');
            $this->table(
                ['Code', 'Devis', 'Commandes', 'BL', 'Factures', 'Mouvements', 'Avant', 'Après', 'Décision'],
                array_map(fn ($s) => [
                    $s['code'], $s['devis'], $s['commandes'], $s['bl'], $s['factures'],
                    $s['mouvements'], $s['avant'], $s['apres'], $s['decision'],
                ], $j['sous_produits'])
            );
        }

        if ($j['articles']) {
            $this->info('  Champs complétés (faible risque uniquement)');
            $this->table(['Article', 'Champ', 'Avant', 'Après'], array_map(fn ($a) => [
                $a['code'], ProductionTestDataSpec::LIBELLES[$a['champ']] ?? $a['champ'],
                $this->valeur($a['avant']), $this->valeur($a['apres']),
            ], $j['articles']));
        }

        if ($j['lots']) {
            $this->info('  Lots de matière créés');
            $this->table(['Article', 'Lot', 'Quantité', 'Unité', 'Dépôt', 'Mouvement', 'Nouveau'],
                array_map(fn ($l) => [
                    $l['article'], $l['lot'], $l['quantite'], $l['unite'],
                    $l['depot'], $l['mouvement'], $l['nouveau'] ? 'oui' : 'déjà présent',
                ], $j['lots']));
        }

        if ($j['bobines']) {
            $this->info('  Bobines physiques créées');
            $this->table(['Article', 'Référence', 'ML', 'kg', 'Nouveau'], array_map(fn ($b) => [
                $b['article'], $b['reference'], $b['metres'], $b['poids'],
                $b['nouveau'] ? 'oui' : 'déjà présente',
            ], $j['bobines']));
        }

        if ($j['exclus']) {
            $this->warn('  Écarté de l’écriture');
            foreach ($j['exclus'] as [$code, $raison]) {
                $this->line(sprintf('     %-12s %s', $code, $raison));
            }
        }

        if ($j['preserves']) {
            $this->warn(sprintf('  %d paramétrage(s) structurant(s) préservé(s) — non modifiés', count($j['preserves'])));
        }
    }

    private function rendreControles(array $c): void
    {
        if (! $c) {
            return;
        }
        $this->newLine();
        $this->info('── Contrôles finaux ──────────────────────────────────────────');
        $this->table(['Contrôle', 'Mesuré', 'Attendu'], [
            ['Fil machine (dépôt de test)', number_format($c['fil_machine_kg'], 2, ',', ' ').' kg', '27 000,00 kg'],
            ['Bobines prélaquées', number_format($c['bobines_ml'], 2, ',', ' ').' ML', '4 000,00 ML'],
            ['Poids des bobines', number_format($c['bobines_kg'], 3, ',', ' ').' kg', '8 680,000 kg'],
            ['Produits finis MTS', number_format($c['produits_finis'], 2, ',', ' '), '0,00 (volontaire)'],
            ['Stock négatif', $c['stock_negatif'], '0'],
            ['Mouvements du lot de test', $c['mouvements_lot_test'], '26'],
        ]);
    }

    /** Purge du seul lot de test — jamais des données réelles. */
    private function resetTestBatch(): int
    {
        $batch = ProductionTestDataSpec::BATCH;
        $this->info("── Purge du lot de test {$batch} ──");

        if (! Schema::hasColumn('stock_movements', 'idempotency_key')) {
            $this->error('Colonne idempotency_key absente : le lot de test n’est pas traçable.');

            return self::FAILURE;
        }

        $n = DB::table('stock_movements')->where('idempotency_key', 'like', $batch.'%')->count();
        $this->line("  {$n} mouvement(s) portent la référence du lot de test.");

        if ($n === 0) {
            $this->line('  Rien à purger.');

            return self::SUCCESS;
        }

        $this->error('Purge refusée — non ouverte tant que le plan n’a pas été validé.');
        $this->line('  Supprimer des mouvements de stock exige de vérifier au préalable');
        $this->line('  qu’aucune consommation, aucun OF ni aucune écriture n’en dépend.');

        return self::FAILURE;
    }

    private function badge(string $statut): string
    {
        return match ($statut) {
            'conforme' => 'conforme',
            'completable' => 'complétable',
            'conflit' => 'CONFLIT',
            'conflit_bloquant' => 'CONFLIT BLOQUANT',
            default => strtoupper($statut),
        };
    }

    private function decision(array $e): string
    {
        return match ($e['verdict']) {
            'completable' => 'Complétable',
            'impossible' => 'Impossible (schéma)',
            default => $e['risque'] === 'eleve' ? 'PRÉSERVER' : 'Arbitrage requis',
        };
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

    private function resumerDependances(array $deps): string
    {
        $parts = [];
        foreach ($deps as $libelle => $n) {
            $parts[] = "{$n} {$libelle}";
        }

        return implode(', ', array_slice($parts, 0, 3)).(count($parts) > 3 ? ', …' : '');
    }
}
