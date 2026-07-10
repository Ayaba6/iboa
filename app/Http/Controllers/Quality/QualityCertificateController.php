<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCertificate;
use App\Services\DocumentSequenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QualityCertificateController extends Controller
{
    public function __construct(private DocumentSequenceService $seq) {}

    public function index(Request $request)
    {
        $this->authorize('quality.view');

        $query = QualityCertificate::with(['controleur', 'validateur'])
            ->when($request->type,     fn($q, $v) => $q->where('type', $v))
            ->when($request->resultat, fn($q, $v) => $q->where('resultat', $v))
            ->when($request->lot,      fn($q, $v) => $q->where('lot_number', 'like', "%$v%"))
            ->when($request->search,   fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('number', 'like', "%$v%")
                  ->orWhere('fournisseur', 'like', "%$v%")
                  ->orWhere('lot_number', 'like', "%$v%");
            }))
            ->orderByDesc('date_certificat');

        $certificates = $query->paginate(20)->withQueryString();

        return view('qualite.certificats.index', [
            'certificates' => $certificates,
            'types'        => QualityCertificate::TYPES,
            'resultats'    => QualityCertificate::RESULTATS,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('quality.manage');

        return view('qualite.certificats.form', [
            'certificate' => new QualityCertificate(['date_certificat' => now()->toDateString()]),
            'types'       => QualityCertificate::TYPES,
            'resultats'   => QualityCertificate::RESULTATS,
            'lotPrefill'  => $request->lot_number,
            'refType'     => $request->ref_type,
            'refId'       => $request->ref_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('quality.manage');

        $validated = $request->validate([
            'type'            => 'required|in:reception_bobine,produit_fini,matiere_premiere,autre',
            'ref_type'        => 'nullable|string|max:80',
            'ref_id'          => 'nullable|integer',
            'lot_number'      => 'nullable|string|max:60',
            'fournisseur'     => 'nullable|string|max:120',
            'date_reception'  => 'nullable|date',
            'date_certificat' => 'required|date',
            'poids_reel'      => 'nullable|numeric|min:0',
            'largeur_mm'      => 'nullable|numeric|min:0',
            'epaisseur_mm'    => 'nullable|numeric|min:0',
            'couleur'         => 'nullable|string|max:40',
            'norme'           => 'nullable|string|max:60',
            'resultat'        => 'required|in:conforme,non_conforme,sous_reserve',
            'observations'    => 'nullable|string|max:2000',
            'controles'       => 'nullable|array',
        ]);

        $company = currentCompany();
        $validated['company_id']   = $company->id;
        $validated['number']       = $this->seq->nextNumber($company, 'certificat_qualite');
        $validated['controleur_id']= Auth::id();

        $cert = QualityCertificate::create($validated);

        return redirect()->route('qualite.certificats.show', $cert)
            ->with('success', "Certificat {$cert->number} créé.");
    }

    public function show(QualityCertificate $certificat)
    {
        $this->authorize('quality.view');

        return view('qualite.certificats.show', [
            'certificate' => $certificat->load(['controleur', 'validateur']),
            'types'       => QualityCertificate::TYPES,
            'resultats'   => QualityCertificate::RESULTATS,
        ]);
    }

    public function edit(QualityCertificate $certificat)
    {
        $this->authorize('quality.manage');

        return view('qualite.certificats.form', [
            'certificate' => $certificat,
            'types'       => QualityCertificate::TYPES,
            'resultats'   => QualityCertificate::RESULTATS,
        ]);
    }

    public function update(Request $request, QualityCertificate $certificat)
    {
        $this->authorize('quality.manage');

        $validated = $request->validate([
            'type'            => 'required|in:reception_bobine,produit_fini,matiere_premiere,autre',
            'lot_number'      => 'nullable|string|max:60',
            'fournisseur'     => 'nullable|string|max:120',
            'date_reception'  => 'nullable|date',
            'date_certificat' => 'required|date',
            'poids_reel'      => 'nullable|numeric|min:0',
            'largeur_mm'      => 'nullable|numeric|min:0',
            'epaisseur_mm'    => 'nullable|numeric|min:0',
            'couleur'         => 'nullable|string|max:40',
            'norme'           => 'nullable|string|max:60',
            'resultat'        => 'required|in:conforme,non_conforme,sous_reserve',
            'observations'    => 'nullable|string|max:2000',
            'controles'       => 'nullable|array',
        ]);

        $certificat->update($validated);

        return redirect()->route('qualite.certificats.show', $certificat)
            ->with('success', 'Certificat mis à jour.');
    }

    public function approve(QualityCertificate $certificat)
    {
        $this->authorize('quality.manage');

        $certificat->update([
            'validateur_id' => Auth::id(),
            'validated_at'  => now(),
        ]);

        return back()->with('success', 'Certificat validé.');
    }

    public function pdf(QualityCertificate $certificat)
    {
        $this->authorize('quality.view');

        $certificat->load(['controleur', 'validateur', 'company']);
        $pdf = Pdf::loadView('qualite.certificats.pdf', compact('certificat'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("certificat-{$certificat->number}.pdf");
    }

    public function destroy(QualityCertificate $certificat)
    {
        $this->authorize('quality.manage');

        $certificat->delete();

        return redirect()->route('qualite.certificats.index')
            ->with('success', 'Certificat supprimé.');
    }
}
