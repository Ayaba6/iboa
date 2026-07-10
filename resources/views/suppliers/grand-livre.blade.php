@extends('layouts.erp')
@section('title', 'Grand livre fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Grand livre fournisseurs</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Grand livre fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">Écritures validées — comptes fournisseurs (401)</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('suppliers.grand-livre.export-excel', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('suppliers.grand-livre.export-pdf', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('suppliers.journal-achats') }}" class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Journal achats</a>
            <a href="{{ route('suppliers.balance') }}"        class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Balance</a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex flex-wrap gap-3">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to"   value="{{ $dateTo }}"   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="text" name="search"    value="{{ $search }}"   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-64" placeholder="Compte, libellé, référence…">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Filtrer
            </button>
            @if($dateFrom || $dateTo || $search)
            <a href="{{ route('suppliers.grand-livre') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    @if(empty($accounts))
    <div class="bg-[#f4f9f6] border border-emerald-200 rounded-[4px] p-6 text-center">
        <p class="text-emerald-700 text-sm">Aucune écriture fournisseur trouvée pour ces critères.</p>
    </div>
    @else

    {{-- Per-account blocks --}}
    @foreach($accounts as $account)
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">

        {{-- Account header --}}
        <div class="text-white px-5 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2" style="background:#065f46;">
            <div class="font-bold text-sm">{{ $account['code'] }} — {{ $account['name'] }}</div>
            <div class="flex gap-6 text-xs text-emerald-200">
                <span>Solde ouv. : <strong class="text-white tabular-nums">{{ number_format(abs($account['solde_ouv']), 0, ',', ' ') }} F</strong></span>
                <span>Solde fin : <strong class="text-white tabular-nums">{{ number_format(abs($account['solde_fin']), 0, ',', ' ') }} F</strong></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="bg-[#eef5f0] border-b border-gray-300">
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N° Écriture</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Jnl</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-left   text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Libellé</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit</th>
                        <th class="px-3 py-1.5 text-right  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($account['lines'] as $item)
                    @php $l = $item['line']; @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-700 whitespace-nowrap text-xs">{{ $l->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 font-mono text-xs text-gray-600">{{ $l->journalEntry?->number ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                {{ $l->journalEntry?->journalType?->code ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $l->journalEntry?->reference ?? '' }}</td>
                        <td class="px-3 py-1.5 text-gray-800 text-xs">{{ $l->label ?: ($l->journalEntry?->description ?? '') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-xs text-gray-700">
                            {{ (int)$l->debit > 0 ? number_format((int)$l->debit, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-xs text-gray-700">
                            {{ (int)$l->credit > 0 ? number_format((int)$l->credit, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-xs font-semibold {{ $item['solde'] >= 0 ? 'text-red-700' : 'text-emerald-600' }}">
                            {{ number_format(abs($item['solde']), 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-[#eef5f0] border-t border-emerald-200">
                        <td colspan="5" class="px-3 py-1.5 font-bold text-xs text-emerald-900 uppercase">Total {{ $account['code'] }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-xs text-emerald-900">{{ number_format($account['total_d'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-xs text-emerald-900">{{ number_format($account['total_c'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums text-xs {{ $account['solde_fin'] >= 0 ? 'text-red-800' : 'text-emerald-700' }}">
                            {{ number_format(abs($account['solde_fin']), 0, ',', ' ') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endforeach

    @endif

</div>
@endsection
