<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesRepController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesRep::class);

        $reps = SalesRep::withCount('clients')
            ->withSum('commissions as commissions_total', 'commission_amount')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->is_active))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('gestion.representants.index', compact('reps'));
    }

    public function create(): View
    {
        $this->authorize('create', SalesRep::class);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('gestion.representants.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SalesRep::class);

        $data = $request->validate([
            'code'            => 'nullable|string|max:20',
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|max:100',
            'phone'           => 'nullable|string|max:30',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'is_active'       => 'boolean',
            'user_id'         => 'nullable|exists:users,id',
            'notes'           => 'nullable|string',
        ]);

        $data['company_id'] = currentCompany()->id;
        $data['is_active']  = $request->boolean('is_active', true);

        $rep = SalesRep::create($data);

        return redirect()->route('representants.show', $rep)
                         ->with('success', 'Représentant créé avec succès.');
    }

    public function show(SalesRep $representant, Request $request): View
    {
        $this->authorize('view', $representant);

        $period = $request->get('period', now()->format('Y-m'));

        $clients = $representant->clients()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'email', 'balance']);

        $commissions = Commission::where('sales_rep_id', $representant->id)
            ->with('client:id,name', 'payment:id,number,payment_date,amount')
            ->when($request->filled('period'), fn ($q) => $q->where('period', $period))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $totals = [
            'calculee' => (float) Commission::where('sales_rep_id', $representant->id)->where('status', 'calculee')->sum('commission_amount'),
            'validee'  => (float) Commission::where('sales_rep_id', $representant->id)->where('status', 'validee')->sum('commission_amount'),
            'payee'    => (float) Commission::where('sales_rep_id', $representant->id)->where('status', 'payee')->sum('commission_amount'),
        ];

        return view('gestion.representants.show', compact('representant', 'clients', 'commissions', 'totals', 'period'));
    }

    public function edit(SalesRep $representant): View
    {
        $this->authorize('update', $representant);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('gestion.representants.edit', compact('representant', 'users'));
    }

    public function update(Request $request, SalesRep $representant): RedirectResponse
    {
        $this->authorize('update', $representant);

        $data = $request->validate([
            'code'            => 'nullable|string|max:20',
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|max:100',
            'phone'           => 'nullable|string|max:30',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'is_active'       => 'boolean',
            'user_id'         => 'nullable|exists:users,id',
            'notes'           => 'nullable|string',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $representant->update($data);

        return redirect()->route('representants.show', $representant)
                         ->with('success', 'Représentant mis à jour.');
    }

    public function destroy(SalesRep $representant): RedirectResponse
    {
        $this->authorize('delete', $representant);

        if ($representant->clients()->exists()) {
            return back()->with('error', 'Impossible de supprimer : des clients sont rattachés à ce représentant.');
        }

        $representant->delete();
        return redirect()->route('representants.index')
                         ->with('success', 'Représentant supprimé.');
    }

    public function updateCommissionStatus(Request $request, Commission $commission): RedirectResponse
    {
        $this->authorize('update', $commission->salesRep);

        $data = $request->validate([
            'status' => 'required|in:calculee,validee,payee',
        ]);

        $commission->update($data);

        return back()->with('success', 'Statut de commission mis à jour.');
    }
}
