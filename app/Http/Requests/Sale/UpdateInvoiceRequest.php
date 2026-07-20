<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use \App\Http\Requests\Sale\Concerns\ChecksFloorPrice;
    use \App\Http\Requests\Sale\Concerns\ChecksSheetLength;

    // [CDC §4/§6] Contrôles serveur des lignes : prix plancher + longueur fabricable.
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $this->checkFloorPrice($validator);
        $this->checkSheetLength($validator);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'                    => 'required|exists:clients,id',
            'issued_at'                    => 'required|date',
            'due_at'                       => 'nullable|date|after_or_equal:issued_at',
            'type'                         => 'nullable|in:standard,acompte,partielle,recurrente,proforma',
            'order_id'                     => 'nullable|exists:orders,id',
            'delivery_note_id'             => 'nullable|exists:delivery_notes,id',
            'reference'                    => 'nullable|string|max:50',
            'payment_terms'                => 'nullable|string|max:100',
            'global_discount_amount'       => 'nullable|numeric|min:0',
            'global_discount_percent'      => 'nullable|numeric|min:0|max:100',
            'billing_address'              => 'nullable|string',
            'notes'                        => 'nullable|string',
            'terms'                        => 'nullable|string',
            'footer_note'                  => 'nullable|string',
            'currency_code'                => 'nullable|string|size:3',
            'is_recurring'                 => 'nullable|boolean',
            'recurring_frequency'          => 'nullable|in:monthly,quarterly,yearly',
            'next_recurring_date'          => 'nullable|date',

            // [Maquette Facture de vente]
            'contact_id'                   => 'nullable|exists:client_contacts,id',
            'warehouse_id'                 => 'nullable|exists:warehouses,id',
            'price_mode'                   => 'nullable|in:ttc,ht,exonere',
            'net_prices'                   => 'nullable|boolean',
            'project_reference'            => 'nullable|string|max:60',
            'price_list'                   => 'nullable|string|max:60',
            'fiscal_representative'        => 'nullable|string|max:100',
            'payment_method'               => 'nullable|string|max:30',
            'fiscal_regime'                => 'nullable|string|max:40',
            'default_tax_label'            => 'nullable|string|max:20',
            'beneficiary_bank'             => 'nullable|string|max:100',
            'due_type'                     => 'nullable|string|max:30',
            'sales_rep_id'                 => 'nullable|exists:users,id',
            'delivery_date'                => 'nullable|date',
            'carrier'                      => 'nullable|string|max:80',
            'vehicle_number'               => 'nullable|string|max:30',
            'delivery_location'            => 'nullable|string|max:100',
            'total_weight_kg'              => 'nullable|numeric|min:0',

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
            'due_at'                   => 'date d\'échéance',
            'reference'                => 'référence',
            'payment_terms'            => 'conditions de paiement',
            'billing_address'          => 'adresse de facturation',
            'currency_code'            => 'devise',
            'global_discount_percent'  => 'remise globale (%)',
            'global_discount_amount'   => 'remise globale (montant)',
            'next_recurring_date'      => 'prochaine date de récurrence',
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
            'due_at.date'                           => 'La date d\'échéance n\'est pas une date valide.',
            'due_at.after_or_equal'                 => 'La date d\'échéance doit être égale ou postérieure à la date d\'émission.',
            'currency_code.size'                    => 'Le code devise doit comporter exactement 3 caractères (ex. : MAD, EUR, USD).',

            'items.min'                             => 'Veuillez conserver au moins une ligne dans la facture.',

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
