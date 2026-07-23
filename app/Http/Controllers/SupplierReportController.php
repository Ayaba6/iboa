<?php

namespace App\Http\Controllers;

use App\Exports\Suppliers\BalanceAgeeSupplierExport;
use App\Exports\Suppliers\BalanceSupplierExport;
use App\Exports\Suppliers\FacturesImpayeesSupplierExport;
use App\Exports\Suppliers\GrandLivreSupplierExport;
use App\Exports\Suppliers\JournalAchatsExport;
use App\Exports\Suppliers\ReleveSupplierExport;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class SupplierReportController extends Controller
{
    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  1. RELEVÉ FOURNISSEUR                                                  */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function releve(Request $request)
    {
        $suppliers  = Supplier::active()->orderBy('name')->get(['id', 'name', 'code']);
        $supplierId = $request->input('supplier_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $supplier   = $supplierId ? Supplier::find($supplierId) : null;
        [$lines, $soldeOuv] = $this->computeReleveLines($supplier, $dateFrom, $dateTo);

        return view('suppliers.releve', compact('suppliers', 'supplier', 'lines', 'soldeOuv', 'supplierId', 'dateFrom', 'dateTo'));
    }

    public function releveExportExcel(Request $request)
    {
        $supplierId = (int) $request->input('supplier_id');
        $supplier   = Supplier::find($supplierId);
        $name       = $supplier ? str($supplier->name)->slug('-') : 'fournisseur';
        return Excel::download(new ReleveSupplierExport($supplierId, $request->input('date_from'), $request->input('date_to')), 'releve-fourn-' . $name . '-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function releveExportPdf(Request $request)
    {
        $supplierId = (int) $request->input('supplier_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $company    = currentCompany();
        $supplier   = Supplier::find($supplierId);
        [$lines, $soldeOuv] = $this->computeReleveLines($supplier, $dateFrom, $dateTo);
        $name = $supplier ? str($supplier->name)->slug('-') : 'fournisseur';
        return Pdf::loadView('suppliers.pdf.releve', compact('company', 'supplier', 'lines', 'soldeOuv', 'dateFrom', 'dateTo'))
            ->setPaper('a4', 'landscape')->download('releve-fourn-' . $name . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  2. BALANCE FOURNISSEURS                                                */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function balance(Request $request)
    {
        $search   = $request->input('search');
        [$rows, $totals] = $this->computeBalance($search);
        return view('suppliers.balance', compact('rows', 'totals', 'search'));
    }

    public function balanceExportExcel(Request $request)
    {
        return Excel::download(new BalanceSupplierExport($request->input('search')), 'balance-fournisseurs-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function balanceExportPdf(Request $request)
    {
        $search = $request->input('search');
        $company = currentCompany();
        [$rows, $totals] = $this->computeBalance($search);
        return Pdf::loadView('suppliers.pdf.balance', compact('company', 'rows', 'totals', 'search'))
            ->setPaper('a4', 'landscape')->download('balance-fournisseurs-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  3. BALANCE ÂGÉE FOURNISSEURS                                           */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function balanceAgee(Request $request)
    {
        $today      = Carbon::today();
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        $suppliers  = Supplier::active()->orderBy('name')->get(['id', 'name', 'code']);
        [$rows, $totals] = $this->computeBalanceAgee($today, $supplierId);
        return view('suppliers.balance-agee', compact('rows', 'totals', 'today', 'suppliers', 'supplierId'));
    }

    public function balanceAgeeExportExcel(Request $request)
    {
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        return Excel::download(new BalanceAgeeSupplierExport($supplierId), 'balance-agee-fourn-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function balanceAgeeExportPdf(Request $request)
    {
        $today      = Carbon::today();
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        $company    = currentCompany();
        [$rows, $totals] = $this->computeBalanceAgee($today, $supplierId);
        return Pdf::loadView('suppliers.pdf.balance-agee', compact('company', 'rows', 'totals', 'today'))
            ->setPaper('a4', 'landscape')->download('balance-agee-fourn-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  4. FACTURES IMPAYÉES                                                   */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function facturesImpayees(Request $request)
    {
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        $suppliers  = Supplier::active()->orderBy('name')->get(['id', 'name', 'code']);
        $today      = Carbon::today();
        $query      = SupplierInvoice::with('supplier')->where('remaining_amount', '>', 0)->whereNotIn('status', ['brouillon', 'annulee']);
        if ($supplierId) $query->where('supplier_id', $supplierId);
        $invoices = $query->orderBy('due_at')->get();
        $totalDue = $invoices->sum('remaining_amount');
        return view('suppliers.factures-impayees', compact('invoices', 'suppliers', 'supplierId', 'today', 'totalDue'));
    }

    public function facturesImpayeesExportExcel(Request $request)
    {
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        return Excel::download(new FacturesImpayeesSupplierExport($supplierId), 'factures-impayees-fourn-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function facturesImpayeesExportPdf(Request $request)
    {
        $supplierId = $request->input('supplier_id') ? (int) $request->input('supplier_id') : null;
        $today      = Carbon::today();
        $company    = currentCompany();
        $query      = SupplierInvoice::with('supplier')->where('remaining_amount', '>', 0)->whereNotIn('status', ['brouillon', 'annulee']);
        if ($supplierId) $query->where('supplier_id', $supplierId);
        $invoices = $query->orderBy('due_at')->get();
        $totalDue = $invoices->sum('remaining_amount');
        return Pdf::loadView('suppliers.pdf.factures-impayees', compact('company', 'invoices', 'today', 'totalDue'))
            ->setPaper('a4', 'landscape')->download('factures-impayees-fourn-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  5. JOURNAL DES ACHATS                                                  */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function journalAchats(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');
        $entries  = $this->queryJournalAchats($dateFrom, $dateTo, $search);
        $totalDebit  = $entries->sum('total_debit');
        $totalCredit = $entries->sum('total_credit');
        return view('suppliers.journal-achats', compact('entries', 'dateFrom', 'dateTo', 'search', 'totalDebit', 'totalCredit'));
    }

    public function journalAchatsExportExcel(Request $request)
    {
        return Excel::download(new JournalAchatsExport($request->input('date_from'), $request->input('date_to'), $request->input('search')), 'journal-achats-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function journalAchatsExportPdf(Request $request)
    {
        $dateFrom    = $request->input('date_from');
        $dateTo      = $request->input('date_to');
        $search      = $request->input('search');
        $company     = currentCompany();
        $entries     = $this->queryJournalAchats($dateFrom, $dateTo, $search);
        $totalDebit  = $entries->sum('total_debit');
        $totalCredit = $entries->sum('total_credit');
        return Pdf::loadView('suppliers.pdf.journal-achats', compact('company', 'entries', 'dateFrom', 'dateTo', 'totalDebit', 'totalCredit'))
            ->setPaper('a4', 'landscape')->download('journal-achats-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  6. GRAND LIVRE FOURNISSEURS                                            */
    /* ═══════════════════════════════════════════════════════════════════════ */

    public function grandLivre(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');
        $accounts = $this->computeGrandLivre($dateFrom, $dateTo, $search);
        return view('suppliers.grand-livre', compact('accounts', 'dateFrom', 'dateTo', 'search'));
    }

    public function grandLivreExportExcel(Request $request)
    {
        return Excel::download(new GrandLivreSupplierExport($request->input('date_from'), $request->input('date_to'), $request->input('search')), 'grand-livre-fourn-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function grandLivreExportPdf(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');
        $company  = currentCompany();
        $accounts = $this->computeGrandLivre($dateFrom, $dateTo, $search);
        return Pdf::loadView('suppliers.pdf.grand-livre', compact('company', 'accounts', 'dateFrom', 'dateTo'))
            ->setPaper('a4', 'landscape')->download('grand-livre-fourn-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ═══════════════════════════════════════════════════════════════════════ */
    /*  HELPERS PRIVÉS                                                         */
    /* ═══════════════════════════════════════════════════════════════════════ */

    private function computeReleveLines(?Supplier $supplier, ?string $dateFrom, ?string $dateTo): array
    {
        if (!$supplier || !$dateFrom || !$dateTo) return [collect(), 0];

        $factAvant   = SupplierInvoice::where('supplier_id', $supplier->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereDate('received_at', '<', $dateFrom)->sum('total_ttc');
        $retourAvant = SupplierReturn::where('supplier_id', $supplier->id)->where('status', 'valide')->whereDate('returned_at', '<', $dateFrom)->sum('total_ttc');
        $reglAvant   = SupplierPayment::where('supplier_id', $supplier->id)->whereNotIn('status', ['annule', 'rejete'])->whereDate('payment_date', '<', $dateFrom)->sum('amount');
        $soldeOuv    = $factAvant - $retourAvant - $reglAvant;

        $lines = collect();
        foreach (SupplierInvoice::where('supplier_id', $supplier->id)->whereNotIn('status', ['brouillon', 'annulee'])->whereBetween('received_at', [$dateFrom, $dateTo])->orderBy('received_at')->get() as $inv) {
            $lines->push(['date' => $inv->received_at, 'type' => 'facture', 'reference' => $inv->supplier_invoice_number ?: $inv->number, 'echeance' => $inv->due_at, 'debit' => $inv->total_ttc, 'credit' => 0]);
        }
        foreach (SupplierReturn::where('supplier_id', $supplier->id)->where('status', 'valide')->whereBetween('returned_at', [$dateFrom, $dateTo])->orderBy('returned_at')->get() as $ret) {
            $lines->push(['date' => $ret->returned_at, 'type' => 'retour', 'reference' => $ret->number, 'echeance' => null, 'debit' => 0, 'credit' => $ret->total_ttc]);
        }
        foreach (SupplierPayment::where('supplier_id', $supplier->id)->whereNotIn('status', ['annule', 'rejete'])->whereBetween('payment_date', [$dateFrom, $dateTo])->orderBy('payment_date')->get() as $p) {
            $lines->push(['date' => $p->payment_date, 'type' => 'paiement', 'reference' => $p->number, 'echeance' => null, 'debit' => 0, 'credit' => $p->amount]);
        }

        $solde = $soldeOuv;
        $lines = $lines->sortBy('date')->values()->map(function ($l) use (&$solde) {
            $solde += $l['debit'] - $l['credit'];
            return array_merge($l, ['solde' => $solde]);
        });

        return [$lines, $soldeOuv];
    }

    private function computeBalance(?string $search): array
    {
        $query = Supplier::query();
        if ($search) $query->where(fn($q) => $q->where('name', 'like', "%$search%")->orWhere('code', 'like', "%$search%"));

        $rows = $query->get()->map(function ($s) {
            $totalFact  = SupplierInvoice::where('supplier_id', $s->id)->whereNotIn('status', ['brouillon', 'annulee'])->sum('total_ttc');
            $totalRetour = SupplierReturn::where('supplier_id', $s->id)->where('status', 'valide')->sum('total_ttc');
            $totalPaye  = SupplierPayment::where('supplier_id', $s->id)->whereNotIn('status', ['annule', 'rejete'])->sum('amount');
            $solde      = $totalFact - $totalRetour - $totalPaye;
            return ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'total_fact' => $totalFact, 'total_retour' => $totalRetour, 'total_paye' => $totalPaye, 'solde' => $solde];
        })->filter(fn($r) => $r['total_fact'] > 0 || $r['solde'] != 0)->sortByDesc('solde')->values();

        $totals = ['total_fact' => $rows->sum('total_fact'), 'total_retour' => $rows->sum('total_retour'), 'total_paye' => $rows->sum('total_paye'), 'solde' => $rows->sum('solde')];

        return [$rows, $totals];
    }

    private function computeBalanceAgee(Carbon $today, ?int $supplierId): array
    {
        $query = SupplierInvoice::with('supplier')->whereNotIn('status', ['brouillon', 'annulee'])->where('remaining_amount', '>', 0);
        if ($supplierId) $query->where('supplier_id', $supplierId);

        $data = [];
        foreach ($query->get() as $inv) {
            $sid    = $inv->supplier_id;
            $amount = (int) $inv->remaining_amount;
            $due    = $inv->due_at;
            $days   = $due ? (int) $today->diffInDays($due, false) * -1 : 0;
            if (!isset($data[$sid])) {
                $data[$sid] = ['code' => $inv->supplier?->code ?? '', 'name' => $inv->supplier?->name ?? '—', 'total' => 0, 'non_echu' => 0, 'j1_30' => 0, 'j31_60' => 0, 'j61_90' => 0, 'j90p' => 0];
            }
            $data[$sid]['total'] += $amount;
            if (!$due || $days <= 0)  { $data[$sid]['non_echu'] += $amount; }
            elseif ($days <= 30)      { $data[$sid]['j1_30']    += $amount; }
            elseif ($days <= 60)      { $data[$sid]['j31_60']   += $amount; }
            elseif ($days <= 90)      { $data[$sid]['j61_90']   += $amount; }
            else                      { $data[$sid]['j90p']     += $amount; }
        }

        $rows   = collect(array_values($data))->sortByDesc('total')->values();
        $totals = ['total' => $rows->sum('total'), 'non_echu' => $rows->sum('non_echu'), 'j1_30' => $rows->sum('j1_30'), 'j31_60' => $rows->sum('j31_60'), 'j61_90' => $rows->sum('j61_90'), 'j90p' => $rows->sum('j90p')];

        return [$rows, $totals];
    }

    private function queryJournalAchats(?string $dateFrom, ?string $dateTo, ?string $search)
    {
        $query = JournalEntry::with(['journalType', 'lines.account'])
            ->whereHas('journalType', fn($q) => $q->where('type', 'achat'))
            ->where('status', 'valide');
        if ($dateFrom) $query->whereDate('entry_date', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('entry_date', '<=', $dateTo);
        if ($search) {
            $s = "%$search%";
            $query->where(fn($q) => $q->where('number', 'like', $s)->orWhere('reference', 'like', $s)->orWhere('description', 'like', $s));
        }
        return $query->orderBy('entry_date')->orderBy('id')->get();
    }

    private function computeGrandLivre(?string $dateFrom, ?string $dateTo, ?string $search): array
    {
        $query = JournalEntryLine::with(['account', 'journalEntry.journalType'])
            ->whereHas('account', fn($q) => $q->where('code', 'like', '401%'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide'));
        if ($dateFrom) $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '>=', $dateFrom));
        if ($dateTo)   $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $dateTo));
        if ($search) {
            $s = "%$search%";
            $query->where(fn($q) => $q->whereHas('account', fn($aq) => $aq->where('code', 'like', $s)->orWhere('name', 'like', $s))->orWhere('label', 'like', $s)->orWhereHas('journalEntry', fn($eq) => $eq->where('number', 'like', $s)->orWhere('reference', 'like', $s)));
        }

        $lines   = $query->orderBy(\App\Models\JournalEntry::select('entry_date')->whereColumn('id', 'journal_entry_lines.journal_entry_id'))->orderBy('journal_entry_id')->get();
        $grouped = $lines->groupBy(fn($l) => $l->account?->code);
        $accounts = [];

        foreach ($grouped as $code => $accountLines) {
            $soldeOuv = 0;
            if ($dateFrom) {
                $open     = JournalEntryLine::whereHas('account', fn($q) => $q->where('code', $code))->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')->whereDate('entry_date', '<', $dateFrom))->get();
                $soldeOuv = $open->sum('credit') - $open->sum('debit');
            }
            $solde = $soldeOuv;
            $linesWithSolde = $accountLines->map(function ($l) use (&$solde) {
                $solde += (int)$l->credit - (int)$l->debit;
                return ['line' => $l, 'solde' => $solde];
            });
            $accounts[] = ['code' => $code, 'name' => $accountLines->first()?->account?->name ?? '—', 'solde_ouv' => $soldeOuv, 'lines' => $linesWithSolde, 'total_d' => $accountLines->sum('debit'), 'total_c' => $accountLines->sum('credit'), 'solde_fin' => $solde];
        }

        usort($accounts, fn($a, $b) => strcmp($a['code'], $b['code']));
        return $accounts;
    }
}
