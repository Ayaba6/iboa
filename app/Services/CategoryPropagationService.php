<?php

namespace App\Services;

use App\Models\ItemCategory;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [X3 §8 — Propagation contrôlée] Une modification de catégorie ne s'applique
 * par défaut qu'aux FUTURS articles. Cette action distincte propage des champs
 * CHOISIS vers les articles existants de la catégorie :
 *  - aperçu (diff par article, valeurs personnalisées signalées) avant exécution ;
 *  - liste noire absolue : prix, stocks, unités historiques, comptes déjà
 *    mouvementés — jamais propagés ;
 *  - transaction + journalisation + rapport.
 */
class CategoryPropagationService
{
    /** Champs interdits de propagation (X3 §8 « ne jamais écraser »). */
    public const FORBIDDEN = [
        'sale_price', 'min_sale_price', 'max_sale_price', 'purchase_price',
        'stock_min', 'stock_max', 'stock_securite',
        'unit_id', 'sale_unit_id', 'purchase_unit_id',
    ];

    public function __construct(private CategoryDefaultsService $defaults) {}

    /** Champs propagables = défauts de la catégorie − liste noire. */
    public function propagatableFields(ItemCategory $cat): array
    {
        return array_values(array_diff(array_keys($this->defaults->defaultsFor($cat)), self::FORBIDDEN));
    }

    /**
     * Aperçu : pour chaque article de la catégorie, les champs sélectionnés dont
     * la valeur diffère du défaut catégorie (= personnalisés ou périmés).
     *
     * @return array{fields: array, articles: array, count: int}
     */
    public function preview(ItemCategory $cat, array $fields): array
    {
        $fields   = array_values(array_intersect($fields, $this->propagatableFields($cat)));
        $defaults = array_intersect_key($this->defaults->defaultsFor($cat), array_flip($fields));

        $articles = [];
        foreach ($cat->products()->get() as $p) {
            $diff = [];
            foreach ($defaults as $field => $target) {
                $current = $p->$field;
                if ((string) $current !== (string) $target) {
                    $diff[$field] = ['de' => $current, 'vers' => $target];
                }
            }
            if ($diff) {
                $articles[] = ['id' => $p->id, 'name' => $p->name, 'diff' => $diff];
            }
        }

        return ['fields' => $fields, 'articles' => $articles, 'count' => count($articles)];
    }

    /**
     * Exécute la propagation des champs sélectionnés (transaction) et retourne
     * le rapport. Journalise l'utilisateur, la date et le détail.
     */
    public function propagate(ItemCategory $cat, array $fields): array
    {
        $preview = $this->preview($cat, $fields);

        DB::transaction(function () use ($preview, $cat) {
            foreach ($preview['articles'] as $row) {
                $data = collect($row['diff'])->map(fn ($d) => $d['vers'])->all();
                Product::where('id', $row['id'])->update($data);
            }
            Log::info('[X3 §8] Propagation catégorie → articles', [
                'category'  => $cat->code,
                'fields'    => $preview['fields'],
                'articles'  => count($preview['articles']),
                'user_id'   => Auth::id(),
                'at'        => now()->toDateTimeString(),
            ]);
        });

        return $preview + ['propagated_at' => now()->toDateTimeString(), 'by' => Auth::id()];
    }
}
