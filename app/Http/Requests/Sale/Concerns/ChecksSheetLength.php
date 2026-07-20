<?php

namespace App\Http\Requests\Sale\Concerns;

use App\Models\Product;
use Illuminate\Validation\Validator;

/**
 * [CDC §4/§6 — contrôle dimensions tôle bac] La longueur unitaire saisie
 * (metrage_par_tole) doit rester dans les bornes fabricables de l'article
 * (products.longueur_min / longueur_max) quand elles sont définies.
 *
 * S'applique aux devis, commandes et factures (Store + Update). Contrôle
 * strictement côté serveur — la valeur front n'est jamais présumée valide.
 * Article sans bornes définies = aucun contrôle (comportement inchangé).
 */
trait ChecksSheetLength
{
    public function checkSheetLength(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $items = (array) $this->input('items', []);
            $ids = collect($items)->pluck('product_id')->filter()->unique()->values();
            if ($ids->isEmpty()) {
                return;
            }

            $bounds = Product::whereIn('id', $ids)
                ->where(fn ($q) => $q->whereNotNull('longueur_min')->orWhereNotNull('longueur_max'))
                ->get(['id', 'longueur_min', 'longueur_max'])
                ->keyBy('id');

            foreach ($items as $i => $item) {
                $pid = $item['product_id'] ?? null;
                $len = (float) ($item['metrage_par_tole'] ?? 0);
                if (! $pid || $len <= 0 || ! isset($bounds[$pid])) {
                    continue;
                }
                $min = $bounds[$pid]->longueur_min !== null ? (float) $bounds[$pid]->longueur_min : null;
                $max = $bounds[$pid]->longueur_max !== null ? (float) $bounds[$pid]->longueur_max : null;

                if ($max !== null && $len > $max) {
                    $v->errors()->add("items.$i.metrage_par_tole", sprintf(
                        'Ligne %d : longueur unitaire (%s m) supérieure à la longueur maximale fabricable (%s m).',
                        $i + 1,
                        rtrim(rtrim(number_format($len, 3, ',', ' '), '0'), ','),
                        rtrim(rtrim(number_format($max, 3, ',', ' '), '0'), ',')
                    ));
                }
                if ($min !== null && $len < $min) {
                    $v->errors()->add("items.$i.metrage_par_tole", sprintf(
                        'Ligne %d : longueur unitaire (%s m) inférieure à la longueur minimale fabricable (%s m).',
                        $i + 1,
                        rtrim(rtrim(number_format($len, 3, ',', ' '), '0'), ','),
                        rtrim(rtrim(number_format($min, 3, ',', ' '), '0'), ',')
                    ));
                }
            }
        });
    }
}
