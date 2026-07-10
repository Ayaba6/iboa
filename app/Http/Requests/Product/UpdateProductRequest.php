<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->product->id;

        return [
            // Identification
            // CDC articles : désignation 110 car, référence 16 car
            'name'              => 'required|string|max:110',
            'reference'         => "nullable|string|max:16|unique:products,reference,{$id}",
            'barcode'           => "nullable|string|max:50|unique:products,barcode,{$id}",
            'description'       => 'nullable|string',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',

            // Classification
            'family_id'             => 'nullable|exists:product_families,id',
            'brand_id'              => 'nullable|exists:brands,id',
            'unit_id'               => 'nullable|exists:units,id',
            'tax_rate_id'           => 'nullable|exists:tax_rates,id',
            'sale_account_id'              => 'nullable|exists:accounts,id',
            'purchase_account_id'          => 'nullable|exists:accounts,id',
            'stock_account_id'             => 'nullable|exists:accounts,id',
            'variation_stock_account_id'   => 'nullable|exists:accounts,id',
            'default_supplier_id'   => 'nullable|exists:suppliers,id',
            'supplier_reference'    => 'nullable|string|max:80',
            'delivery_delay_days'   => 'nullable|integer|min:0|max:365',
            'type'                  => 'required|in:simple,service,compose',

            // [PHASE E] Référentiel enrichi
            'code_article'          => "nullable|string|max:10|unique:products,code_article,{$id}", // CDC : 10 car
            'designation_courte'    => 'nullable|string|max:80',
            'type_article'          => 'nullable|in:marchandise,matiere_premiere,produit_fini,service,consommable',
            'statut'                => 'nullable|in:actif,inactif,bloque',
            'production_mode'       => 'nullable|in:mts,mto',
            'famille1_id'           => 'nullable|exists:product_families,id',
            'famille2_id'           => 'nullable|exists:product_families,id',
            'famille3_id'           => 'nullable|exists:product_families,id',
            'purchase_unit_id'      => 'nullable|exists:units,id',
            'sale_unit_id'          => 'nullable|exists:units,id',
            'weight_unit_id'        => 'nullable|exists:units,id',
            'ua_to_us_coef'         => 'nullable|numeric|min:0',
            'uv_to_us_coef'         => 'nullable|numeric|min:0',
            'gross_weight_per_us'   => 'nullable|numeric|min:0',
            'net_weight_per_us'     => 'nullable|numeric|min:0',
            'thickness'             => 'nullable|numeric|min:0',
            'linear_meters'         => 'nullable|numeric|min:0',
            'density'               => 'nullable|numeric|min:0',
            // [SAGE parité] identification / dépôts / production / analytique
            'site_id'               => 'nullable|exists:warehouses,id',
            'designation_2'         => 'nullable|string|max:200',
            'client_type_canal'     => 'nullable|string|max:60',
            'production_warehouse_id' => 'nullable|exists:warehouses,id',
            'sale_warehouse_id'     => 'nullable|exists:warehouses,id',
            'quality_warehouse_id'  => 'nullable|exists:warehouses,id',
            'seuil_alerte'          => 'nullable|numeric|min:0',
            'tax_rate_achat_id'     => 'nullable|exists:tax_rates,id',
            'section_analytique_id' => 'nullable|exists:cost_centers,id',
            'cost_center_id'        => 'nullable|exists:cost_centers,id',
            'nomenclature_ref'      => 'nullable|string|max:60',
            'profil'                => 'nullable|string|max:60',
            'couleur'               => 'nullable|string|max:60',
            'largeur_utile'         => 'nullable|numeric|min:0',
            'longueur_standard'     => 'nullable|numeric|min:0',
            'machine_defaut_id'     => 'nullable|exists:production_machines,id',
            'rendement_standard'    => 'nullable|numeric|min:0|max:9.9999',
            'taux_perte'            => 'nullable|numeric|min:0|max:9.9999',
            'article_avarie_id'     => 'nullable|exists:products,id',
            'article_chute_id'      => 'nullable|exists:products,id',
            'depots'                => 'nullable|array',
            'depots.*.can_production' => 'boolean',
            'depots.*.can_sale'       => 'boolean',
            'depots.*.can_purchase'   => 'boolean',
            'depots.*.can_stock'      => 'boolean',
            'documents'             => 'nullable|array',
            'documents.*'           => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'allow_negative_stock'  => 'boolean',
            'stock_securite'        => 'nullable|numeric|min:0',
            'main_warehouse_id'     => 'nullable|exists:warehouses,id',

            // Comportement
            'is_stockable'      => 'boolean',
            'is_purchasable'    => 'boolean',
            'is_sellable'       => 'boolean',
            'is_manufacturable' => 'boolean',
            'is_active'         => 'boolean',

            // Traçabilité & qualité
            'has_serial_number' => 'boolean',
            'has_lot_number'    => 'boolean',
            'has_expiry_date'   => 'boolean',
            'controle_qualite'  => 'boolean',

            // Tarification
            'purchase_price'        => 'nullable|integer|min:0',
            'sale_price'            => 'nullable|integer|min:0',
            'min_sale_price'        => 'nullable|integer|min:0',
            'cout_standard'         => 'nullable|numeric|min:0',
            'margin_rate_target'    => 'nullable|numeric|min:0|max:999.99',
            'valuation_method'      => 'nullable|in:cmp,fifo,lifo',

            // Stock & seuils
            'stock_min'         => 'nullable|numeric|min:0',
            'stock_max'         => 'nullable|numeric|min:0|gte:stock_min',
            'reorder_point'     => 'nullable|numeric|min:0',

            // Poids
            'weight'            => 'nullable|numeric|min:0',
            'weight_unit'       => 'nullable|in:kg,g,t',

            // Composants (type composé)
            'components'                            => 'nullable|array',
            'components.*.component_product_id'     => 'required_with:components|exists:products,id',
            'components.*.quantity'                 => 'required_with:components|numeric|min:0.001',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                              => 'désignation',
            'reference'                         => 'référence',
            'sale_price'                        => 'prix de vente',
            'tax_rate_id'                       => 'taux TVA',
            'stock_max'                         => 'stock maximum',
            'reorder_point'                     => 'seuil de réapprovisionnement',
            'components.*.component_product_id' => 'composant',
            'components.*.quantity'             => 'quantité du composant',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_stockable'      => $this->boolean('is_stockable',      true),
            'is_purchasable'    => $this->boolean('is_purchasable',    true),
            'is_sellable'       => $this->boolean('is_sellable',       true),
            'is_manufacturable' => $this->boolean('is_manufacturable', false),
            'is_active'         => $this->boolean('is_active',         true),
            'has_serial_number' => $this->boolean('has_serial_number', false),
            'has_lot_number'    => $this->boolean('has_lot_number',    false),
            'has_expiry_date'   => $this->boolean('has_expiry_date',   false),
            'controle_qualite'  => $this->boolean('controle_qualite',  false),
        ]);
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Un autre article utilise déjà cette référence interne.',
            'barcode.unique'   => 'Un autre article utilise déjà ce code-barres (EAN).',
        ];
    }

    /**
     * [Vente à perte interdite] Prix de vente ≥ prix d'achat pour un article vendable.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $purchase = (float) $this->input('purchase_price', 0);
            $sale     = (float) $this->input('sale_price', 0);

            if ($this->boolean('is_sellable') && $purchase > 0 && $sale > 0 && $sale < $purchase) {
                $validator->errors()->add(
                    'sale_price',
                    sprintf(
                        'Vente à perte interdite : le prix de vente (%s) ne peut pas être inférieur au prix d’achat (%s).',
                        number_format($sale, 0, ',', ' '),
                        number_format($purchase, 0, ',', ' ')
                    )
                );
            }
        });
    }
}
