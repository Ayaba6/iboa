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
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Commercial</label>
                <select name="user_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-400 bg-white">
                    <option value="">— Tous —</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ $userId == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">Appliquer</button>
            <a href="{{ route('reports.sales-performance') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs globaux --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-violet-600 rounded-[4px] p-5 shadow-sm shadow-violet-200 text-white">
            <p class="text-xs font-semibold text-violet-200 uppercase tracking-widest">CA total</p>
            <p class="text-3xl font-extrabold mt-1">{{ number_format($grandTotal->ca_total ?? 0, 0, ',', ' ') }}</p>
            <p class="text-xs text-violet-300 mt-0.5">FCFA sur la période</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 shadow-sm">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-widest">Factures émises</p>
            <p class="text-3xl font-extrabold text-gray-900 mt-1">{{ number_format($grandTotal->nb_factures ?? 0, 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">documents</p>
        </div>
        <div class="bg-emerald-50 rounded-[4px] border border-emerald-100 p-5 shadow-sm">
            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-widest">Total encaissé</p>
            <p class="text-3xl font-extrabold text-emerald-800 mt-1">{{ number_format($grandTotal->encaisse ?? 0, 0, ',', ' ') }}</p>
            <p class="text-xs text-emerald-500 mt-0.5">FCFA</p>
        </div>
    </div>

    {{-- Charts --}}
    @if($perUser->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 shadow-sm">
        <h2 class="text-sm font-bold text-gray-800 mb-4">CA par commercial</h2>
        <div id="chart-perf-bar"></div>
    </div>
    @endif

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-800">Classement des commerciaux</h2>
        </div>
        @if($perUser->isEmpty())
            <div class="py-12 text-center text-gray-400 text-sm">Aucune donnée sur cette période</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm divide-y divide-gray-100">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Rang</th>
                        <th class="px-5 py-3 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Commercial</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nb Fact.</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nb Clients</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">CA Total</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Encaissé</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Panier moy.</th>
                        <th class="px-5 py-3 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Taux enc.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($perUser as $i => $u)
                    @php
                        $tauxEnc = $u->ca_total > 0 ? round(($u->encaisse / $u->ca_total) * 100) : 0;
                        $rankColors = ['text-yellow-500', 'text-gray-400', 'text-amber-600'];
                    @endphp
                    <tr class="hover:bg-violet-50/30 transition-colors {{ $i === 0 ? 'bg-yellow-50/30' : '' }}">
                        <td class="px-5 py-3">
                            <span class="text-base font-bold {{ $rankColors[$i] ?? 'text-gray-400' }}">
                                @if($i < 3) {{ ['🥇','🥈','🥉'][$i] }} @else {{ $i + 1 }} @endif
                            </span>
                        </td>
                        <td class="px-5 py-3 font-semibold text-gray-900">
                            {{ optional($u->creator)->name ?? 'Utilisateur #'.$u->created_by }}
                        </td>
                        <td class="px-5 py-3 text-right text-gray-600">{{ $u->nb_factures }}</td>
                        <td class="px-5 py-3 text-right text-gray-600">{{ $u->nb_clients }}</td>
                        <td class="px-5 py-3 text-right font-mono font-bold text-gray-900 tabular-nums">{{ number_format($u->ca_total, 0, ',', ' ') }}</td>
                        <td class="px-5 py-3 text-right font-mono text-emerald-700 tabular-nums">{{ number_format($u->encaisse, 0, ',', ' ') }}</td>
                        <td class="px-5 py-3 text-right font-mono text-gray-600 tabular-nums">{{ number_format($u->panier_moyen, 0, ',', ' ') }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
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
        colors: ['#7c3aed', '#10b981'],
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
