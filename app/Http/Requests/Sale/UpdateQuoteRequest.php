<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    use \App\Http\Requests\Sale\Concerns\ChecksFloorPrice;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'                    => 'required|exists:clients,id',
            'issued_at'                    => 'required|date',
            'expires_at'                   => 'nullable|date|after:issued_at',
            'reference'                    => 'nullable|string|max:50',
            'global_discount_amount'       => 'nullable|numeric|min:0',
            'global_discount_percent'      => 'nullable|numeric|min:0|max:100',
            'notes'                        => 'nullable|string',
            'terms'                        => 'nullable|string',
            'footer_note'                  => 'nullable|string',

            // [Maquette Nouveau devis]
            'contact_id'                   => 'nullable|exists:client_contacts,id',
            'warehouse_id'                 => 'nullable|exists:warehouses,id',
            'sales_rep_id'                 => 'nullable|exists:users,id',
            'delivery_address'             => 'nullable|string',
            'validity_duration'            => 'nullable|string|max:15',
            'project_reference'            => 'nullable|string|max:60',
            'price_list'                   => 'nullable|string|max:60',
            'price_mode'                   => 'nullable|in:ttc,ht',
            'net_prices'                   => 'nullable|boolean',
            'payment_terms'                => 'nullable|string|max:100',
            'payment_method'               => 'nullable|string|max:30',
            'fiscal_representative'        => 'nullable|string|max:100',
            'fiscal_regime'                => 'nullable|string|max:40',
            'default_tax_label'            => 'nullable|string|max:20',
            'currency_code'                => 'nullable|string|size:3',
            'exchange_rate'                => 'nullable|numeric|min:0',
            'source'                       => 'nullable|string|max:30',
            'origin'                       => 'nullable|string|max:30',
            'priority'                     => 'nullable|string|max:15',
            'desired_delivery_date'        => 'nullable|date',
            'delivery_location'            => 'nullable|string|max:100',
            'incoterm'                     => 'nullable|string|max:15',

            'documents'                    => 'nullable|array',
            'documents.*'                  => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',

            'items'                        => 'nullable|array|min:1',
            'items.*.product_id'           => ['nullable', 'exists:products,id', new \App\Rules\ProductFlux('vendu')],
            'items.*.description'          => 'required_with:items|string|max:255',
            'items.*.unit_id'              => 'nullable|exists:units,id',
            'items.*.quantity'             => 'required_with:items|numeric|min:0.0001',
            'items.*.nb_toles'             => 'nullable|numeric|min:0',
            'items.*.metrage_par_tole'     => 'nullable|numeric|min:0',
            'items.*.unit_price'           => 'required_with:items|numeric|min:0',
            'items.*.discount_percent'     => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate_id'          => 'nullable|exists:tax_rates,id',
            'items.*.tax_rate_value'       => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'                => 'client',
            'issued_at'                => 'date d\'émission',
            'expires_at'               => 'date d\'expiration',
            'reference'                => 'référence',
            'global_discount_percent'  => 'remise globale (%)',
            'global_discount_amount'   => 'remise globale (montant)',
            'items'                    => 'lignes',
            'items.*.description'      => 'description',
            'items.*.quantity'         => 'quantité',
            'items.*.unit_price'       => 'prix unitaire',
            'items.*.discount_percent' => 'remise (%)',
            'items.*.tax_rate_value'   => 'taux TVA',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'                    => 'Veuillez sélectionner un client.',
            'client_id.exists'                      => 'Le client sélectionné est invalide.',
            'issued_at.required'                    => 'La date d\'émission est obligatoire.',
            'issued_at.date'                        => 'La date d\'émission n\'est pas une date valide.',
            'expires_at.date'                       => 'La date d\'expiration n\'est pas une date valide.',
            'expires_at.after'                      => 'La date d\'expiration doit être postérieure à la date d\'émission.',

            'items.min'                             => 'Veuillez conserver au moins une ligne dans le devis.',

            'items.*.description.required_with'     => 'La description est obligatoire pour chaque ligne.',
            'items.*.description.max'               => 'La description ne peut pas dépasser 255 caractères.',
            'items.*.quantity.required_with'        => 'La quantité est obligatoire pour chaque ligne.',
            'items.*.quantity.numeric'              => 'La quantité doit être un nombre valide.',
            'items.*.quantity.min'                  => 'La quantité doit être supérieure à 0.',
            'items.*.unit_price.required_with'      => 'Le prix unitaire est obligatoire pour chaque ligne.',
            'items.*.unit_price.numeric'            => 'Le prix unitaire doit être un nombre valide.',
            'items.*.unit_price.min'                => 'Le prix unitaire ne peut pas être négatif.',
            'items.*.discount_percent.min'          => 'La remise ne peut pas être négative.',
            'items.*.discount_percent.max'          => 'La remise ne peut pas dépasser 100 %.',
            'items.*.tax_rate_value.min'            => 'Le taux de TVA ne peut pas être négatif.',
            'items.*.tax_rate_value.max'            => 'Le taux de TVA ne peut pas dépasser 100 %.',
        ];
    }
}
