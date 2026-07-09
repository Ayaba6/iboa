<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CDC articles — type de flux A/V/F :
 * un article non coché dans un flux ne peut pas être exécuté dans ce flux
 * (non acheté → pas d'achat, non vendu → pas de vente, non fabriqué → pas d'OF).
 */
class ProductFlux implements ValidationRule
{
    public function __construct(private string $flux) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // lignes libres sans article — la règle exists gère l'invalide
        }

        $product = Product::withoutGlobalScopes()->find($value);
        if (! $product) {
            return;
        }

        [$ok, $label] = match ($this->flux) {
            'vendu'    => [$product->is_sellable,       'vendable (flux Vendu)'],
            'achete'   => [$product->is_purchasable,    'achetable (flux Acheté)'],
            'fabrique' => [$product->is_manufacturable, 'fabricable (flux Fabriqué)'],
            default    => [true, ''],
        };

        if (! $ok) {
            $fail("L'article « {$product->name} » n'est pas {$label} — flux non autorisé sur cet article.");
        }
    }
}
