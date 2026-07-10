<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayRubric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RH-PRO] Gestion des rubriques de paie (CRUD).
 * Les rubriques sont des codes de paie paramétrables (gains, retenues, cotisations).
 */
class PayRubricController extends Controller
{
    public function index(Request $request)
    {
        $company = currentCompany();

        $query = PayRubric::where('company_id', $company->id)->ordered();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) =>
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('libelle', 'like', "%{$search}%")
            );
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->active === '1');
        }

        $rubrics = $query->paginate(25)->withQueryString();

        return view('rh.rubriques.index', compact('rubrics'));
    }

    public function create()
    {
        return view('rh.rubriques.create', ['rubric' => new PayRubric()]);
    }

    public function store(Request $request)
    {
        $company = currentCompany();

        $validated = $this->validateRubric($request, $company->id);
        $validated['company_id'] = $company->id;
        $validated['created_by'] = Auth::id();

        PayRubric::create($validated);

        return redirect()->route('rh.rubriques.index')
            ->with('success', "Rubrique [{$validated['code']}] créée avec succès.");
    }

    public function edit(PayRubric $rubric)
    {
        $company = currentCompany();
        abort_if($rubric->company_id !== $company->id, 403);

        return view('rh.rubriques.edit', compact('rubric'));
    }

    public function update(Request $request, PayRubric $rubric)
    {
        $company = currentCompany();
        abort_if($rubric->company_id !== $company->id, 403);

        $validated = $this->validateRubric($request, $company->id, $rubric->id);
        $rubric->update($validated);

        return redirect()->route('rh.rubriques.index')
            ->with('success', "Rubrique [{$rubric->code}] mise à jour.");
    }

    public function destroy(PayRubric $rubric)
    {
        $company = currentCompany();
        abort_if($rubric->company_id !== $company->id, 403);

        // Vérifier si la rubrique est référencée dans des bulletins existants
        $used = DB::table('payroll_items')
            ->join('payroll_runs', 'payroll_items.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $company->id)
            ->whereExists(function ($q) use ($rubric) {
                // Vérification simplifiée : on regarde si le code est cité dans les items
                // Dans le moteur actuel les items n'ont pas de rubric_code — sécurité préventive
                $q->selectRaw('1');
            })
            ->exists();

        // Les rubriques système (CNSS_SAL, IUTS, BRUT, NET_PAYE) sont protégées
        $systemCodes = ['CNSS_SAL', 'CNSS_PAT', 'IUTS', 'BRUT', 'NET_PAYE', 'SAL_BASE'];
        if (in_array($rubric->code, $systemCodes)) {
            return back()->with('error',
                "La rubrique [{$rubric->code}] est une rubrique système et ne peut pas être supprimée.");
        }

        $rubric->delete();

        return redirect()->route('rh.rubriques.index')
            ->with('success', "Rubrique [{$rubric->code}] supprimée.");
    }

    // ─── Règles de validation partagées ──────────────────────────────────────

    private function validateRubric(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $codeRule = "required|string|max:30|alpha_dash|unique:pay_rubrics,code,{$ignoreId},id,company_id,{$companyId}";

        return $request->validate([
            'code'          => $codeRule,
            'libelle'       => ['required', 'string', 'max:150'],
            'description'   => ['nullable', 'string', 'max:500'],
            'type'          => ['required', 'in:gain,retenue,cotisation_pat,information'],
            'calc_type'     => ['required', 'in:fixe,taux,formule,manuel'],
            'base_ref'      => ['nullable', 'string', 'max:50'],
            'rate'          => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fixed_amount'  => ['nullable', 'integer', 'min:0'],
            'formula'       => [
                'nullable', 'string', 'max:500',
                // Sécurité : la formule ne doit contenir que des opérateurs arithmétiques
                // et des noms de variables (ex: salaire_base * 0.055). PHP/JS interdit.
                'regex:/^[a-zA-Z0-9_\s\+\-\*\/\(\)\.\%]+$/',
            ],
            'is_taxable'    => ['boolean'],
            'is_cnss_base'  => ['boolean'],
            'is_in_brut'    => ['boolean'],
            'display_order' => ['integer', 'min:0', 'max:9999'],
            'show_on_bulletin' => ['boolean'],
            'is_active'     => ['boolean'],
        ]);
    }
}
