<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Http\Request;

class ProductPriceTierController extends Controller
{
    public function __construct()
    {
        // [SEC-PHASE1] Modifier une grille tarifaire revient à modifier un article :
        // on exige donc la permission "products.edit" sur l'ensemble du controller.
        $this->middleware('permission:products.edit');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'       => 'required|exists:products,id',
            'label'            => 'nullable|string|max:80',
            'price'            => 'required|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'client_id'        => 'nullable|exists:clients,id',
            'site_id'          => 'nullable|exists:warehouses,id',
            'client_category'  => 'nullable|in:gros,semi-gros,detail',
            'min_quantity'     => 'nullable|numeric|min:0',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date|after_or_equal:starts_at',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        // [SEC-PHASE1] Vérifier qu'on a bien le droit de modifier le produit visé.
        $this->authorize('update', Product::findOrFail($data['product_id']));

        ProductPriceTier::create($data);

        return redirect()->back()->with('success', 'Tarif spécial ajouté.');
    }

    public function destroy(ProductPriceTier $tier)
    {
        // [SEC-PHASE1] Idem pour la suppression.
        $this->authorize('update', $tier->product);

        $productId = $tier->product_id;
        $tier->delete();

        return redirect()->route('products.show', $productId)->with('success', 'Tarif supprimé.');
    }
}
