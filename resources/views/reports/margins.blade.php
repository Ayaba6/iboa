@extends('layouts.erp')
@section('title', 'Analyse des marges')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Marges</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Analyse des marges</h1>
            <p class="text-sm text-gray-500 mt-0.5">Rentabilité par produit</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Export Excel
            </a>
            <a href="{{ route('reports.margins-pdf', request()->query()) }}"
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
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Famille</label>
                <select name="family_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="">— Toutes —</option>
                    @foreach($families as $f)
                        <option value="{{ $f->id }}" {{ $familyId == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Trier par</label>
                <select name="sort_by" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 bg-white">
                    <option value="marge_brute" {{ $sortBy === 'marge_brute' ? 'selected' : '' }}>Marge brute</option>
                    <option value="ca_ht"       {{ $sortBy === 'ca_ht'       ? 'selected' : '' }}>CA HT</option>
                    <option value="qty_vendue"  {{ $sortBy === 'qty_vendue'  ? 'selected' : '' }}>Quantité vendue</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">Appliquer</button>
            <a href="{{ route('reports.margins') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">Réinitialiser</a>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">CA HT total</p>
            <p class="text-[17px] font-bold text-gray-900 mt-1">{{ number_format($totalCaHt, 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Coût total</p>
            <p class="text-[17px] font-bold text-gray-900 mt-1">{{ number_format($totalCout, 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA</p>
        </div>
        <div class="bg-emerald-50 rounded-[4px] border border-emerald-100 p-4 shadow-sm">
            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-widest">Marge brute</p>
            <p class="text-[17px] font-bold text-emerald-800 mt-1">{{ number_format($totalMarge, 0, ',', ' ') }}</p>
            <p class="text-xs text-emerald-500 mt-0.5">FCFA</p>
        </div>
        <div class="bg-emerald-600 rounded-[4px] p-4 shadow-sm shadow-emerald-200">
            <p class="text-xs font-semibold text-emerald-200 uppercase tracking-widest">Taux de marge</p>
            <p class="text-3xl font-extrabold text-white mt-1">{{ $tauxMoyen }}%</p>
            <p class="text-xs text-emerald-300 mt-0.5">Taux moyen</p>
        </div>
    </div>

    {{-- Chart top 10 --}}
    @if($top10->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 shadow-sm">
        <h2 class="text-sm font-bold text-gray-800 mb-4">Top 10 produits par marge brute</h2>
        <div id="chart-margins-top10"></div>
    </div>
    @endif

    {{-- Tableau --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">Détail par produit ({{ $products->count() }} produits)</h2>
        </div>
        @if($products->isEmpty())
            <div class="py-12 text-center text-gray-400 text-sm">Aucun produit vendu sur cette période</div>
        @else
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky">
                <thead>
                    <tr>
                        <th class="text-left">Produit</th>
                        <th class="text-right">Qté</th>
                        <th class="text-right">CA HT</th>
                        <th class="text-right">Coût</th>
                        <th class="text-right">Marge</th>
                        <th class="text-center">Taux</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $p)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">{{ $p->name }}</div>
                            @if($p->reference)
                            <div class="text-xs text-gray-400 font-mono">{{ $p->reference }}</div>
                            @endif
                        </td>
                        <td class="text-right text-gray-600 tabular-nums">{{ number_format($p->qty_vendue, 1, ',', ' ') }}</td>
                        <td class="text-right font-mono text-gray-700 tabular-nums">{{ number_format($p->ca_ht, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono text-gray-500 tabular-nums">{{ number_format($p->cout_achats, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono font-bold {{ $p->marge_brute >= 0 ? 'text-emerald-700' : 'text-rose-600' }} tabular-nums">
                            {{ number_format($p->marge_brute, 0, ',', ' ') }}
                        </td>
                        <td class="text-center">
                            @php
                                $t = $p->taux_marge;
                                $cls = $t >= 30 ? 'bg-emerald-100 text-emerald-700' : ($t >= 15 ? 'bg-amber-100 text-amber-700' : ($t >= 0 ? 'bg-orange-100 text-orange-700' : 'bg-rose-100 text-rose-700'));
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $cls }}">{{ $t }}%</span>
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
@if($top10->isNotEmpty())
(window.__pendingApex = window.__pendingApex || []).push(function () {
    const fmt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' F';
    const el  = document.querySelector('#chart-margins-top10');
    if (!el) return;
    const chart = new ApexCharts(el, {
        chart:  { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Marge brute', data: @json($top10->pluck('marge_brute')->map(fn($v) => (int)$v)) }],
        xaxis:  {
            categories: @json($top10->pluck('name')->map(fn($n) => strlen($n) > 22 ? substr($n, 0, 22).'…' : $n)),
            labels: { style: { fontSize: '11px', colors: '#64748b' } }, axisBorder: { show: false }
        },
        yaxis:  { labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: fmt } },
        colors: ['#059669'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%', borderRadiusApplication: 'end' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
        tooltip: { theme: 'light', y: { formatter: fmt } },
    });
    chart.render();
    window.__turboCleanups = window.__turboCleanups || [];
    window.__turboCleanups.push(() => { try { chart.destroy(); } catch(e) {} });
});
@endif
</script>
@endpush
