<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'              => 'required|exists:suppliers,id',
            'issued_at'                => 'required|date',
            'expected_at'              => 'nullable|date',
            'reference'                => 'nullable|string|max:50',
            'currency_code'            => 'nullable|string|max:3',
            'delivery_address'         => 'nullable|string',
            'depot_reception_id'       => ['nullable', 'exists:warehouses,id', new \App\Rules\WarehouseAllows('can_purchase')],
            'terms'                    => 'nullable|string',
            'footer_note'              => 'nullable|string',
            'notes'                    => 'nullable|string',
            'documents'                => 'nullable|array',
            'documents.*'              => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            // [Maquette Commande fournisseur]
            'supplier_contact_id'          => 'nullable|exists:supplier_contacts,id',
            'buyer_id'                     => 'nullable|exists:users,id',
            'price_mode'                   => 'nullable|in:ttc,ht',
            'net_prices'                   => 'nullable|boolean',
            'price_list'                   => 'nullable|string|max:60',
            'payment_terms'                => 'nullable|string|max:100',
            'payment_method'               => 'nullable|string|max:30',
            'project_reference'            => 'nullable|string|max:60',
            'carrier'                      => 'nullable|string|max:80',
            'vehicle_number'               => 'nullable|string|max:30',
            'delivery_location'            => 'nullable|string|max:100',
            'incoterm'                     => 'nullable|string|max:15',
            'priority'                     => 'nullable|string|max:15',
            'total_weight_kg'              => 'nullable|numeric|min:0',

            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => ['nullable', 'exists:products,id', new \App\Rules\ProductFlux('achete')],
            'items.*.description'      => 'required|string|max:500',
            'items.*.quantity'         => 'required|numeric|min:0.0001',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate_value'   => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id'    => 'fournisseur',
            'issued_at'      => 'date de commande',
            'expected_at'    => 'date de livraison prévue',
            'items'          => 'lignes',
            'items.*.description' => 'description',
            'items.*.quantity'    => 'quantité',
            'items.*.unit_price'  => 'prix unitaire',
        ];
    }
}
