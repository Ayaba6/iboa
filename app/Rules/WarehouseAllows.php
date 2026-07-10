<?php

namespace App\Rules;

use App\Models\Warehouse;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CDC dépôts — propriétés Production / Ventes / Achat / Stock :
 * une opération n'est permise que sur un dépôt qui porte la propriété
 * correspondante (ex. vendre uniquement depuis un dépôt de vente).
 */
class WarehouseAllows implements ValidationRule
{
    public function __construct(private string $capability) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $warehouse = Warehouse::withoutGlobalScopes()->find($value);
        if (! $warehouse) {
            return;
        }

        if (! $warehouse->{$this->capability}) {
            $label = match ($this->capability) {
                'can_production' => 'de production',
                'can_sale'       => 'de vente',
                'can_purchase'   => "d'achat",
                'can_stock'      => 'de stockage',
                default          => '',
            };
            $fail("Le dépôt « {$warehouse->name} » n'est pas un dépôt {$label} — opération non autorisée sur ce dépôt.");
        }
    }
}
