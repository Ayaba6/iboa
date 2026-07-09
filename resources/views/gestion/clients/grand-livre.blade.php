@extends('layouts.erp')
@section('title', 'Grand livre clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Grand livre clients</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Grand livre clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Écritures comptables des comptes 411 — par compte client</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('clients.grand-livre.export-excel', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('clients.grand-livre.export-pdf', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('clients.releve') }}"      class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Relevé client</a>
            <a href="{{ route('clients.balance-agee') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Balance âgée</a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Compte, libellé, référence..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_from" value="{{ $dateFrom }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to" value="{{ $dateTo }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if($search || $dateFrom || $dateTo)
                <a href="{{ route('clients.grand-livre') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    @if(count($accounts))

    {{-- Summary cards --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Comptes</p>
            <p class="text-[16px] font-bold text-gray-900">{{ count($accounts) }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Débit</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">
                {{ number_format(collect($accounts)->sum('total_d'), 0, ',', ' ') }}
                <span class="text-sm font-normal text-gray-400">F</span>
            </p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Crédit</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">
                {{ number_format(collect($accounts)->sum('total_c'), 0, ',', ' ') }}
                <span class="text-sm font-normal text-gray-400">F</span>
            </p>
        </div>
    </div>

    {{-- One block per account --}}
    @foreach($accounts as $account)
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        {{-- Account header --}}
        <div class="text-white px-3 py-2.5 flex items-center justify-between" style="background:#065f46;">
            <div class="flex items-center gap-3">
                <span class="font-mono font-bold text-emerald-200 text-sm">{{ $account['code'] }}</span>
                <span class="font-semibold">{{ $account['name'] }}</span>
            </div>
            <div class="flex items-center gap-6 text-sm">
                @if($account['solde_ouv'] != 0)
                <span class="text-emerald-200">
                    Solde ouv. : <strong class="text-white">{{ number_format($account['solde_ouv'], 0, ',', ' ') }}</strong>
                </span>
                @endif
                <span class="text-emerald-200">
                    Solde fin : <strong class="{{ $account['solde_fin'] >= 0 ? 'text-white' : 'text-green-300' }}">
                        {{ number_format($account['solde_fin'], 0, ',', ' ') }}
                    </strong>
                </span>
            </div>
        </div>

        {{-- Lines --}}
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N° Écriture</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Jnl</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($account['lines'] as $item)
                    @php $l = $item['line']; $s = $item['solde']; @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">
                            {{ $l->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="font-mono text-xs text-emerald-700">{{ $l->journalEntry?->number ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="text-xs font-semibold bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">
                                {{ $l->journalEntry?->journalType?->code ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $l->journalEntry?->reference ?? '' }}</td>
                        <td class="px-3 py-1.5 text-gray-800">{{ $l->label ?: ($l->journalEntry?->description ?? '') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">
                            {{ $l->debit > 0 ? number_format((int)$l->debit, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">
                            {{ $l->credit > 0 ? number_format((int)$l->credit, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $s >= 0 ? 'text-gray-800' : 'text-green-700' }}">
                            {{ number_format($s, 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-[#eef5f0] border-t border-emerald-200">
                        <td colspan="5" class="px-3 py-1.5 text-xs font-bold text-emerald-900 uppercase">Total {{ $account['code'] }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-emerald-900">
                            {{ number_format($account['total_d'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-emerald-900">
                            {{ number_format($account['total_c'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-emerald-900">
                            {{ number_format($account['solde_fin'], 0, ',', ' ') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endforeach

    @else
    <div class="bg-white rounded-[4px] border border-gray-200 p-12 text-center">
        <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-gray-500 font-medium">Aucune écriture trouvée pour les comptes 411</p>
        <p class="text-gray-400 text-sm mt-1">Ajustez les filtres ou vérifiez que des écritures validées existent sur les comptes clients</p>
    </div>
    @endif

</div>
@endsection
