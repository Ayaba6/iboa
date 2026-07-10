<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_requests.create');
    }

    public function rules(): array
    {
        return [
            'department'   => ['nullable', 'string', 'max:100'],
            'justification'=> ['nullable', 'string', 'max:255'],
            'needed_at'    => ['nullable', 'date'],
            // [Maquette Demande d'achat]
            'priority'          => ['nullable', 'string', 'max:15'],
            'project_reference' => ['nullable', 'string', 'max:60'],
            'warehouse_id'      => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['nullable', 'integer', 'exists:products,id', new \App\Rules\ProductFlux('achete')],
            'items.*.description'         => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.unit_id'             => ['nullable', 'integer', 'exists:units,id'],
            'items.*.quantity'            => ['required', 'numeric', 'min:0.001'],
            'items.*.estimated_price'     => ['nullable', 'integer', 'min:0'],
            'items.*.notes'               => ['nullable', 'string', 'max:255'],

            'documents'   => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'needed_at'           => 'date souhaitée',
            'items'               => 'lignes',
            'items.*.quantity'    => 'quantité',
            'items.*.description' => 'description',
        ];
    }
}
