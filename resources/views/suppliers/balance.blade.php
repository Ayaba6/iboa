@extends('layouts.erp')
@section('title', 'Balance fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Balance fournisseurs</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Balance fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">Solde comptable par fournisseur — au {{ now()->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('suppliers.balance.export-excel', array_filter(['search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('suppliers.balance.export-pdf', array_filter(['search' => $search])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('suppliers.balance-agee') }}"      class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Balance âgée</a>
            <a href="{{ route('suppliers.grand-livre') }}"       class="text-sm text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:bg-emerald-50 px-3 py-2 rounded-[4px] transition-colors">Grand livre</a>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="flex gap-3">
            <input type="text" name="search" value="{{ $search }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 w-72"
                   placeholder="Rechercher un fournisseur…">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Rechercher
            </button>
            @if($search)
            <a href="{{ route('suppliers.balance') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total facturé</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ number_format($totals['total_fact'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Retours</p>
            <p class="text-[16px] font-bold text-orange-600 tabular-nums">{{ number_format($totals['total_retour'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total payé</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums">{{ number_format($totals['total_paye'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Solde dû</p>
            <p class="text-[16px] font-bold text-red-700 tabular-nums">{{ number_format($totals['solde'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Code</th>
                        <th class="px-3 py-1.5 text-left  text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Fournisseur</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total facturé</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-orange-600 uppercase tracking-wide">Retours</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-700 uppercase tracking-wide">Total payé</th>
                        <th class="px-3 py-1.5 text-right text-xs font-semibold text-red-700 uppercase tracking-wider">Solde dû</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-500 text-xs font-mono">{{ $row['code'] }}</td>
                        <td class="px-3 py-1.5">
                            <a href="{{ route('suppliers.show', $row['id']) }}" class="font-medium text-emerald-700 hover:text-emerald-900">
                                {{ $row['name'] }}
                            </a>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($row['total_fact'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-orange-600">
                            {{ $row['total_retour'] > 0 ? number_format($row['total_retour'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ number_format($row['total_paye'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $row['solde'] > 0 ? 'text-red-700' : 'text-emerald-600' }}">
                            {{ number_format($row['solde'], 0, ',', ' ') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-sm">Aucun fournisseur trouvé.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot>
                    <tr class="text-white" style="background:#065f46;">
                        <td class="px-3 py-1.5 font-bold text-xs uppercase" colspan="2">TOTAL</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['total_fact'],   0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['total_retour'], 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['total_paye'],   0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right font-bold tabular-nums">{{ number_format($totals['solde'],        0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
