<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Services\PayrollPaymentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * [PAI-07] Virements & paiements de paie d'un run.
 */
class PayrollPaymentController extends Controller
{
    public function __construct(private PayrollPaymentService $service) {}

    public function index(PayrollRun $run)
    {
        $run->load(['payments.employee', 'payments.cashAccount']);
        $summary = [
            'total'      => (int) $run->payments->sum('net_amount'),
            'paid'       => (int) $run->payments->where('status', 'paye')->sum('net_amount'),
            'pending'    => (int) $run->payments->where('status', 'en_attente')->sum('net_amount'),
            'count'      => $run->payments->count(),
            'count_paid' => $run->payments->where('status', 'paye')->count(),
        ];

        return view('rh.virements.index', compact('run', 'summary'));
    }

    public function generate(PayrollRun $run)
    {
        if (! $run->isValidated()) {
            return back()->with('error', 'Le run de paie doit être validé avant de générer les virements.');
        }

        $n = $this->service->generateForRun($run);

        return redirect()->route('rh.paie.virements.index', $run)
            ->with('success', "{$n} ligne(s) de paiement générée(s).");
    }

    public function markPaid(Request $request, PayrollRun $run, PayrollPayment $payment)
    {
        abort_unless($payment->payroll_run_id === $run->id, 404);
        $this->service->markPaid($payment, $request->input('reference'));

        return back()->with('success', "Paiement de {$payment->employee_name} marqué payé.");
    }

    public function markRunPaid(Request $request, PayrollRun $run)
    {
        $n = $this->service->markRunPaid($run, $request->input('reference'));

        return back()->with('success', "{$n} virement(s) marqué(s) payé(s). Run clôturé.");
    }

    public function bankFile(PayrollRun $run): StreamedResponse
    {
        $run->loadMissing('payments');
        $content  = $this->service->bankFileContent($run);
        $filename = 'virements_paie_' . $run->period_year . '_' . str_pad((string) $run->period_month, 2, '0', STR_PAD_LEFT) . '.csv';

        return response()->streamDownload(fn () => print($content), $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
