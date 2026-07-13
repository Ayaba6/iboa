@extends('layouts.erp')
@section('title', 'Indicateurs qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Indicateurs qualité</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $months = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    $maxMonthly = max(1, max($monthly));
@endphp
<div class="space-y-4">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Indicateurs qualité {{ $year }}</h1>
            <p class="text-[12px] text-gray-500">Taux NC, rebut, délais de traitement, efficacité CAPA, libérations et récurrence.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="year" onchange="this.form.submit()" class="h-8 py-0 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white">
                @foreach($years as $y)<option value="{{ $y }}" @selected($year==$y)>{{ $y }}</option>@endforeach
            </select>
        </form>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php
        $cards = [
            ['Taux NC', $kpis['taux_nc'].' %', 'inspections non conformes', $kpis['taux_nc'] > 5 ? 'text-red-600' : 'text-emerald-700'],
            ['Taux de rebut', $kpis['taux_rebut'].' %', 'quantités rejetées / contrôlées', $kpis['taux_rebut'] > 3 ? 'text-red-600' : 'text-gray-900'],
            ['NC ouvertes', $kpis['nc_open'], 'à traiter', $kpis['nc_open'] > 0 ? 'text-amber-600' : 'text-emerald-700'],
            ['Délai moyen', $kpis['avg_lead'] === null ? '—' : $kpis['avg_lead'].' j', 'traitement NC clôturées', 'text-gray-900'],
            ['CAPA en retard', $kpis['capa_overdue'], 'actions hors délai', $kpis['capa_overdue'] > 0 ? 'text-red-600' : 'text-emerald-700'],
            ['Efficacité CAPA', $kpis['capa_efficacite'] === null ? '—' : $kpis['capa_efficacite'].' %', 'actions vérifiées efficaces', 'text-emerald-700'],
            ['Taux de refus', $kpis['taux_refus'].' %', 'lots refusés / décidés', $kpis['taux_refus'] > 0 ? 'text-red-600' : 'text-emerald-700'],
            ['Réclamations', $kpis['client_claims'], 'NC d\'origine client', $kpis['client_claims'] > 0 ? 'text-amber-600' : 'text-gray-900'],
        ];
        @endphp
        @foreach($cards as [$label, $value, $sub, $color])
        <div class="bg-white border border-gray-300 rounded-[4px] px-3 py-2">
            <p class="text-[11px] text-gray-500 uppercase">{{ $label }}</p>
            <p class="text-[20px] font-bold {{ $color }} tabular-nums leading-tight">{{ $value }}</p>
            <p class="text-[10px] text-gray-400">{{ $sub }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Tendance mensuelle NC --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-[13px] font-semibold text-gray-800 mb-3">Non-conformités par mois ({{ $ncTotal }} au total)</p>
            <div class="flex items-end gap-1 h-40">
                @for($m = 1; $m <= 12; $m++)
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <span class="text-[10px] text-gray-500 tabular-nums">{{ $monthly[$m] ?: '' }}</span>
                    <div class="w-full bg-emerald-500 rounded-t" style="height: {{ $monthly[$m] ? max(4, round($monthly[$m] / $maxMonthly * 130)) : 0 }}px"></div>
                    <span class="text-[10px] text-gray-400 mt-1">{{ $months[$m] }}</span>
                </div>
                @endfor
            </div>
        </div>

        {{-- Gravité + libérations --}}
        <div class="space-y-3">
            <div class="bg-white border border-gray-300 rounded-[4px] p-4">
                <p class="text-[13px] font-semibold text-gray-800 mb-2">NC par gravité</p>
                @php $sevMax = max(1, max($ncBySeverity)); @endphp
                @foreach(['mineure'=>['Mineure','bg-gray-400'],'majeure'=>['Majeure','bg-amber-500'],'critique'=>['Critique','bg-red-500']] as $k => [$lbl,$bg])
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-[12px] text-gray-600 w-16">{{ $lbl }}</span>
                    <div class="flex-1 bg-gray-100 rounded h-4 overflow-hidden"><div class="{{ $bg }} h-4" style="width: {{ round($ncBySeverity[$k] / $sevMax * 100) }}%"></div></div>
                    <span class="text-[12px] font-semibold tabular-nums w-8 text-right">{{ $ncBySeverity[$k] }}</span>
                </div>
                @endforeach
            </div>
            <div class="bg-white border border-gray-300 rounded-[4px] p-4">
                <p class="text-[13px] font-semibold text-gray-800 mb-2">Libérations qualité</p>
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div><p class="text-[16px] font-bold text-emerald-700 tabular-nums">{{ $relByStatus['libere'] }}</p><p class="text-[10px] text-gray-500">Libérés</p></div>
                    <div><p class="text-[16px] font-bold text-indigo-700 tabular-nums">{{ $relByStatus['derogation'] }}</p><p class="text-[10px] text-gray-500">Dérogations</p></div>
                    <div><p class="text-[16px] font-bold text-red-600 tabular-nums">{{ $relByStatus['refuse'] }}</p><p class="text-[10px] text-gray-500">Refusés</p></div>
                    <div><p class="text-[16px] font-bold text-amber-600 tabular-nums">{{ $relByStatus['en_attente'] }}</p><p class="text-[10px] text-gray-500">En attente</p></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Récurrence articles --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold">Articles récurrents (top 5 par nombre de NC)</div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-right">NC</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $tp)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40">
                        <td class="px-3 py-1.5">{{ $productNames[$tp->product_id] ?? ('#'.$tp->product_id) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $tp->nc_count }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-4 py-8 text-center text-gray-400 text-sm">Aucune NC rattachée à un article sur {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Exercice : <span class="text-white font-semibold">{{ $year }}</span></span>
        <span class="border-l border-white/10 pl-6">Inspections : <span class="text-white font-semibold">{{ $insTotal }} ({{ $insNok }} NOK)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
