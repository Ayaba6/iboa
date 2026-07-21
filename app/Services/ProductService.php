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

    public function create(array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $image) {
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
