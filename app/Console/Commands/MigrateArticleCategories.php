<?php

namespace App\Console\Commands;

use App\Models\ItemCategory;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [X3 §19] Rattache les articles existants à leur catégorie de gestion et
 * répare les axes statistiques morts (famille1..3 pointant sur une famille
 * soft-supprimée). Dry-run par défaut ; --fix pour appliquer (transaction).
 */
class MigrateArticleCategories extends Command
{
    protected $signature = 'articles:migrate-categories {--fix : Applique les changements (défaut : dry-run)}';

    protected $description = 'Mappe les articles existants sur les catégories X3 + répare les axes statistiques morts';

    public function handle(): int
    {
        $cats = ItemCategory::pluck('id', 'code');
        if ($cats->isEmpty()) {
            $this->error('Aucune catégorie — lancer d\'abord ItemCategorySeeder.');

            return self::FAILURE;
        }

        $deadFamilyIds = DB::table('product_families')->whereNotNull('deleted_at')->pluck('id')->all();

        $plan = [];
        foreach (Product::withTrashed()->get() as $p) {
            // Mapping catégorie : priorité au comportement réel de l'article.
            $code = match (true) {
                $p->production_mode === 'mto'                                   => 'PF_TOLE_MTO',
                $p->production_mode === 'mts' && $p->type_article !== 'produit_fini' => 'SOUS_PRODUIT',
                $p->production_mode === 'mts'                                   => 'PF_FER_MTS',
                $p->type === 'service' || $p->type_article === 'service'        => ($p->is_sellable ? 'SERVICE_VENTE' : 'SERVICE_ACHAT'),
                str_contains(mb_strtolower($p->name), 'avarie')                 => 'REBUT',
                str_contains(mb_strtolower($p->name), 'chute')                  => 'SOUS_PRODUIT',
                $p->type_article === 'matiere_premiere' && str_contains(mb_strtolower($p->name), 'bobine') => 'MP_BOBINE',
                $p->type_article === 'matiere_premiere'                         => 'MP_STANDARD',
                $p->type_article === 'consommable'                              => 'CONSOMMABLE',
                $p->type_article === 'marchandise'                              => 'MARCHANDISE',
                $p->type_article === 'produit_fini'                             => 'MARCHANDISE', // fini non fabriqué = négoce
                default                                                          => 'MARCHANDISE',
            };

            $axesToClear = [];
            foreach (['famille1_id', 'famille2_id', 'famille3_id'] as $axe) {
                if ($p->$axe && in_array($p->$axe, $deadFamilyIds)) {
                    $axesToClear[] = $axe;
                }
            }

            if ($p->item_category_id !== ($cats[$code] ?? null) || $axesToClear) {
                $plan[] = ['product' => $p, 'code' => $code, 'clear' => $axesToClear];
            }
        }

        $this->table(['Article', 'Catégorie cible', 'Axes morts à vider'],
            collect($plan)->map(fn ($r) => [
                '#' . $r['product']->id . ' ' . mb_substr($r['product']->name, 0, 40),
                $r['code'],
                implode(',', $r['clear']) ?: '—',
            ])->all());
        $this->info(count($plan) . ' article(s) à mettre à jour.');

        if (! $this->option('fix')) {
            $this->warn('Dry-run — rien modifié. Relancer avec --fix pour appliquer.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $cats) {
            foreach ($plan as $r) {
                $data = ['item_category_id' => $cats[$r['code']]];
                foreach ($r['clear'] as $axe) {
                    $data[$axe] = null;
                }
                $r['product']->update($data);
            }
        });
        $this->info('Appliqué : ' . count($plan) . ' article(s) mis à jour (transaction).');

        return self::SUCCESS;
    }
}
