<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client')->id;

        return [
            'name'             => 'required|string|max:150',
            // [ANTI-DUPLICATE] Ignore l'enregistrement courant pour l'update.
            'code'             => 'nullable|string|max:30|unique:clients,code,' . $clientId,
            'type'             => 'required|in:particulier,entreprise,distributeur,minier',
            'payment_mode'     => 'nullable|in:cash,credit',
            'email'            => 'nullable|email|max:150|unique:clients,email,' . $clientId,
            'phone'            => 'nullable|string|max:20',
            'mobile'           => 'nullable|string|max:20',
            'website'          => 'nullable|url|max:150',
            'ifu'              => 'nullable|string|max:50|unique:clients,ifu,' . $clientId,
            'rccm'             => 'nullable|string|max:50|unique:clients,rccm,' . $clientId,
            'tax_regime'             => 'nullable|string|max:100',
            'tax_division'           => 'nullable|string|max:150',
            // Exonération TVA
            'is_tax_exempt'          => 'nullable|boolean',
            'tax_exemption_reason'   => 'nullable|string|max:200',
            'tax_exemption_number'   => 'nullable|string|max:100',
            'tax_rate_ids'           => 'nullable|array',
            'tax_rate_ids.*'   => 'integer|exists:tax_rates,id',
            'credit_limit'     => 'nullable|numeric|min:0',
            'is_blocked'       => 'boolean',
            'blocked_reason'   => 'nullable|string|max:255',
            'payment_days'     => 'nullable|integer|min:0|max:365',
            'default_discount' => 'nullable|numeric|min:0|max:100',
            'notes'            => 'nullable|string',
            // [SAGE parité] identification / coordonnées / adresse / commercial / livraison / compta
            'site_id'              => 'nullable|exists:warehouses,id',
            'trade_name'           => 'nullable|string|max:100',
            'category'             => 'nullable|string|max:60',
            'numero_contribuable'  => 'nullable|string|max:30',
            'groupe_client'        => 'nullable|string|max:60',
            'secteur_activite'     => 'nullable|string|max:100',
            'currency'             => 'nullable|string|max:3',
            'language'             => 'nullable|string|max:5',
            'is_active'            => 'nullable|boolean',
            'is_livrable'          => 'nullable|boolean',
            'is_facturable'        => 'nullable|boolean',
            'soumis_tva'           => 'nullable|boolean',
            'soumis_bic'           => 'nullable|boolean',
            'bic_exemption_reason' => 'nullable|string|max:150',
            'blocage_commande'     => 'nullable|boolean',
            'phone2'               => 'nullable|string|max:20',
            'boite_postale'        => 'nullable|string|max:60',
            'address'              => 'nullable|string|max:200',
            'address_line2'        => 'nullable|string|max:200',
            'postal_code'          => 'nullable|string|max:20',
            'city'                 => 'nullable|string|max:100',
            'quartier'             => 'nullable|string|max:100',
            'region'               => 'nullable|string|max:100',
            'country'              => 'nullable|string|max:100',
            'gps_lat'              => 'nullable|numeric|between:-90,90',
            'gps_lng'              => 'nullable|numeric|between:-180,180',
            'sales_rep_id'         => 'nullable|exists:sales_reps,id',
            'canal'                => 'nullable|string|max:60',
            'zone_commerciale'     => 'nullable|string|max:60',
            'famille_tarifaire'    => 'nullable|string|max:60',
            'compte_collectif'     => 'nullable|string|max:30',
            'tax_rate_id'          => 'nullable|exists:tax_rates,id',
            'depot_livraison_id'   => 'nullable|exists:warehouses,id',
            'mode_livraison'       => 'nullable|string|max:60',
            'transporteur'         => 'nullable|string|max:100',
            'delai_livraison'      => 'nullable|integer|min:0|max:365',
            'adresse_livraison_defaut' => 'nullable|string|max:60',
            'compte_tiers'         => 'nullable|string|max:30',
            'condition_paiement'   => 'nullable|string|max:60',
            'echeance'             => 'nullable|string|max:60',
            'banque'               => 'nullable|string|max:100',
            'rib_iban'             => 'nullable|string|max:40',
            'numero_compte'        => 'nullable|string|max:30',
            'swift'                => 'nullable|string|max:20',
            // [Parité Sage X3] Juridique / fiscal
            'forme_juridique'      => 'nullable|string|max:60',
            'no_agrement'          => 'nullable|string|max:60',
            // [Parité Sage X3] Risque crédit
            'code_risque'          => 'nullable|string|max:30',
            'garantie_montant'     => 'nullable|numeric|min:0',
            'nature_garantie'      => 'nullable|string|max:80',
            'assurance_credit'     => 'nullable|string|max:120',
            'rrr_montant'          => 'nullable|numeric|min:0',
            'rrr_taux'             => 'nullable|numeric|min:0|max:100',
            'reference_cadastrale' => 'nullable|string|max:80',
            // [Parité Sage X3] Tiers comptables — un client ne peut pas se référencer
            // lui-même. `different:id` visait un CHAMP de la requête nommé `id` : le
            // formulaire n'en poste aucun, l'identifiant venant de l'URL. La règle
            // passait donc toujours, et l'interdiction n'existait que dans le
            // commentaire. `not_in` compare à la valeur réelle.
            'client_facture_id'    => 'nullable|integer|exists:clients,id|not_in:' . $clientId,
            'client_payeur_id'     => 'nullable|integer|exists:clients,id|not_in:' . $clientId,
            'client_groupe_id'     => 'nullable|integer|exists:clients,id|not_in:' . $clientId,
            'client_risque_id'     => 'nullable|integer|exists:clients,id|not_in:' . $clientId,
            'factor_id'            => 'nullable|integer|exists:clients,id|not_in:' . $clientId,
            'documents'            => 'nullable|array',
            'documents.*'          => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            // contacts
            'contacts'                  => 'nullable|array',
            'contacts.*.civility'       => 'nullable|string|max:10',
            'contacts.*.first_name'     => 'nullable|string|max:80',
            'contacts.*.last_name'      => 'required_with:contacts.*.first_name|string|max:80',
            'contacts.*.phone'          => 'nullable|string|max:20',
            'contacts.*.mobile'         => 'nullable|string|max:20',
            'contacts.*.email'          => 'nullable|email|max:150',
            'contacts.*.job_title'      => 'nullable|string|max:100',
            'contacts.*.is_primary'     => 'nullable|boolean',
            // addresses
            'addresses'                 => 'nullable|array',
            'addresses.*.type'          => 'required_with:addresses.*|in:livraison,facturation,siege',
            'addresses.*.label'         => 'nullable|string|max:100',
            'addresses.*.address'       => 'required_with:addresses.*|string|max:200',
            'addresses.*.city'          => 'nullable|string|max:100',
            'addresses.*.country'       => 'nullable|string|max:100',
            'addresses.*.is_default'    => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'             => 'raison sociale',
            'type'             => 'type de client',
            'email'            => 'email',
            'phone'            => 'téléphone',
            'credit_limit'     => 'limite de crédit',
            'payment_days'     => 'délai de paiement',
            'default_discount' => 'remise par défaut',
            'ifu'              => 'IFU / NIF',
            'rccm'             => 'RCCM',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'  => 'Un autre client utilise déjà ce code interne.',
            'email.unique' => 'Un autre client est déjà enregistré avec cette adresse email.',
            'ifu.unique'   => 'Un autre client est déjà enregistré avec ce numéro IFU/NIF.',
            'rccm.unique'  => 'Un autre client est déjà enregistré avec ce numéro RCCM.',
        ];
    }
}
