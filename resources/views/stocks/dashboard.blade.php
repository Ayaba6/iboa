@extends('layouts.erp')
@section('title', 'Stock — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Stock</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Tableau de bord stock</h1>
            <p class="text-[11.5px] text-gray-400">Vue d'ensemble · valorisation, ruptures, alertes, DLC</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('stocks.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 py-1.5 rounded-[4px]">Liste détaillée</a>
            <a href="{{ route('stocks.movements') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 py-1.5 rounded-[4px]">Mouvements</a>
            <a href="{{ route('stocks.transfers.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 py-1.5 rounded-[4px]">Transferts</a>
            <a href="{{ route('stocks.dashboard.abc') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 py-1.5 rounded-[4px]">Analyse ABC</a>
            @can('stocks.write')
            <a href="{{ route('stocks.movement.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 py-1.5 rounded-[4px]">+ Mouvement</a>
            @endcan
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-1.5">
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valorisation</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $fmt($kpis['total_valuation']) }}</p>
            <p class="text-[10px] text-gray-400 mt-1">FCFA · qté × CMP</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur réservée</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-blue-700 leading-none">{{ $fmt($kpis['reserved_value']) }}</p>
            <p class="text-[10px] text-gray-400 mt-1">FCFA · devis / cmd</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Articles actifs</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $kpis['active_products'] }}</p>
            <p class="text-[10px] text-gray-400 mt-1">références</p>
        </div>

        <a href="{{ route('stocks.dashboard.restock') }}" class="bg-white rounded-[4px] border border-orange-300 hover:bg-orange-50 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-orange-600 uppercase tracking-wide">⚠ Réappro</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-orange-700 leading-none">{{ $kpis['reorder_count'] }}</p>
            <p class="text-[10px] text-orange-500 mt-1">qté ≤ pt réappro</p>
        </a>

        <div class="bg-white rounded-[4px] border border-red-300 px-3 py-2">
            <p class="text-[10px] font-bold text-red-600 uppercase tracking-wide">🛑 Ruptures</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-red-700 leading-none">{{ $kpis['rupture_count'] }}</p>
            <p class="text-[10px] text-red-500 mt-1">dispo ≤ 0</p>
        </div>

        <div class="bg-white rounded-[4px] border border-amber-300 px-3 py-2">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wide">Sous seuil min</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-amber-700 leading-none">{{ $kpis['below_min_count'] }}</p>
            <p class="text-[10px] text-amber-500 mt-1">qté &lt; min</p>
        </div>

        <a href="{{ route('stocks.dashboard.dormant') }}" class="bg-white rounded-[4px] border border-gray-300 hover:bg-gray-50 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-gray-600 uppercase tracking-wide">💤 Dormants</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-700 leading-none">{{ $kpis['dormant_count'] }}</p>
            <p class="text-[10px] text-gray-500 mt-1">aucun mvt &gt; 90 j</p>
        </a>

        <a href="{{ route('stocks.dashboard.expiry') }}" class="bg-white rounded-[4px] border border-rose-300 hover:bg-rose-50 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-rose-600 uppercase tracking-wide">🗓 DLC proches</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-rose-700 leading-none">
                {{ $kpis['expiring_count'] }}<span class="text-[11px] text-rose-400 ml-1">+{{ $kpis['expired_count'] }} périmés</span>
            </p>
            <p class="text-[10px] text-rose-500 mt-1">lots &lt; 30 j</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">

        {{-- Top valorisation --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-300 bg-[#eef5f0] flex items-center justify-between">
                <h2 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Top 10 par valorisation</h2>
                <a href="{{ route('stocks.valuation') }}" class="text-[11px] text-blue-600 hover:underline">Voir tout →</a>
            </div>
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Article</th>
                        <th class="px-3 py-1.5 text-right">Qté</th>
                        <th class="px-3 py-1.5 text-right">Valeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topValuation as $row)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1">
                            <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-[11px] text-emerald-800">{{ $row->reference }}</a>
                            <span class="text-gray-700"> · {{ $row->name }}</span>
                            <span class="text-[10.5px] text-gray-400">· {{ $row->warehouse_name }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ number_format($row->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold">{{ $fmt($row->valuation) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400">Aucune donnée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top mouvements ce mois --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-300 bg-[#eef5f0] flex items-center justify-between">
                <h2 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Top 10 mouvementés — {{ now()->translatedFormat('F Y') }}</h2>
                <a href="{{ route('stocks.movements') }}" class="text-[11px] text-blue-600 hover:underline">Tous →</a>
            </div>
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Article</th>
                        <th class="px-3 py-1.5 text-right">Entrées</th>
                        <th class="px-3 py-1.5 text-right">Sorties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topMoved as $row)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1">
                            <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-[11px] text-emerald-800">{{ $row->reference }}</a>
                            <span class="text-gray-700"> · {{ $row->name }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-emerald-700">+ {{ number_format($row->qty_in, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-red-600">− {{ number_format($row->qty_out, 2, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400">Aucun mouvement ce mois.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

        {{-- Aperçu alertes réappro --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-orange-200 bg-orange-50 flex items-center justify-between">
                <h2 class="text-[11px] font-bold text-orange-800 uppercase tracking-wide">Alertes réappro</h2>
                <a href="{{ route('stocks.dashboard.restock') }}" class="text-[11px] text-orange-700 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($alertsPreview as $r)
                <div class="px-3 py-1.5">
                    <p class="text-[12.5px] text-gray-900 truncate"><span class="font-mono text-[11px] text-emerald-800">{{ $r->reference }}</span> · {{ $r->name }}</p>
                    <p class="text-[10.5px] text-orange-600">Dispo : {{ number_format($r->available_qty, 0, ',', ' ') }} / réappro à {{ $r->reorder_point }}</p>
                </div>
                @empty
                <div class="px-3 py-6 text-center text-gray-400 text-[12px]">Aucune alerte.</div>
                @endforelse
            </div>
        </div>

        {{-- Aperçu dormants --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-300 bg-[#eef5f0] flex items-center justify-between">
                <h2 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Articles dormants</h2>
                <a href="{{ route('stocks.dashboard.dormant') }}" class="text-[11px] text-gray-600 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($dormantPreview as $r)
                <div class="px-3 py-1.5">
                    <p class="text-[12.5px] text-gray-900 truncate"><span class="font-mono text-[11px] text-emerald-800">{{ $r->reference }}</span> · {{ $r->name }}</p>
                    <p class="text-[10.5px] text-gray-500">
                        @if($r->days_idle === null)
                            <span class="italic">jamais mouvementé</span>
                        @else
                            {{ (int) $r->days_idle }} j inactif
                        @endif
                        · {{ $fmt($r->immobilized_value) }} FCFA
                    </p>
                </div>
                @empty
                <div class="px-3 py-6 text-center text-gray-400 text-[12px]">Aucun.</div>
                @endforelse
            </div>
        </div>

        {{-- Aperçu DLC --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-rose-200 bg-rose-50 flex items-center justify-between">
                <h2 class="text-[11px] font-bold text-rose-800 uppercase tracking-wide">DLC proches</h2>
                <a href="{{ route('stocks.dashboard.expiry') }}" class="text-[11px] text-rose-700 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($expiringPreview as $r)
                <div class="px-3 py-1.5">
                    <p class="text-[12.5px] text-gray-900 truncate"><span class="font-mono text-[11px] text-emerald-800">{{ $r->reference }}</span> · {{ $r->name }} <span class="text-[10.5px] text-gray-500">· lot {{ $r->lot_number ?? '—' }}</span></p>
                    <p class="text-[10.5px] text-rose-600">DLC {{ \Carbon\Carbon::parse($r->expiry_date)->format('d/m/Y') }} ({{ (int) $r->days_left }} j)</p>
                </div>
                @empty
                <div class="px-3 py-6 text-center text-gray-400 text-[12px]">Aucun lot proche.</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
