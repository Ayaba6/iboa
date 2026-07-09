<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('unit')->id;

        return [
            'name'           => "required|string|max:100|unique:units,name,{$id}",
            'abbreviation'   => "required|string|max:20|unique:units,abbreviation,{$id}",
            'type'           => 'nullable|in:quantite,poids,volume,longueur,surface,temps,autre',
            'decimal_places' => 'nullable|integer|min:0|max:6',
            'is_active'      => 'boolean',
            // [Maquette Unité de mesure]
            'code'                 => "nullable|string|max:10|unique:units,code,{$id}",
            'name_en'              => 'nullable|string|max:100',
            'dimension'            => 'nullable|string|max:30',
            'parent_unit_id'       => "nullable|exists:units,id|not_in:{$id}",
            'conversion_factor'    => 'nullable|numeric|min:0.000001|max:999999999999',
            'rounding_mode'        => 'nullable|in:mathematique,superieur,inferieur',
            'unit_category'        => 'nullable|string|max:50',
            'is_default_inventory' => 'boolean',
            'is_default_sales'     => 'boolean',
            'description'          => 'nullable|string|max:1000',
            'internal_notes'       => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => "Le nom de l'unité est obligatoire.",
            'name.unique'           => 'Ce nom d\'unité existe déjà.',
            'abbreviation.required' => "L'abréviation est obligatoire.",
            'abbreviation.unique'   => 'Cette abréviation existe déjà.',
            'code.unique'           => 'Ce code d\'unité existe déjà.',
            'parent_unit_id.not_in' => 'Une unité ne peut pas être sa propre unité parente.',
        ];
    }
}
