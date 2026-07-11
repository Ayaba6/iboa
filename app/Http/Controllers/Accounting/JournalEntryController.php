<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalType;
use App\Services\JournalEntryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(private JournalEntryService $service)
    {
        $this->middleware('can:accounting.view')->only(['index', 'show']);
        $this->middleware('can:accounting.write')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $filters      = $request->only(['journal_type_id', 'status', 'date_from', 'date_to', 'search']);
        $entries      = $this->service->search($filters, 20);
        $journalTypes = JournalType::orderBy('code')->get(['id', 'code', 'name']);

        return view('comptabilite.journaux.index', compact('entries', 'filters', 'journalTypes'));
    }

    public function exportPdf(Request $request): mixed
    {
        $filters = $request->only(['journal_type_id', 'status', 'date_from', 'date_to', 'search']);
        $company = currentCompany();

        $query = \App\Models\JournalEntry::with(['journalType'])
            ->where('company_id', $company->id)
            ->when(!empty($filters['journal_type_id']), fn($q) => $q->where('journal_type_id', $filters['journal_type_id']))
            ->when(!empty($filters['status']),          fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['date_from']),       fn($q) => $q->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),         fn($q) => $q->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),          fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhere('description', 'like', '%'.$filters['search'].'%')
                   ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->orderBy('entry_date')->orderBy('number');

        $entries    = $query->get();
        $totalDebit = $entries->sum('total_debit');
        $totalCredit= $entries->sum('total_credit');
        $journalTypes = \App\Models\JournalType::orderBy('code')->get(['id', 'code', 'name']);

        $pdf = Pdf::loadView('comptabilite.pdf.journaux', compact(
            'company', 'entries', 'filters', 'totalDebit', 'totalCredit', 'journalTypes'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('journaux_' . now()->format('Ymd_His') . '.pdf');
    }

    public function create(): View
    {
        $journalTypes = JournalType::where('is_active', true)->orderBy('code')->get();
        $accounts     = Account::postable()->orderBy('code')->get(['id', 'code', 'name']);

        return view('comptabilite.journaux.create', compact('journalTypes', 'accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'documents'   => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        $data = $this->validateEntryPayload($request);

        $entry = $this->service->create($data);

        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/journalentry/' . $entry->id, 'local');
            $entry->attachments()->create([
                'disk' => 'local', 'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        // [Maquette X3] « Enregistrer + Valider » : tente la validation immédiate.
        // (Sans effet sur une simulation — le service la refuse avec un message clair.)
        if ($request->boolean('save_and_validate')) {
            try {
                $this->service->validate($entry);

                return redirect()
                    ->route('comptabilite.journaux.show', $entry)
                    ->with('success', 'Écriture ' . $entry->number . ' créée et validée. Les soldes ont été mis à jour.');
            } catch (\Exception $e) {
                return redirect()
                    ->route('comptabilite.journaux.show', $entry)
                    ->with('error', 'Écriture ' . $entry->number . ' créée en brouillon, mais validation impossible : ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('comptabilite.journaux.show', $entry)
            ->with('success', 'Écriture comptable ' . $entry->number . ' créée.');
    }

    public function edit(JournalEntry $journalEntry): View
    {
        if (! $journalEntry->isEditable()) {
            abort(403, "L'écriture est " . $journalEntry->status . " — la modification est interdite. Utilisez la contre-passation.");
        }
        $entry        = $this->service->repository->findWithDetails($journalEntry->id);
        $journalTypes = JournalType::where('is_active', true)->orderBy('code')->get();
        $accounts     = Account::postable()->orderBy('code')->get(['id', 'code', 'name']);

        return view('comptabilite.journaux.edit', compact('entry', 'journalTypes', 'accounts'));
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        if (! $journalEntry->isEditable()) {
            return back()->with('error', "L'écriture {$journalEntry->number} est " . $journalEntry->status . " — modification interdite.");
        }
        $data = $this->validateEntryPayload($request);

        try {
            $this->service->update($journalEntry, $data);
            return redirect()
                ->route('comptabilite.journaux.show', $journalEntry)
                ->with('success', "Écriture {$journalEntry->number} mise à jour.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * [COMPTA-FIX-01] Validation métier centralisée pour create/update.
     *
     * Au-delà des règles syntaxiques, applique les invariants comptables :
     *   - au moins 2 lignes effectives (1 débit + 1 crédit minimum)
     *   - aucune ligne avec debit=0 ET credit=0 (ligne vide)
     *   - aucune ligne avec debit>0 ET credit>0 (incohérent)
     *   - comptes utilisés doivent être postables (is_detail=true, is_active=true)
     */
    private function validateEntryPayload(Request $request): array
    {
        $data = $request->validate([
            'journal_type_id'       => ['required', 'integer', 'exists:journal_types,id'],
            'entry_date'            => ['required', 'date'],
            'value_date'            => ['nullable', 'date'],
            'description'           => ['required', 'string', 'max:255'],
            'reference'             => ['nullable', 'string', 'max:50'],
            'partner_name'          => ['nullable', 'string', 'max:100'],
            'is_simulation'         => ['nullable', 'boolean'],
            'lines'                 => ['required', 'array', 'min:2'],
            'lines.*.account_id'    => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.label'         => ['nullable', 'string', 'max:255'],
            'lines.*.debit'         => ['nullable', 'integer', 'min:0'],
            'lines.*.credit'        => ['nullable', 'integer', 'min:0'],
            'lines.*.due_date'      => ['nullable', 'date'],
            'lines.*.partner_name'       => ['nullable', 'string', 'max:100'],
            'lines.*.cost_center'        => ['nullable', 'string', 'max:30'],
            'lines.*.tax_code'           => ['nullable', 'string', 'max:10'],
            'lines.*.reconciliation_ref' => ['nullable', 'string', 'max:50'],
        ]);

        $effective = [];
        foreach ($data['lines'] as $idx => $line) {
            $d = (int) ($line['debit'] ?? 0);
            $c = (int) ($line['credit'] ?? 0);

            // Ligne entièrement vide → on la rejette (mais sans erreur dure : on la filtre)
            if ($d === 0 && $c === 0) continue;

            // Ligne avec débit ET crédit > 0 : erreur métier
            if ($d > 0 && $c > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$idx}.debit" => "Ligne {$idx} : impossible d'avoir un débit ET un crédit sur la même ligne.",
                ]);
            }

            $effective[] = $line;
        }

        if (count($effective) < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => 'Une écriture doit comporter au moins 2 lignes non vides (1 débit + 1 crédit).',
            ]);
        }

        // Vérifie que tous les comptes utilisés sont postables
        $accountIds  = array_unique(array_column($effective, 'account_id'));
        $postable    = Account::whereIn('id', $accountIds)
            ->where('is_detail', true)->where('is_active', true)
            ->pluck('id')->all();
        $nonPostable = array_diff($accountIds, $postable);
        if (!empty($nonPostable)) {
            $bad = Account::whereIn('id', $nonPostable)->pluck('code')->implode(', ');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => "Compte(s) non postable(s) : {$bad}. Utilisez un compte de détail actif.",
            ]);
        }

        $data['lines'] = array_values($effective);
        return $data;
    }

    public function show(JournalEntry $journalEntry): View
    {
        $entry = $this->service->repository->findWithDetails($journalEntry->id);

        return view('comptabilite.journaux.show', compact('entry'));
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->service->delete($journalEntry);

            return redirect()
                ->route('comptabilite.journaux.index')
                ->with('success', 'Écriture supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateEntry(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->service->validate($journalEntry);

            return redirect()
                ->route('comptabilite.journaux.show', $journalEntry)
                ->with('success', 'Écriture validée. Les soldes ont été mis à jour.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Contre-passation : annule comptablement une écriture validée
     * en créant une écriture miroir (DR ↔ CR inversés).
     */
    public function reverse(JournalEntry $journalEntry, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:255',
        ], [
            'reason.required' => 'Le motif de la contre-passation est obligatoire (traçabilité comptable).',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            $reversal = $this->service->reverse($journalEntry, $data['reason']);
            return redirect()
                ->route('comptabilite.journaux.show', $reversal ?? $journalEntry)
                ->with('success', 'Écriture ' . $journalEntry->number . ' contre-passée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
