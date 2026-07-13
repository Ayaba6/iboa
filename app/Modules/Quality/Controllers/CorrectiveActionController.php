<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Quality\Models\CorrectiveAction;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Services\CorrectiveActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [QUA-05] Actions correctives / préventives (CAPA).
 */
class CorrectiveActionController extends Controller
{
    public function __construct(private CorrectiveActionService $service)
    {
        $this->middleware('permission:production.view')->only(['index', 'forNc']);
        $this->middleware('permission:production.update')->only(['store', 'changeStatus', 'verify']);
    }

    /** Registre global des CAPA. */
    public function index(Request $request): View
    {
        $items = CorrectiveAction::with(['responsible', 'nonConformity'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->boolean('overdue'), fn ($q) => $q
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())
                ->whereNotIn('status', ['faite', 'verifiee', 'cloturee']))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'ouvertes' => CorrectiveAction::whereIn('status', ['a_faire', 'en_cours'])->count(),
            'en_retard' => CorrectiveAction::whereNotNull('due_date')->whereDate('due_date', '<', now())
                ->whereNotIn('status', ['faite', 'verifiee', 'cloturee'])->count(),
            'inefficaces' => CorrectiveAction::where('is_effective', false)->count(),
        ];

        return view('qualite.corrective-actions.index', compact('items', 'stats'));
    }

    /** Page CAPA d'une non-conformité (cause racine, plan, vérification). */
    public function forNc(NonConformity $nonConformity): View
    {
        $nonConformity->load(['correctiveActions.responsible', 'correctiveActions.verifiedBy', 'responsible']);
        $employees = Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('qualite.corrective-actions.nc', [
            'nc'        => $nonConformity,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request, NonConformity $nonConformity): RedirectResponse
    {
        $data = $request->validate([
            'type'           => ['required', 'in:'.implode(',', array_keys(CorrectiveAction::TYPES))],
            'root_cause'     => ['nullable', 'string', 'max:2000'],
            'action_plan'    => ['required', 'string', 'max:2000'],
            'responsible_id' => ['nullable', 'exists:employees,id'],
            'due_date'       => ['nullable', 'date'],
        ]);
        $data['company_id']        = currentCompany()->id;
        $data['non_conformity_id'] = $nonConformity->id;
        $data['reference']         = $this->service->nextReference($nonConformity);
        $data['status']            = 'a_faire';
        $data['created_by']        = auth()->id();

        CorrectiveAction::create($data);

        // NC passe en_cours dès qu'un plan d'action existe.
        if ($nonConformity->status === 'ouverte') {
            $nonConformity->update(['status' => 'en_cours']);
        }

        return back()->with('success', 'Action corrective ajoutée.');
    }

    public function changeStatus(Request $request, CorrectiveAction $action): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:a_faire,en_cours,faite']]);
        $this->service->changeStatus($action, $data['status']);

        return back()->with('success', 'Statut de l’action mis à jour.');
    }

    public function verify(Request $request, CorrectiveAction $action): RedirectResponse
    {
        $data = $request->validate([
            'is_effective' => ['required', 'boolean'],
            'comment'      => ['nullable', 'string', 'max:1000'],
        ]);
        $verifierId = Employee::where('user_id', auth()->id())->value('id');
        $this->service->verify($action, (bool) $data['is_effective'], $data['comment'] ?? null, $verifierId);

        $msg = $data['is_effective']
            ? 'Action vérifiée efficace.'
            : 'Action jugée inefficace — remise en cours.';

        return back()->with('success', $msg);
    }
}
