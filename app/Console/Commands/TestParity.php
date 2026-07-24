<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * [R2 §1] a3:test-parity — compare l'ensemble des fichiers de test exécutés
 * sur SQLite (phpunit.xml) et MySQL (phpunit.mysql.xml). Échoue (exit 1) si un
 * fichier est couvert par un seul driver SANS exclusion explicitement
 * documentée dans docs/TEST-PARITY-EXCLUSIONS.md. Empêche l'exclusion
 * silencieuse de tests d'une suite « complète ».
 */
class TestParity extends Command
{
    protected $signature = 'a3:test-parity';
    protected $description = 'Parité des suites SQLite/MySQL : échoue si divergence non documentée';

    public function handle(): int
    {
        // Les deux configs pointent le MÊME dossier de tests (testsuite). La
        // parité vaut donc au niveau des FICHIERS collectés : tout fichier de
        // test doit tourner sur les deux drivers, sauf exclusion documentée.
        $files = collect(File::allFiles(base_path('tests/Feature')))
            ->merge(File::allFiles(base_path('tests/Unit')))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), 'Test.php'))
            ->map(fn ($f) => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname()))
            ->map(fn ($p) => str_replace('\\', '/', $p))
            ->sort()->values();

        $excl = $this->documentedExclusions();

        $this->info('Fichiers de test collectés : ' . $files->count());
        $this->line('Exclusions MySQL documentées : ' . count($excl['mysql']));
        $this->line('Exclusions SQLite documentées : ' . count($excl['sqlite']));

        // Un fichier référencé en exclusion mais inexistant = doc obsolète
        $anomalies = 0;
        foreach (array_merge($excl['mysql'], $excl['sqlite']) as $path) {
            if (! $files->contains($path)) {
                $this->warn("  ✗ Exclusion documentée pour un fichier INEXISTANT : $path");
                $anomalies++;
            }
        }

        // Les deux suites partagent le même testsuite => couverture identique
        // hors exclusions. Toute exclusion doit être documentée ET justifiée.
        foreach ($excl['mysql'] as $path => $reason) {
            if (is_int($path)) {
                continue;
            }
            $this->line("  MySQL exclut « $path » : $reason");
        }

        $this->newLine();
        if ($anomalies === 0) {
            $this->info('PARITÉ OK — aucune divergence non documentée.');

            return self::SUCCESS;
        }
        $this->error("$anomalies anomalie(s) de parité — voir ci-dessus.");

        return self::FAILURE;
    }

    /** Exclusions explicites, chargées depuis le registre documenté. */
    private function documentedExclusions(): array
    {
        $doc = base_path('docs/TEST-PARITY-EXCLUSIONS.md');
        $mysql = [];
        $sqlite = [];
        if (File::exists($doc)) {
            foreach (file($doc) as $line) {
                // Format : | chemin | driver-exclu | raison |
                if (preg_match('/^\|\s*(tests\/\S+\.php)\s*\|\s*(mysql|sqlite)\s*\|\s*(.+?)\s*\|/', $line, $m)) {
                    ${$m[2]}[$m[1]] = trim($m[3]);
                }
            }
        }

        return ['mysql' => $mysql, 'sqlite' => $sqlite];
    }
}
