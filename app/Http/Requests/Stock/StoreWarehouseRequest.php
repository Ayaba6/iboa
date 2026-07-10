<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:120',
            'code'               => 'required|string|max:20|unique:warehouses,code',
            'type'               => 'nullable|string|max:30',
            'site'               => 'nullable|string|max:20',
            'address'            => 'nullable|string|max:255',
            'address_complement' => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:80',
            'postal_code'        => 'nullable|string|max:20',
            'country'            => 'nullable|string|max:60',
            'manager_name'       => 'nullable|string|max:100',
            'phone'              => 'nullable|string|max:30',
            'email'              => 'nullable|email|max:120',
            'is_default'         => 'boolean',
            'is_active'          => 'boolean',
            'can_production'     => 'boolean',
            'can_sale'           => 'boolean',
            'can_purchase'       => 'boolean',
            'can_stock'          => 'boolean',
            'allow_negative_stock' => 'boolean',
            // [PARITÉ SAGE X3] Champs étendus
            'long_name'                => 'nullable|string|max:255',
            'parent_id'                => 'nullable|integer|exists:warehouses,id',
            'latitude'                 => 'nullable|numeric|between:-90,90',
            'longitude'                => 'nullable|numeric|between:-180,180',
            'default_location'         => 'nullable|string|max:60',
            'quality_warehouse_id'     => 'nullable|integer|exists:warehouses,id',
            'scrap_warehouse_id'       => 'nullable|integer|exists:warehouses,id',
            'max_capacity'             => 'nullable|numeric|min:0',
            'capacity_unit'            => 'nullable|string|max:10',
            'overload_alert_percent'   => 'nullable|numeric|between:0,100',
            'issue_method'             => 'nullable|string|in:fifo,lifo,cmp',
            'issue_priority'           => 'nullable|string|max:30',
            'stock_account'            => 'nullable|string|max:20',
            'stock_journal'            => 'nullable|string|max:20',
            'cost_center'              => 'nullable|string|max:30',
            'analytic_section'         => 'nullable|string|max:30',
            'requires_quality_control' => 'boolean',
            'can_delivery'             => 'boolean',
            'can_transfer'             => 'boolean',
            'flow_settings'            => 'nullable|array',
            'documents'          => 'nullable|array',
            'documents.*'        => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "Le nom de l'entrepôt est obligatoire.",
            'code.required' => "Le code de l'entrepôt est obligatoire.",
            'code.unique'   => 'Ce code entrepôt existe déjà.',
        ];
    }
}
