<?php
namespace App\Services;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Repositories\ProductRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private ProductRepository $repository) {}

    /**
     * [X3 §5/§14] La sous-famille choisie doit appartenir à la famille de
     * l'article (parent_id = family_id). Sous-famille facultative.
     */
    private function assertSubFamilyCoherent(array $data, ?Product $product = null): void
    {
        $subId = $data['sub_family_id'] ?? null;
        if (! $subId) {
            return;
        }
        $familyId = $data['family_id'] ?? $product?->family_id;
        $sub = \App\Models\ProductFamily::find($subId);
        if (! $sub || ! $sub->parent_id) {
            throw new \RuntimeException('La sous-famille sélectionnée n\'est pas une sous-famille (aucune famille parente).');
        }
        if (! $familyId || (int) $sub->parent_id !== (int) $familyId) {
            throw new \RuntimeException(sprintf(
                'Sous-famille incohérente : « %s » appartient à la famille « %s », pas à celle de l\'article.',
                $sub->name, $sub->parent?->name ?? '?'
            ));
        }
    }

    /**
     * [X3 §10] Enregistre les attributs dynamiques (attributes[code] => valeur)
     * définis par la catégorie ; exige les attributs marqués obligatoires.
     */
    private function syncCategoryAttributes(Product $product, array $input): void
    {
        $cat = $product->itemCategory()->with('attributes')->first();
        if (! $cat || $cat->attributes->isEmpty()) {
            return;
        }
        $values = (array) ($input['attributes'] ?? []);
        foreach ($cat->attributes as $attr) {
            $value = $values[$attr->code] ?? null;
            if ($attr->required && ($value === null || $value === '')) {
                throw new \RuntimeException(sprintf(
                    'Attribut « %s » obligatoire pour la catégorie %s.', $attr->label, $cat->code
                ));
            }
            if ($attr->type === 'select' && $value !== null && $value !== ''
                && ! in_array($value, (array) $attr->options, true)) {
                throw new \RuntimeException(sprintf(
                    'Valeur « %s » invalide pour l\'attribut %s (%s).',
                    $value, $attr->label, implode(', ', (array) $attr->options)
                ));
            }
            if ($value !== null && $value !== '') {
                $product->attributeValues()->updateOrCreate(
                    ['category_attribute_id' => $attr->id],
                    ['value' => (string) $value]
                );
            }
        }
    }

    public function create(array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $image) {
            $this->assertSubFamilyCoherent($data);

            // [X3 §7] Héritage catégorie → article à la CRÉATION : la catégorie pose
            // les défauts (flux, stratégie, stock, unités, comptes) ; la saisie ne
            // surcharge que les champs autorisés par la catégorie. Jamais rétroactif.
            if (! empty($data['item_category_id'])) {
                $cat = \App\Models\ItemCategory::find($data['item_category_id']);
                if ($cat) {
                    $data = app(\App\Services\CategoryDefaultsService::class)
                        ->apply($data, $cat, isset($data['site_id']) ? (int) $data['site_id'] : null);
                }
            }

            if ($image) {
                $data['image'] = $image->store('products', 'public');
            }
            if (empty($data['reference'])) {
                $data['reference'] = $this->generateReference();
            }
            $product = Product::create($data);

            // [X3 §10] Attributs dynamiques de la catégorie (obligatoires contrôlés).
            $this->syncCategoryAttributes($product, $data);

            if (!empty($data['components'])) {
                foreach ($data['components'] as $component) {
                    $product->components()->create($component);
                }
            }
            return $product->load(['family', 'brand', 'unit', 'taxRate']);
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($product, $data, $image) {
            $this->assertSubFamilyCoherent($data, $product);

            // [X3 §15.1] Changement de catégorie INTERDIT dès que l'article a des
            // mouvements de stock : le modèle de gestion ne peut plus changer
            // librement (stock, valorisation et compta en dépendent).
            if (array_key_exists('item_category_id', $data)
                && (int) $data['item_category_id'] !== (int) $product->item_category_id
                && $product->stockMovements()->exists()) {
                throw new \RuntimeException(
                    'Changement de catégorie refusé : l\'article a des mouvements de stock. '
                    . 'Créez un nouvel article ou passez par une demande contrôlée.'
                );
            }

            // [X3 §15.5] Changement d'UNITÉ DE STOCK interdit dès qu'il existe des
            // mouvements : l'historique perdrait son sens (quantités hétérogènes).
            if (array_key_exists('unit_id', $data)
                && (int) $data['unit_id'] !== (int) $product->unit_id
                && $product->stockMovements()->exists()) {
                throw new \RuntimeException(
                    'Changement d\'unité de stock refusé : des mouvements existent. '
                    . 'Créez un nouvel article avec la bonne unité.'
                );
            }

            // [X3 §15.2] Passage stocké → non stocké interdit si un stock existe.
            if (array_key_exists('is_stockable', $data)
                && ! $data['is_stockable'] && $product->is_stockable
                && \App\Models\ProductStock::where('product_id', $product->id)->where('quantity', '>', 0)->exists()) {
                throw new \RuntimeException(
                    'Impossible de rendre l\'article non stocké : un stock physique existe encore.'
                );
            }

            if ($image) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $image->store('products', 'public');
            }
            $product->update($data);

            // [X3 §10] Attributs dynamiques (mêmes règles qu'à la création).
            if (array_key_exists('attributes', $data)) {
                $this->syncCategoryAttributes($product->fresh('itemCategory'), $data);
            }

            if (isset($data['components'])) {
                $product->components()->delete();
                foreach ($data['components'] as $component) {
                    $product->components()->create($component);
                }
            }
            return $product->fresh(['family', 'brand', 'unit', 'taxRate']);
        });
    }

    public function delete(Product $product): bool
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        return $product->delete();
    }

    private function generateReference(): string
    {
        $last = Product::withTrashed()->orderByDesc('id')->value('reference');
        $num  = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
        return 'ART-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    public function getFamiliesTree(): \Illuminate\Database\Eloquent\Collection
    {
        return ProductFamily::whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();
    }
}
