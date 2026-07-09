<?php
namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::with(['family', 'brand', 'unit', 'taxRate', 'createdBy:id,name', 'productStocks'])
            ->when(isset($filters['search']), fn($q) => $q->where(function($q2) use ($filters) {
                $q2->where('name', 'like', "%{$filters['search']}%")
                   ->orWhere('reference', 'like', "%{$filters['search']}%")
                   ->orWhere('code_article', 'like', "%{$filters['search']}%")
                   ->orWhere('barcode', 'like', "%{$filters['search']}%");
            }))
            ->when(isset($filters['family_id']), fn($q) => $q->where('family_id', $filters['family_id']))
            // [PHASE E] Recherche avancée : code article, familles 3 niveaux, statut
            ->when(!empty($filters['code_article']), fn($q) => $q->where('code_article', 'like', "%{$filters['code_article']}%"))
            ->when(!empty($filters['famille1_id']), fn($q) => $q->where('famille1_id', $filters['famille1_id']))
            ->when(!empty($filters['famille2_id']), fn($q) => $q->where('famille2_id', $filters['famille2_id']))
            ->when(!empty($filters['famille3_id']), fn($q) => $q->where('famille3_id', $filters['famille3_id']))
            ->when(!empty($filters['statut']), fn($q) => $q->where('statut', $filters['statut']))
            ->when(isset($filters['brand_id']), fn($q) => $q->where('brand_id', $filters['brand_id']))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['low_stock']), fn($q) => $q->whereHas('productStocks', function($q2) {
                $q2->whereRaw('quantity <= products.stock_min');
            }))
            ->orderBy('name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): Product
    {
        return Product::with(['family', 'brand', 'unit', 'taxRate', 'components.component', 'productStocks.warehouse', 'productPriceTiers.client'])->findOrFail($id);
    }

    public function getLowStockProducts(int $limit = 100): Collection
    {
        return Product::select(['id', 'name', 'reference', 'stock_min', 'is_active'])
            ->whereHas('productStocks', function ($q) {
                $q->whereRaw('quantity <= products.stock_min AND products.stock_min > 0');
            })
            ->with(['productStocks' => fn ($q) => $q->select(['id', 'product_id', 'warehouse_id', 'quantity'])
                ->with('warehouse:id,name')])
            ->limit($limit)
            ->get();
    }
}
