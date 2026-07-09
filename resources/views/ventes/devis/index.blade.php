@extends('layouts.erp')
@section('title', 'Devis')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Devis</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Total TTC filtré</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Montant accepté</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $fmt($summary['total_accepted']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-[16px] font-bold text-blue-600 tabular-nums">{{ $summary['count_pending'] }} <span class="text-xs font-normal text-gray-400">devis</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Expirés</p>
            <p class="text-[16px] font-bold {{ $summary['count_expired'] > 0 ? 'text-orange-600' : 'text-gray-900' }} tabular-nums">{{ $summary['count_expired'] }} <span class="text-xs font-normal text-gray-400">devis</span></p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Devis</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $quotes->total() }} devis</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('ventes.devis.export', array_filter([
                    'status'    => $filters['status']    ?? null,
                    'search'    => $filters['search']    ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to'   => $filters['date_to']   ?? null,
                ])) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-[4px] transition-colors"
               data-loading data-loading-text="Export Excel en cours…">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('ventes.devis.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau devis
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, client..."
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <select name="status" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="envoye"                {{ ($filters['status'] ?? '') === 'envoye'                ? 'selected' : '' }}>Envoyé</option>
                <option value="accepte"               {{ ($filters['status'] ?? '') === 'accepte'               ? 'selected' : '' }}>Accepté</option>
                <option value="refuse"                {{ ($filters['status'] ?? '') === 'refuse'                ? 'selected' : '' }}>Refusé</option>
                <option value="expire"                {{ ($filters['status'] ?? '') === 'expire'                ? 'selected' : '' }}>Expiré</option>
                <option value="annule"                {{ ($filters['status'] ?? '') === 'annule'                ? 'selected' : '' }}>Annulé</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search','status','client_id','date_from','date_to']))
                <a href="{{ route('ventes.devis.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, montants HT/TTC --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="tbl-scroll">
            <table class="w-full text-[12.5px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide w-32">N° devis</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide">Client</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden md:table-cell w-24">Date</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-24">Validité</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-32">Montant HT</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-center font-bold px-3 py-1.5 uppercase tracking-wide w-32">Statut</th>
                        <th class="px-3 py-2 w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotes as $quote)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.devis.show', $quote) }}" class="hover:underline font-semibold">{{ $quote->number }}</a>
                            @if($quote->reference)<p class="text-[11px] text-gray-400 font-sans">{{ $quote->reference }}</p>@endif
                        </td>
                        <td class="px-3 py-1">
                            <span class="font-medium text-gray-900">{{ $quote->client?->name ?? '—' }}</span>
                            @if($quote->client?->trade_name)<p class="text-[11px] text-gray-400">{{ $quote->client->trade_name }}</p>@endif
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $quote->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1 hidden lg:table-cell whitespace-nowrap">
                            @if($quote->expires_at)
                                <span class="{{ $quote->expires_at->isPast() && !in_array($quote->status, ['accepte','annule']) ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ $quote->expires_at->format('d/m/Y') }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 hidden lg:table-cell whitespace-nowrap">{{ number_format($quote->subtotal_ht, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($quote->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-center">
                            <x-workflow.status-badge :status="$quote->status" :label="$quote->status_label" size="sm" />
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                {{-- Voir --}}
                                <a href="{{ route('ventes.devis.show', $quote) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Modifier (draft ou sent) --}}
                                @if(in_array($quote->status, ['brouillon', 'envoye']))
                                <a href="{{ route('ventes.devis.edit', $quote) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif
                                {{-- Convertir en commande (accepted ou sent) --}}
                                @if(in_array($quote->status, ['envoye', 'brouillon']) && !$quote->converted_to_order_id)
                                <form action="{{ route('ventes.devis.convert', $quote) }}" method="POST"
                                      data-confirm="Convertir ce devis en commande ?"
                                      data-confirm-title="Convertir en commande"
                                      data-confirm-label="Convertir"
                                      data-confirm-danger="false">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Convertir en commande">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                {{-- Supprimer (draft seulement) --}}
                                @if($quote->status === 'brouillon')
                                <form action="{{ route('ventes.devis.destroy', $quote) }}" method="POST"
                                      data-confirm="Supprimer le devis {{ $quote->number }} ?"
                                      data-confirm-title="Supprimer le devis">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucun devis trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $quotes->total() }} devis · Total TTC filtré : <b class="text-emerald-700 tabular-nums">{{ $fmt($summary['total_ttc']) }} FCFA</b></span>
            @if($quotes->hasPages())<div>{{ $quotes->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
