@extends('layouts.erp')
@section('title', 'Avoirs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Avoirs</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-3">

    <x-sales.module-nav />

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total avoirs TTC</p>
            <p class="text-[15px] font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-[12px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Crédit restant</p>
            <p class="text-[15px] font-bold text-purple-600 tabular-nums">{{ $fmt($summary['remaining_credit']) }} <span class="text-[12px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En attente</p>
            <p class="text-[15px] font-bold text-blue-600 tabular-nums">{{ $summary['count_pending'] }} <span class="text-[12px] font-normal text-gray-400">avoir(s)</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Utilisés</p>
            <p class="text-[15px] font-bold text-emerald-600 tabular-nums">{{ $summary['count_used'] }} <span class="text-[12px] font-normal text-gray-400">avoir(s)</span></p>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">Avoirs</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $creditNotes->total() }} avoir(s)</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° avoir, client..."
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-purple-500 focus:border-purple-500">
            <select name="status" class="h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-purple-500 focus:border-purple-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="valide"                {{ ($filters['status'] ?? '') === 'valide'                ? 'selected' : '' }}>Validé</option>
                <option value="applique"  {{ ($filters['status'] ?? '') === 'applique'  ? 'selected' : '' }}>Appliqué</option>
                <option value="annule"    {{ ($filters['status'] ?? '') === 'annule'    ? 'selected' : '' }}>Annulé</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] transition-colors">Filtrer</button>
                @if(request()->hasAny(['search','status']))
                <a href="{{ route('ventes.avoirs.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] px-2.5 py-1.5 rounded-[4px]">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, crédit restant --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide w-32">N° avoir</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide">Client</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden md:table-cell w-32">Facture liée</th>
                        <th class="text-left font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-24">Date</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-right font-bold px-3 py-1.5 uppercase tracking-wide hidden lg:table-cell w-32">Solde restant</th>
                        <th class="text-center font-bold px-3 py-1.5 uppercase tracking-wide w-28">Statut</th>
                        <th class="px-3 py-2 w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($creditNotes as $cn)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.avoirs.show', $cn) }}" class="hover:underline font-semibold">{{ $cn->number }}</a>
                        </td>
                        <td class="px-3 py-1 font-medium text-gray-900">{{ $cn->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1 hidden md:table-cell whitespace-nowrap">
                            @if($cn->invoice)
                            <a href="{{ route('ventes.factures.show', $cn->invoice) }}" class="font-mono text-emerald-700 hover:underline text-[12px]">{{ $cn->invoice->number }}</a>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-gray-500 hidden lg:table-cell whitespace-nowrap">{{ $cn->issued_at?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold text-purple-700 whitespace-nowrap">{{ number_format($cn->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums hidden lg:table-cell whitespace-nowrap {{ $cn->remaining_credit > 0 ? 'text-orange-600 font-semibold' : 'text-gray-300' }}">
                            {{ number_format($cn->remaining_credit, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1 text-center">
                            <x-workflow.status-badge :status="$cn->status" :label="$cn->status_label" size="sm" />
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('ventes.avoirs.show', $cn) }}" class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('ventes.avoirs.pdf', $cn) }}" target="_blank" class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-[13px]">Aucun avoir trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            <span>{{ $creditNotes->total() }} avoir(s) · Total TTC : <b class="text-purple-700 tabular-nums">{{ $fmt($summary['total_ttc']) }} FCFA</b> · Crédit restant : <b class="text-orange-600 tabular-nums">{{ $fmt($summary['remaining_credit']) }} FCFA</b></span>
            @if($creditNotes->hasPages())<div>{{ $creditNotes->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
