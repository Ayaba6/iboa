<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Models\StockLoss;
use App\Models\Warehouse;
use App\Services\StockLossService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * [STO-12] Pertes & casses de stock.
 */
class StockLossController extends Controller
{
    public function __construct(private StockLossService $service) {}

    public function index(Request $request)
    {
        $losses = StockLoss::with(['product', 'warehouse', 'responsible'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'a_valider' => StockLoss::where('status', 'declaree')->count(),
            'valeur_annee' => (float) StockLoss::where('status', 'validee')
                ->whereYear('validated_at', now()->year)->sum('estimated_value'),
        ];

        return view('stock.pertes.index', compact('losses', 'stats'));
    }

    public function create()
    {
        return view('stock.pertes.form', $this->formData(new StockLoss(['type' => 'casse'])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['company_id']  = currentCompany()->id;
        $data['declared_by'] = auth()->id();
        $data['status']      = 'declaree';
        $data['reference']   = 'PC-'.now()->format('Ymd').'-'.str_pad((string) (StockLoss::withoutGlobalScopes()->count() + 1), 4, '0', STR_PAD_LEFT);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('stock-losses', 'local');
        }

        $loss = StockLoss::create($data);
        $loss->update(['estimated_value' => $this->service->estimateValue($loss)]);

        return redirect()->route('stocks.pertes.show', $loss)->with('success', 'Perte/casse déclarée.');
    }

    public function show(StockLoss $perte)
    {
        $perte->load(['product', 'warehouse', 'responsible']);

        return view('stock.pertes.show', ['loss' => $perte]);
    }

    public function validateLoss(StockLoss $perte)
    {
        abort_unless($perte->status === 'declaree', 403);
        $this->service->validateLoss($perte, auth()->id());

        return back()->with('success', 'Perte validée — stock sorti au PMP ('.number_format((float) $perte->fresh()->estimated_value, 0, ',', ' ').' F).');
    }

    public function reject(Request $request, StockLoss $perte)
    {
        abort_unless($perte->status === 'declaree', 403);
        $this->service->reject($perte, $request->input('reject_reason'));

        return back()->with('success', 'Perte rejetée.');
    }

    public function photo(StockLoss $perte)
    {
        abort_unless($perte->photo_path && Storage::disk('local')->exists($perte->photo_path), 404);

        return Storage::disk('local')->response($perte->photo_path);
    }

    private function formData(StockLoss $loss): array
    {
        return [
            'loss'        => $loss,
            'products'    => Product::orderBy('name')->get(['id', 'name', 'code']),
            'warehouses'  => Warehouse::orderBy('name')->get(['id', 'name', 'code']),
            'employees'   => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id'     => ['required', 'exists:products,id'],
            'warehouse_id'   => ['required', 'exists:warehouses,id'],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'lot_number'     => ['nullable', 'string', 'max:80'],
            'type'           => ['required', 'in:'.implode(',', array_keys(StockLoss::TYPES))],
            'cause'          => ['nullable', 'string', 'max:1000'],
            'responsible_id' => ['nullable', 'exists:employees,id'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'photo'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
    }
}
