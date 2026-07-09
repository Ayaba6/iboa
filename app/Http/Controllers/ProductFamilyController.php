<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductFamily\StoreProductFamilyRequest;
use App\Http\Requests\ProductFamily\UpdateProductFamilyRequest;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\CostCenter;
use App\Models\ProductFamily;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductFamilyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index()
    {
        $families = ProductFamily::with(['children' => fn($q) => $q->withCount('products')])
            ->withCount('products')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('product-families.index', compact('families'));
    }

    public function create()
    {
        return view('product-families.create', $this->formData());
    }

    public function store(StoreProductFamilyRequest $request)
    {
        $family = ProductFamily::create($this->normalize($request));
        $this->syncDepots($family, $request);
        $this->uploadDocuments($family, $request);

        return redirect()->route('product-families.index')->with('success', 'Catégorie créée avec succès.');
    }

    public function edit(ProductFamily $family)
    {
        $family->load(['warehouses', 'attachments']);

        return view('product-families.edit', array_merge(
            ['family' => $family],
            $this->formData($family->id)
        ));
    }

    /** Variables de référence partagées par les formulaires create/edit. */
    private function formData(?int $excludeId = null): array
    {
        return [
            'parents'         => ProductFamily::where('depth', 0)
                                    ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                                    ->orderBy('name')->get(),
            'accounts'        => $this->loadAccountsByType(),
            'units'           => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'warehouses'      => Warehouse::orderBy('name')->get(['id', 'name', 'code', 'can_production', 'can_sale', 'can_purchase', 'can_stock']),
            'typeFluxOptions' => ProductFamily::typeFluxOptions(),
            'costCenters'     => CostCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'taxRates'        => TaxRate::where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate']),
            'familles'        => ProductFamily::orderBy('name')->get(['id', 'code', 'name']),
            'typeCategorieOptions' => [
                'matiere_premiere' => 'Matière première',
                'produit_fini'     => 'Produit fini',
                'marchandise'      => 'Article de négoce',
                'consommable'      => 'Consommable',
                'service'          => 'Service',
            ],
            // [SAGE parité] Panneau gauche « sélection » : dernières catégories.
            'selectorFamilies' => ProductFamily::orderByDesc('id')->limit(20)
                                    ->get(['id', 'code', 'name', 'type_categorie']),
        ];
    }

    /** Normalise les cases à cocher et champs dérivés (partagé create/update). */
    private function normalize(\Illuminate\Http\Request $request): array
    {
        $data = $request->validated();
        foreach ([
            'gestion_stock', 'stock_negatif', 'gestion_lot', 'lot_obligatoire',
            'suivi_bobine', 'utilisable_production', 'actif_tous_sites',
            'gestion_numero_serie', 'controle_qualite', 'cq_entree', 'cq_sortie',
            'numerotation_auto', 'prix_plancher_obligatoire', 'autoriser_surcharge',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['is_active'] = $request->boolean('is_active', true);
        $data['depth']     = ($data['parent_id'] ?? null) ? 1 : 0;
        $data['type_flux'] = $data['type_flux'] ?? [];

        // Clés hors colonnes (traitées séparément)
        unset($data['depots'], $data['documents']);

        return $data;
    }

    /** Synchronise le pivot des dépôts autorisés (4 capacités par dépôt). */
    private function syncDepots(ProductFamily $family, \Illuminate\Http\Request $request): void
    {
        $depots = $request->input('depots', []);
        $sync = [];
        foreach ($depots as $warehouseId => $caps) {
            $sync[(int) $warehouseId] = [
                'can_production' => ! empty($caps['can_production']),
                'can_sale'       => ! empty($caps['can_sale']),
                'can_purchase'   => ! empty($caps['can_purchase']),
                'can_stock'      => ! empty($caps['can_stock']),
            ];
        }
        $family->warehouses()->sync($sync);
    }

    /** Enregistre les pièces jointes (documents) de la catégorie. */
    private function uploadDocuments(ProductFamily $family, \Illuminate\Http\Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/productfamily/'.$family->id, 'local');
            $family->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    /**
     * Récupère les comptes comptables groupés par usage (vente / achat / stock).
     * Filtre sur le 1er chiffre du code (norme SYSCOA/OHADA) :
     *  - 7xx : Produits (ventes)
     *  - 6xx : Charges (achats)
     *  - 3xx : Stocks
     */
    private function loadAccountsByType(): array
    {
        $all = Account::active()->postable()->orderBy('code')->get(['id', 'code', 'name']);

        return [
            'sale'     => $all->filter(fn($a) => str_starts_with($a->code, '7'))->values(),
            'purchase' => $all->filter(fn($a) => str_starts_with($a->code, '6'))->values(),
            'stock'    => $all->filter(fn($a) => str_starts_with($a->code, '3'))->values(),
        ];
    }

    public function update(UpdateProductFamilyRequest $request, ProductFamily $family)
    {
        $family->update($this->normalize($request));
        $this->syncDepots($family, $request);
        $this->uploadDocuments($family, $request);

        return redirect()->route('product-families.index')->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(ProductFamily $family)
    {
        if ($family->products()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des articles appartiennent à cette famille.');
        }
        if ($family->children()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : cette famille contient des sous-familles.');
        }
        $family->delete();
        return redirect()->route('product-families.index')->with('success', 'Famille supprimée.');
    }

    /**
     * Quick-create via AJAX — retourne JSON {id, name}.
     */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'nullable|string|max:30|unique:product_families,code',
            'parent_id' => 'nullable|exists:product_families,id',
        ]);
        $data['is_active'] = true;
        $data['depth']     = isset($data['parent_id']) ? 1 : 0;

        $family = ProductFamily::create($data);

        $label = $data['depth'] === 1
            ? ProductFamily::find($data['parent_id'])?->name . ' › ' . $family->name
            : $family->name;

        return response()->json(['id' => $family->id, 'name' => $family->name, 'label' => $label], 201);
    }
}
