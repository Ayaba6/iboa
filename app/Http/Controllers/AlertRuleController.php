<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Services\AlertRuleService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * [PIL-04] Alertes par seuil — configuration et évaluation.
 */
class AlertRuleController extends Controller
{
    public function __construct(private AlertRuleService $service)
    {
        $this->middleware(['auth', 'verified', 'permission:settings.manage']);
    }

    public function index()
    {
        $rules = AlertRule::orderBy('name')->get()->map(function ($rule) {
            $rule->eval = $this->service->evaluate($rule);

            return $rule;
        });

        return view('pilotage.alertes.index', [
            'rules'   => $rules,
            'metrics' => $this->service->metrics(),
            'roles'   => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        AlertRule::create($this->validated($request) + [
            'company_id' => currentCompany()->id,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Alerte créée.');
    }

    public function update(Request $request, AlertRule $alerte)
    {
        $alerte->update($this->validated($request));

        return back()->with('success', 'Alerte mise à jour.');
    }

    public function destroy(AlertRule $alerte)
    {
        $alerte->delete();

        return back()->with('success', 'Alerte supprimée.');
    }

    public function run()
    {
        $n = $this->service->run();

        return back()->with('success', $n > 0
            ? "{$n} alerte(s) déclenchée(s) — destinataires notifiés."
            : 'Aucune alerte déclenchée.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'metric'         => ['required', 'string', 'in:'.implode(',', array_keys($this->service->metrics()))],
            'operator'       => ['required', 'in:'.implode(',', array_keys(AlertRule::OPERATORS))],
            'threshold'      => ['required', 'numeric'],
            'target_roles'   => ['array'],
            'target_roles.*' => ['string', 'exists:roles,name'],
            'is_active'      => ['boolean'],
            'description'    => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['target_roles'] = $data['target_roles'] ?? [];

        return $data;
    }
}
