<?php

namespace App\Http\Controllers;

use App\Jobs\SendRelanceEmailJob;
use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientRelanceController extends Controller
{
    /**
     * Dashboard des impayés / relances.
     */
    public function index(Request $request)
    {
        $urgency  = $request->input('urgency', 'all');
        $clientId = $request->input('client_id');
        $today    = Carbon::today();

        // ── Toutes les factures avec un reste à payer (hors brouillon/payée/annulée).
        // On affiche TOUT (échues, à venir, sans échéance) — c'est à l'utilisateur de filtrer
        // via la catégorie d'urgence (Critique / Urgent / Normal / Sans échéance / À venir).
        $query = Invoice::with(['client'])
            ->whereNotIn('status', ['brouillon', 'payee', 'annulee'])
            ->where('remaining_amount', '>', 0);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $invoices = $query->orderByRaw('due_at IS NULL, due_at ASC')->get();

        // Pre-load all relance interactions for the affected clients in ONE query,
        // then match in PHP — eliminates the N+1 (one query per invoice).
        $clientIds = $invoices->pluck('client_id')->unique()->filter()->values();
        $relances  = ClientInteraction::whereIn('client_id', $clientIds)
            ->where('type', 'relance')
            ->orderByDesc('occurred_at')
            ->get()
            ->groupBy('client_id');

        // Enrichir chaque facture avec les jours de retard / à venir et la dernière relance
        $invoices = $invoices->map(function (Invoice $invoice) use ($today, $relances) {
            if ($invoice->due_at) {
                // diffInDays(today, false) : positif si due_at est dans le PASSÉ, négatif si dans le FUTUR
                $invoice->days_overdue = (int) $invoice->due_at->diffInDays($today, false);
            } else {
                $invoice->days_overdue = null;   // pas d'échéance définie
            }

            $invoice->last_relance = ($relances->get($invoice->client_id) ?? collect())
                ->first(fn ($r) => str_contains((string) $r->notes, $invoice->number));

            return $invoice;
        });

        // ── Catégorisation
        $isCritique  = fn($i) => $i->days_overdue !== null && $i->days_overdue >= 60;
        $isUrgent    = fn($i) => $i->days_overdue !== null && $i->days_overdue >= 30 && $i->days_overdue < 60;
        $isNormal    = fn($i) => $i->days_overdue !== null && $i->days_overdue > 0   && $i->days_overdue < 30;
        $isAVenir    = fn($i) => $i->days_overdue !== null && $i->days_overdue <= 0; // due_at >= today
        $isSansEch   = fn($i) => $i->days_overdue === null;

        // Filtrer par catégorie demandée
        $invoices = match ($urgency) {
            'critique'    => $invoices->filter($isCritique),
            'urgent'      => $invoices->filter($isUrgent),
            'normal'      => $invoices->filter($isNormal),
            'a_venir'     => $invoices->filter($isAVenir),
            'sans_ech'    => $invoices->filter($isSansEch),
            default       => $invoices,
        };

        // Statistiques globales (sur le filtré)
        $stats = [
            'total_clients'  => $invoices->pluck('client_id')->unique()->count(),
            'total_factures' => $invoices->count(),
            'total_montant'  => $invoices->sum('remaining_amount'),
            'critique'       => $invoices->filter($isCritique)->count(),
            'urgent'         => $invoices->filter($isUrgent)->count(),
            'normal'         => $invoices->filter($isNormal)->count(),
            'a_venir'        => $invoices->filter($isAVenir)->count(),
            'sans_ech'       => $invoices->filter($isSansEch)->count(),
        ];

        // Grouper par client
        $byClient = $invoices->groupBy('client_id')->map(function ($clientInvoices) {
            $client = $clientInvoices->first()->client;
            return [
                'client'           => $client,
                'invoices'         => $clientInvoices,
                'total_du'         => $clientInvoices->sum('remaining_amount'),
                'max_days_overdue' => $clientInvoices->max('days_overdue'),
                'last_relance'     => $clientInvoices->map->last_relance->filter()->sortByDesc('occurred_at')->first(),
            ];
        })->sortByDesc('max_days_overdue');

        // Pagination manuelle : la catégorisation d'urgence et le groupement
        // par client se font en PHP — on pagine la collection finale (20 clients/page).
        $page      = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage   = 20;
        $byClient  = new \Illuminate\Pagination\LengthAwarePaginator(
            $byClient->forPage($page, $perPage), // clés client_id préservées (utilisées par la vue)
            $byClient->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('relances.index', compact('byClient', 'stats', 'urgency', 'clients', 'clientId'));
    }

    /**
     * Génère un courrier de relance PDF pour un client + factures sélectionnées.
     */
    public function letter(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'type'          => 'required|in:amiable,formelle,mise_en_demeure',
            'message'       => 'nullable|string|max:1000',
        ]);

        $client   = Client::findOrFail($request->client_id);
        $invoices = Invoice::whereIn('id', $request->invoice_ids)
            ->where('client_id', $client->id)
            ->where('remaining_amount', '>', 0)
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture valide sélectionnée pour le courrier.');
        }

        $today = Carbon::today();
        $invoices->each(function (Invoice $i) use ($today) {
            $i->days_overdue = $i->due_at ? (int) $i->due_at->diffInDays($today, false) : null;
        });

        $typeLabels = [
            'amiable'         => '1ère relance (amiable)',
            'formelle'        => '2ème relance (formelle)',
            'mise_en_demeure' => 'Mise en demeure',
        ];

        $company  = \App\Models\currentCompany();
        $totalDu  = (float) $invoices->sum('remaining_amount');
        $typeLabel = $typeLabels[$request->type];
        $message   = $request->message;

        $pdf = Pdf::loadView('relances.pdf.letter', compact(
            'client', 'invoices', 'company', 'totalDu', 'typeLabel', 'message', 'today'
        ))->setPaper('a4');

        $filename = 'relance_' . $client->id . '_' . now()->format('Ymd') . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Envoyer une relance email à un client pour une ou plusieurs factures.
     */
    public function send(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'message'     => 'nullable|string|max:1000',
            'type'        => 'required|in:amiable,formelle,mise_en_demeure',
        ]);

        $client   = Client::with('contacts')->findOrFail($request->client_id);
        $invoices = Invoice::whereIn('id', $request->invoice_ids)
                           ->where('client_id', $client->id)
                           ->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture valide sélectionnée.');
        }

        $typeLabels = [
            'amiable'          => '1ère relance (amiable)',
            'formelle'         => '2ème relance (formelle)',
            'mise_en_demeure'  => 'Mise en demeure',
        ];

        // Collecter tous les destinataires : email principal + contacts recevant les factures
        $additionalContacts = $client->contacts
            ->where('receives_invoices', true)
            ->whereNotNull('email')
            ->filter(fn ($c) => $c->email !== $client->email); // éviter doublon si même adresse

        $hasRecipient = $client->email || $additionalContacts->isNotEmpty();

        if (! $hasRecipient) {
            return back()->with('error',
                'Ce client n\'a pas d\'adresse email renseignée et aucun contact ne reçoit les factures.'
            );
        }

        $invoiceNumbers = $invoices->pluck('number')->join(', ');
        $totalDu        = (float) $invoices->sum('remaining_amount');

        // Construire la liste des destinataires pour le log
        $recipientList = collect();
        if ($client->email) {
            $recipientList->push($client->email);
        }
        $additionalContacts->each(fn ($c) => $recipientList->push("{$c->fullName()} <{$c->email}>"));

        // [PERF-C1] Dispatch asynchronously — the job handles all recipients + logging.
        SendRelanceEmailJob::dispatch(
            client:   $client,
            invoices: $invoices->all(),
            type:     $request->type,
            message:  $request->message ?? '',
            totalDu:  $totalDu,
        );

        // Record the interaction immediately (no need to wait for email delivery)
        ClientInteraction::create([
            'client_id'   => $client->id,
            'user_id'     => Auth::id(),
            'type'        => 'relance',
            'occurred_at' => now(),
            'subject'     => $typeLabels[$request->type].' — '.$invoiceNumbers,
            'notes'       => 'Email planifié vers : '.$recipientList->join(', ')
                             .($request->message ? "\n\nMessage : ".$request->message : '')
                             ."\n\nFactures : ".$invoiceNumbers,
            'outcome'     => 'neutre',
            'followup_at' => now()->addDays(match($request->type) {
                'amiable'         => 7,
                'formelle'        => 5,
                'mise_en_demeure' => 3,
            }),
        ]);

        $recipientCount = $recipientList->count();
        $suffix = $recipientCount > 1 ? " ({$recipientCount} destinataires)" : '';
        return back()->with('success', "Relance programmée pour {$client->displayName()} — {$invoiceNumbers}{$suffix}.");
    }
}
