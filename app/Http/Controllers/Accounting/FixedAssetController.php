<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function __construct(private FixedAssetService $service) {}

    // ── Index ────────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = FixedAsset::where('company_id', Auth::user()->company_id)
            ->with(['depreciations' => fn ($q) => $q->where('is_posted', true)])
            ->orderByDesc('acquisition_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('code', 'like', $s));
        }

        $assets = $query->paginate(20)->withQueryString();

        // Totaux
        $totals = FixedAsset::where('company_id', Auth::user()->company_id)
            ->where('status', 'en_service')
            ->selectRaw('SUM(acquisition_cost) as total_cost')
            ->first();

        $cumulatedDepr = FixedAssetDepreciation::whereHas('fixedAsset', fn ($q) => $q->where('company_id', Auth::user()->company_id))
            ->where('is_posted', true)
            ->sum('depreciation_amount');

        return view('comptabilite.immobilisations.index', [
            'assets'         => $assets,
            'categoryLabels' => FixedAsset::$categoryLabels,
            'statusLabels'   => FixedAsset::$statusLabels,
            'totalCost'      => (int) ($totals->total_cost ?? 0),
            'totalDepr'      => (int) $cumulatedDepr,
            'filters'        => $request->only(['status', 'category', 'search']),
        ]);
    }

    // ── Create ───────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('comptabilite.immobilisations.create', [
            'categoryLabels'  => FixedAsset::$categoryLabels,
            'categoryDefaults'=> FixedAsset::$categoryDefaults,
            'methodLabels'    => FixedAsset::$methodLabels,
        ]);
    }

    // ── Store ────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'category'            => ['required', 'in:' . implode(',', array_keys(FixedAsset::$categoryLabels))],
            'acquisition_date'    => ['required', 'date'],
            'commissioning_date'  => ['required', 'date', 'gte:acquisition_date'],
            'acquisition_cost'    => ['required', 'integer', 'min:1'],
            'residual_value'      => ['nullable', 'integer', 'min:0'],
            'useful_life_years'   => ['required', 'integer', 'min:0', 'max:99'],
            'depreciation_method' => ['required', 'in:lineaire,degressif'],
            'asset_account'       => ['required', 'string', 'max:10'],
            'depr_account'        => ['nullable', 'string', 'max:10'],
            'charge_account'      => ['nullable', 'string', 'max:10'],
            'vendor'              => ['nullable', 'string', 'max:255'],
            'invoice_ref'         => ['nullable', 'string', 'max:100'],
            'notes'               => ['nullable', 'string'],
            // [Maquette X3]
            'famille'             => ['nullable', 'string', 'max:30'],
            'asset_type'          => ['nullable', 'string', 'max:30'],
            'serial_number'       => ['nullable', 'string', 'max:60'],
            'accessory_cost'      => ['nullable', 'integer', 'min:0'],
            'periodicity'         => ['nullable', 'in:mensuelle,trimestrielle,annuelle'],
            'prorata_temporis'    => ['nullable', 'boolean'],
            'degressive_rate'     => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'service_code'        => ['nullable', 'string', 'max:30'],
            'responsable'         => ['nullable', 'string', 'max:100'],
            'utilisateur'         => ['nullable', 'string', 'max:100'],
            'localisation'        => ['nullable', 'string', 'max:60'],
            'batiment'            => ['nullable', 'string', 'max:60'],
            'bureau'              => ['nullable', 'string', 'max:60'],
            'centre_analytique'   => ['nullable', 'string', 'max:30'],
            'projet'              => ['nullable', 'string', 'max:60'],
            'code_activite'       => ['nullable', 'string', 'max:30'],
        ]);

        $data['residual_value'] = $data['residual_value'] ?? 0;
        $data['depr_account']   = $data['depr_account']   ?? '';
        $data['charge_account'] = $data['charge_account'] ?? '';
        $data['accessory_cost'] = $data['accessory_cost'] ?? 0;
        $data['prorata_temporis'] = $request->boolean('prorata_temporis', true);

        try {
            $asset = $this->service->create($data);
            return redirect()->route('comptabilite.immobilisations.show', $asset)
                ->with('success', "Immobilisation « {$asset->code} — {$asset->name} » créée avec son plan d'amortissement.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── Show ─────────────────────────────────────────────────────────────────────

    public function show(FixedAsset $immobilisation): View
    {
        $immobilisation->load(['depreciations.journalEntry', 'createdBy']);

        return view('comptabilite.immobilisations.show', [
            'asset'          => $immobilisation,
            'categoryLabels' => FixedAsset::$categoryLabels,
            'methodLabels'   => FixedAsset::$methodLabels,
            'statusLabels'   => FixedAsset::$statusLabels,
        ]);
    }

    // ── Post depreciation ────────────────────────────────────────────────────────

    public function postDepreciation(FixedAssetDepreciation $depreciation): RedirectResponse
    {
        // Sécurité : l'actif doit appartenir à la company de l'utilisateur
        $asset = $depreciation->fixedAsset;
        abort_if($asset->company_id !== Auth::user()->company_id, 403);

        try {
            $this->service->postDepreciation($depreciation);
            return back()->with('success', sprintf(
                'Dotation %d de %s FCFA comptabilisée.',
                $depreciation->fiscal_year,
                number_format($depreciation->depreciation_amount, 0, ',', ' ')
            ));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Regenerate schedule ──────────────────────────────────────────────────────

    public function regenerateSchedule(FixedAsset $immobilisation): RedirectResponse
    {
        abort_if($immobilisation->company_id !== Auth::user()->company_id, 403);

        $this->service->generateSchedule($immobilisation);
        return back()->with('success', 'Plan d\'amortissement recalculé (les dotations déjà comptabilisées sont conservées).');
    }
}
