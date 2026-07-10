@extends('layouts.erp')
@section('title', 'Performance commerciale')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Performance commerciale</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Performance commerciale</h1>
            <p class="text-sm text-gray-500 mt-0.5">Ventes par commercial</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Export Excel
            </a>
            <a href="{{ route('reports.sales-performance-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400">
            </div>
            <div class="w-64">
                <label class="block text-[11px] font-bold text-gray-600 mb-1">Commercial</label>
                <select name="user_id" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2 text-[13px] focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="">— Tous —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ $userId == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Appliquer</button>
            <a href="{{ route('reports.sales-performance') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs globaux --}}
    @php
        $kpis = [
            ['label' => 'CA total',        'value' => number_format($grandTotal->ca_total ?? 0, 0, ',', ' ') . ' F',  'text' => 'text-blue-700',    'bd' => 'border-blue-200'],
            ['label' => 'Factures émises', 'value' => number_format($grandTotal->nb_factures ?? 0, 0, ',', ' '),      'text' => 'text-gray-900',    'bd' => 'border-gray-300'],
            ['label' => 'Total encaissé',  'value' => number_format($grandTotal->encaisse ?? 0, 0, ',', ' ') . ' F',  'text' => 'text-emerald-700', 'bd' => 'border-emerald-200'],
        ];
    @endphp
    <div class="grid grid-cols-3 gap-1.5">
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border {{ $kpi['bd'] }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="mt-0.5 text-[17px] font-bold {{ $kpi['text'] }} tabular-nums leading-none">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Charts --}}
    @if($perUser->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h2 class="text-[13px] font-bold text-gray-800 mb-3">CA par commercial</h2>
        <div id="chart-perf-bar"></div>
    </div>
    @endif

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-4 py-2 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
            Classement des commerciaux
        </div>
        @if($perUser->isEmpty())
            <div class="py-12 text-center text-gray-400 text-[13px]">Aucune donnée sur cette période</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-[14px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left w-16">Rang</th>
                        <th class="px-3 py-1.5 text-left">Commercial</th>
                        <th class="px-3 py-1.5 text-right">Nb Fact.</th>
                        <th class="px-3 py-1.5 text-right">Nb Clients</th>
                        <th class="px-3 py-1.5 text-right">CA Total</th>
                        <th class="px-3 py-1.5 text-right">Encaissé</th>
                        <th class="px-3 py-1.5 text-right">Panier moy.</th>
                        <th class="px-3 py-1.5 text-center">Taux enc.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($perUser as $i => $u)
                    @php
                        $tauxEnc = $u->ca_total > 0 ? round(($u->encaisse / $u->ca_total) * 100) : 0;
                    @endphp
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $i === 0 ? '!bg-yellow-50/40' : '' }}">
                        <td class="px-3 py-1">
                            @if($i < 3) <span class="text-[15px]">{{ ['🥇','🥈','🥉'][$i] }}</span> @else <span class="font-bold text-gray-400 tabular-nums">{{ $i + 1 }}</span> @endif
                        </td>
                        <td class="px-3 py-1 font-semibold text-gray-900">
                            {{ optional($u->creator)->name ?? 'Utilisateur #'.$u->created_by }}
                        </td>
                        <td class="px-3 py-1 text-right text-gray-600 tabular-nums">{{ $u->nb_factures }}</td>
                        <td class="px-3 py-1 text-right text-gray-600 tabular-nums">{{ $u->nb_clients }}</td>
                        <td class="px-3 py-1 text-right font-mono font-bold text-gray-900 tabular-nums">{{ number_format($u->ca_total, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right font-mono text-emerald-700 tabular-nums">{{ number_format($u->encaisse, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right font-mono text-gray-600 tabular-nums">{{ number_format($u->panier_moyen, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10.5px] font-bold
                                {{ $tauxEnc >= 80 ? 'bg-emerald-100 text-emerald-700' : ($tauxEnc >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                {{ $tauxEnc }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
{{-- ApexCharts est bundlé via app.js (window.ApexCharts) — pas besoin de CDN. --}}
<script>
@if($perUser->isNotEmpty())
(window.__pendingApex = window.__pendingApex || []).push(function () {
    const fmt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' F';
    const el  = document.querySelector('#chart-perf-bar');
    if (!el) return;
    const chart = new ApexCharts(el, {
        chart:  { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'CA Total', data: @json($perUser->pluck('ca_total')->map(fn($v) => (int)$v)) },
            { name: 'Encaissé', data: @json($perUser->pluck('encaisse')->map(fn($v) => (int)$v)) },
        ],
        xaxis:  {
            categories: @json($perUser->map(fn($u) => optional($u->creator)->name ?? 'User #'.$u->created_by)),
            labels: { style: { fontSize: '11px', colors: '#64748b' } }, axisBorder: { show: false }
        },
        yaxis:  { labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: fmt } },
        colors: ['#3b82f6', '#00843d'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', borderRadiusApplication: 'end' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
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
