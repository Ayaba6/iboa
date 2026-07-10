@extends('layouts.erp')
@section('title', 'Journal des achats')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Journal des achats</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Journal des achats</h1>
            <p class="text-sm text-gray-500 mt-0.5">Écritures validées — journal Achat</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('suppliers.journal-achats.export-excel', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('suppliers.journal-achats.export-pdf', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('suppliers.grand-livre') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Grand livre</a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="flex flex-wrap gap-3">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to"   value="{{ $dateTo }}"   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="text" name="search"    value="{{ $search }}"   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-64" placeholder="N° écriture, référence…">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if($dateFrom || $dateTo || $search)
            <a href="{{ route('suppliers.journal-achats') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Écritures</p>
            <p class="text-[16px] font-bold text-gray-900">{{ $entries->count() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total débit</p>
            <p class="text-[17px] font-bold text-gray-900 tabular-nums">{{ number_format($totalDebit, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-amber-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total crédit</p>
            <p class="text-[17px] font-bold text-amber-700 tabular-nums">{{ number_format($totalCredit, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N° Écriture</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Jnl</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit (FCFA)</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit (FCFA)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($entries as $entry)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-700 whitespace-nowrap">{{ $entry->entry_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-700">{{ $entry->number }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                {{ $entry->journalType?->code ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 text-xs">{{ $entry->reference ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-800">{{ $entry->description ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-800">
                            {{ $entry->total_debit > 0 ? number_format($entry->total_debit, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-800">
                            {{ $entry->total_credit > 0 ? number_format($entry->total_credit, 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucune écriture d'achat trouvée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($entries->count())
                <tfoot>
                    <tr class="text-white" style="background:#065f46;">
                        <td colspan="5" class="px-3 py-1.5 font-bold text-xs uppercase">TOTAUX</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totalDebit,  0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totalCredit, 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
