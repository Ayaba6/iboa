<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('supplier_returns.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id'   => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'reception_id'        => ['nullable', 'integer', 'exists:receptions,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'returned_at'         => ['required', 'date'],
            'reason'              => ['nullable', 'string', 'max:255'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'currency_code'       => ['nullable', 'string', 'size:3'],
            'exchange_rate'       => ['nullable', 'numeric', 'min:0.000001'],

            // [Maquette Retour fournisseur]
            'return_type'        => 'nullable|string|max:30',
            'warehouse_id'       => 'nullable|exists:warehouses,id',
            'priority'           => 'nullable|string|max:15',
            'project_reference'  => 'nullable|string|max:60',
            // (déjà présent) 'purchase_order_id'  => 'nullable|exists:purchase_orders,id',
            // (déjà présent) 'reception_id'       => 'nullable|exists:receptions,id',
            // (déjà présent) 'supplier_invoice_id'=> 'nullable|exists:supplier_invoices,id',

            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.unit_id'     => ['nullable', 'integer', 'exists:units,id'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'  => ['required', 'integer', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id'  => ['nullable', 'integer', 'exists:tax_rates,id'],
            'items.*.tax_rate_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id'        => 'fournisseur',
            'returned_at'        => 'date de retour',
            'items'              => 'lignes',
            'items.*.quantity'   => 'quantité',
            'items.*.unit_price' => 'prix unitaire',
        ];
    }
}
