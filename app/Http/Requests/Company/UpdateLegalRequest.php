<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'legal_form'           => 'nullable|string|max:50',
            'rccm'                 => 'nullable|string|max:50',
            'ifu'                  => 'nullable|string|max:30',
            'nif'                  => 'nullable|string|max:30',
            'is_vat_subject'       => 'boolean',
            // vat_number est un IDENTIFIANT TVA (texte), pas un taux : l'ancienne
            // règle numeric|max:100 venait d'un champ « Taux TVA » mal mappé qui
            // écrasait ce numéro avec « 18 ». Le taux vit dans default_vat_rate.
            'vat_number'           => 'nullable|string|max:30',
            'share_capital'        => 'nullable|integer|min:0',
            'share_capital_currency' => 'nullable|string|max:3',
        ];
    }
}
