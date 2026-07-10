<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'              => 'required|exists:suppliers,id',
            'received_at'              => 'required|date',
            'due_at'                   => 'nullable|date|after_or_equal:received_at',
            'supplier_invoice_number'  => 'nullable|string|max:50',
            'notes'                    => 'nullable|string',
            // [Maquette Facture fournisseur]
            'supplier_contact_id' => 'nullable|exists:supplier_contacts,id',
            'buyer_id'            => 'nullable|exists:users,id',
            'price_mode'          => 'nullable|in:ttc,ht',
            'net_prices'          => 'nullable|boolean',
            'payment_terms'       => 'nullable|string|max:100',
            'payment_method'      => 'nullable|string|max:30',
            'due_type'            => 'nullable|string|max:30',
            'beneficiary_bank'    => 'nullable|string|max:100',
            'fiscal_regime'       => 'nullable|string|max:40',
            'default_tax_label'   => 'nullable|string|max:20',
            'project_reference'   => 'nullable|string|max:60',
            'priority'            => 'nullable|string|max:15',

            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => ['nullable', 'exists:products,id', new \App\Rules\ProductFlux('achete')],
            'items.*.description'      => 'required|string|max:500',
            'items.*.quantity'         => 'required|numeric|min:0.0001',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate_value'   => 'nullable|numeric|min:0|max:100',
            'documents'                => 'nullable|array',
            'documents.*'              => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'fournisseur',
            'received_at' => 'date de réception',
            'due_at'      => 'date d\'échéance',
            'items'       => 'lignes',
            'items.*.description' => 'description',
            'items.*.quantity'    => 'quantité',
            'items.*.unit_price'  => 'prix unitaire',
        ];
    }
}
