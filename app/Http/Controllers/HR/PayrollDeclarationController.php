<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\PayrollDeclaration;
use App\Models\PayrollRun;
use App\Services\PayrollDeclarationService;
use Illuminate\Http\Request;

/**
 * [PAI-08] Archive des déclarations sociales/fiscales (CNSS, IUTS).
 */
class PayrollDeclarationController extends Controller
{
    public function __construct(private PayrollDeclarationService $service) {}

    public function index(Request $request)
    {
        $declarations = PayrollDeclaration::with('payrollRun')
            ->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->input('year')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('period_year')->orderByDesc('period_month')->orderBy('type')
            ->paginate(30)->withQueryString();

        $years = PayrollDeclaration::query()
            ->select('period_year')->distinct()->orderByDesc('period_year')->pluck('period_year');

        return view('rh.declarations.index', compact('declarations', 'years'));
    }

    public function generate(PayrollRun $run)
    {
        if (! $run->isValidated()) {
            return back()->with('error', 'Le run de paie doit être validé avant de figer les déclarations.');
        }

        $decls = $this->service->generateForRun($run);

        return redirect()->route('rh.paie.declarations.index')
            ->with('success', count($decls).' déclaration(s) figée(s) pour '.str_pad((string) $run->period_month, 2, '0', STR_PAD_LEFT).'/'.$run->period_year.'.');
    }

    public function markDeposited(Request $request, PayrollDeclaration $declaration)
    {
        $this->service->markDeposited($declaration, $request->input('receipt_number'));

        return back()->with('success', $declaration->typeLabel().' '.$declaration->periodLabel().' marquée déposée.');
    }

    public function markPaid(PayrollDeclaration $declaration)
    {
        $this->service->markPaid($declaration);

        return back()->with('success', $declaration->typeLabel().' '.$declaration->periodLabel().' marquée payée.');
    }
}
