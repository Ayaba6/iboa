<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;

        // Vue Kanban : regrouper par stage
        $query = CrmOpportunity::forCompany($companyId)->with(['contact', 'user']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('product_service', 'like', "%$search%");
            });
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $all = $query->orderBy('sort_order')->get();

        // Grouper par stage dans l'ordre défini
        $kanban = [];
        foreach (array_keys(CrmOpportunity::STAGES) as $stage) {
            $kanban[$stage] = $all->where('stage', $stage)->values();
        }

        $users   = User::where('company_id', $companyId)->orderBy('name')->get();
        $filters = $request->only(['search', 'user_id']);

        // Stats globales
        $actives       = $all->whereNotIn('stage', ['gagne', 'perdu']);
        $totalPipeline = $actives->sum('amount');
        $totalWeighted = $actives->sum(fn ($o) => $o->amount * $o->probability / 100);
        $totalWon      = $all->where('stage', 'gagne')->sum('amount');
        $totalLost     = $all->where('stage', 'perdu')->sum('amount');

        return view('crm.opportunities.index', compact('kanban', 'users', 'filters', 'totalPipeline', 'totalWeighted', 'totalWon', 'totalLost'));
    }

    public function create(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $contacts  = CrmContact::forCompany($companyId)->orderBy('name')->get();
        $users     = User::where('company_id', $companyId)->orderBy('name')->get();
        $contactId = $request->input('contact_id');

        return view('crm.opportunities.create', compact('contacts', 'users', 'contactId'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateOpportunity($request);
        $validated['company_id'] = Auth::user()->company_id;

        CrmOpportunity::create($validated);

        return redirect()->route('crm.opportunities.index')
            ->with('success', 'Opportunité créée avec succès.');
    }

    public function show(CrmOpportunity $opportunity)
    {
        $this->authorizeCompany($opportunity);
        $opportunity->load(['contact', 'user', 'activities.user']);

        return view('crm.opportunities.show', compact('opportunity'));
    }

    public function edit(CrmOpportunity $opportunity)
    {
        $this->authorizeCompany($opportunity);
        $companyId = Auth::user()->company_id;
        $contacts  = CrmContact::forCompany($companyId)->orderBy('name')->get();
        $users     = User::where('company_id', $companyId)->orderBy('name')->get();

        return view('crm.opportunities.edit', compact('opportunity', 'contacts', 'users'));
    }

    public function update(Request $request, CrmOpportunity $opportunity)
    {
        $this->authorizeCompany($opportunity);
        $validated = $this->validateOpportunity($request);
        $opportunity->update($validated);

        return redirect()->route('crm.opportunities.show', $opportunity)
            ->with('success', 'Opportunité mise à jour.');
    }

    public function destroy(CrmOpportunity $opportunity)
    {
        $this->authorizeCompany($opportunity);
        $opportunity->delete();
        return redirect()->route('crm.opportunities.index')
            ->with('success', 'Opportunité supprimée.');
    }

    /** AJAX : déplacer une opportunité dans le Kanban */
    public function moveStage(Request $request, CrmOpportunity $opportunity)
    {
        $this->authorizeCompany($opportunity);
        $request->validate([
            'stage'      => ['required', 'in:' . implode(',', array_keys(CrmOpportunity::STAGES))],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $opportunity->update([
            'stage'       => $request->input('stage'),
            'sort_order'  => $request->input('sort_order', 0),
            'probability' => CrmOpportunity::STAGES[$request->input('stage')]['prob'],
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeCompany(CrmOpportunity $opp): void
    {
        abort_if($opp->company_id !== Auth::user()->company_id, 403);
    }

    private function validateOpportunity(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'crm_contact_id'   => ['nullable', 'exists:crm_contacts,id'],
            'user_id'          => ['nullable', 'exists:users,id'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'probability'      => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close'   => ['nullable', 'date'],
            'stage'            => ['required', 'in:' . implode(',', array_keys(CrmOpportunity::STAGES))],
            'lost_reason'      => ['nullable', 'string', 'max:255'],
            'product_service'  => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
