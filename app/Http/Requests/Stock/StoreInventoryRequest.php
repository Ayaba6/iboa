<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id', new \App\Rules\WarehouseAllows('can_stock')],
            'type'         => 'nullable|in:tournant,annuel,complet',
            'notes'        => 'nullable|string',
            // [PARITÉ SAGE X3] Paramètres de comptage (métadonnées)
            'site'               => 'nullable|string|max:40',
            'responsible'        => 'nullable|string|max:100',
            'counting_type'      => 'nullable|in:complet,partiel',
            'counting_method'    => 'nullable|string|max:20',
            'valuation_method'   => 'nullable|string|max:20',
            'valuation_currency' => 'nullable|string|size:3',
            'currency_code'      => 'nullable|string|size:3',
            'freeze_stock'       => 'nullable|boolean',
            'include_lots'       => 'nullable|boolean',
            'include_locations'  => 'nullable|boolean',
            'location_scope'     => 'nullable|string|max:60',
            'article_scope'      => 'nullable|string|max:60',
            'comment'            => 'nullable|string|max:500',
            'documents'          => 'nullable|array',
            'documents.*'        => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Veuillez sélectionner un entrepôt.',
            'warehouse_id.exists'   => 'L\'entrepôt sélectionné est invalide.',
        ];
    }
}
