@extends('layouts.erp')
@section('title', 'Factures')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Factures</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int)$n, 0, ',', ' ');
    $inp = 'w-full h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500';
    $lbl = 'block text-[12px] font-medium text-gray-700 mb-1';
@endphp
<div class="space-y-3">

    <x-sales.module-nav />

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[20px] font-bold text-gray-900">Factures</h1>
            <p class="text-[12px] text-gray-400 mt-0.5">{{ $invoices->total() }} facture(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('ventes.factures.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-[13px] font-semibold transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle facture
            </a>
            <a href="{{ route('ventes.factures.index', array_merge(request()->query(), ['export' => 1])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-[13px] font-medium transition-colors"
               data-loading data-loading-text="Export Excel en cours…">
                Excel
            </a>
            <a href="{{ route('ventes.factures.export-pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-[13px] font-medium transition-colors"
               data-loading data-loading-text="Génération du PDF liste…">
                PDF
            </a>
        </div>
    </div>

    {{-- ══ 1. Critères de sélection ══════════════════════════════════════════ --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300" data-autosubmit>
        <div class="px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Critères de sélection</p>
        </div>
        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="f-search" class="{{ $lbl }}">Recherche</label>
                <input id="f-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Numéro, client…" class="{{ $inp }}">
            </div>
            <div>
                <label for="f-client" class="{{ $lbl }}">Client</label>
                <select id="f-client" name="client_id" class="{{ $inp }} py-0">
                    <option value="">— Tous les clients —</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ ($filters['client_id'] ?? '') == $c->id ? 'selected' : '' }}>
                        {{ $c->trade_name ?? $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-status" class="{{ $lbl }}">Statut</label>
                <select id="f-status" name="status" class="{{ $inp }} py-0">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                    <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>En attente de validation</option>
                    <option value="emise"                 {{ ($filters['status'] ?? '') === 'emise'                 ? 'selected' : '' }}>Émise</option>
                    <option value="envoyee"               {{ ($filters['status'] ?? '') === 'envoyee'               ? 'selected' : '' }}>Envoyée</option>
                    <option value="partiellement_payee"   {{ ($filters['status'] ?? '') === 'partiellement_payee'   ? 'selected' : '' }}>Partiellement payée</option>
                    <option value="payee"                 {{ ($filters['status'] ?? '') === 'payee'                 ? 'selected' : '' }}>Payée</option>
                    <option value="en_retard"             {{ ($filters['status'] ?? '') === 'en_retard'             ? 'selected' : '' }}>En retard</option>
                    <option value="annulee"               {{ ($filters['status'] ?? '') === 'annulee'               ? 'selected' : '' }}>Annulée</option>
                </select>
            </div>
            <div>
                <label for="f-type" class="{{ $lbl }}">Type de facture</label>
                <select id="f-type" name="type" class="{{ $inp }} py-0">
                    <option value="">Tous les types</option>
                    <option value="standard"   {{ ($filters['type'] ?? '') === 'standard'   ? 'selected' : '' }}>Standard</option>
                    <option value="proforma"   {{ ($filters['type'] ?? '') === 'proforma'   ? 'selected' : '' }}>Proforma</option>
                    <option value="acompte"    {{ ($filters['type'] ?? '') === 'acompte'    ? 'selected' : '' }}>Acompte</option>
                    <option value="partielle"  {{ ($filters['type'] ?? '') === 'partielle'  ? 'selected' : '' }}>Partielle</option>
                    <option value="recurrente" {{ ($filters['type'] ?? '') === 'recurrente' ? 'selected' : '' }}>Récurrente</option>
                </select>
            </div>
            <div>
                <label for="f-overdue" class="{{ $lbl }}">Échéance</label>
                <select id="f-overdue" name="overdue" class="{{ $inp }} py-0">
                    <option value="">Toutes</option>
                    <option value="1" {{ ($filters['overdue'] ?? '') === '1' ? 'selected' : '' }}>En retard seulement</option>
                </select>
            </div>
            <div>
                <label for="f-from" class="{{ $lbl }}">Émission du</label>
                <input id="f-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inp }}">
            </div>
            <div>
                <label for="f-to" class="{{ $lbl }}">Émission au</label>
                <input id="f-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inp }}">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-4 h-8 rounded-[4px] transition-colors">Filtrer</button>
                @if(request()->hasAny(['search','status','type','overdue','client_id','date_from','date_to']))
                <a href="{{ route('ventes.factures.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-3 h-8 inline-flex items-center rounded-[4px] transition-colors"
                   title="Réinitialiser les filtres">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- ══ 2. Liste des factures ═════════════════════════════════════════════ --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. Liste des factures</p>
            <p class="text-[11px] text-emerald-600">{{ $invoices->total() }} facture(s) · XOF</p>
        </div>
        <div class="tbl-scroll">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0]/70 text-emerald-900 border-b border-gray-300 text-[11px]">
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide w-36">N° facture</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide">Client</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden md:table-cell w-24">Émission</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-28">Échéance</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-28">Montant HT</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-32">Reste à payer</th>
                        <th class="text-center font-bold px-3 py-1.5 uppercase tracking-wide w-28">Statut</th>
                        <th class="px-3 py-1.5 w-24"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    @php
                        $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                            && !in_array($invoice->status, ['payee', 'annulee']);
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-[#eef5f0]/40 transition-colors {{ $isOverdue ? '!bg-red-50/40' : '' }}">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.factures.show', $invoice) }}" class="hover:underline font-semibold">{{ $invoice->number }}</a>
                            {{-- [UX-3] Badge type — n'affiche pas "standard" (cas par défaut) --}}
                            @if($invoice->type && $invoice->type !== 'standard')
                                @php
                                    $typeBadges = [
                                        'proforma'   => 'bg-emerald-100 text-emerald-800',
                                        'acompte'    => 'bg-amber-100 text-amber-700',
                                        'partielle'  => 'bg-cyan-100 text-cyan-700',
                                        'recurrente' => 'bg-blue-100 text-blue-700',
                                    ];
                                    $typeLabels = [
                                        'proforma'   => 'Proforma',
                                        'acompte'    => 'Acompte',
                                        'partielle'  => 'Partielle',
                                        'recurrente' => 'Récurrente',
                                    ];
                                @endphp
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[11px] font-bold {{ $typeBadges[$invoice->type] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $typeLabels[$invoice->type] ?? $invoice->type }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-1 font-medium text-gray-900">{{ $invoice->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1 hidden lg:table-cell whitespace-nowrap">
                            @if($invoice->due_at)
                                <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $invoice->due_at->format('d/m/Y') }}</span>
                                @if($isOverdue)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-[3px] text-[11px] font-bold bg-red-100 text-red-700">RETARD</span>@endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-right font-mono tabular-nums text-gray-700 hidden lg:table-cell whitespace-nowrap">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right font-mono tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($invoice->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right font-mono tabular-nums hidden lg:table-cell whitespace-nowrap">
                            @if($invoice->remaining_amount > 0)
                                <span class="font-semibold text-red-600">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-center">
                            <x-workflow.status-badge :status="$invoice->status" :label="$invoice->status_label" size="sm" />
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('ventes.factures.show', $invoice) }}" aria-label="Voir la facture {{ $invoice->number }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('ventes.factures.pdf', $invoice) }}" target="_blank" aria-label="PDF de la facture {{ $invoice->number }}"
                                   class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                @if($invoice->status === 'brouillon')
                                <a href="{{ route('ventes.factures.edit', $invoice) }}" aria-label="Modifier la facture {{ $invoice->number }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('ventes.factures.validate', $invoice) }}" method="POST"
                                      data-confirm="Valider la facture {{ addslashes($invoice->number) }} ?">
                                    @csrf
                                    <button type="submit" aria-label="Valider la facture {{ $invoice->number }}"
                                            class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400 text-[13px]">Aucune facture trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">{{ $invoices->appends($filters)->links() }}</div>
        @endif
    </div>

    {{-- ══ Synthèse (pattern X3 : barre basse) ═══════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-200">
        <div class="p-3 text-center">
            <p class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">Total TTC filtré</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-gray-900 mt-0.5">{{ $fmt($summary['total_ttc']) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">Reste à encaisser</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-orange-600 mt-0.5">{{ $fmt($summary['total_remaining']) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">En retard</p>
            <p class="text-[15px] font-bold {{ $summary['count_overdue'] > 0 ? 'text-red-600' : 'text-gray-900' }} mt-0.5">{{ $summary['count_overdue'] }} <span class="text-[11px] font-normal text-gray-400">fact.</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">Payées</p>
            <p class="text-[15px] font-bold text-emerald-700 mt-0.5">{{ $summary['count_paid'] }} <span class="text-[11px] font-normal text-gray-400">fact.</span></p>
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-[12px]">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">Ventes — Factures</strong></span>
            <span>Filtre : <strong class="text-white">{{ request()->hasAny(['search','status','type','overdue','client_id','date_from','date_to']) ? 'Actif' : 'Aucun' }}</strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
