<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommercialContract;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// [Maquette X3] Contrats commerciaux — CRUD complet.
class CommercialContractController extends Controller
{
    public function __construct(private DocumentSequenceService $sequences)
    {
        $this->middleware('permission:orders.view')->only(['index', 'show', 'pdf']);
        $this->middleware('permission:orders.create')->except(['index', 'show', 'pdf']);
    }

    public function index(Request $request): View
    {
        $contracts = CommercialContract::with(['client:id,name', 'supplier:id,name', 'salesRep:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq
                ->where('number', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$s}%"))))
            ->orderByDesc('contract_date')
            ->paginate(20)->withQueryString();

        return view('ventes.contrats.index', compact('contracts'));
    }

    public function create(): View
    {
        return view('ventes.contrats.form', $this->formData(null));
    }

    // [Maquette X3] Fiche de consultation en lecture seule.
    public function show(CommercialContract $contrat): View
    {
        $contrat->load(['items.product:id,name', 'client', 'supplier:id,name', 'salesRep:id,name', 'warehouse:id,code,name', 'creator:id,name', 'attachments']);

        return view('ventes.contrats.show', compact('contrat'));
    }

    // [Maquette X3] Export PDF du contrat commercial.
    public function pdf(CommercialContract $contrat, Request $request)
    {
        try {
            $contrat->load(['items.product:id,name', 'client', 'supplier:id,name', 'salesRep:id,name']);
            $company  = currentCompany();
            $settings = $company?->documentSetting;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('ventes.pdf.contract', [
                'contract' => $contrat, 'company' => $company, 'settings' => $settings,
            ])->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

            $filename = 'Contrat_' . str_replace(['/', '\\', ' '], '-', $contrat->number) . '.pdf';

            return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PDF contrat error', ['id' => $contrat->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }

    public function edit(CommercialContract $contrat): View
    {
        $contrat->load('items', 'attachments');

        return view('ventes.contrats.form', $this->formData($contrat));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $company = currentCompany();

        $contract = DB::transaction(function () use ($data, $request, $company) {
            $contract = CommercialContract::create($this->headerData($data) + [
                'company_id' => $company->id,
                'number'     => $this->sequences->nextNumber($company, 'contrat'),
                'created_by' => Auth::id(),
            ]);
            $this->syncItems($contract, $data['items'] ?? []);

            return $contract;
        });

        $this->storeAttachments($request, $contract);

        return redirect()->route('ventes.contrats.edit', $contract)
            ->with('success', 'Contrat ' . $contract->number . ' créé.');
    }

    public function update(Request $request, CommercialContract $contrat): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($contrat, $data) {
            $contrat->update($this->headerData($data));
            $contrat->items()->delete();
            $this->syncItems($contrat, $data['items'] ?? []);
        });

        $this->storeAttachments($request, $contrat);

        return redirect()->route('ventes.contrats.edit', $contrat)
            ->with('success', 'Contrat ' . $contrat->number . ' mis à jour.');
    }

    public function destroy(CommercialContract $contrat): RedirectResponse
    {
        if ($contrat->status !== 'brouillon') {
            return back()->with('error', 'Seul un contrat en brouillon peut être supprimé.');
        }
        $contrat->delete();

        return redirect()->route('ventes.contrats.index')->with('success', 'Contrat supprimé.');
    }

    // ── privé ────────────────────────────────────────────────────────────────

    private function formData(?CommercialContract $contract): array
    {
        return [
            'contract'   => $contract,
            'clients'    => Client::where('is_active', true)->orderBy('name')->get(['id', 'name', 'ifu', 'phone', 'address', 'city']),
            'suppliers'  => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users'      => User::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'code', 'name']),
            'products'   => Product::where('is_active', true)->orderBy('name')
                ->get(['id', 'reference', 'name', 'sale_price']),
            'company'    => currentCompany(),
            'nextNumber' => $contract?->number ?? 'CT-' . now()->format('Y') . '-…',
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contract_type'     => ['required', 'in:vente,achat'],
            'client_id'         => ['nullable', 'required_if:contract_type,vente', 'exists:clients,id'],
            'supplier_id'       => ['nullable', 'required_if:contract_type,achat', 'exists:suppliers,id'],
            'description'       => ['required', 'string', 'max:255'],
            'currency_code'     => ['nullable', 'string', 'max:10'],
            'sales_rep_id'      => ['nullable', 'exists:users,id'],
            'contract_date'     => ['required', 'date'],
            'starts_at'         => ['required', 'date'],
            'ends_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_framework'      => ['boolean'],
            'status'            => ['nullable', 'in:brouillon,actif,suspendu,termine,annule'],
            'priority'          => ['nullable', 'in:basse,normale,haute,urgente'],
            'project_reference' => ['nullable', 'string', 'max:60'],
            'category'          => ['nullable', 'string', 'max:60'],
            'payment_terms'     => ['nullable', 'string', 'max:80'],
            'incoterm'          => ['nullable', 'string', 'max:40'],
            'warehouse_id'      => ['nullable', 'exists:warehouses,id'],
            'billing_currency'  => ['nullable', 'string', 'max:10'],
            'client_contact'    => ['nullable', 'string', 'max:100'],
            'supplier_contact'  => ['nullable', 'string', 'max:100'],
            'transport_mode'    => ['nullable', 'in:route,air,mer,rail,multimodal'],
            'validity_days'     => ['nullable', 'integer', 'min:1', 'max:3650'],
            'observations'      => ['nullable', 'string', 'max:1000'],
            'items'                     => ['nullable', 'array'],
            'items.*.product_id'        => ['nullable', 'exists:products,id'],
            'items.*.designation'       => ['required_with:items.*.quantity', 'string', 'max:255'],
            'items.*.unit'              => ['nullable', 'string', 'max:20'],
            'items.*.quantity'          => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price'        => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.starts_at'         => ['nullable', 'date'],
            'items.*.ends_at'           => ['nullable', 'date'],
            'documents'   => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);
    }

    private function headerData(array $data): array
    {
        unset($data['items'], $data['documents']);
        $data['is_framework'] = (bool) ($data['is_framework'] ?? false);
        $data['currency_code'] = $data['currency_code'] ?? 'XOF';
        $data['status'] = $data['status'] ?? 'brouillon';
        $data['priority'] = $data['priority'] ?? 'normale';

        return $data;
    }

    private function syncItems(CommercialContract $contract, array $items): void
    {
        $total = 0;
        $order = 0;
        foreach ($items as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            if ($qty <= 0 && empty($line['designation'])) {
                continue;
            }
            $price    = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount_percent'] ?? 0);
            $amount   = round($qty * $price * (1 - $discount / 100), 2);

            $contract->items()->create([
                'product_id'       => $line['product_id'] ?? null,
                'designation'      => $line['designation'] ?? '',
                'unit'             => $line['unit'] ?? null,
                'quantity'         => $qty,
                'unit_price'       => $price,
                'discount_percent' => $discount,
                'amount_ht'        => $amount,
                'starts_at'        => $line['starts_at'] ?? $contract->starts_at,
                'ends_at'          => $line['ends_at'] ?? $contract->ends_at,
                'warehouse_id'     => $contract->warehouse_id,
                'status'           => $contract->status,
                'sort_order'       => $order++,
            ]);
            $total += $amount;
        }
        $contract->update(['total_ht' => $total]);
    }

    private function storeAttachments(Request $request, CommercialContract $contract): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/contrat/' . $contract->id, 'local');
            $contract->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }
}
