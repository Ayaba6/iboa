<?php

namespace App\Http\Requests\ProductFamily;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductFamilyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('family')->id;

        return [
            'name'                 => 'required|string|max:100',
            'designation_longue'   => 'nullable|string|max:255',
            'libelle_court'        => 'nullable|string|max:50',
            'code_prefix'          => 'nullable|string|max:10',
            'type_categorie'       => 'nullable|in:matiere_premiere,produit_fini,marchandise,consommable,service',
            'famille_principale_id'=> "nullable|exists:product_families,id|not_in:{$id}",
            'site_id'              => 'nullable|exists:warehouses,id',
            'code'                 => "nullable|string|max:30|unique:product_families,code,{$id}",
            'parent_id'            => "nullable|exists:product_families,id|not_in:{$id}",
            'description'          => 'nullable|string|max:500',
            'suivi_bobine'         => 'boolean',
            'utilisable_production'=> 'boolean',
            'actif_tous_sites'     => 'boolean',
            'numerotation_auto'    => 'boolean',
            'prix_plancher_obligatoire' => 'boolean',
            'autoriser_surcharge'  => 'boolean',
            'section_analytique_id'=> 'nullable|exists:cost_centers,id',
            'cost_center_id'       => 'nullable|exists:cost_centers,id',
            'tax_rate_achat_id'    => 'nullable|exists:tax_rates,id',
            'tax_rate_vente_id'    => 'nullable|exists:tax_rates,id',
            'depots'               => 'nullable|array',
            'depots.*.can_production' => 'boolean',
            'depots.*.can_sale'       => 'boolean',
            'depots.*.can_purchase'   => 'boolean',
            'depots.*.can_stock'      => 'boolean',
            'documents'            => 'nullable|array',
            'documents.*'          => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'type_flux'            => 'nullable|array',
            'type_flux.*'          => 'in:achete,vendu,fabrique,sous_traite,service,livrable',
            'gestion_stock'        => 'boolean',
            'stock_negatif'        => 'boolean',
            'unite_stock_id'       => 'nullable|exists:units,id',
            'unite_achat_id'       => 'nullable|exists:units,id',
            'unite_vente_id'       => 'nullable|exists:units,id',
            'unite_poids_id'       => 'nullable|exists:units,id',
            'coef_ua_us'           => 'nullable|numeric|min:0',
            'coef_uv_us'           => 'nullable|numeric|min:0',
            'densite'              => 'nullable|numeric|min:0',
            'poids_brut'           => 'nullable|numeric|min:0',
            'poids_net'            => 'nullable|numeric|min:0',
            'epaisseur'            => 'nullable|numeric|min:0',
            'metrage'              => 'nullable|numeric|min:0',
            'site_stockage_id'     => 'nullable|exists:warehouses,id',
            'gestion_lot'          => 'boolean',
            'lot_obligatoire'      => 'boolean',
            'gestion_numero_serie' => 'boolean',
            'controle_qualite'     => 'boolean',
            'cq_entree'            => 'boolean',
            'cq_sortie'            => 'boolean',
            'sale_account_id'      => 'nullable|exists:accounts,id',
            'purchase_account_id'  => 'nullable|exists:accounts,id',
            'stock_account_id'     => 'nullable|exists:accounts,id',
            'is_active'            => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Le libellé de la catégorie est obligatoire.',
            'code.unique'       => 'Ce code catégorie existe déjà.',
            'parent_id.not_in'  => 'Une famille ne peut pas être sa propre parente.',
            'type_flux.*.in'    => 'Type de flux invalide.',
        ];
    }
}
