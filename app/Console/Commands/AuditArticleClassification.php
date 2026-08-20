<?php

namespace App\Console\Commands;

use App\Services\CategoryDefaultsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [Référentiel articles] Confronte le type d'article à la nature de sa catégorie.
 *
 * Deux classifications coexistent, et c'est voulu : `item_categories.nature`
 * décrit finement (9 valeurs), `products.type_article` décrit le FLUX (5
 * valeurs). Une chute est de nature « sous_produit » et de type « produit fini »
 * — elle entre en stock comme une sortie valorisable. Ce n'est pas une
 * incohérence, c'est la correspondance établie par
 * CategoryDefaultsService::natureToTypeArticle().
 *
 * Ce qui EST une anomalie : un article sans type alors que sa catégorie le
 * détermine, ou un type qui contredit cette correspondance sans raison visible.
 * Le premier cas s'est produit sur l'article le plus utilisé de la base —
 * celui que les six ordres de fabrication produisent — parce qu'il avait été
 * créé hors de ProductService, seul chemin portant l'héritage.
 *
 * Lecture seule. Un type divergent peut être un choix métier délibéré : on le
 * signale, on ne le réécrit pas. Le filet posé sur le modèle Product empêche
 * désormais le cas « type vide », mais ne touche jamais un type déjà rempli.
 */
class AuditArticleClassification extends Command
{
    protected $signature = 'a3:audit-article-classification {--company= : Restreindre à une société}';

    protected $description = 'Confronte le type d’article à la nature de sa catégorie (lecture seule).';

    public function handle(): int
    {
        $this->info('[Référentiel articles] Type d’article ↔ nature de la catégorie');
        $this->newLine();

        $articles = DB::table('products as p')
            ->leftJoin('item_categories as c', 'c.id', '=', 'p.item_category_id')
            ->whereNull('p.deleted_at')
            ->when($this->option('company'), fn ($q, $v) => $q->where('p.company_id', $v))
            ->orderBy('p.reference')
            ->get(['p.id', 'p.reference', 'p.name', 'p.type_article', 'p.item_category_id',
                'c.code as cat_code', 'c.nature']);

        $sansCategorie = [];
        $sansType      = [];
        $divergents    = [];

        foreach ($articles as $a) {
            $nom = mb_strimwidth((string) $a->name, 0, 32, '…');

            // Sans catégorie, rien ne peut être déduit : ce n'est pas une
            // divergence, c'est une absence de référentiel.
            if (! $a->item_category_id || ! $a->nature) {
                $sansCategorie[] = [$a->reference ?: '#'.$a->id, $nom, $a->type_article ?: '—'];

                continue;
            }

            $attendu = CategoryDefaultsService::natureToTypeArticle($a->nature);

            if (empty($a->type_article)) {
                $sansType[] = [$a->reference ?: '#'.$a->id, $nom, $a->cat_code, $a->nature, $attendu];

                continue;
            }

            if ($a->type_article !== $attendu) {
                $divergents[] = [$a->reference ?: '#'.$a->id, $nom, $a->cat_code, $a->nature,
                    $a->type_article, $attendu];
            }
        }

        $anomalies = 0;

        $this->line('  <options=bold>1. Articles sans type, alors que la catégorie le détermine</>');
        if ($sansType === []) {
            $this->info('     Aucun.');
        } else {
            $anomalies += count($sansType);
            $this->table(['Référence', 'Désignation', 'Catégorie', 'Nature', 'Type attendu'], $sansType);
            $this->line('     <fg=gray>Créés hors de ProductService — le filet du modèle empêche désormais ce cas.</>');
        }
        $this->newLine();

        $this->line('  <options=bold>2. Types divergents de la correspondance</>');
        if ($divergents === []) {
            $this->info('     Aucun.');
        } else {
            $anomalies += count($divergents);
            $this->table(['Référence', 'Désignation', 'Catégorie', 'Nature', 'Type actuel', 'Attendu'], $divergents);
            $this->line('     <fg=gray>Peut être un choix délibéré : rien n’est réécrit.</>');
        }
        $this->newLine();

        $this->line('  <options=bold>3. Articles sans catégorie</>');
        if ($sansCategorie === []) {
            $this->info('     Aucun.');
        } else {
            $anomalies += count($sansCategorie);
            $this->table(['Référence', 'Désignation', 'Type'], $sansCategorie);
        }
        $this->newLine();

        if ($anomalies > 0) {
            $this->warn(sprintf('  %d article(s) signalé(s) sur %d.', $anomalies, $articles->count()));

            return self::FAILURE;
        }

        $this->info(sprintf('  %d articles — tous classés conformément à leur catégorie.', $articles->count()));

        return self::SUCCESS;
    }
}
