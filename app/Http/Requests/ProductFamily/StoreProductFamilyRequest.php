<?php

namespace App\Http\Requests\ProductFamily;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [X3] La famille est un CLASSEMENT commercial et statistique pur.
 * Les champs de gestion historiques (flux, stock, unités, comptes, TVA,
 * qualité, dépôts…) relèvent des catégories d'article et ne sont plus
 * acceptés ici — leurs colonnes en base sont dépréciées.
 */
class StoreProductFamilyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:30|unique:product_families,code',
            'parent_id'   => 'nullable|exists:product_families,id',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'L\'intitulé de la famille est obligatoire.',
            'code.unique'      => 'Ce code famille existe déjà.',
            'parent_id.exists' => 'La famille parente sélectionnée est invalide.',
        ];
    }
}
