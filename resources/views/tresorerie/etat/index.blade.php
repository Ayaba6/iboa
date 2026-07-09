@extends('layouts.erp')
@section('title', 'État de trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">État de trésorerie</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header + filtres --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">État de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">Flux par compte sur la période</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Du</label>
                    <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Au</label>
                    <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="px-4 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Appliquer</button>
            </form>
            <a href="{{ route('tresorerie.etat.pdf', ['from' => $from, 'to' => $to]) }}"
               class="inline-flex items-center gap-1.5 px-4 py-1.5 border border-red-300 text-red-700 hover:bg-red-50 rounded-[4px] text-sm font-medium"
               data-loading data-loading-text="Génération PDF…">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 uppercase">Solde ouverture</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-gray-900">{{ number_format($totals['ouverture'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-emerald-600 uppercase">Entrées</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-emerald-700">+{{ number_format($totals['entrees'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-red-600 uppercase">Sorties</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-red-700">−{{ number_format($totals['sorties'], 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-[4px] border-2 border-emerald-200 p-4">
            <p class="text-xs text-emerald-700 uppercase">Solde clôture</p>
            <p class="mt-1 text-[17px] font-bold tabular-nums text-emerald-800">{{ number_format($totals['cloture'], 0, ',', ' ') }}</p>
        </div>
    </div>

    {{-- Évolution mensuelle --}}
    @if($monthly->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Flux nets mensuels</h2>
        <div id="chart-treso-flux"></div>
    </div>
    @endif

    {{-- Table par compte --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Compte</th>
                        <th class="text-left">Type</th>
                        <th class="text-right">Ouverture</th>
                        <th class="text-right">Entrées</th>
                        <th class="text-right">Sorties</th>
                        <th class="text-right">Clôture</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $r->name }}</td>
                        <td class="text-gray-500 capitalize">{{ $r->type }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-600">{{ number_format($r->ouverture, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono tabular-nums text-emerald-700">{{ $r->entrees ? '+'.number_format($r->entrees, 0, ',', ' ') : '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-red-600">{{ $r->sorties ? '−'.number_format($r->sorties, 0, ',', ' ') : '—' }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums {{ $r->cloture < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($r->cloture, 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun mouvement de trésorerie sur la période.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot class="bg-[#eef5f0] font-bold">
                    <tr>
                        <td colspan="2" class="text-emerald-900 uppercase text-xs">Total</td>
                        <td class="text-right font-mono text-emerald-900">{{ number_format($totals['ouverture'], 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-emerald-800">+{{ number_format($totals['entrees'], 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-red-700">−{{ number_format($totals['sorties'], 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-emerald-900">{{ number_format($totals['cloture'], 0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@if($monthly->isNotEmpty())
<script>
(window.__pendingApex = window.__pendingApex || []).push(function () {
    const el = document.querySelector('#chart-treso-flux');
    if (!el) return;
    const fmt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' F';
    const chart = new ApexCharts(el, {
        chart:  { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Entrées', data: @json($monthly->pluck('entrees')->map(fn($v) => (int) $v)) },
            { name: 'Sorties', data: @json($monthly->pluck('sorties')->map(fn($v) => (int) $v)) },
        ],
        xaxis:  { categories: @json($monthly->pluck('mois')), labels: { style: { fontSize: '11px', colors: '#94a3b8' } }, axisBorder: { show: false } },
        yaxis:  { labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: fmt } },
        colors: ['#10b981', '#f43f5e'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%', borderRadiusApplication: 'end' } },
        dataLabels: { enabled: false },
        legend: { position: 'top', fontSize: '12px' },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        tooltip: { theme: 'light', y: { formatter: fmt } },
    });
    chart.render();
    window.__turboCleanups = window.__turboCleanups || [];
    window.__turboCleanups.push(() => { try { chart.destroy(); } catch(e) {} });
});
</script>
@endif
@endpush
