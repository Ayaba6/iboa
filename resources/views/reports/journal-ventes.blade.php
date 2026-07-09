@extends('layouts.erp')
@section('title', 'Journal des ventes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Journal des ventes</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Journal des ventes</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($clientName)
                    Factures de <span class="font-medium text-emerald-800">{{ $clientName }}</span> — {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }} — FCFA
                @else
                    Toutes les factures émises sur la période — FCFA
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export Excel
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="">— Tous les clients —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Appliquer</button>
            <a href="{{ route('reports.journal-ventes') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @php
            $kpis = [
                ['label' => 'Nb factures',  'value' => $rows->count(),                                         'color' => 'indigo'],
                ['label' => 'Total HT',     'value' => number_format($totals['ht'],  0, ',', ' ') . ' F',       'color' => 'blue'],
                ['label' => 'Total TVA',    'value' => number_format($totals['tva'], 0, ',', ' ') . ' F',       'color' => 'amber'],
                ['label' => 'Total TTC',    'value' => number_format($totals['ttc'], 0, ',', ' ') . ' F',       'color' => 'emerald'],
            ];
        @endphp
        @foreach($kpis as $k)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $k['label'] }}</p>
            <p class="mt-1 text-[17px] font-bold text-{{ $k['color'] }}-700">{{ $k['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-emerald-700 text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left font-semibold">N° Facture</th>
                    <th class="px-3 py-1.5 text-center font-semibold">Date</th>
                    <th class="px-3 py-1.5 text-left font-semibold">Client</th>
                    <th class="px-3 py-1.5 text-center font-semibold">Statut</th>
                    <th class="px-3 py-1.5 text-right font-semibold">HT</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Remise</th>
                    <th class="px-3 py-1.5 text-right font-semibold">TVA</th>
                    <th class="px-3 py-1.5 text-right font-semibold">TTC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                @php
                    $statusColor = match($r->status) {
                        'payee'               => 'green',
                        'partiellement_payee' => 'amber',
                        'en_retard'           => 'red',
                        'annulee'             => 'red',
                        'envoyee'             => 'blue',
                        'validee'             => 'blue',
                        default               => 'gray',
                    };
                    $statusLabel = match($r->status) {
                        'brouillon'           => 'Brouillon',
                        'validee'             => 'Validée',
                        'envoyee'             => 'Envoyée',
                        'partiellement_payee' => 'Part. payée',
                        'payee'               => 'Payée',
                        'en_retard'           => 'En retard',
                        'annulee'             => 'Annulée',
                        default               => $r->status,
                    };
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2.5 font-medium text-emerald-800">
                        <a href="{{ route('ventes.factures.show', $r->id) }}" class="hover:underline">{{ $r->number }}</a>
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-600">{{ $r->issued_at?->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 text-gray-800">{{ $r->client?->name ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($r->subtotal_ht, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-500">{{ number_format($r->total_discount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($r->total_tax, 0, ',', ' ') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($r->total_ttc, 0, ',', ' ') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune facture sur cette période</td>
                </tr>
                @endforelse
            </tbody>
            @if($rows->count())
            <tfoot class="bg-indigo-900 text-white font-bold">
                <tr>
                    <td class="px-3 py-1.5" colspan="4">TOTAL ({{ $rows->count() }} facture{{ $rows->count() > 1 ? 's' : '' }})</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ht'],  0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['rem'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['tva'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totals['ttc'], 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

</div>
@endsection
