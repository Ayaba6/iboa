<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Reception;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityInspectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        // Enregistrer un contrôle est un acte QUALITÉ. Le garder derrière la seule
        // `production.update` verrouillait `responsable_qualite` — qui porte
        // `quality.manage` mais pas `production.update` — hors de son propre module.
        // Ajout par OU : aucun droit existant n'est retiré.
        $this->middleware('permission:production.update|quality.manage')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $inspections = QualityInspection::with(['reception', 'product', 'controller'])
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'total'        => QualityInspection::count(),
            'non_conforme' => QualityInspection::where('status', 'non_conforme')->count(),
            'rejected'     => (float) QualityInspection::sum('quantity_rejected'),
        ];

        return view('qualite.inspections.index', compact('inspections', 'stats'));
    }

    public function create(Request $request): View
    {
        $inspection = new QualityInspection(['type' => $request->input('type', 'reception'), 'inspected_at' => now()]);
        if ($r = $request->input('reception_id')) {
            $inspection->reception_id = $r;
        }

        return view('qualite.inspections.form', $this->formData($inspection));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        $data['reference']  = $request->input('reference') ?: $this->nextRef();
        $inspection = QualityInspection::create($data);
        $this->syncCharacteristics($inspection, $request);

        return redirect()->route('qualite.inspections.index')->with('success', 'Contrôle qualité enregistré : ' . $data['reference']);
    }

    public function edit(QualityInspection $inspection): View
    {
        $inspection->load('characteristics');

        return view('qualite.inspections.form', $this->formData($inspection));
    }

    public function update(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $previousStatus = $inspection->status;
        $inspection->update($this->validateData($request));
        $inspection->characteristics()->delete();
        $this->syncCharacteristics($inspection, $request);

        // [Sync ERP] disposition qualité journalisée quand le contrôle est tranché :
        // conforme → stock disponible ; non conforme → traitement NC/rebut.
        if ($previousStatus !== $inspection->status && in_array($inspection->status, ['conforme', 'non_conforme', 'partiel'], true)) {
            app(\App\Services\Sync\SyncOrchestrator::class)->run(
                sourceModule: 'qualite',
                targetModule: 'stock',
                eventName: 'quality_inspection.decided',
                action: 'record_disposition',
                source: $inspection,
                callback: fn () => null, // trace de la décision — les flux stock NC/rebut ont leurs propres workflows
                payload: ['status' => $inspection->status, 'lot_number' => $inspection->lot_number],
                rethrow: false,
                idempotent: false,
            );
            event(new \App\Events\QualityControlValidated($inspection));
        }

        return redirect()->route('qualite.inspections.index')->with('success', 'Contrôle mis à jour.');
    }

    /** [Maquette Contrôle qualité] Enregistre les caractéristiques contrôlées. */
    private function syncCharacteristics(QualityInspection $inspection, Request $request): void
    {
        foreach ((array) $request->input('characteristics', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $inspection->characteristics()->create([
                'number'         => $row['number'] ?? ($i + 1),
                'name'           => $row['name'],
                'spec_min'       => $row['spec_min'] ?? null,
                'spec_max'       => $row['spec_max'] ?? null,
                'unit'           => $row['unit'] ?? null,
                'control_method' => $row['control_method'] ?? null,
                'frequency'      => $row['frequency'] ?? 'chaque_lot',
                'result'         => $row['result'] ?? null,
                'conformity'     => $row['conformity'] ?? null,
                'sort_order'     => $i,
            ]);
        }
    }

    public function destroy(QualityInspection $inspection): RedirectResponse
    {
        $inspection->delete();

        return back()->with('success', 'Contrôle supprimé.');
    }

    private function nextRef(): string
    {
        return 'CQ-' . str_pad((string) (QualityInspection::withoutGlobalScopes()->where('company_id', currentCompany()->id)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function formData(QualityInspection $inspection): array
    {
        return [
            'inspection'  => $inspection,
            'receptions'  => Reception::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'products'    => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'employees'   => Employee::orderBy('last_name')->get(),
            'orders'      => \App\Modules\Production\Models\ProductionOrder::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'lines'       => \App\Modules\Production\Models\ProductionLine::orderBy('name')->get(['id', 'name']),
            'workCenters' => \App\Modules\Production\Models\WorkCenter::orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'type'               => ['required', 'in:reception,en_cours,produit_fini'],
            'reception_id'       => ['nullable', 'integer', 'exists:receptions,id'],
            'production_order_id'=> ['nullable', 'integer', 'exists:production_orders,id'],
            'product_id'         => ['nullable', 'integer', 'exists:products,id'],
            'controller_id'      => ['nullable', 'integer', 'exists:employees,id'],
            'inspected_at'       => ['nullable', 'date'],
            'status'             => ['required', 'in:en_cours,conforme,non_conforme,partiel'],
            'quantity_checked'   => ['nullable', 'numeric', 'min:0'],
            'quantity_rejected'  => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            // [Maquette Contrôle qualité]
            'reference'          => ['nullable', 'string', 'max:30'],
            'lot_number'         => ['nullable', 'string', 'max:60'],
            'atelier'            => ['nullable', 'string', 'max:60'],
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'work_center_id'     => ['nullable', 'integer', 'exists:work_centers,id'],
            'quantity_unit'      => ['nullable', 'string', 'max:15'],
            'sampling_method'    => ['nullable', 'string', 'max:30'],
            'norm_reference'     => ['nullable', 'string', 'max:60'],
            'inspection_stage'   => ['nullable', 'string', 'max:20'],
            'characteristics'                   => ['nullable', 'array'],
            'characteristics.*.number'          => ['nullable', 'integer', 'min:0'],
            'characteristics.*.name'            => ['nullable', 'string', 'max:100'],
            'characteristics.*.spec_min'        => ['nullable', 'string', 'max:20'],
            'characteristics.*.spec_max'        => ['nullable', 'string', 'max:20'],
            'characteristics.*.unit'            => ['nullable', 'string', 'max:15'],
            'characteristics.*.control_method'  => ['nullable', 'string', 'max:60'],
            'characteristics.*.frequency'       => ['nullable', 'string', 'max:30'],
            'characteristics.*.result'          => ['nullable', 'string', 'max:30'],
            'characteristics.*.conformity'      => ['nullable', 'string', 'max:20'],
        ]);
    }
}
