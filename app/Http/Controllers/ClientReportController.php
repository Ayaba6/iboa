<?php

namespace App\Http\Controllers;

use App\Exports\Clients\BalanceAgeeExport;
use App\Exports\Clients\GrandLivreClientExport;
use App\Exports\Clients\ReleveAllClientsExport;
use App\Exports\Clients\ReleveClientExport;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ClientReportController extends Controller
{
    /* ─────────────────────────────────────────────────────────────────────── */
    /*  HELPER PRIVÉ — Construit le relevé d'un client sur une période         */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function buildStatement(Client $client, string $dateFrom, string $dateTo): array
    {
        // Solde d'ouverture = (factures - avoirs - règlements) AVANT date_from
        $factAvant  = Invoice::where('client_id', $client->id)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereDate('issued_at', '<', $dateFrom)
            ->sum('total_ttc');

        $avoirAvant = CreditNote::where('client_id', $client->id)
            ->whereIn('status', ['valide', 'applique'])
            ->whereDate('issued_at', '<', $dateFrom)
            ->sum('total_ttc');

        $reglAvant = ClientPayment::where('client_id', $client->id)
            ->whereNotIn('status', ['annule', 'rejete'])
            ->whereDate('payment_date', '<', $dateFrom)
            ->sum('amount');

        $soldeOuv = $factAvant - $avoirAvant - $reglAvant;

        $lines = collect();

        foreach (Invoice::where('client_id', $client->id)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereBetween('issued_at', [$dateFrom, $dateTo])
            ->orderBy('issued_at')->get() as $inv) {
            $lines->push([
                'date'      => $inv->issued_at,
                'type'      => 'facture',
                'reference' => $inv->number,
                'label'     => 'Facture',
                'echeance'  => $inv->due_at,
                'debit'     => $inv->total_ttc,
                'credit'    => 0,
            ]);
        }

        foreach (CreditNote::where('client_id', $client->id)
            ->whereIn('status', ['valide', 'applique'])
            ->whereBetween('issued_at', [$dateFrom, $dateTo])
            ->orderBy('issued_at')->get() as $av) {
            $lines->push([
                'date'      => $av->issued_at,
                'type'      => 'avoir',
                'reference' => $av->number,
                'label'     => 'Avoir',
                'echeance'  => null,
                'debit'     => 0,
                'credit'    => $av->total_ttc,
            ]);
        }

        foreach (ClientPayment::where('client_id', $client->id)
            ->whereNotIn('status', ['annule', 'rejete'])
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->orderBy('payment_date')->get() as $r) {
            $lines->push([
                'date'      => $r->payment_date,
                'type'      => 'reglement',
                'reference' => $r->number,
                'label'     => 'Règlement',
                'echeance'  => null,
                'debit'     => 0,
                'credit'    => $r->amount,
            ]);
        }

        $lines = $lines->sortBy('date')->values();

        $solde = $soldeOuv;
        $lines = $lines->map(function ($l) use (&$solde) {
            $solde += $l['debit'] - $l['credit'];
            return array_merge($l, ['solde' => $solde]);
        });

        return ['lines' => $lines, 'soldeOuv' => $soldeOuv];
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  1. RELEVÉ CLIENT                                                       */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function releve(Request $request)
    {
        $clients        = Client::active()->orderBy('name')->get(['id', 'name', 'code', 'email', 'phone']);
        $clientId       = $request->input('client_id');
        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');

        // [Maquette X3] Le relevé mono-client vit désormais sur l'écran Comptabilité › Tiers.
        // Seul le mode consolidé « tous les clients » reste ici (et les exports).
        if ($clientId !== 'all') {
            return redirect()->route('comptabilite.tiers.releve-client', array_filter([
                'client_id' => $clientId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ]));
        }

        $client         = null;
        $lines          = collect();
        $soldeOuv       = 0;
        $allClientsData = null; // tableau pour le mode "tous les clients"

        if ($clientId === 'all' && $dateFrom && $dateTo) {
            // ── Mode tous les clients ───────────────────────────────────────
            $allClientsData = [];
            foreach ($clients as $c) {
                $stmt = $this->buildStatement($c, $dateFrom, $dateTo);
                // On inclut même les clients sans mouvement pour les montrer (solde ouv. non nul ou lignes)
                $allClientsData[] = [
                    'client'   => $c,
                    'lines'    => $stmt['lines'],
                    'soldeOuv' => $stmt['soldeOuv'],
                ];
            }
        } elseif ($clientId && $dateFrom && $dateTo) {
            // ── Mode client unique ──────────────────────────────────────────
            $client = Client::find($clientId);
            if ($client) {
                $stmt     = $this->buildStatement($client, $dateFrom, $dateTo);
                $lines    = $stmt['lines'];
                $soldeOuv = $stmt['soldeOuv'];
            }
        }

        return view('gestion.clients.releve', compact(
            'clients', 'client', 'lines', 'soldeOuv',
            'clientId', 'dateFrom', 'dateTo', 'allClientsData'
        ));
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  2. BALANCE ÂGÉE                                                        */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function balanceAgee(Request $request)
    {
        $today    = Carbon::today();
        $clientId = $request->input('client_id');

        $query = Invoice::with('client')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('remaining_amount', '>', 0);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $invoices = $query->get();

        // Agrégation par client
        $rows = [];
        foreach ($invoices as $inv) {
            $cid    = $inv->client_id;
            $name   = $inv->client?->name ?? '—';
            $code   = $inv->client?->code ?? '';
            $amount = (int) $inv->remaining_amount;
            $due    = $inv->due_at;
            $days   = $due ? (int) $today->diffInDays($due, false) * -1 : 0;

            if (!isset($rows[$cid])) {
                $rows[$cid] = [
                    'client_id' => $cid,
                    'code'      => $code,
                    'name'      => $name,
                    'total'     => 0,
                    'non_echu'  => 0,
                    'j1_30'     => 0,
                    'j31_60'    => 0,
                    'j61_90'    => 0,
                    'j90p'      => 0,
                ];
            }

            $rows[$cid]['total'] += $amount;

            if (!$due || $days <= 0) {
                $rows[$cid]['non_echu'] += $amount;
            } elseif ($days <= 30) {
                $rows[$cid]['j1_30'] += $amount;
            } elseif ($days <= 60) {
                $rows[$cid]['j31_60'] += $amount;
            } elseif ($days <= 90) {
                $rows[$cid]['j61_90'] += $amount;
            } else {
                $rows[$cid]['j90p'] += $amount;
            }
        }

        $rows = collect(array_values($rows))->sortByDesc('total');

        $totals = [
            'total'    => $rows->sum('total'),
            'non_echu' => $rows->sum('non_echu'),
            'j1_30'    => $rows->sum('j1_30'),
            'j31_60'   => $rows->sum('j31_60'),
            'j61_90'   => $rows->sum('j61_90'),
            'j90p'     => $rows->sum('j90p'),
        ];

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'code']);

        return view('gestion.clients.balance-agee', compact('rows', 'totals', 'clients', 'clientId', 'today'));
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  3. GRAND LIVRE CLIENT                                                  */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function grandLivreClient(Request $request)
    {
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $search    = $request->input('search');

        $query = JournalEntryLine::with(['account', 'journalEntry.journalType'])
            ->whereHas('account', fn($q) => $q->where('code', 'like', '411%'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide'));

        if ($dateFrom) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '>=', $dateFrom));
        }
        if ($dateTo) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $dateTo));
        }
        if ($search) {
            $query->where(fn($q) =>
                $q->whereHas('account', fn($aq) => $aq->where('code', 'like', "%$search%")->orWhere('name', 'like', "%$search%"))
                  ->orWhere('label', 'like', "%$search%")
                  ->orWhereHas('journalEntry', fn($eq) => $eq->where('number', 'like', "%$search%")->orWhere('reference', 'like', "%$search%"))
            );
        }

        $lines = $query->orderBy(
            \App\Models\JournalEntry::select('entry_date')
                ->whereColumn('id', 'journal_entry_lines.journal_entry_id'),
            'asc'
        )->orderBy('journal_entry_id')->get();

        $grouped  = $lines->groupBy(fn($l) => $l->account?->code);
        $accounts = [];

        foreach ($grouped as $code => $accountLines) {
            $soldeOuv = 0;
            if ($dateFrom) {
                $openLines = JournalEntryLine::whereHas('account', fn($q) => $q->where('code', $code))
                    ->whereHas('journalEntry', fn($q) =>
                        $q->where('status', 'valide')->whereDate('entry_date', '<', $dateFrom)
                    )->get();
                $soldeOuv = $openLines->sum('debit') - $openLines->sum('credit');
            }

            $solde          = $soldeOuv;
            $linesWithSolde = $accountLines->map(function ($l) use (&$solde) {
                $solde += (int)$l->debit - (int)$l->credit;
                return ['line' => $l, 'solde' => $solde];
            });

            $accounts[] = [
                'code'      => $code,
                'name'      => $accountLines->first()?->account?->name ?? '—',
                'solde_ouv' => $soldeOuv,
                'lines'     => $linesWithSolde,
                'total_d'   => $accountLines->sum('debit'),
                'total_c'   => $accountLines->sum('credit'),
                'solde_fin' => $solde,
            ];
        }

        usort($accounts, fn($a, $b) => strcmp($a['code'], $b['code']));

        return view('gestion.clients.grand-livre', compact(
            'accounts', 'dateFrom', 'dateTo', 'search'
        ));
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  EXPORTS — RELEVÉ                                                        */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function releveExportExcel(Request $request)
    {
        $clientId = $request->input('client_id');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        // ── Mode tous les clients ────────────────────────────────────────────
        if ($clientId === 'all' && $dateFrom && $dateTo) {
            $filename = 'releve-tous-clients-' . now()->format('Y-m-d') . '.xlsx';
            return Excel::download(new ReleveAllClientsExport($dateFrom, $dateTo), $filename);
        }

        // ── Mode client unique ───────────────────────────────────────────────
        $clientIdInt = (int) $clientId;
        $client      = Client::find($clientIdInt);
        $name        = $client ? str($client->name)->slug('-') : 'client';
        $filename    = 'releve-' . $name . '-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new ReleveClientExport($clientIdInt, $dateFrom, $dateTo), $filename);
    }

    public function releveExportPdf(Request $request)
    {
        $clientId = $request->input('client_id');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $company  = currentCompany();

        // ── Mode tous les clients ───────────────────────────────────────────
        if ($clientId === 'all' && $dateFrom && $dateTo) {
            $allClients = Client::active()->orderBy('name')->get(['id', 'name', 'code', 'email', 'phone']);
            $allClientsData = [];
            foreach ($allClients as $c) {
                $stmt = $this->buildStatement($c, $dateFrom, $dateTo);
                $allClientsData[] = [
                    'client'   => $c,
                    'lines'    => $stmt['lines'],
                    'soldeOuv' => $stmt['soldeOuv'],
                ];
            }
            $pdf = Pdf::loadView('gestion.clients.pdf.releve-all', compact(
                'company', 'allClientsData', 'dateFrom', 'dateTo'
            ))->setPaper('a4', 'landscape');
            return $pdf->download('releve-tous-clients-' . now()->format('Y-m-d') . '.pdf');
        }

        // ── Mode client unique ──────────────────────────────────────────────
        $client   = Client::find((int) $clientId);
        $lines    = collect();
        $soldeOuv = 0;

        if ($client && $dateFrom && $dateTo) {
            $stmt     = $this->buildStatement($client, $dateFrom, $dateTo);
            $lines    = $stmt['lines'];
            $soldeOuv = $stmt['soldeOuv'];
        }

        $pdf = Pdf::loadView('gestion.clients.pdf.releve', compact('company', 'client', 'lines', 'soldeOuv', 'dateFrom', 'dateTo'))
            ->setPaper('a4', 'landscape');
        $name = $client ? str($client->name)->slug('-') : 'client';
        return $pdf->download('releve-' . $name . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  EXPORTS — BALANCE ÂGÉE                                                 */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function balanceAgeeExportExcel(Request $request)
    {
        $clientId = $request->input('client_id') ? (int) $request->input('client_id') : null;
        $filename = 'balance-agee-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new BalanceAgeeExport($clientId), $filename);
    }

    public function balanceAgeeExportPdf(Request $request)
    {
        $today    = Carbon::today();
        $clientId = $request->input('client_id') ? (int) $request->input('client_id') : null;
        $company  = currentCompany();

        $query = Invoice::with('client')->whereNotIn('status', ['brouillon', 'annulee'])->where('remaining_amount', '>', 0);
        if ($clientId) $query->where('client_id', $clientId);

        $rowsMap = [];
        foreach ($query->get() as $inv) {
            $cid    = $inv->client_id;
            $amount = (int) $inv->remaining_amount;
            $due    = $inv->due_at;
            $days   = $due ? (int) $today->diffInDays($due, false) * -1 : 0;
            if (!isset($rowsMap[$cid])) {
                $rowsMap[$cid] = ['code' => $inv->client?->code ?? '', 'name' => $inv->client?->name ?? '—', 'total' => 0, 'non_echu' => 0, 'j1_30' => 0, 'j31_60' => 0, 'j61_90' => 0, 'j90p' => 0];
            }
            $rowsMap[$cid]['total'] += $amount;
            if (!$due || $days <= 0) { $rowsMap[$cid]['non_echu'] += $amount; }
            elseif ($days <= 30)     { $rowsMap[$cid]['j1_30']   += $amount; }
            elseif ($days <= 60)     { $rowsMap[$cid]['j31_60']  += $amount; }
            elseif ($days <= 90)     { $rowsMap[$cid]['j61_90']  += $amount; }
            else                     { $rowsMap[$cid]['j90p']    += $amount; }
        }

        $rows   = collect(array_values($rowsMap))->sortByDesc('total');
        $totals = ['total' => $rows->sum('total'), 'non_echu' => $rows->sum('non_echu'), 'j1_30' => $rows->sum('j1_30'), 'j31_60' => $rows->sum('j31_60'), 'j61_90' => $rows->sum('j61_90'), 'j90p' => $rows->sum('j90p')];

        $pdf = Pdf::loadView('gestion.clients.pdf.balance-agee', compact('company', 'rows', 'totals', 'today'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('balance-agee-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ─────────────────────────────────────────────────────────────────────── */
    /*  EXPORTS — GRAND LIVRE                                                   */
    /* ─────────────────────────────────────────────────────────────────────── */

    public function grandLivreExportExcel(Request $request)
    {
        $filename = 'grand-livre-clients-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(
            new GrandLivreClientExport($request->input('date_from'), $request->input('date_to'), $request->input('search')),
            $filename
        );
    }

    public function grandLivreExportPdf(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $search   = $request->input('search');
        $company  = currentCompany();

        $query = JournalEntryLine::with(['account', 'journalEntry.journalType'])
            ->whereHas('account', fn($q) => $q->where('code', 'like', '411%'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide'));

        if ($dateFrom) $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '>=', $dateFrom));
        if ($dateTo)   $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $dateTo));
        if ($search) {
            $s = '%' . $search . '%';
            $query->where(fn($q) =>
                $q->whereHas('account', fn($aq) => $aq->where('code', 'like', $s)->orWhere('name', 'like', $s))
                  ->orWhere('label', 'like', $s)
                  ->orWhereHas('journalEntry', fn($eq) => $eq->where('number', 'like', $s)->orWhere('reference', 'like', $s))
            );
        }

        $lines   = $query->orderBy(\App\Models\JournalEntry::select('entry_date')->whereColumn('id', 'journal_entry_lines.journal_entry_id'), 'asc')->orderBy('journal_entry_id')->get();
        $grouped = $lines->groupBy(fn($l) => $l->account?->code);
        $accounts = [];
        foreach ($grouped as $code => $accountLines) {
            $soldeOuv = 0;
            if ($dateFrom) {
                $openLines = JournalEntryLine::whereHas('account', fn($q) => $q->where('code', $code))
                    ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')->whereDate('entry_date', '<', $dateFrom))->get();
                $soldeOuv = $openLines->sum('debit') - $openLines->sum('credit');
            }
            $solde = $soldeOuv;
            $linesWithSolde = $accountLines->map(function ($l) use (&$solde) {
                $solde += (int)$l->debit - (int)$l->credit;
                return ['line' => $l, 'solde' => $solde];
            });
            $accounts[] = ['code' => $code, 'name' => $accountLines->first()?->account?->name ?? '—', 'solde_ouv' => $soldeOuv, 'lines' => $linesWithSolde, 'total_d' => $accountLines->sum('debit'), 'total_c' => $accountLines->sum('credit'), 'solde_fin' => $solde];
        }
        usort($accounts, fn($a, $b) => strcmp($a['code'], $b['code']));

        $pdf = Pdf::loadView('gestion.clients.pdf.grand-livre', compact('company', 'accounts', 'dateFrom', 'dateTo', 'search'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('grand-livre-clients-' . now()->format('Y-m-d') . '.pdf');
    }
}
