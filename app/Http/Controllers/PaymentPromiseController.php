<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentPromise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentPromiseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:clients.view')->only('index');
        $this->middleware('permission:clients.create')->only(['store', 'updateStatus']);
        $this->middleware('permission:clients.delete')->only('destroy');
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['client_id', 'status']);

        $promises = PaymentPromise::with(['client', 'invoice', 'createdBy'])
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("CASE status WHEN 'en_attente' THEN 0 WHEN 'non_tenue' THEN 1 WHEN 'tenue' THEN 2 ELSE 3 END")
            ->orderBy('promised_date')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'en_attente' => PaymentPromise::where('status', 'en_attente')->count(),
            'montant'    => (int) PaymentPromise::where('status', 'en_attente')->sum('amount'),
            'tenues'     => PaymentPromise::where('status', 'tenue')->count(),
            'non_tenues' => PaymentPromise::where('status', 'non_tenue')->count(),
        ];

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('relances.promesses.index', compact('promises', 'stats', 'filters', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'     => ['required', 'exists:clients,id'],
            'invoice_id'    => ['nullable', 'exists:invoices,id'],
            'amount'        => ['required', 'integer', 'min:1'],
            'promised_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        // [SÉCURITÉ] La facture (si fournie) doit appartenir au client.
        if (!empty($data['invoice_id'])) {
            $belongs = Invoice::where('id', $data['invoice_id'])
                ->where('client_id', $data['client_id'])->exists();
            if (!$belongs) {
                return back()->withInput()->with('error', 'La facture sélectionnée n\'appartient pas à ce client.');
            }
        }

        $data['company_id'] = currentCompany()->id;
        $data['status']     = 'en_attente';

        PaymentPromise::create($data);

        return back()->with('success', 'Promesse de paiement enregistrée.');
    }

    public function updateStatus(Request $request, PaymentPromise $promise): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:en_attente,tenue,non_tenue,annulee'],
        ]);

        $promise->update(['status' => $data['status']]);

        return back()->with('success', "Promesse {$promise->statusLabel()} mise à jour.");
    }

    public function destroy(PaymentPromise $promise): RedirectResponse
    {
        $promise->delete();
        return back()->with('success', 'Promesse supprimée.');
    }
}
