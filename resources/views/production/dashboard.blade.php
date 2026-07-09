@extends('layouts.erp')
@section('title', 'Tableau de bord production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Production</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $panelH = 'flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white';
    $panelT = 'text-[13px] font-bold text-gray-900';
    $foot = 'px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8]';
    $lnk = 'text-[12px] text-emerald-700 hover:text-emerald-900 font-semibold';

    $ofBadge = fn($s) => match($s) {
        'en_cours'  => ['En cours',  'bg-emerald-100 text-emerald-700'],
        'lance'     => ['Lancé',     'bg-blue-100 text-blue-700'],
        'planifie'  => ['Planifié',  'bg-indigo-100 text-indigo-700'],
        'valide'    => ['Validé',    'bg-teal-100 text-teal-700'],
        'brouillon' => ['Brouillon', 'bg-gray-100 text-gray-600'],
        'termine'   => ['Clôturé',   'bg-gray-200 text-gray-700'],
        'annule'    => ['Annulé',    'bg-red-100 text-red-700'],
        default     => [ucfirst((string)$s), 'bg-gray-100 text-gray-600'],
    };

    // Sparkline : normalise prod7Days sur 100x28
    $spark = function(array $serie) {
        $max = max(max($serie), 1);
        $pts = [];
        foreach ($serie as $i => $v) {
            $x = round($i * (100 / max(count($serie) - 1, 1)), 1);
            $y = round(26 - ($v / $max) * 22, 1);
            $pts[] = $x . ',' . $y;
        }
        return implode(' ', $pts);
    };
    $sparkPts = $spark($prod7Days);
@endphp
<div class="space-y-4">

    {{-- Bandeau validations --}}
    @if(($pendingCount ?? 0) > 0)
    <div class="flex items-center justify-between bg-[#fff8ec] border border-amber-200 rounded-[4px] px-3 py-2.5">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <div>
                <p class="text-[13px] font-bold text-amber-700">{{ $pendingCount }} document(s) en attente de votre validation</p>
                <p class="text-[11.5px] text-gray-500">Ordres de fabrication, rebuts, approvisionnement matières, déclarations de production…</p>
            </div>
        </div>
        <a href="{{ route('validations.index') }}" class="text-[13px] font-semibold text-emerald-700 hover:text-emerald-900 flex items-center gap-1">
            Traiter
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
    @endif

    {{-- Carte profil --}}
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-[4px] bg-emerald-800 flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <p class="text-[14px] font-bold text-gray-900">{{ auth()->user()->name }}</p>
                <p class="text-[12px] text-gray-500">Responsable Production</p>
                <p class="text-[11px] text-gray-400">{{ now()->translatedFormat('l j F Y') }}</p>
            </div>
        </div>
        <div class="border-l border-gray-200 pl-6">
            <p class="text-[11px] text-gray-500">Production mensuelle</p>
            <p class="text-[17px] font-bold text-gray-900 tabular-nums">{{ number_format($kpis['meters'], 0, ',', ' ') }} m</p>
            <p class="text-[11px] text-gray-400">du {{ \Illuminate\Support\Carbon::parse($from)->format('d/m') }} au {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</p>
        </div>
        <div class="border-l border-gray-200 pl-6" x-data="{ time: '' }" x-init="setInterval(() => time = new Date().toLocaleTimeString('fr-FR'), 1000)">
            <span class="inline-flex items-center gap-2 border border-gray-200 rounded-[4px] px-3 py-1.5 text-[14px] font-semibold text-gray-800 tabular-nums">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span x-text="time || '—'"></span>
            </span>
        </div>
        <div class="flex items-center gap-2 ml-auto flex-wrap">
            <a href="{{ route('production.orders.create') }}" class="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvel OF
            </a>
            <a href="{{ route('production.planning') }}" class="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Suivi fabrication
            </a>
            <a href="{{ route('production.orders.index') }}" class="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Déclaration production
            </a>
            <a href="{{ route('production.reports') }}" class="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Rapports
            </a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach([
            ['label' => 'OF en cours',        'value' => number_format($kpis['of_en_cours'], 0, ',', ' '),                                        'sub' => $kpis['of_en_retard'] . ' en retard',                    'color' => '#10b981', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'Production du jour', 'value' => number_format($kpis['meters_today'], 0, ',', ' ') . ' m',                                'sub' => 'mois : ' . number_format($kpis['meters'], 0, ',', ' ') . ' m', 'color' => '#10b981', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
            ['label' => 'Rendement moyen',    'value' => $kpis['yield'] !== null ? str_replace('.', ',', (string) $kpis['yield']) . ' %' : '—',   'sub' => 'matière consommée',                                     'color' => '#10b981', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['label' => 'Rebuts / Pertes',    'value' => number_format($kpis['waste_weight'], 0, ',', ' ') . ' kg',                               'sub' => ($kpis['rebut_pct'] !== null ? str_replace('.', ',', (string) $kpis['rebut_pct']) . ' % de la prod.' : '—'), 'color' => '#f87171', 'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
            ['label' => 'Taux de conformité', 'value' => $kpis['conformite'] !== null ? str_replace('.', ',', (string) $kpis['conformite']) . ' %' : '—', 'sub' => 'contrôles qualité',                             'color' => '#10b981', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['label' => 'Arrêts machine',     'value' => str_replace('.', ',', (string) $trs['downtime_h']) . ' h',                               'sub' => 'TRS : ' . str_replace('.', ',', (string) $trs['trs']) . ' %', 'color' => '#f87171', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <p class="text-[17px] font-bold {{ $kpi['color'] === '#f87171' ? 'text-red-600' : 'text-gray-900' }} tabular-nums mt-1">{{ $kpi['value'] }}</p>
            <p class="text-[11px] text-gray-400">{{ $kpi['sub'] }}</p>
            <svg viewBox="0 0 100 28" class="w-full h-6 mt-1" preserveAspectRatio="none">
                <polyline points="{{ $sparkPts }}" fill="none" stroke="{{ $kpi['color'] }}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
        </div>
        @endforeach
    </div>

    {{-- Rangée 1 : OF en cours / Suivi production / Alertes --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-1">
            <div class="{{ $panelH }}"><h2 class="{{ $panelT }}">Ordres de fabrication en cours</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="{{ $th }} text-left">Référence</th>
                            <th class="{{ $th }} text-left">Article</th>
                            <th class="{{ $th }} text-right">Prévue</th>
                            <th class="{{ $th }} text-right">Réalisée</th>
                            <th class="{{ $th }} text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ofEnCours as $of)
                        @php [$sl, $sc] = $ofBadge($of->status); @endphp
                        <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                            <td class="px-3 py-1.5 whitespace-nowrap">
                                <a href="{{ route('production.orders.show', $of) }}" class="font-mono text-emerald-800 hover:underline">{{ $of->number }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-gray-900 max-w-[180px] truncate">{{ $of->product?->name ?? $of->designation ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format((float) $of->quantity_requested, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format((float) $of->quantity_produced, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium {{ $sc }}">{{ $sl }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun OF en cours.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="{{ $foot }}"><a href="{{ route('production.orders.index') }}" class="{{ $lnk }}">Voir tous les OF en cours</a></div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-1">
            <div class="{{ $panelH }}"><h2 class="{{ $panelT }}">Suivi production du jour</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="{{ $th }} text-left">Date</th>
                            <th class="{{ $th }} text-left">OF</th>
                            <th class="{{ $th }} text-left">Article</th>
                            <th class="{{ $th }} text-right">Métrage</th>
                            <th class="{{ $th }} text-left">Opérateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suiviJour as $out)
                        <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                            <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $out->produced_at?->format('d/m H:i') ?? '—' }}</td>
                            <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $out->productionOrder?->number ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-900 max-w-[160px] truncate">{{ $out->product?->name ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format((float) $out->total_meters, 0, ',', ' ') }} m</td>
                            <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $suiviUsers[$out->created_by] ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune production déclarée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="{{ $foot }}"><a href="{{ route('production.planning') }}" class="{{ $lnk }}">Voir tout le suivi du jour</a></div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-1">
            <div class="{{ $panelH }}">
                <h2 class="{{ $panelT }}">Alertes production</h2>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($alertes as $al)
                <div class="px-3 py-2.5 flex items-start gap-2.5">
                    @if($al['niveau'] === 'rouge')
                    <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @else
                    <svg class="w-4 h-4 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-[12.5px] font-semibold {{ $al['niveau'] === 'rouge' ? 'text-red-600' : 'text-amber-600' }}">{{ $al['titre'] }}</p>
                        <p class="text-[11.5px] text-gray-500 truncate">{{ $al['detail'] }}</p>
                    </div>
                    @if($al['heure'])<span class="text-[11px] text-gray-400 tabular-nums shrink-0">{{ $al['heure'] }}</span>@endif
                </div>
                @empty
                <div class="px-4 py-8 text-center text-gray-400 text-[12px]">Aucune alerte active.</div>
                @endforelse
            </div>
            <div class="{{ $foot }}"><a href="{{ route('production.maintenance.index') }}" class="{{ $lnk }}">Voir toutes les alertes</a></div>
        </div>
    </div>

    {{-- Rangée 2 : Consommation matières / Performances machines / Contrôle qualité --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $panelH }}"><h2 class="{{ $panelT }}">Consommation matières</h2></div>
            <table class="w-full text-[12px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-right">Stock dispo.</th>
                        <th class="{{ $th }} text-right">Conso. jour</th>
                        <th class="{{ $th }} text-right">Conso. mois</th>
                        <th class="{{ $th }} text-left">Unité</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consoMatieres as $cm)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-gray-900 max-w-[170px] truncate">{{ $cm['name'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($cm['stock'], 1, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($cm['jour'], 1, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($cm['mois'], 1, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-gray-500">{{ $cm['unite'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune matière première.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="{{ $foot }}"><a href="{{ route('production.coils.index') }}" class="{{ $lnk }}">Voir toutes les matières</a></div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $panelH }}"><h2 class="{{ $panelT }}">Performances machines</h2></div>
            <table class="w-full text-[12px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-right">Disponibilité</th>
                        <th class="{{ $th }} text-right">Arrêts</th>
                        <th class="{{ $th }} text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($perfMachines as $pm)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5">
                            <span class="font-mono text-emerald-800">{{ $pm['code'] }}</span>
                            <p class="text-[10.5px] text-gray-400 truncate max-w-[140px]">{{ $pm['name'] }}</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $pm['dispo'] < 80 ? 'text-red-600' : 'text-gray-900' }}">{{ str_replace('.', ',', (string) $pm['dispo']) }} %</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $pm['arrets'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ str_replace('.', ',', (string) $pm['arrets']) }} h</td>
                        <td class="px-3 py-1.5 text-center">
                            @php
                                [$ml, $mc] = match($pm['statut']) {
                                    'en_service', 'active', 'disponible' => ['En service', 'bg-emerald-100 text-emerald-700'],
                                    'en_panne'                           => ['En panne',   'bg-red-100 text-red-700'],
                                    'maintenance', 'en_maintenance'      => ['Maintenance','bg-amber-100 text-amber-700'],
                                    default                              => [ucfirst(str_replace('_', ' ', (string) $pm['statut'])), 'bg-gray-100 text-gray-600'],
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium {{ $mc }}">{{ $ml }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucune machine active.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="{{ $foot }}"><a href="{{ route('production.machines.index') }}" class="{{ $lnk }}">Voir le détail des performances</a></div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $panelH }}"><h2 class="{{ $panelT }}">Contrôle qualité</h2></div>
            <table class="w-full text-[12px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Date</th>
                        <th class="{{ $th }} text-left">OF</th>
                        <th class="{{ $th }} text-left">Contrôle</th>
                        <th class="{{ $th }} text-center">Résultat</th>
                        <th class="{{ $th }} text-left">Opérateur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($controlesQualite as $ci)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $ci->inspected_at?->format('d/m H:i') ?? '—' }}</td>
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $ci->productionOrder?->number ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $ci->typeLabel() }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php
                                [$ql, $qc] = match($ci->status) {
                                    'conforme'     => ['Conforme',     'bg-emerald-100 text-emerald-700'],
                                    'non_conforme' => ['Non conforme', 'bg-red-100 text-red-700'],
                                    'partiel'      => ['Partiel',      'bg-amber-100 text-amber-700'],
                                    default        => [ucfirst((string) $ci->status), 'bg-gray-100 text-gray-600'],
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium {{ $qc }}">{{ $ql }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $ci->controller?->full_name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun contrôle enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="{{ $foot }}"><a href="{{ route('qualite.inspections.index') }}" class="{{ $lnk }}">Voir tous les contrôles</a></div>
        </div>
    </div>

</div>
@endsection
