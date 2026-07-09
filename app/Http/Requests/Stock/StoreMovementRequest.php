<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'    => 'required|exists:products,id',
            'warehouse_id'  => ['required', 'exists:warehouses,id', new \App\Rules\WarehouseAllows('can_stock')],
            'movement_type'     => 'required|in:entree,sortie,transfert,ajustement,retour_client,retour_fournisseur',
            'quantity'          => [
                'required', 'numeric',
                $this->input('movement_type') === 'ajustement' ? 'not_in:0' : 'min:0.0001',
            ],
            'unit_cost'         => 'nullable|numeric|min:0',
            'movement_date'     => 'required|date',
            'dest_warehouse_id' => [
                'nullable',
                'exists:warehouses,id',
                $this->input('movement_type') === 'transfert' ? 'required' : 'nullable',
            ],
            'lot_number'        => 'nullable|string|max:50',
            'serial_number'     => 'nullable|string|max:50',
            'expiry_date'       => 'nullable|date',
            'notes'             => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'    => 'Veuillez sélectionner un article.',
            'product_id.exists'      => 'L\'article sélectionné est invalide.',
            'warehouse_id.required'  => 'Veuillez sélectionner un entrepôt.',
            'warehouse_id.exists'    => 'L\'entrepôt sélectionné est invalide.',
            'movement_type.required' => 'Veuillez sélectionner le type de mouvement.',
            'movement_type.in'       => 'Type de mouvement invalide.',
            'quantity.required'      => 'La quantité est obligatoire.',
            'quantity.numeric'       => 'La quantité doit être un nombre.',
            'quantity.min'           => 'La quantité doit être supérieure à zéro.',
            'movement_date.required' => 'La date du mouvement est obligatoire.',
            'movement_date.date'     => 'La date du mouvement est invalide.',
        ];
    }
}
