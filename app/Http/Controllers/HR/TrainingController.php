<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use Illuminate\Http\Request;

/**
 * [RH-10] Formation & compétences : sessions, participants, habilitations.
 */
class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $sessions = TrainingSession::withCount('participants')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")->orWhere('competence', 'like', "%{$v}%")))
            ->orderByDesc('start_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $stats = [
            'planifiees' => TrainingSession::where('status', 'planifiee')->count(),
            'terminees'  => TrainingSession::where('status', 'terminee')->count(),
            'cout_annee' => (float) TrainingSession::whereYear('start_date', now()->year)->sum('cost'),
            'habilit_echeance' => TrainingParticipant::whereNotNull('certificate_expiry')
                ->whereDate('certificate_expiry', '<=', now()->addDays(60))->count(),
        ];

        return view('rh.formations.index', compact('sessions', 'stats'));
    }

    public function create()
    {
        return view('rh.formations.form', ['session' => new TrainingSession(['status' => 'planifiee'])]);
    }

    public function store(Request $request)
    {
        $session = TrainingSession::create($this->validated($request) + [
            'company_id' => currentCompany()->id,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('rh.formations.show', $session)->with('success', 'Session de formation créée.');
    }

    public function show(TrainingSession $formation)
    {
        $formation->load(['participants.employee']);
        $employees = Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('rh.formations.show', ['session' => $formation, 'employees' => $employees]);
    }

    public function update(Request $request, TrainingSession $formation)
    {
        $formation->update($this->validated($request));

        return back()->with('success', 'Session mise à jour.');
    }

    public function storeParticipant(Request $request, TrainingSession $formation)
    {
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id']]);

        TrainingParticipant::firstOrCreate(
            ['training_session_id' => $formation->id, 'employee_id' => $data['employee_id']],
            ['status' => 'inscrit'],
        );

        return back()->with('success', 'Participant inscrit.');
    }

    public function updateParticipant(Request $request, TrainingSession $formation, TrainingParticipant $participant)
    {
        abort_unless($participant->training_session_id === $formation->id, 404);

        $data = $request->validate([
            'status'             => ['required', 'in:inscrit,present,absent'],
            'score'              => ['nullable', 'numeric', 'min:0', 'max:20'],
            'passed'             => ['nullable', 'boolean'],
            'certificate_number' => ['nullable', 'string', 'max:80'],
            'certificate_expiry' => ['nullable', 'date'],
            'comment'            => ['nullable', 'string', 'max:500'],
        ]);
        $data['passed'] = $request->boolean('passed');
        $participant->update($data);

        return back()->with('success', 'Participant mis à jour.');
    }

    public function destroyParticipant(TrainingSession $formation, TrainingParticipant $participant)
    {
        abort_unless($participant->training_session_id === $formation->id, 404);
        $participant->delete();

        return back()->with('success', 'Participant retiré.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:150'],
            'competence'       => ['nullable', 'string', 'max:120'],
            'provider'         => ['nullable', 'string', 'max:120'],
            'location'         => ['nullable', 'string', 'max:120'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'cost'             => ['nullable', 'numeric', 'min:0'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status'           => ['required', 'in:'.implode(',', array_keys(TrainingSession::STATUSES))],
            'description'      => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
