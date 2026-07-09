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
    $btn = 'inline-flex items-center gap-1.5 px-3 py-1 text-[12px] font-semibold rounded-[4px] transition-colors';
    $kpi = 'bg-white rounded-[4px] border border-gray-300 px-3 py-1.5';
    $kpL = 'text-[10px] font-bold text-gray-400 uppercase tracking-wide';
    $kpV = 'text-[15px] font-bold tabular-nums';
@endphp
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Factures</h1>
            <p class="text-[11.5px] text-gray-400">{{ $invoices->total() }} facture(s)</p>
        </div>
        <div class="flex items-center gap-1.5 self-start flex-wrap">
            <a href="{{ route('ventes.factures.index', array_merge(request()->query(), ['export' => 1])) }}"
               class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700 text-white" data-loading data-loading-text="Export Excel en cours…">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Excel
            </a>
            <a href="{{ route('ventes.factures.export-pdf', request()->query()) }}"
               class="{{ $btn }} bg-red-600 hover:bg-red-700 text-white" data-loading data-loading-text="Génération du PDF liste…">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('ventes.factures.create') }}" class="{{ $btn }} bg-emerald-700 hover:bg-emerald-800 text-white">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle facture
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-2.5" data-autosubmit>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, client..."
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">

            <select name="client_id" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">
                <option value="">— Tous les clients —</option>
                @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ ($filters['client_id'] ?? '') == $c->id ? 'selected' : '' }}>
                    {{ $c->trade_name ?? $c->name }}
                </option>
                @endforeach
            </select>

            <select name="status" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="emise"                 {{ ($filters['status'] ?? '') === 'emise'                 ? 'selected' : '' }}>Émise</option>
                <option value="envoyee"             {{ ($filters['status'] ?? '') === 'envoyee'             ? 'selected' : '' }}>Envoyée</option>
                <option value="partiellement_payee" {{ ($filters['status'] ?? '') === 'partiellement_payee' ? 'selected' : '' }}>Partiellement payée</option>
                <option value="payee"               {{ ($filters['status'] ?? '') === 'payee'               ? 'selected' : '' }}>Payée</option>
                <option value="en_retard"           {{ ($filters['status'] ?? '') === 'en_retard'           ? 'selected' : '' }}>En retard</option>
                <option value="annulee"             {{ ($filters['status'] ?? '') === 'annulee'             ? 'selected' : '' }}>Annulée</option>
            </select>

            <select name="overdue" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">
                <option value="">Toutes</option>
                <option value="1" {{ ($filters['overdue'] ?? '') === '1' ? 'selected' : '' }}>En retard seulement</option>
            </select>

            {{-- [UX-3] Filtre par type de facture --}}
            <select name="type" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">
                <option value="">Tous les types</option>
                <option value="standard"   {{ ($filters['type'] ?? '') === 'standard'   ? 'selected' : '' }}>Standard</option>
                <option value="proforma"   {{ ($filters['type'] ?? '') === 'proforma'   ? 'selected' : '' }}>Proforma</option>
                <option value="acompte"    {{ ($filters['type'] ?? '') === 'acompte'    ? 'selected' : '' }}>Acompte</option>
                <option value="partielle"  {{ ($filters['type'] ?? '') === 'partielle'  ? 'selected' : '' }}>Partielle</option>
                <option value="recurrente" {{ ($filters['type'] ?? '') === 'recurrente' ? 'selected' : '' }}>Récurrente</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Du"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">

            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Au"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] bg-white focus:outline-none focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500">

            <div class="flex gap-2 lg:col-span-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12.5px] font-semibold px-4 h-8 rounded-[4px] transition-colors">Filtrer</button>
                @if(request()->hasAny(['search','status','type','overdue','client_id','date_from','date_to']))
                <a href="{{ route('ventes.factures.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12.5px] px-3 h-8 inline-flex items-center rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- KPI summary bar (dense SAGE) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Total TTC filtré</p><p class="{{ $kpV }} text-gray-900">{{ $fmt($summary['total_ttc']) }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Reste à encaisser</p><p class="{{ $kpV }} text-orange-600">{{ $fmt($summary['total_remaining']) }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">En retard</p><p class="{{ $kpV }} {{ $summary['count_overdue'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $summary['count_overdue'] }} <span class="text-[10px] font-normal text-gray-400">fact.</span></p></div>
        <div class="{{ $kpi }}"><p class="{{ $kpL }}">Payées</p><p class="{{ $kpV }} text-emerald-600">{{ $summary['count_paid'] }} <span class="text-[10px] font-normal text-gray-400">fact.</span></p></div>
    </div>

    {{-- Liste style SAGE X3 : grille dense, codes mono, montants HT/TTC/reste --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="tbl-scroll">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-1 uppercase tracking-wide w-36">N° facture</th>
                        <th class="text-left font-bold px-3 py-1 uppercase tracking-wide">Client</th>
                        <th class="text-left font-bold px-3 py-1 uppercase tracking-wide hidden md:table-cell w-24">Émission</th>
                        <th class="text-left font-bold px-3 py-1 uppercase tracking-wide hidden lg:table-cell w-28">Échéance</th>
                        <th class="text-right font-bold px-3 py-1 uppercase tracking-wide hidden lg:table-cell w-28">Montant HT</th>
                        <th class="text-right font-bold px-3 py-1 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-right font-bold px-3 py-1 uppercase tracking-wide hidden lg:table-cell w-32">Reste à payer</th>
                        <th class="text-center font-bold px-3 py-1 uppercase tracking-wide w-28">Statut</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    @php
                        $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                            && !in_array($invoice->status, ['payee', 'annulee']);
                    @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $isOverdue ? '!bg-red-50/40' : '' }}">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.factures.show', $invoice) }}" class="hover:underline font-semibold">{{ $invoice->number }}</a>
                            {{-- [UX-3] Badge type — n'affiche pas "standard" (cas par défaut) --}}
                            @if($invoice->type && $invoice->type !== 'standard')
                                @php
                                    $typeBadges = [
                                        'proforma'   => 'bg-emerald-100 text-emerald-800',
                                        'acompte'    => 'bg-amber-100 text-amber-700',
                                        'partielle'  => 'bg-cyan-100 text-cyan-700',
                                        'recurrente' => 'bg-purple-100 text-purple-700',
                                    ];
                                    $typeLabels = [
                                        'proforma'   => 'Proforma',
                                        'acompte'    => 'Acompte',
                                        'partielle'  => 'Partielle',
                                        'recurrente' => 'Récurrente',
                                    ];
                                @endphp
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10px] font-bold {{ $typeBadges[$invoice->type] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $typeLabels[$invoice->type] ?? $invoice->type }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-1 font-medium text-gray-900">{{ $invoice->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1 hidden lg:table-cell whitespace-nowrap">
                            @if($invoice->due_at)
                                <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $invoice->due_at->format('d/m/Y') }}</span>
                                @if($isOverdue)<span class="ml-1 inline-flex px-1.5 py-0.5 rounded-[3px] text-[10px] font-bold bg-red-100 text-red-700">RETARD</span>@endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 hidden lg:table-cell whitespace-nowrap">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($invoice->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums hidden lg:table-cell whitespace-nowrap">
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
                                <a href="{{ route('ventes.factures.show', $invoice) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('ventes.factures.pdf', $invoice) }}" target="_blank"
                                   class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                @if($invoice->status === 'brouillon')
                                <a href="{{ route('ventes.factures.edit', $invoice) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('ventes.factures.validate', $invoice) }}" method="POST"
                                      onsubmit="return confirm('Valider la facture {{ addslashes($invoice->number) }} ?')">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune facture trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $invoices->total() }} facture(s) · Total TTC filtré : <b class="text-emerald-700 tabular-nums">{{ $fmt($summary['total_ttc']) }} FCFA</b> · Reste à encaisser : <b class="text-orange-600 tabular-nums">{{ $fmt($summary['total_remaining']) }} FCFA</b></span>
        @if($invoices->hasPages())<div>{{ $invoices->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
