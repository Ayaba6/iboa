<?php

namespace App\Http\Requests\ProductFamily;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [X3] Voir StoreProductFamilyRequest : classement pur, champs de
 * gestion dépréciés non acceptés.
 */
class UpdateProductFamilyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('family')->id;

        return [
            'name'        => 'required|string|max:100',
            'code'        => "nullable|string|max:30|unique:product_families,code,{$id}",
            'parent_id'   => "nullable|exists:product_families,id|not_in:{$id}",
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
            'parent_id.not_in' => 'Une famille ne peut pas être sa propre parente.',
        ];
    }
}
