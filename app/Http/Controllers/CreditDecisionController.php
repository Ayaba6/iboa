<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\CreditDecisionService;
use Illuminate\Http\Request;

/**
 * [VEN Crédit client] Historique et prise de décisions de crédit.
 */
class CreditDecisionController extends Controller
{
    public function __construct(private CreditDecisionService $service)
    {
        $this->middleware(['auth', 'verified', 'permission:clients.view'])->only('index');
        $this->middleware(['auth', 'verified', 'permission:clients.edit'])->only('store');
    }

    public function index(Client $client)
    {
        $client->load(['creditDecisions.decidedBy']);

        return view('gestion.clients.credit', ['client' => $client]);
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'type'      => ['required', 'in:'.implode(',', array_keys(\App\Models\CreditDecision::TYPES))],
            'new_limit' => ['nullable', 'numeric', 'min:0', 'required_if:type,relevement_plafond,reduction_plafond'],
            'amount'    => ['nullable', 'numeric', 'min:0', 'required_if:type,derogation'],
            'reason'    => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->record($client, $data);

        return redirect()->route('clients.credit.index', $client)->with('success', 'Décision de crédit enregistrée.');
    }
}
