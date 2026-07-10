<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * [Maquette X3] Relevé client — position d'un client à date d'arrêté :
 * solde initial + factures (débit) / avoirs & encaissements (crédit) → solde cumulé,
 * statuts d'échéance et de lettrage, balance âgée, synthèse.
 */
class ClientStatementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.view');
    }

    public function show(Request $request): View
    {
        $company = currentCompany();
        $clients = Client::orderBy('code')->get(['id', 'code', 'name', 'credit_limit', 'compte_collectif', 'assigned_to']);

        $client = $request->input('client_id')
            ? $clients->firstWhere('id', (int) $request->input('client_id'))
            : $clients->first(fn ($c) => Invoice::where('client_id', $c->id)->exists()) ?? $clients->first();

        $fyStart    = $company?->currentFiscalYear?->starts_at?->toDateString() ?? date('Y') . '-01-01';
        $dateFrom   = $request->input('date_from', $fyStart);
        $dateTo     = $request->input('date_to', now()->toDateString());
        $dateArrete = Carbon::parse($request->input('date_arrete', $dateTo));
        $onlyDue    = $request->boolean('only_due');
        $lettrage   = $request->input('lettrage'); // '' | lettrees | non_lettrees

        $rows = collect();
        $soldeInitial = 0;
        $agees = ['non_echu' => 0, 'j0_30' => 0, 'j31_60' => 0, 'j61_90' => 0, 'j90p' => 0];

        if ($client) {
            $docStatuses = ['brouillon', 'annulee'];

            // ── Solde initial : documents antérieurs à la période
            $factAvant = (int) Invoice::where('client_id', $client->id)
                ->whereNotIn('status', $docStatuses)->whereDate('issued_at', '<', $dateFrom)->sum('total_ttc');
            $avoirsAvant = (int) CreditNote::where('client_id', $client->id)
                ->whereIn('status', ['valide', 'applique'])->whereDate('issued_at', '<', $dateFrom)->sum('total_ttc');
            $pmtAvant = (int) ClientPayment::where('client_id', $client->id)
                ->whereDate('payment_date', '<', $dateFrom)->sum('amount');
            $soldeInitial = $factAvant - $avoirsAvant - $pmtAvant;

            // ── Factures de la période
            foreach (Invoice::where('client_id', $client->id)
                ->whereNotIn('status', $docStatuses)
                ->whereDate('issued_at', '>=', $dateFrom)->whereDate('issued_at', '<=', $dateTo)
                ->orderBy('issued_at')->get() as $inv) {
                $echue = $inv->due_at && $inv->due_at->lte($dateArrete) && $inv->remaining_amount > 0;
                $rows->push((object) [
                    'date'      => $inv->issued_at,
                    'journal'   => 'VE',
                    'piece'     => $inv->number,
                    'reference' => $inv->order?->number ?? $inv->reference ?? '—',
                    'libelle'   => 'Facture n° ' . $inv->number,
                    'echeance'  => $inv->due_at,
                    'debit'     => (int) $inv->total_ttc,
                    'credit'    => 0,
                    'due'       => (int) $inv->remaining_amount,
                    'est_echue' => $echue,
                    // L'échéance prime sur le lettrage partiel : une facture échue
                    // même partiellement payée reste « Échue » (le KPI en dépend).
                    'statut'    => $inv->remaining_amount <= 0 ? 'lettree'
                                    : ($echue ? 'echue'
                                    : ($inv->paid_amount > 0 ? 'partielle' : 'non_echue')),
                    'url'       => route('ventes.factures.show', $inv),
                ]);
            }

            // ── Avoirs de la période
            foreach (CreditNote::where('client_id', $client->id)
                ->whereIn('status', ['valide', 'applique'])
                ->whereDate('issued_at', '>=', $dateFrom)->whereDate('issued_at', '<=', $dateTo)
                ->orderBy('issued_at')->get() as $cn) {
                $rows->push((object) [
                    'date'      => $cn->issued_at,
                    'journal'   => 'AV',
                    'piece'     => $cn->number,
                    'reference' => $cn->invoice?->number ?? '—',
                    'libelle'   => 'Avoir n° ' . $cn->number,
                    'echeance'  => $cn->issued_at,
                    'debit'     => 0,
                    'credit'    => (int) $cn->total_ttc,
                    'due'       => 0,
                    'statut'    => $cn->status === 'applique' ? 'lettree' : 'non_lettree',
                    'url'       => route('ventes.avoirs.show', $cn),
                ]);
            }

            // ── Encaissements de la période
            foreach (ClientPayment::with('allocations')->where('client_id', $client->id)
                ->whereDate('payment_date', '>=', $dateFrom)->whereDate('payment_date', '<=', $dateTo)
                ->orderBy('payment_date')->get() as $pmt) {
                $alloue = (int) $pmt->allocations->sum('amount');
                $rows->push((object) [
                    'date'      => $pmt->payment_date,
                    'journal'   => 'RZ',
                    'piece'     => $pmt->number,
                    'reference' => $pmt->reference ?: ($pmt->bank_reference ?: '—'),
                    'libelle'   => 'Règlement ' . ($pmt->paymentMethod?->name ?? ''),
                    'echeance'  => $pmt->payment_date,
                    'debit'     => 0,
                    'credit'    => (int) $pmt->amount,
                    'due'       => 0,
                    'statut'    => $alloue >= (int) $pmt->amount ? 'lettree'
                                    : ($alloue > 0 ? 'partielle' : 'non_lettree'),
                    'url'       => route('tresorerie.encaissements.show', $pmt),
                ]);
            }

            $rows = $rows->sortBy('date')->values();

            // Synthèse calculée sur le relevé COMPLET (les filtres ne restreignent
            // que l'affichage — la position du client reste la vraie).
            $fullRows = $rows;

            // Filtres échéances / lettrage (affichage)
            if ($onlyDue)                     $rows = $rows->filter(fn ($r) => $r->statut === 'echue')->values();
            if ($lettrage === 'lettrees')     $rows = $rows->filter(fn ($r) => $r->statut === 'lettree')->values();
            if ($lettrage === 'non_lettrees') $rows = $rows->filter(fn ($r) => in_array($r->statut, ['non_lettree', 'non_echue', 'echue', 'partielle']))->values();

            // Solde cumulé sur les lignes affichées (relevé partiel si filtré)
            $running = $soldeInitial;
            $rows = $rows->map(function ($r) use (&$running) {
                $running += $r->debit - $r->credit;
                $r->solde = $running;
                return $r;
            });

            // ── Balance âgée sur le restant dû des factures (toutes, à l'arrêté)
            foreach (Invoice::where('client_id', $client->id)
                ->whereNotIn('status', $docStatuses)->where('remaining_amount', '>', 0)->get() as $inv) {
                $r = (int) $inv->remaining_amount;
                if (! $inv->due_at || $inv->due_at->gt($dateArrete)) { $agees['non_echu'] += $r; continue; }
                $j = $inv->due_at->diffInDays($dateArrete);
                if ($j <= 30)      $agees['j0_30']  += $r;
                elseif ($j <= 60)  $agees['j31_60'] += $r;
                elseif ($j <= 90)  $agees['j61_90'] += $r;
                else               $agees['j90p']   += $r;
            }
        }

        $base = $fullRows ?? collect();
        $soldeFinal = $soldeInitial + $base->sum('debit') - $base->sum('credit');
        $stats = [
            'solde_initial' => $soldeInitial,
            'debit'         => (int) $base->sum('debit'),
            'credit'        => (int) $base->sum('credit'),
            'solde_final'   => $soldeFinal,
            'non_echues'    => (int) $base->where('journal', 'VE')->filter(fn ($r) => ! ($r->est_echue ?? false) && $r->due > 0)->sum('due'),
            'echues'        => (int) $base->where('journal', 'VE')->filter(fn ($r) => ($r->est_echue ?? false))->sum('due'),
            'encours_aut'   => (int) ($client->credit_limit ?? 0),
            'depassement'   => max(0, $soldeFinal - (int) ($client->credit_limit ?? 0)),
        ];

        // Historique : 5 derniers événements d'audit du client (si dispo)
        $historique = collect();
        if ($client && class_exists(\App\Models\AuditLog::class)) {
            $historique = \App\Models\AuditLog::where('model_type', Client::class)
                ->where('model_id', $client->id)
                ->latest()->limit(5)->get();
        }

        return view('comptabilite.tiers.releve', compact(
            'company', 'clients', 'client', 'rows', 'stats', 'agees', 'historique',
            'dateFrom', 'dateTo', 'dateArrete', 'onlyDue', 'lettrage'
        ));
    }

    /** POST — envoie le relevé PDF par email au client. */
    public function send(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        if (! $client->email) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse email renseignée.');
        }

        // Réutilise la génération PDF du relevé Gestion (même rendu que l'export)
        $stmt = app(\App\Http\Controllers\ClientReportController::class)
            ->buildStatement($client, $data['date_from'], $data['date_to']);
        $soldeFinal = (int) ($stmt['lines']->last()['solde'] ?? $stmt['soldeOuv']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gestion.clients.pdf.releve', [
            'client'   => $client,
            'lines'    => $stmt['lines'],
            'soldeOuv' => $stmt['soldeOuv'],
            'dateFrom' => $data['date_from'],
            'dateTo'   => $data['date_to'],
            'company'  => currentCompany(),
        ])->setPaper('a4', 'portrait');

        \Illuminate\Support\Facades\Mail::to($client->email)
            ->send(new \App\Mail\ClientStatementMail($client, $data['date_from'], $data['date_to'], $soldeFinal, $pdf->output()));

        \App\Models\AuditLog::create([
            'user_id'    => \Illuminate\Support\Facades\Auth::id(),
            'user_name'  => \Illuminate\Support\Facades\Auth::user()->name,
            'action'     => 'releve_envoye',
            'model_type' => Client::class,
            'model_id'   => $client->id,
            'new_values' => ['email' => $client->email, 'periode' => $data['date_from'] . ' → ' . $data['date_to']],
        ]);

        return back()->with('success', 'Relevé envoyé à ' . $client->email . '.');
    }

    /** POST — ajoute un commentaire au dossier client (journalisé). */
    public function comment(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'text'      => ['required', 'string', 'min:3', 'max:500'],
        ]);

        \App\Models\AuditLog::create([
            'user_id'    => \Illuminate\Support\Facades\Auth::id(),
            'user_name'  => \Illuminate\Support\Facades\Auth::user()->name,
            'action'     => 'commentaire',
            'model_type' => Client::class,
            'model_id'   => $data['client_id'],
            'new_values' => ['text' => $data['text']],
        ]);

        return back()->with('success', 'Commentaire ajouté.');
    }
}
