<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')->id;

        return [
            'name'    => 'required|string|max:150',
            // [ANTI-DUPLICATE] Ignore l'enregistrement courant.
            'code'    => 'nullable|string|max:30|unique:suppliers,code,' . $supplierId,
            'type'    => 'nullable|in:particulier,entreprise',
            'email'   => 'nullable|email|max:150|unique:suppliers,email,' . $supplierId,
            'phone'   => 'nullable|string|max:20',
            'phone2'  => 'nullable|string|max:20',
            'website' => 'nullable|url|max:150',
            'ifu'     => 'nullable|string|max:50|unique:suppliers,ifu,' . $supplierId,
            'rccm'    => 'nullable|string|max:50|unique:suppliers,rccm,' . $supplierId,
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes'   => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'rating'            => 'nullable|numeric|min:0|max:5',
            'avg_delivery_days' => 'nullable|integer|min:0|max:365',
            // [SAGE parité]
            'site_id'              => 'nullable|exists:warehouses,id',
            'civility'             => 'nullable|string|max:10',
            'trade_name'           => 'nullable|string|max:100',
            'mobile'               => 'nullable|string|max:20',
            'category'             => 'nullable|string|max:60',
            'numero_contribuable'  => 'nullable|string|max:30',
            'groupe_fournisseur'   => 'nullable|string|max:60',
            'secteur_activite'     => 'nullable|string|max:100',
            'currency'             => 'nullable|string|max:3',
            'language'             => 'nullable|string|max:5',
            'soumis_tva'           => 'nullable|boolean',
            'blocage_achat'        => 'nullable|boolean',
            'boite_postale'        => 'nullable|string|max:60',
            'address_line2'        => 'nullable|string|max:200',
            'postal_code'          => 'nullable|string|max:20',
            'quartier'             => 'nullable|string|max:100',
            'region'               => 'nullable|string|max:100',
            'gps_lat'              => 'nullable|numeric|between:-90,90',
            'gps_lng'              => 'nullable|numeric|between:-180,180',
            'canal'                => 'nullable|string|max:60',
            'famille_tarifaire'    => 'nullable|string|max:60',
            'tax_rate_id'          => 'nullable|exists:tax_rates,id',
            'default_discount'     => 'nullable|numeric|min:0|max:100',
            'payment_mode'         => 'nullable|string|max:20',
            'payment_days'         => 'nullable|integer|min:0|max:365',
            'credit_limit'         => 'nullable|numeric|min:0',
            'encours_autorise'     => 'nullable|numeric|min:0',
            'compte_collectif'     => 'nullable|string|max:30',
            'depot_reception_id'   => 'nullable|exists:warehouses,id',
            'mode_livraison'       => 'nullable|string|max:60',
            'transporteur'         => 'nullable|string|max:100',
            'delai_livraison'      => 'nullable|integer|min:0|max:365',
            'compte_tiers'         => 'nullable|string|max:30',
            'condition_paiement'   => 'nullable|string|max:60',
            'echeance'             => 'nullable|string|max:60',
            'banque'               => 'nullable|string|max:100',
            'rib_iban'             => 'nullable|string|max:40',
            'numero_compte'        => 'nullable|string|max:30',
            'swift'                => 'nullable|string|max:20',
            'documents'            => 'nullable|array',
            'documents.*'          => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',

            'contacts'              => 'nullable|array',
            'contacts.*.civility'   => 'nullable|string|max:10',
            'contacts.*.first_name' => 'nullable|string|max:80',
            'contacts.*.last_name'  => 'nullable|string|max:80',
            'contacts.*.job_title'  => 'nullable|string|max:100',
            'contacts.*.phone'      => 'nullable|string|max:20',
            'contacts.*.mobile'     => 'nullable|string|max:20',
            'contacts.*.email'      => 'nullable|email|max:150',
            'contacts.*.is_primary' => 'nullable|boolean',

            'addresses'               => 'nullable|array',
            'addresses.*.type'        => 'nullable|in:livraison,facturation,siege',
            'addresses.*.label'       => 'nullable|string|max:100',
            'addresses.*.address'     => 'nullable|string|max:255',
            'addresses.*.city'        => 'nullable|string|max:100',
            'addresses.*.country'     => 'nullable|string|max:100',
            'addresses.*.is_default'  => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'  => 'raison sociale',
            'code'  => 'code fournisseur',
            'email' => 'adresse e-mail',
            'ifu'   => 'IFU',
            'rccm'  => 'RCCM',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'  => 'Un autre fournisseur utilise déjà ce code interne.',
            'email.unique' => 'Un autre fournisseur est déjà enregistré avec cette adresse email.',
            'ifu.unique'   => 'Un autre fournisseur est déjà enregistré avec ce numéro IFU.',
            'rccm.unique'  => 'Un autre fournisseur est déjà enregistré avec ce numéro RCCM.',
        ];
    }
}
