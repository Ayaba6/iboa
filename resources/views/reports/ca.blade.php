@extends('layouts.erp')
@section('title', 'Rapport CA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Chiffre d'affaires</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Rapport Chiffre d'affaires</h1>
            <p class="text-sm text-gray-500 mt-0.5">Analyse par période — FCFA</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Export Excel
            </a>
            <a href="{{ route('reports.ca-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="">— Tous —</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Regrouper par</label>
                <select name="group_by" class="w-full border border-gray-300 rounded-[4px] px-2 h-8 text-[13px] focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="month" {{ $groupBy === 'month' ? 'selected' : '' }}>Mois</option>
                    <option value="week"  {{ $groupBy === 'week'  ? 'selected' : '' }}>Semaine</option>
                    <option value="day"   {{ $groupBy === 'day'   ? 'selected' : '' }}>Jour</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                Appliquer
            </button>
            <a href="{{ route('reports.ca') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                Réinitialiser
            </a>
        </div>
    </form>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $kpis = [
                ['label' => 'Factures', 'value' => number_format($totals->nb_factures ?? 0, 0, ',', ' '), 'unit' => 'docs', 'color' => 'indigo'],
                ['label' => 'CA HT',    'value' => number_format($totals->total_ht ?? 0, 0, ',', ' '),     'unit' => 'FCFA', 'color' => 'blue'],
                ['label' => 'TVA',      'value' => number_format($totals->total_tva ?? 0, 0, ',', ' '),    'unit' => 'FCFA', 'color' => 'sky'],
                ['label' => 'CA TTC',   'value' => number_format($totals->total_ttc ?? 0, 0, ',', ' '),    'unit' => 'FCFA', 'color' => 'violet'],
                ['label' => 'Encaissé', 'value' => number_format($totals->total_encaisse ?? 0, 0, ',', ' '),'unit' => 'FCFA', 'color' => 'emerald'],
                ['label' => 'Reste dû', 'value' => number_format($totals->total_reste ?? 0, 0, ',', ' '),  'unit' => 'FCFA', 'color' => 'rose'],
            ];
            $colorMap = [
                'indigo'  => 'bg-[#eef5f0] text-emerald-800 border-emerald-100',
                'blue'    => 'bg-blue-50 text-blue-700 border-blue-100',
                'sky'     => 'bg-sky-50 text-sky-700 border-sky-100',
                'violet'  => 'bg-violet-50 text-violet-700 border-violet-100',
                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'rose'    => 'bg-rose-50 text-rose-700 border-rose-100',
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="rounded-[4px] border p-4 {{ $colorMap[$kpi['color']] }}">
            <p class="text-xs font-semibold uppercase tracking-widest opacity-70">{{ $kpi['label'] }}</p>
            <p class="text-[17px] font-bold mt-1 leading-none">{{ $kpi['value'] }}</p>
            <p class="text-xs opacity-60 mt-0.5">{{ $kpi['unit'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Charts + Top Clients --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Chart CA TTC --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-800 mb-4">Évolution du CA TTC</h2>
            @if($serie->isEmpty())
                <div class="flex items-center justify-center h-52 text-gray-400 text-sm">Aucune donnée sur cette période</div>
            @else
                <div id="chart-ca-serie"></div>
            @endif
        </div>

        {{-- Top 10 clients --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-gray-800 mb-4">Top clients</h2>
            @if($topClients->isEmpty())
                <div class="flex items-center justify-center h-52 text-gray-400 text-sm">Aucune donnée</div>
            @else
                @php $maxCa = $topClients->max('ca'); @endphp
                <div class="space-y-3">
                    @foreach($topClients as $i => $tc)
                    @php $pct = $maxCa > 0 ? round(($tc->ca / $maxCa) * 100) : 0; @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-400 w-4">{{ $i+1 }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between text-xs mb-0.5">
                                <span class="font-medium text-gray-700 truncate max-w-[55%]">{{ optional($tc->client)->name ?? 'Client #'.$tc->client_id }}</span>
                                <span class="font-bold text-gray-900 ml-1">{{ number_format($tc->ca, 0, ',', ' ') }} F</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="bg-emerald-600 h-1.5 rounded-full" style="width:{{ $pct }}%"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Tableau détaillé --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-800">Détail par période</h2>
        </div>
        @if($serie->isEmpty())
            <div class="py-12 text-center text-gray-400 text-sm">Aucune donnée sur cette période</div>
        @else
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky">
                <thead>
                    <tr>
                        <th class="text-left">Période</th>
                        <th class="text-right">Nb Fact.</th>
                        <th class="text-right">CA HT</th>
                        <th class="text-right">CA TTC</th>
                        <th class="text-right">Encaissé</th>
                        <th class="text-right">Taux encais.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($serie as $row)
                    @php $tauxEnc = $row->ttc > 0 ? round(($row->encaisse / $row->ttc) * 100) : 0; @endphp
                    <tr>
                        <td class="font-semibold text-gray-900">{{ $row->label }}</td>
                        <td class="text-right text-gray-600">{{ $row->nb }}</td>
                        <td class="text-right font-mono text-gray-700">{{ number_format($row->ht, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono font-bold text-gray-900">{{ number_format($row->ttc, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-emerald-700">{{ number_format($row->encaisse, 0, ',', ' ') }}</td>
                        <td class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold
                                {{ $tauxEnc >= 80 ? 'bg-emerald-100 text-emerald-700' : ($tauxEnc >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                {{ $tauxEnc }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-[#eef5f0] font-bold">
                    <tr>
                        <td class="text-emerald-900 font-bold">Total</td>
                        <td class="text-right text-emerald-900">{{ $serie->sum('nb') }}</td>
                        <td class="text-right font-mono text-emerald-900">{{ number_format($serie->sum('ht'), 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-emerald-900 font-extrabold">{{ number_format($serie->sum('ttc'), 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-emerald-800">{{ number_format($serie->sum('encaisse'), 0, ',', ' ') }}</td>
                        <td class="text-right">
                            @php $ttot = $serie->sum('ttc'); $etot = $serie->sum('encaisse'); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                {{ $ttot > 0 ? round(($etot / $ttot) * 100) : 0 }}%
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
{{-- ApexCharts est bundlé via app.js (window.ApexCharts) — pas besoin de CDN. --}}
<script>
@if($serie->isNotEmpty())
(window.__pendingApex = window.__pendingApex || []).push(function () {
    const fmt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' F';
    const el  = document.querySelector('#chart-ca-serie');
    if (!el) return;
    const chart = new ApexCharts(el, {
        chart:  { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true, easing: 'easeinout', speed: 500 } },
        series: [
            { name: 'CA TTC',   data: @json($serie->pluck('ttc')) },
            { name: 'Encaissé', data: @json($serie->pluck('encaisse')) },
        ],
        xaxis:  { categories: @json($serie->pluck('label')), labels: { style: { fontSize: '11px', colors: '#94a3b8' } }, axisBorder: { show: false } },
        yaxis:  { labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: fmt } },
        colors: ['#4f46e5', '#10b981'],
        fill:   { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02, stops: [0, 100] } },
        stroke: { curve: 'smooth', width: 2.5 },
        grid:   { borderColor: '#f1f5f9', strokeDashArray: 3 },
        dataLabels: { enabled: false },
        tooltip: { theme: 'light', y: { formatter: fmt } },
        legend: { position: 'top', fontSize: '12px' },
    });
    chart.render();
    window.__turboCleanups = window.__turboCleanups || [];
    window.__turboCleanups.push(() => { try { chart.destroy(); } catch(e) {} });
});
@endif
</script>
@endpush
