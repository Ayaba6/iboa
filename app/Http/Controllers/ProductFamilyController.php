<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductFamily\StoreProductFamilyRequest;
use App\Http\Requests\ProductFamily\UpdateProductFamilyRequest;
use App\Models\ProductFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductFamilyController extends Controller
{

    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(Request $request)
    {
        // [X3] Deux sous-modules : Catégories (racines, arborescence) / Familles (sous-familles, à plat)
        $niveau = $request->input('niveau') === 'famille' ? 'famille' : 'categorie';
        // Défaut : actives seulement — les familles archivées (ex-référentiel plat)
        // restent consultables via ?statut=toutes.
        $toutes = $request->input('statut') === 'toutes';

        if ($niveau === 'famille') {
            $families = ProductFamily::with('parent:id,code,name')
                ->withCount('subProducts')
                ->whereNotNull('parent_id')
                ->when(! $toutes, fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        } else {
            $families = ProductFamily::with(['children' => fn($q) => $q->withCount('subProducts')])
                ->withCount('products')
                ->whereNull('parent_id')
                ->when(! $toutes, fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        }

        // Compteurs GLOBAUX (les sommes sur $families ne couvriraient que la page
        // courante) — mais sur la MÊME population que la liste. Ils comptaient
        // toutes les familles, archivées comprises, pendant que la liste n'en
        // montrait que les actives : le bandeau annonçait 12 au-dessus de 3 lignes.
        $actives = fn ($q) => $toutes ? $q : $q->where('is_active', true);

        $stats = [
            'racines'  => $actives(ProductFamily::whereNull('parent_id'))->count(),
            'sous'     => $actives(ProductFamily::whereNotNull('parent_id'))->count(),
            // Un article se rattache par sa famille OU par sa sous-famille :
            // ne compter que family_id laisserait de côté ceux classés au seul
            // second niveau. La recherche d'articles couvre déjà les deux axes.
            'articles' => \App\Models\Product::where(fn ($q) => $q
                ->whereNotNull('family_id')->orWhereNotNull('sub_family_id'))->count(),
            // Ce qui est masqué doit être dit, pas seulement soustrait.
            'archivees' => $toutes ? 0 : ProductFamily::where('is_active', false)->count(),
        ];

        return view('product-families.index', compact('families', 'niveau', 'stats'));
    }

    public function create()
    {
        return view('product-families.create', $this->formData());
    }

    public function store(StoreProductFamilyRequest $request)
    {
        ProductFamily::create($this->normalize($request));

        return redirect()->route('product-families.index')->with('success', 'Famille créée avec succès.');
    }

    /** [X3 §16] Fiche famille à onglets : Général / Sous-familles / Articles / Statistiques. */
    public function show(ProductFamily $family)
    {
        return view('product-families.show', array_merge($this->ficheData($family), $this->formData()));
    }

    public function edit(ProductFamily $family)
    {
        // [X3] L'écran de modification porte les mêmes onglets que la fiche
        // (Général éditable + Sous-familles / Articles / Statistiques en lecture).
        return view('product-families.edit', array_merge(
            $this->ficheData($family),
            // L'exclusion de la famille elle-même comme parent se fait dans
            // `_form.blade.php` (`where('id','!=',$f->id)`) : l'argument était
            // reçu ici sans jamais servir.
            $this->formData()
        ));
    }

    /** Données de fiche partagées show/edit (onglets X3). */
    private function ficheData(ProductFamily $family): array
    {
        $family->load(['parent', 'children' => fn ($q) => $q->withCount('subProducts')])
            ->loadCount('products');

        // [X3 §5] Sous-famille : les articles y sont rattachés par sub_family_id.
        $articlesQuery = $family->parent_id ? $family->subProducts() : $family->products();

        $articles = (clone $articlesQuery)->with('itemCategory:id,code')->orderBy('name')->limit(200)
            ->get(['id', 'reference', 'name', 'item_category_id', 'is_active']);

        // Statistiques simples : CA facturé YTD des articles de la famille.
        $caYtd = (int) \App\Models\InvoiceItem::whereHas('invoice', fn ($q) => $q
                ->whereYear('issued_at', now()->year)->whereNotIn('status', ['annulee', 'brouillon']))
            ->whereIn('product_id', (clone $articlesQuery)->pluck('products.id'))
            ->sum('line_total_ht');

        $articlesCount = (clone $articlesQuery)->count();

        return compact('family', 'articles', 'caYtd', 'articlesCount');
    }

    /**
     * Variables partagées par les formulaires create/edit.
     * [X3] Réduit au strict nécessaire depuis la séparation catégorie/famille :
     * le form (classement pur) calcule lui-même ses parentes ; seul le panneau
     * de sélection gauche a besoin de données.
     */
    private function formData(): array
    {
        return [
            // Le panneau de navigation était plafonné aux 20 dernières créées,
            // triées par id décroissant. Son champ « Filtrer » ne cherche QUE
            // dans les lignes chargées : une famille hors des vingt devenait
            // introuvable — « Tôles bac », racine du métier, en faisait partie.
            //
            // Les familles sont un référentiel de classement, donc peu nombreux
            // par nature. On les charge toutes, dans l'ordre où l'œil les
            // cherche : les racines d'abord, puis alphabétiquement.
            'selectorFamilies' => ProductFamily::where('is_active', true)
                                    ->orderByRaw('parent_id IS NOT NULL')
                                    ->orderBy('name')
                                    ->get(['id', 'code', 'name', 'parent_id']),
        ];
    }

    /** [X3] Classement pur : seuls les champs de classement sont acceptés. */
    private function normalize(\Illuminate\Http\Request $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['depth']     = ($data['parent_id'] ?? null) ? 1 : 0;

        return $data;
    }


    public function update(UpdateProductFamilyRequest $request, ProductFamily $family)
    {
        // [X3] Plus de syncDepots/uploadDocuments : le form ne poste plus ces
        // champs — l'ancien sync([]) vidait le pivot dépôts à chaque update.
        $family->update($this->normalize($request));

        return redirect()->route('product-families.index')->with('success', 'Famille mise à jour.');
    }

    public function destroy(ProductFamily $family)
    {
        if ($family->products()->count() > 0 || $family->subProducts()->count() > 0) {
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
