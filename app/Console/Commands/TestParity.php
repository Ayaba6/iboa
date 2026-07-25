<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * a3:test-parity - compare the full collected test identifiers for SQLite and
 * MySQL, not just the test files.
 */
class TestParity extends Command
{
    protected $signature = 'a3:test-parity {--report= : Chemin du rapport JSON}';
    protected $description = 'Parite SQLite/MySQL sur les identifiants de tests individuels';

    public function handle(): int
    {
        $sha = $this->gitHeadSha();
        $dirty = $this->isGitDirty();
        $exclusions = $this->documentedExclusions();

        $sqlite = $this->collectTests('sqlite', 'phpunit.xml', $exclusions['sqlite']);
        $mysql = $this->collectTests('mysql', 'phpunit.mysql.xml', $exclusions['mysql']);

        $sqliteOnly = array_values(array_diff($sqlite['ids'], $mysql['ids']));
        $mysqlOnly = array_values(array_diff($mysql['ids'], $sqlite['ids']));
        sort($sqliteOnly);
        sort($mysqlOnly);

        $report = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'git_sha' => $sha,
                'git_dirty' => $dirty,
                'sqlite_config' => 'phpunit.xml',
                'mysql_config' => 'phpunit.mysql.xml',
            ],
            'sqlite' => $sqlite,
            'mysql' => $mysql,
            'diff' => [
                'sqlite_only' => $sqliteOnly,
                'mysql_only' => $mysqlOnly,
                'matches' => $sqliteOnly === [] && $mysqlOnly === [],
            ],
            'documented_exclusions' => $exclusions,
            'explanation' => [
                'legacy_file_count' => $sqlite['file_count'],
                'legacy_count_dimension' => 'test_files',
                'legacy_count_formula' => 'nombre de fichiers de test uniques derives des identifiants collectes',
                'legacy_behavior' => 'L ancienne version comparait les fichiers de tests collectes.',
                'current_behavior' => 'La version actuelle compare les identifiants individuels des tests collectes par Pest.',
                'why_counts_differ' => '882 represente les tests individuels collectes, 201 represente les fichiers de test uniques contenant ces tests.',
            ],
        ];

        $reportPath = $this->writeReport($report);

        $this->info('SHA commun collecte : ' . $sha . ($dirty ? ' (arbre sale)' : ''));
        $this->line('Tests SQLite collectes : ' . $sqlite['count']);
        $this->line('Tests MySQL collectes : ' . $mysql['count']);
        $this->line('Fichiers de test collectes (ancienne logique) : ' . $sqlite['file_count']);
        $this->line('Empreinte SQLite : ' . $sqlite['fingerprint']);
        $this->line('Empreinte MySQL : ' . $mysql['fingerprint']);
        $this->line('Exclusions SQLite documentees : ' . count($exclusions['sqlite']));
        $this->line('Exclusions MySQL documentees : ' . count($exclusions['mysql']));
        $this->line('Rapport : ' . $reportPath);

        if ($sqliteOnly === [] && $mysqlOnly === []) {
            $this->info('PARITE OK - aucune divergence d identifiants de tests.');

            return self::SUCCESS;
        }

        foreach ($sqliteOnly as $id) {
            $this->warn('SQLite uniquement : ' . $id);
        }
        foreach ($mysqlOnly as $id) {
            $this->warn('MySQL uniquement : ' . $id);
        }

        $this->error('PARITE EN ECHEC - voir le rapport JSON pour la diff complete.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, string>  $excludedFiles
     * @return array{driver:string,count:int,file_count:int,fingerprint:string,ids:array<int,string>,files:array<int,string>,excluded_files:array<int,string>,raw_count:int}
     */
    private function collectTests(string $driver, string $configuration, array $excludedFiles): array
    {
        $tmpDir = storage_path('framework/testing/parity');
        File::ensureDirectoryExists($tmpDir);
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . "parity-{$driver}-" . Str::uuid() . '.xml';

        $process = new Process([
            PHP_BINARY,
            base_path('vendor/bin/pest'),
            '-c',
            base_path($configuration),
            '--list-tests-xml',
            $tmpPath,
        ], base_path());
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Collecte de parite {$driver} en echec : " . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if (! File::exists($tmpPath)) {
            throw new \RuntimeException("Collecte de parite {$driver} : fichier XML introuvable ({$tmpPath}).");
        }

        $xml = simplexml_load_file($tmpPath);
        if ($xml === false) {
            File::delete($tmpPath);
            throw new \RuntimeException("Collecte de parite {$driver} : XML illisible.");
        }

        $xml->registerXPathNamespace('t', 'https://xml.phpunit.de/testSuite');
        $nodes = $xml->xpath('//t:testMethod') ?: [];

        $ids = collect($nodes)
            ->map(fn ($node) => (string) $node['id'])
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rawCount = $ids->count();

        $filteredIds = $ids
            ->reject(fn (string $id) => isset($excludedFiles[$this->deriveFilePathFromTestId($id)]))
            ->values();

        $files = $filteredIds
            ->map(fn (string $id) => $this->deriveFilePathFromTestId($id))
            ->unique()
            ->sort()
            ->values();

        File::delete($tmpPath);

        return [
            'driver' => $driver,
            'raw_count' => $rawCount,
            'count' => $filteredIds->count(),
            'file_count' => $files->count(),
            'fingerprint' => hash('sha256', implode("\n", $filteredIds->all())),
            'ids' => $filteredIds->all(),
            'files' => $files->all(),
            'excluded_files' => array_keys($excludedFiles),
        ];
    }

    private function deriveFilePathFromTestId(string $id): string
    {
        $class = Str::before($id, '::');
        $class = preg_replace('/^P\\\\/', '', $class) ?: $class;
        $class = preg_replace('/^Tests\\\\/', 'tests\\\\', $class) ?: $class;

        return str_replace('\\', '/', $class) . '.php';
    }

    /**
     * @return array{mysql: array<string,string>, sqlite: array<string,string>}
     */
    private function documentedExclusions(): array
    {
        $doc = base_path('docs/TEST-PARITY-EXCLUSIONS.md');
        $mysql = [];
        $sqlite = [];

        if (File::exists($doc)) {
            foreach (file($doc) as $line) {
                if (preg_match('/^\|\s*(tests\/\S+\.php)\s*\|\s*(mysql|sqlite)\s*\|\s*(.+?)\s*\|/', $line, $m)) {
                    ${$m[2]}[$m[1]] = trim($m[3]);
                }
            }
        }

        return ['mysql' => $mysql, 'sqlite' => $sqlite];
    }

    private function gitHeadSha(): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    private function isGitDirty(): bool
    {
        $process = new Process(['git', 'status', '--short'], base_path());
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(array $report): string
    {
        $path = (string) ($this->option('report') ?: storage_path('logs/test-parity-' . now()->format('Ymd-His') . '.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
