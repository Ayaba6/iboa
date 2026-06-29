@extends('layouts.erp')
@section('title', 'Chaîne de Valeur Intégrée')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Chaîne de Valeur</span>
@endsection

@section('content')
@php
$colorMap = [
    'orange' => ['bg'=>'bg-orange-50',  'border'=>'border-orange-200',  'icon'=>'text-orange-500',  'badge'=>'bg-orange-100 text-orange-700',  'dot'=>'bg-orange-400',  'arrow'=>'text-orange-300',  'header'=>'bg-orange-500'],
    'violet' => ['bg'=>'bg-violet-50',  'border'=>'border-violet-200',  'icon'=>'text-violet-500',  'badge'=>'bg-violet-100 text-violet-700',  'dot'=>'bg-violet-400',  'arrow'=>'text-violet-300',  'header'=>'bg-violet-500'],
    'sky'    => ['bg'=>'bg-sky-50',     'border'=>'border-sky-200',     'icon'=>'text-sky-500',     'badge'=>'bg-sky-100 text-sky-700',        'dot'=>'bg-sky-400',     'arrow'=>'text-sky-300',     'header'=>'bg-sky-500'],
    'lime'   => ['bg'=>'bg-lime-50',    'border'=>'border-lime-200',    'icon'=>'text-lime-600',    'badge'=>'bg-lime-100 text-lime-700',      'dot'=>'bg-lime-500',    'arrow'=>'text-lime-300',    'header'=>'bg-lime-600'],
    'teal'   => ['bg'=>'bg-teal-50',    'border'=>'border-teal-200',    'icon'=>'text-teal-500',    'badge'=>'bg-teal-100 text-teal-700',      'dot'=>'bg-teal-400',    'arrow'=>'text-teal-300',    'header'=>'bg-teal-500'],
    'green'  => ['bg'=>'bg-green-50',   'border'=>'border-green-200',   'icon'=>'text-green-600',   'badge'=>'bg-green-100 text-green-700',    'dot'=>'bg-green-500',   'arrow'=>'text-green-300',   'header'=>'bg-green-600'],
    'cyan'   => ['bg'=>'bg-cyan-50',    'border'=>'border-cyan-200',    'icon'=>'text-cyan-500',    'badge'=>'bg-cyan-100 text-cyan-700',      'dot'=>'bg-cyan-400',    'arrow'=>'text-cyan-300',    'header'=>'bg-cyan-500'],
    'indigo' => ['bg'=>'bg-indigo-50',  'border'=>'border-indigo-200',  'icon'=>'text-indigo-500',  'badge'=>'bg-indigo-100 text-indigo-700',  'dot'=>'bg-indigo-400',  'arrow'=>'text-indigo-300',  'header'=>'bg-indigo-500'],
    'amber'  => ['bg'=>'bg-amber-50',   'border'=>'border-amber-200',   'icon'=>'text-amber-500',   'badge'=>'bg-amber-100 text-amber-700',   'dot'=>'bg-amber-400',   'arrow'=>'text-amber-300',   'header'=>'bg-amber-500'],
    'rose'   => ['bg'=>'bg-rose-50',    'border'=>'border-rose-200',    'icon'=>'text-rose-500',    'badge'=>'bg-rose-100 text-rose-700',      'dot'=>'bg-rose-400',    'arrow'=>'text-rose-300',    'header'=>'bg-rose-500'],
    'purple' => ['bg'=>'bg-purple-50',  'border'=>'border-purple-200',  'icon'=>'text-purple-500',  'badge'=>'bg-purple-100 text-purple-700',  'dot'=>'bg-purple-400',  'arrow'=>'text-purple-300',  'header'=>'bg-purple-500'],
];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Chaîne de Valeur Intégrée</h1>
            <p class="text-sm text-gray-500 mt-0.5">§3 CDC — Vue temps réel de l'ensemble du processus industriel et commercial</p>
        </div>
        <div class="text-right text-xs text-gray-400">
            <div class="font-medium text-gray-600">{{ now()->format('d/m/Y H:i') }}</div>
            <div>Mois {{ now()->format('F Y') }}</div>
        </div>
    </div>

    {{-- Flux financier global --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach([
            ['Chiffre d\'affaires', $fluxMois['ca_mois'], 'F', 'indigo'],
            ['Encaissements', $fluxMois['encaisse'], 'F', 'green'],
            ['Achats du mois', $fluxMois['achats_mois'], 'F', 'orange'],
            ['Trésorerie nette', $fluxMois['solde'], 'F', $fluxMois['solde'] >= 0 ? 'teal' : 'red'],
            ['Commandes à facturer', $fluxMois['cmd_non_fact'], 'F', 'violet'],
            ['BC en cours (valeur)', $fluxMois['bc_en_cours_val'], 'F', 'amber'],
        ] as [$label, $val, $unit, $col])
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-xs text-gray-500 mb-1 leading-tight">{{ $label }}</p>
            <p class="text-sm font-bold tabular-nums {{ $col === 'red' ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($val, 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-400">{{ $unit }}</p>
        </div>
        @endforeach
    </div>

    {{-- PIPELINE HORIZONTAL — Chaîne de valeur --}}
    {{-- Desktop: scroll horizontal sur les 11 étapes --}}
    <div class="overflow-x-auto pb-4">
        <div class="flex items-stretch gap-0 min-w-max">
            @foreach($steps as $idx => $step)
            @php $c = $colorMap[$step['color']]; @endphp

            {{-- Étape --}}
            <div class="flex items-stretch">
                <a href="{{ $step['route'] }}"
                   class="group relative flex flex-col w-48 rounded-xl border-2 {{ $c['border'] }} {{ $c['bg'] }} hover:shadow-lg transition-all duration-200 overflow-hidden no-underline">

                    {{-- Header coloré --}}
                    <div class="{{ $c['header'] }} px-3 py-2 flex items-center gap-2">
                        <div class="w-6 h-6 flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $step['icon'] }}"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-white truncate">{{ $step['label'] }}</p>
                            <p class="text-[10px] text-white/70">Étape {{ $idx + 1 }}</p>
                        </div>
                    </div>

                    {{-- KPIs --}}
                    <div class="flex-1 px-3 py-3 space-y-2">
                        @foreach($step['kpis'] as $kpi)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] text-gray-500 leading-tight">{{ $kpi['label'] }}</span>
                            <span class="text-xs font-bold tabular-nums flex-shrink-0
                                {{ $kpi['alert'] ? 'text-red-600 bg-red-50 px-1.5 py-0.5 rounded-md' : 'text-gray-800' }}">
                                @if($kpi['alert'])
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500 mr-0.5 align-middle"></span>
                                @endif
                                {{ $kpi['value'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>

                    {{-- Hover overlay --}}
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/3 transition-colors duration-200 rounded-xl pointer-events-none"></div>
                    {{-- External link icon on hover --}}
                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </div>
                </a>

                {{-- Flèche entre étapes --}}
                @if(!$loop->last)
                <div class="flex items-center px-1 flex-shrink-0">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Vue grille responsive (mobile + vue détaillée) --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900 mb-4">Détail par module</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($steps as $idx => $step)
            @php $c = $colorMap[$step['color']]; @endphp
            <a href="{{ $step['route'] }}"
               class="group bg-white rounded-xl border border-gray-200 hover:border-{{ $step['color'] }}-300 hover:shadow-md transition-all duration-200 overflow-hidden block no-underline">
                <div class="{{ $c['header'] }} px-4 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $step['icon'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">{{ $step['label'] }}</p>
                        <p class="text-xs text-white/70">Étape {{ $idx + 1 }} / 11</p>
                    </div>
                </div>
                <div class="p-4 space-y-2.5">
                    @foreach($step['kpis'] as $kpi)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">{{ $kpi['label'] }}</span>
                        <div class="flex items-center gap-1.5">
                            @if($kpi['alert'])
                            <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 animate-pulse"></span>
                            @endif
                            <span class="text-sm font-bold tabular-nums {{ $kpi['alert'] ? 'text-red-600' : 'text-gray-900' }}">
                                {{ $kpi['value'] }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="px-4 pb-3">
                    <span class="text-xs font-medium {{ $c['icon'] }} group-hover:underline flex items-center gap-1">
                        Accéder au module
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </div>
            </a>
            @endforeach
        </div>
    </div>

    {{-- Légende alertes --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="text-sm font-medium text-amber-800">Indicateurs d'alerte</p>
            <p class="text-xs text-amber-700 mt-0.5">
                Les valeurs en rouge avec <span class="inline-block w-2 h-2 rounded-full bg-red-500 align-middle"></span> indiquent un point d'attention nécessitant une action.
                Cliquez sur un module pour accéder directement à la vue de gestion correspondante.
            </p>
        </div>
    </div>

</div>
@endsection
