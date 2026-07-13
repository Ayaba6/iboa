<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobCandidate;
use App\Models\JobPosition;
use App\Models\Recruitment;
use App\Services\RecruitmentService;
use Illuminate\Http\Request;

/**
 * [RH-03] Recrutement & onboarding : besoins de recrutement, pipeline candidats, embauche.
 */
class RecruitmentController extends Controller
{
    public function __construct(private RecruitmentService $service) {}

    public function index(Request $request)
    {
        $recruitments = Recruitment::with(['jobPosition', 'department'])
            ->withCount([
                'candidates',
                'candidates as hired_count' => fn ($q) => $q->where('status', 'embauche'),
            ])
            ->when($request->input('q'), fn ($x, $q) => $x->where('title', 'like', "%{$q}%"))
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        $stats = [
            'ouvert' => Recruitment::where('status', 'ouvert')->count(),
            'en_cours' => Recruitment::where('status', 'en_cours')->count(),
            'pourvu' => Recruitment::where('status', 'pourvu')->count(),
        ];

        return view('rh.recrutement.index', compact('recruitments', 'stats'));
    }

    public function create()
    {
        return view('rh.recrutement.form', [
            'recruitment' => new Recruitment(['status' => 'ouvert', 'positions_count' => 1, 'opened_at' => now()]),
            'positions'   => JobPosition::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $recruitment = Recruitment::create($this->validated($request));

        return redirect()->route('rh.recrutement.show', $recruitment)->with('success', 'Besoin de recrutement créé.');
    }

    public function show(Recruitment $recrutement)
    {
        $recrutement->load(['jobPosition', 'department', 'candidates' => fn ($q) => $q->orderByDesc('created_at')]);

        return view('rh.recrutement.show', ['recruitment' => $recrutement]);
    }

    public function edit(Recruitment $recrutement)
    {
        return view('rh.recrutement.form', [
            'recruitment' => $recrutement,
            'positions'   => JobPosition::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Recruitment $recrutement)
    {
        $recrutement->update($this->validated($request));

        return redirect()->route('rh.recrutement.show', $recrutement)->with('success', 'Besoin mis à jour.');
    }

    public function destroy(Recruitment $recrutement)
    {
        if ($recrutement->candidates()->where('status', 'embauche')->exists()) {
            return back()->with('error', 'Ce besoin a déjà donné lieu à une embauche — annulez-le plutôt.');
        }
        $recrutement->delete();

        return redirect()->route('rh.recrutement.index')->with('success', 'Besoin supprimé.');
    }

    // ── Candidats ───────────────────────────────────────────────────────────────

    public function storeCandidate(Request $request, Recruitment $recrutement)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'max:150'],
            'phone'      => ['nullable', 'string', 'max:40'],
            'source'     => ['nullable', 'string', 'max:80'],
            'rating'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);
        $data['company_id']     = currentCompany()->id;
        $data['recruitment_id'] = $recrutement->id;
        $data['status']         = 'recu';
        $data['applied_at']     = now()->toDateString();

        JobCandidate::create($data);

        return back()->with('success', 'Candidat ajouté.');
    }

    public function advanceCandidate(Request $request, Recruitment $recrutement, JobCandidate $candidate)
    {
        abort_unless($candidate->recruitment_id === $recrutement->id, 404);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(JobCandidate::STATUSES))],
        ]);
        // L'embauche passe par le service (création fiche salarié).
        if ($data['status'] === 'embauche') {
            $this->service->hire($candidate);

            return back()->with('success', $candidate->full_name.' embauché — fiche salarié créée.');
        }

        $candidate->update(['status' => $data['status']]);

        return back()->with('success', 'Statut candidat mis à jour.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'           => ['required', 'string', 'max:150'],
            'job_position_id' => ['nullable', 'exists:job_positions,id'],
            'department_id'   => ['nullable', 'exists:departments,id'],
            'reference'       => ['nullable', 'string', 'max:60'],
            'contract_type'   => ['required', 'in:'.implode(',', array_keys(Recruitment::CONTRACT_TYPES))],
            'positions_count' => ['required', 'integer', 'min:1', 'max:999'],
            'status'          => ['required', 'in:'.implode(',', array_keys(Recruitment::STATUSES))],
            'opened_at'       => ['nullable', 'date'],
            'closed_at'       => ['nullable', 'date', 'after_or_equal:opened_at'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'requirements'    => ['nullable', 'string', 'max:2000'],
        ]);
        $data['company_id'] = currentCompany()->id;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
