<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\CuttingOptimization;
use App\Modules\Production\Services\CuttingOptimizerService;
use App\Modules\Production\Services\CuttingRemnantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CuttingController extends Controller
{
    public function __construct(
        private CuttingOptimizerService $optimizer,
        private CuttingRemnantService $remnants,
    ) {
        $this->middleware('permission:production.view')->only(['index', 'optimize']);
        $this->middleware('permission:production.create')->except(['index', 'optimize']);
    }

    public function index(): View
    {
        $optimizations = CuttingOptimization::with(['product', 'coil'])
            ->withCount('lines')->orderByDesc('id')->paginate(15);

        return view('production.cutting.index', ['plan' => null, 'input' => null, 'optimizations' => $optimizations]);
    }

    // ─── [Maquette Optimisation de découpe] fiches persistées ───

    public function create(): View
    {
        return view('production.cutting.form', $this->formData(new CuttingOptimization()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $opt = DB::transaction(function () use ($data, $request) {
            $opt = CuttingOptimization::create($data + ['company_id' => currentCompany()->id]);
            $this->syncLines($opt, $request);
            return $opt;
        });

        return redirect()->route('production.cutting.edit', $opt)->with('success', 'Optimisation enregistrée.');
    }

    public function edit(CuttingOptimization $optimization): View
    {
        $optimization->load('lines.product');

        return view('production.cutting.form', $this->formData($optimization));
    }

    public function update(Request $request, CuttingOptimization $optimization): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($optimization, $data, $request) {
            $optimization->update($data);
            $optimization->lines()->delete();
            $this->syncLines($optimization, $request);
        });

        return redirect()->route('production.cutting.edit', $optimization)->with('success', 'Optimisation mise à jour.');
    }

    public function destroyOptimization(CuttingOptimization $optimization): RedirectResponse
    {
        $optimization->delete();

        return redirect()->route('production.cutting')->with('success', 'Optimisation supprimée.');
    }

    /** Lance l'optimisation FFD sur les demandes et stocke les résultats. */
    public function run(CuttingOptimization $optimization): RedirectResponse
    {
        $optimization->load('lines');
        if ((float) $optimization->standard_length <= 0) {
            return back()->with('error', 'Longueur standard requise pour lancer l\'optimisation.');
        }

        $items = $optimization->lines
            ->map(fn ($l) => ['length' => (float) $l->requested_length_m, 'quantity' => (int) $l->quantity])
            ->filter(fn ($i) => $i['length'] > 0 && $i['quantity'] > 0)->values()->all();

        if (empty($items)) {
            return back()->with('error', 'Aucune demande valide à optimiser.');
        }

        $threshold = $optimization->valorize_offcuts ? (float) $optimization->min_reusable_offcut : 0;
        // Refente 2D si largeurs bobine + utile saisies, sinon découpe 1D longueur.
        $is2D = (float) $optimization->coil_width > 0 && (float) $optimization->useful_width > 0;

        try {
            $plan = $is2D
                ? $this->optimizer->optimize2D(
                    (float) $optimization->standard_length,
                    (float) $optimization->coil_width,
                    (float) $optimization->useful_width,
                    (float) $optimization->cut_tolerance_mm / 1000, // mm → m
                    $items,
                    $threshold,
                )
                : $this->optimizer->optimize(
                    (float) $optimization->standard_length,
                    (float) $optimization->cut_tolerance_mm / 1000,
                    $items,
                    $threshold,
                );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $optimization->update([
            'total_requested_m' => collect($items)->sum(fn ($i) => $i['length'] * $i['quantity']),
            'optimized_m'       => $plan['used'],
            'material_yield'    => $plan['combined_yield'] ?? $plan['yield'],
            'estimated_waste_m' => $plan['waste'],
            'reusable_offcut_m' => $plan['reusable_offcut'] ?? 0,
            'scrap_m'           => $plan['scrap'] ?? $plan['waste'],
            'cuts_count'        => collect($plan['bars'])->sum(fn ($b) => count($b['cuts'])),
            'coils_used'        => $plan['coils_needed'] ?? $plan['bars_count'],
            'strips_per_coil'   => $plan['strips_per_coil'] ?? 0,
            'width_yield'       => $plan['width_yield'] ?? 0,
            'plan'              => $plan,
            'status'            => 'optimisee',
        ]);

        $yield = $plan['combined_yield'] ?? $plan['yield'];

        return back()->with('success', 'Optimisation calculée — rendement '.number_format($yield, 1, ',', ' ').' %.');
    }

    /**
     * Clôture la découpe : fige le plan et ré-entre en stock la chute réutilisable
     * (dépôt dédié « Chutes », valorisée au PMP de la matière). Idempotent.
     */
    public function close(CuttingOptimization $optimization): RedirectResponse
    {
        if ($optimization->status === 'cloturee') {
            return back()->with('error', 'Découpe déjà clôturée.');
        }
        if (! in_array($optimization->status, ['optimisee', 'validee', 'planifiee'], true)) {
            return back()->with('error', 'Lancez l\'optimisation avant de clôturer la découpe.');
        }

        $movement = $this->remnants->reenter($optimization);
        $optimization->update(['status' => 'cloturee']);

        if ($movement) {
            return back()->with('success', 'Découpe clôturée — '.number_format((float) $optimization->reusable_offcut_m, 2, ',', ' ').' m de chute réutilisable ré-entrés en stock (dépôt Chutes).');
        }

        return back()->with('success', 'Découpe clôturée.');
    }

    /**
     * [Maquette] Importer les demandes depuis les commandes clients en cours.
     * Retourne les lignes de commandes validées/en cours (JSON) pour remplir la table Demandes.
     */
    public function importLines(Request $request)
    {
        $items = \App\Models\OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['validee', 'en_cours', 'partiellement_livre']))
            ->when($request->input('product_id'), fn ($q, $v) => $q->where('product_id', $v))
            ->with(['order.client:id,name', 'product:id,name'])
            ->latest('id')->limit(30)->get();

        return response()->json($items->map(fn ($it) => [
            'order_reference'    => $it->order?->reference,
            'client'             => $it->order?->client?->name,
            'product_id'         => $it->product_id,
            'requested_length_m' => (float) ($it->metrage_par_tole ?: 0) ?: (float) $it->quantity,
            'quantity'           => (int) ($it->nb_toles ?: $it->quantity),
            'priorite'           => 'normale',
            'delivery_date'      => optional($it->order?->delivery_date)->format('Y-m-d'),
            'status'             => 'planifiee',
        ])->values());
    }

    private function syncLines(CuttingOptimization $opt, Request $request): void
    {
        foreach ((array) $request->input('lines', []) as $i => $row) {
            if (empty($row['order_reference']) && empty($row['requested_length_m'])) {
                continue;
            }
            $len = (float) ($row['requested_length_m'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $opt->lines()->create([
                'order_reference'    => $row['order_reference'] ?? null,
                'client'             => $row['client'] ?? null,
                'product_id'         => $row['product_id'] ?? null,
                'requested_length_m' => $len,
                'quantity'           => $qty,
                'total_m'            => $len * $qty,
                'priorite'           => $row['priorite'] ?? 'normale',
                'delivery_date'      => $row['delivery_date'] ?? null,
                'status'             => $row['status'] ?? 'planifiee',
                'sort_order'         => $i,
            ]);
        }
    }

    private function formData(CuttingOptimization $opt): array
    {
        return [
            'optimization' => $opt,
            'coils'        => \App\Modules\Production\Models\Coil::where('status', '!=', 'epuisee')->orderByDesc('received_at')->get(['id', 'reference', 'width', 'thickness']),
            'products'     => \App\Models\Product::orderBy('name')->get(['id', 'name', 'reference']),
            'lines'        => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'code'                => ['nullable', 'string', 'max:30'],
            'site'                => ['nullable', 'string', 'max:20'],
            'atelier'             => ['nullable', 'string', 'max:60'],
            'production_line_id'  => ['nullable', 'integer', 'exists:production_lines,id'],
            'type_optimisation'   => ['nullable', 'string', 'max:30'],
            'coil_id'             => ['nullable', 'integer', 'exists:coils,id'],
            'product_id'          => ['nullable', 'integer', 'exists:products,id'],
            'profil'              => ['nullable', 'string', 'max:30'],
            'thickness'           => ['nullable', 'numeric', 'min:0'],
            'coil_width'          => ['nullable', 'numeric', 'min:0'],
            'useful_width'        => ['nullable', 'numeric', 'min:0'],
            'standard_length'     => ['nullable', 'numeric', 'min:0'],
            'method'              => ['nullable', 'string', 'max:30'],
            'execution_date'      => ['nullable', 'date'],
            'priorite'            => ['nullable', 'string', 'max:15'],
            'status'              => ['nullable', 'string', 'max:20'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'min_reusable_offcut' => ['nullable', 'numeric', 'min:0'],
            'cut_tolerance_mm'    => ['nullable', 'numeric', 'min:0'],
            'lines'                        => ['nullable', 'array'],
            'lines.*.order_reference'      => ['nullable', 'string', 'max:40'],
            'lines.*.client'               => ['nullable', 'string', 'max:100'],
            'lines.*.product_id'           => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.requested_length_m'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity'             => ['nullable', 'integer', 'min:0'],
            'lines.*.priorite'             => ['nullable', 'string', 'max:15'],
            'lines.*.delivery_date'        => ['nullable', 'date'],
            'lines.*.status'               => ['nullable', 'string', 'max:20'],
        ]) + [
            'allow_order_mixing'        => $request->boolean('allow_order_mixing'),
            'respect_client_lot'        => $request->boolean('respect_client_lot'),
            'group_by_color'            => $request->boolean('group_by_color'),
            'optimize_by_delivery_date' => $request->boolean('optimize_by_delivery_date'),
            'valorize_offcuts'          => $request->boolean('valorize_offcuts'),
        ];
    }

    public function optimize(Request $request): View
    {
        $data = $request->validate([
            'stock_length'        => ['required', 'numeric', 'gt:0'],
            'kerf'                => ['nullable', 'numeric', 'min:0'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.length'      => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity'    => ['nullable', 'integer', 'min:0'],
        ]);

        $plan = null;
        $error = null;
        try {
            $plan = $this->optimizer->optimize(
                (float) $data['stock_length'],
                (float) ($data['kerf'] ?? 0),
                $data['items'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $error = collect($e->errors())->flatten()->first();
        }

        return view('production.cutting.index', ['plan' => $plan, 'input' => $data, 'error' => $error]);
    }
}
