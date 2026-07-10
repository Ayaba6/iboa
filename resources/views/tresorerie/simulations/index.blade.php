@extends('layouts.erp')
@section('title', 'Simulation de trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Simulation</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp
<div class="space-y-3">

    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Simulation de trésorerie</h1>
        <p class="text-sm text-gray-500 mt-0.5">Projection what-if de la position future selon vos hypothèses</p>
    </div>

    {{-- Paramètres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Horizon (semaines)</label>
                <input type="number" name="horizon_weeks" min="1" max="52" value="{{ $params['horizon_weeks'] }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Taux de recouvrement (%)</label>
                <input type="number" name="recovery_rate" min="0" max="100" value="{{ $params['recovery_rate'] }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                <p class="text-xs text-gray-400 mt-0.5">% des créances réellement encaissées</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Délai moyen paiement (jours)</label>
                <input type="number" name="delay_days" min="0" max="180" value="{{ $params['delay_days'] }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                <p class="text-xs text-gray-400 mt-0.5">retard appliqué aux encaissements</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Charges récurrentes / sem. (FCFA)</label>
                <input type="number" name="recurring_weekly" min="0" step="1000" value="{{ $params['recurring_weekly'] }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>
        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="px-5 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">Simuler</button>
            <a href="{{ route('tresorerie.simulations.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">Réinitialiser</a>
            <span class="text-xs text-gray-400 ml-2">Scénarios rapides :</span>
            <a href="{{ route('tresorerie.simulations.index', ['recovery_rate'=>70,'delay_days'=>30,'horizon_weeks'=>$params['horizon_weeks']]) }}" class="text-xs text-red-600 hover:underline">Pessimiste</a>
            <a href="{{ route('tresorerie.simulations.index', ['recovery_rate'=>90,'delay_days'=>7,'horizon_weeks'=>$params['horizon_weeks']]) }}" class="text-xs text-amber-600 hover:underline">Réaliste</a>
            <a href="{{ route('tresorerie.simulations.index', ['recovery_rate'=>100,'delay_days'=>0,'horizon_weeks'=>$params['horizon_weeks']]) }}" class="text-xs text-emerald-600 hover:underline">Optimiste</a>
        </div>
    </form>

    {{-- KPI résultat --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Solde actuel</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($result['start']) }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Entrées projetées</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums mt-1">+{{ $fmt($result['total_in']) }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Sorties projetées</p>
            <p class="text-[16px] font-bold text-red-600 tabular-nums mt-1">-{{ $fmt($result['total_out']) }} F</p>
        </div>
        <div class="rounded-[4px] border shadow-sm p-4 {{ $result['min_balance'] < 0 ? 'bg-red-50 border-red-200' : 'bg-white border-gray-100' }}">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Solde min. projeté</p>
            <p class="text-[16px] font-bold tabular-nums mt-1 {{ $result['min_balance'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $fmt($result['min_balance']) }} F</p>
            <p class="text-xs {{ $result['min_balance'] < 0 ? 'text-red-500' : 'text-gray-400' }}">
                {{ $result['min_week'] === 0 ? 'cette semaine' : 'à S+' . $result['min_week'] }}
                {{ $result['min_balance'] < 0 ? '· découvert !' : '' }}
            </p>
        </div>
    </div>

    @if($result['min_balance'] < 0)
    <div class="rounded-[4px] bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
        <div>
            <p class="font-semibold">Risque de découvert détecté</p>
            <p>Sous ces hypothèses, la trésorerie devient négative ({{ $fmt($result['min_balance']) }} F) {{ $result['min_week'] === 0 ? 'dès cette semaine' : 'à la semaine +' . $result['min_week'] }}. Anticipez un financement ou décalez des décaissements.</p>
        </div>
    </div>
    @endif

    {{-- Courbe projetée --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Position de trésorerie projetée</h3>
        <div id="chart-sim" style="min-height:260px;"></div>
    </div>

    {{-- Détail par semaine --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Semaine</th>
                        <th class="text-left">Date</th>
                        <th class="text-right">Entrées</th>
                        <th class="text-right">Sorties</th>
                        <th class="text-right">Net</th>
                        <th class="text-right">Solde projeté</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($result['buckets'] as $b)
                    <tr>
                        <td class="font-medium text-gray-800">{{ $b['label'] }}</td>
                        <td class="text-gray-500 tabular-nums">{{ $b['date'] }}</td>
                        <td class="text-right font-mono tabular-nums text-emerald-600">{{ $b['in'] ? '+' . $fmt($b['in']) : '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-red-600">{{ $b['out'] ? '-' . $fmt($b['out']) : '—' }}</td>
                        <td class="text-right font-mono tabular-nums {{ $b['net'] >= 0 ? 'text-gray-700' : 'text-red-600' }}">{{ ($b['net'] >= 0 ? '+' : '') . $fmt($b['net']) }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums {{ $b['balance'] < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $fmt($b['balance']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels  = @json(array_column($result['buckets'], 'label'));
    const balance = @json(array_column($result['buckets'], 'balance'));
    const fmtFull = v => new Intl.NumberFormat('fr-FR').format(v) + ' FCFA';
    const fmtAxis = v => Math.abs(v) >= 1000000 ? (v/1000000).toFixed(1)+' M' : Math.abs(v) >= 1000 ? Math.round(v/1000)+' k' : v;

    if (window.ApexCharts && document.getElementById('chart-sim')) {
        new ApexCharts(document.getElementById('chart-sim'), {
            chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'inherit', animations: { speed: 500 } },
            series: [{ name: 'Solde projeté', data: balance }],
            colors: ['#6366f1'],
            stroke: { width: 3, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            markers: { size: 3, colors: ['#6366f1'], strokeColors: '#fff', strokeWidth: 2 },
            dataLabels: { enabled: false },
            xaxis: { categories: labels, labels: { style: { fontSize: '11px', colors: '#9ca3af' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: fmtAxis, style: { fontSize: '10px', colors: '#9ca3af' } } },
            annotations: { yaxis: [{ y: 0, borderColor: '#ef4444', strokeDashArray: 4, label: { text: 'Découvert', style: { color: '#fff', background: '#ef4444' } } }] },
            tooltip: { y: { formatter: fmtFull } },
            grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
        }).render();
    }
});
</script>
@endpush
