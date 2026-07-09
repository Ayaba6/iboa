@extends('layouts.erp')
@section('title', 'Trésorerie — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Trésorerie</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $moisLabel = $now->translatedFormat('F Y');

    $soldeBanque = (int) $accounts->where('type', 'banque')->sum('current_balance');
    $soldeCaisse = (int) $accounts->where('type', 'caisse')->sum('current_balance');
    $soldeMobile = (int) $accounts->where('type', 'mobile_money')->sum('current_balance');

    $tauxRecouv = ($creancesClients->total + $encaissMois->total) > 0
        ? round($encaissMois->total / ($creancesClients->total + $encaissMois->total) * 100)
        : 0;
@endphp

<div class="space-y-3">

    {{-- ══ HERO : Position de trésorerie ══════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-[4px] text-white" style="background:linear-gradient(135deg,#047857,#065f46)">
        {{-- motifs déco --}}
        <div class="absolute -top-16 -right-16 w-64 h-64 bg-white/10 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-20 -left-10 w-56 h-56 bg-emerald-400/20 rounded-full blur-3xl"></div>

        <div class="relative p-6 sm:p-8">
            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                {{-- Bloc principal --}}
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-emerald-100/80 text-sm font-medium">
                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-[4px] bg-white/15 backdrop-blur">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </span>
                        Position de trésorerie · {{ ucfirst($moisLabel) }}
                    </div>
                    <div class="mt-3 flex items-end gap-3 flex-wrap">
                        <span class="text-4xl sm:text-5xl font-bold tracking-tight tabular-nums">{{ $fmt($positionTotale) }}</span>
                        <span class="text-lg text-emerald-100 font-medium pb-1">FCFA</span>
                    </div>
                    <p class="text-emerald-100/70 text-sm mt-1">{{ $accounts->count() }} compte(s) actif(s)</p>

                    {{-- Répartition par type --}}
                    <div class="mt-5 flex flex-wrap gap-2.5">
                        <div class="inline-flex items-center gap-2 rounded-[4px] bg-white/10 backdrop-blur px-3.5 py-2 border border-white/10">
                            <span class="w-2 h-2 rounded-full bg-sky-300"></span>
                            <span class="text-xs text-emerald-100/80">Banques</span>
                            <span class="text-sm font-semibold tabular-nums">{{ $fmt($soldeBanque) }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-[4px] bg-white/10 backdrop-blur px-3.5 py-2 border border-white/10">
                            <span class="w-2 h-2 rounded-full bg-emerald-300"></span>
                            <span class="text-xs text-emerald-100/80">Caisses</span>
                            <span class="text-sm font-semibold tabular-nums">{{ $fmt($soldeCaisse) }}</span>
                        </div>
                        @if($soldeMobile > 0)
                        <div class="inline-flex items-center gap-2 rounded-[4px] bg-white/10 backdrop-blur px-3.5 py-2 border border-white/10">
                            <span class="w-2 h-2 rounded-full bg-fuchsia-300"></span>
                            <span class="text-xs text-emerald-100/80">Mobile Money</span>
                            <span class="text-sm font-semibold tabular-nums">{{ $fmt($soldeMobile) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap gap-2 lg:flex-col lg:items-stretch shrink-0">
                    <a href="{{ route('tresorerie.encaissements.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-[4px] bg-white text-emerald-800 px-3 py-2.5 text-sm font-semibold hover:bg-emerald-50 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                        Encaissement
                    </a>
                    <a href="{{ route('tresorerie.decaissements.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-[4px] bg-white/20 backdrop-blur text-white border border-white/40 px-3 py-2.5 text-sm font-semibold hover:bg-white/30 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                        Décaissement
                    </a>
                    @can('treasury.write')
                    <a href="{{ route('tresorerie.operations.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-[4px] bg-white/20 backdrop-blur text-white border border-white/40 px-3 py-2.5 text-sm font-semibold hover:bg-white/30 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m4 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Caisse
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- ══ KPI flux du mois ════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        @php
            $kpis = [
                [
                    'label' => 'Encaissements ' . $now->translatedFormat('M'),
                    'value' => '+' . $fmt($encaissMois->total),
                    'sub'   => $encaissMois->cnt . ' opération(s)',
                    'href'  => route('tresorerie.encaissements.index'),
                    'accent'=> 'emerald',
                    'icon'  => 'M7 11l5-5m0 0l5 5m-5-5v12',
                ],
                [
                    'label' => 'Décaissements ' . $now->translatedFormat('M'),
                    'value' => '-' . $fmt($decaissMois->total),
                    'sub'   => $decaissMois->cnt . ' opération(s)',
                    'href'  => route('tresorerie.decaissements.index'),
                    'accent'=> 'amber',
                    'icon'  => 'M17 13l-5 5m0 0l-5-5m5 5V6',
                ],
                [
                    'label' => 'Flux net du mois',
                    'value' => ($fluxNetMois >= 0 ? '+' : '') . $fmt($fluxNetMois),
                    'sub'   => ($fluxNetMois >= 0 ? 'Excédent' : 'Déficit') . ' ' . $now->translatedFormat('F'),
                    'href'  => null,
                    'accent'=> $fluxNetMois >= 0 ? 'indigo' : 'red',
                    'icon'  => $fluxNetMois >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6',
                ],
                [
                    'label' => 'Créances clients',
                    'value' => $fmt($creancesClients->total),
                    'sub'   => $creancesClients->cnt . ' facture(s) en attente',
                    'href'  => route('tresorerie.echeancier-clients'),
                    'accent'=> 'sky',
                    'icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ],
            ];
            $accentMap = [
                'emerald' => ['bg-emerald-500','text-emerald-600','from-emerald-400 to-emerald-600'],
                'amber'   => ['bg-amber-500','text-amber-600','from-amber-400 to-amber-600'],
                'indigo'  => ['bg-emerald-600','text-emerald-700','from-emerald-500 to-emerald-700'],
                'red'     => ['bg-red-500','text-red-600','from-red-400 to-red-600'],
                'sky'     => ['bg-sky-500','text-sky-600','from-sky-400 to-sky-600'],
            ];
        @endphp

        @foreach($kpis as $k)
        @php [$bar,$txt,$grad] = $accentMap[$k['accent']]; @endphp
        <{{ $k['href'] ? 'a' : 'div' }} @if($k['href']) href="{{ $k['href'] }}" @endif
            class="group relative overflow-hidden rounded-[4px] bg-white border border-gray-300 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 p-4">
            <span class="absolute inset-x-0 top-0 h-1 {{ $bar }}"></span>
            <div class="flex items-start justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 truncate">{{ $k['label'] }}</p>
                    <p class="mt-2 text-[16px] font-bold tracking-tight tabular-nums text-gray-900">{{ $k['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $k['sub'] }} <span class="text-gray-300">FCFA</span></p>
                </div>
                <span class="inline-flex w-10 h-10 items-center justify-center rounded-[4px] bg-gradient-to-br {{ $grad }} text-white shadow-sm shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $k['icon'] }}"/></svg>
                </span>
            </div>
        </{{ $k['href'] ? 'a' : 'div' }}>
        @endforeach

    </div>

    {{-- ══ Bandeau alertes secondaires ═════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <a href="{{ route('tresorerie.echeancier-clients') }}"
           class="flex items-center gap-4 rounded-[4px] border p-4 transition hover:shadow-md {{ $creancesEnRetard->cnt > 0 ? 'bg-red-50 border-red-200' : 'bg-white border-gray-100' }}">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-[4px] {{ $creancesEnRetard->cnt > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500">Créances en retard</p>
                <p class="text-[16px] font-bold tabular-nums {{ $creancesEnRetard->cnt > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $fmt($creancesEnRetard->total) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
                <p class="text-xs text-gray-400">{{ $creancesEnRetard->cnt }} facture(s) échue(s)</p>
            </div>
        </a>

        <a href="{{ route('tresorerie.effets.index') }}"
           class="flex items-center gap-4 rounded-[4px] border p-4 transition hover:shadow-md {{ $effetsEnAttente->cnt > 0 ? 'bg-violet-50 border-violet-200' : 'bg-white border-gray-100' }}">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-[4px] {{ $effetsEnAttente->cnt > 0 ? 'bg-violet-100 text-violet-600' : 'bg-gray-100 text-gray-400' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500">Effets en attente</p>
                <p class="text-[16px] font-bold tabular-nums {{ $effetsEnAttente->cnt > 0 ? 'text-violet-700' : 'text-gray-900' }}">{{ $fmt($effetsEnAttente->total) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
                <p class="text-xs text-gray-400">{{ $effetsEnAttente->cnt }} effet(s) à encaisser</p>
            </div>
        </a>

        <div class="flex items-center gap-4 rounded-[4px] border border-gray-100 bg-white p-4">
            <span class="inline-flex w-11 h-11 items-center justify-center rounded-[4px] bg-emerald-100 text-emerald-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500">Taux d'encaissement</p>
                <p class="text-[16px] font-bold tabular-nums text-gray-900">{{ $tauxRecouv }} %</p>
                <div class="mt-1 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-700" style="width: {{ min(100, $tauxRecouv) }}%"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- ══ Graphes ═════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Flux 6 mois --}}
        <div class="lg:col-span-2 rounded-[4px] bg-white border border-gray-300 p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Flux de trésorerie</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Encaissements vs Décaissements — 6 derniers mois</p>
                </div>
                <a href="{{ route('tresorerie.previsions.index') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium whitespace-nowrap">
                    Voir prévisions →
                </a>
            </div>
            <div id="chart-flux" style="min-height:240px;"></div>
        </div>

        {{-- Répartition --}}
        <div class="rounded-[4px] bg-white border border-gray-300 p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Répartition des soldes</h3>
            @if($accounts->isEmpty())
                <p class="text-sm text-gray-400 text-center py-8">Aucun compte configuré.</p>
            @else
                <div id="chart-comptes" style="min-height:170px;"></div>
                <div class="mt-4 space-y-2">
                    @foreach($accounts as $account)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0
                                @if($loop->index === 0) bg-emerald-600
                                @elseif($loop->index === 1) bg-sky-400
                                @elseif($loop->index === 2) bg-teal-400
                                @else bg-gray-300 @endif"></span>
                            <span class="text-gray-700 truncate">{{ $account->name }}</span>
                        </div>
                        <span class="font-semibold tabular-nums {{ $account->current_balance < 0 ? 'text-red-600' : 'text-gray-900' }} flex-shrink-0 ml-2">
                            {{ $fmt($account->current_balance) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ══ Échéances + derniers encaissements ══════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Échéances 7j --}}
        <div class="rounded-[4px] bg-white border border-gray-300 p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Échéances dans les 7 jours</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Factures arrivant à terme</p>
                </div>
                <a href="{{ route('tresorerie.echeancier-clients') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Voir tout →</a>
            </div>
            @if($echeancesProches->isEmpty())
                <div class="text-center py-10">
                    <span class="inline-flex w-12 h-12 items-center justify-center rounded-[4px] bg-emerald-100 text-emerald-600 mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <p class="text-sm text-gray-500">Aucune échéance dans les 7 prochains jours.</p>
                </div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($echeancesProches as $ech)
                    @php
                        $due = \Carbon\Carbon::parse($ech->due_at);
                        $daysLeft = (int) now()->startOfDay()->diffInDays($due->startOfDay(), false);
                        $isLate = $daysLeft < 0; $isToday = $daysLeft === 0;
                    @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="inline-flex w-9 h-9 items-center justify-center rounded-[4px] text-xs font-bold flex-shrink-0
                            @if($isLate) bg-red-100 text-red-700
                            @elseif($isToday) bg-amber-100 text-amber-700
                            @elseif($daysLeft <= 3) bg-orange-100 text-orange-700
                            @else bg-gray-100 text-gray-500 @endif">
                            @if($isLate) -{{ abs($daysLeft) }}j @elseif($isToday) 0j @else J{{ $daysLeft }} @endif
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $ech->client_name }}</p>
                            <p class="text-xs text-gray-400">{{ $ech->number }} · {{ $due->format('d/m/Y') }}</p>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900 flex-shrink-0">{{ $fmt($ech->remaining_amount) }}</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Derniers encaissements --}}
        <div class="rounded-[4px] bg-white border border-gray-300 p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Derniers encaissements</h3>
                    <p class="text-xs text-gray-500 mt-0.5">5 opérations les plus récentes</p>
                </div>
                <a href="{{ route('tresorerie.encaissements.index') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Voir tout →</a>
            </div>
            @if($derniersEncaissements->isEmpty())
                <div class="text-center py-10">
                    <span class="inline-flex w-12 h-12 items-center justify-center rounded-[4px] bg-gray-100 text-gray-400 mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    </span>
                    <p class="text-sm text-gray-500">Aucun encaissement enregistré.</p>
                    <a href="{{ route('tresorerie.encaissements.create') }}" class="inline-flex items-center gap-1.5 mt-3 rounded-[4px] bg-emerald-700 text-white text-xs font-semibold px-3 py-2 hover:bg-emerald-800 transition">
                        Enregistrer un encaissement
                    </a>
                </div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($derniersEncaissements as $enc)
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="inline-flex w-9 h-9 rounded-[4px] bg-emerald-100 text-emerald-600 items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $enc->client_name }}</p>
                            <p class="text-xs text-gray-400">{{ $enc->payment_method_name }} · {{ \Carbon\Carbon::parse($enc->payment_date)->format('d/m/Y') }}</p>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-emerald-600 flex-shrink-0">+{{ $fmt($enc->amount) }}</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</div><!-- /space-y-3 -->
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const mois = @json($chartMois);
    const enc  = @json($chartEnc);
    const dec  = @json($chartDec);
    const flux = enc.map((v, i) => v - dec[i]);

    const fmtAxis = v => v >= 1000000 ? (v / 1000000).toFixed(1) + ' M'
        : v >= 1000 ? Math.round(v / 1000) + ' k' : v;
    const fmtFull = v => new Intl.NumberFormat('fr-FR').format(v) + ' FCFA';

    if (window.ApexCharts && document.getElementById('chart-flux')) {
        new ApexCharts(document.getElementById('chart-flux'), {
            chart: { height: 240, toolbar: { show: false }, fontFamily: 'inherit', animations: { speed: 500 } },
            series: [
                { name: 'Encaissements', type: 'column', data: enc },
                { name: 'Décaissements', type: 'column', data: dec },
                { name: 'Flux net',      type: 'line',   data: flux },
            ],
            colors: ['#10b981', '#f59e0b', '#047857'],
            stroke: { width: [0, 0, 3], curve: 'smooth' },
            fill: {
                type: ['solid', 'solid', 'solid'],
            },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 5 } },
            markers: { size: [0, 0, 4], colors: ['#047857'], strokeColors: '#fff', strokeWidth: 2 },
            dataLabels: { enabled: false },
            xaxis: { categories: mois, labels: { style: { fontSize: '11px', colors: '#9ca3af' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: fmtAxis, style: { fontSize: '10px', colors: '#9ca3af' } } },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', markers: { radius: 4 } },
            tooltip: { shared: true, intersect: false, y: { formatter: fmtFull } },
            grid: { borderColor: '#f3f4f6', strokeDashArray: 4, padding: { left: 4, right: 4 } },
        }).render();
    }

    const labels = @json($compteLabels);
    const soldes = @json($compteSoldes);

    if (window.ApexCharts && document.getElementById('chart-comptes') && labels.length > 0) {
        new ApexCharts(document.getElementById('chart-comptes'), {
            chart: { type: 'donut', height: 170, toolbar: { show: false }, fontFamily: 'inherit' },
            series: soldes,
            labels: labels,
            colors: ['#047857', '#38bdf8', '#2dd4bf', '#d1d5db', '#a78bfa', '#fbbf24'],
            legend: { show: false },
            stroke: { width: 2, colors: ['#fff'] },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true,
                total: { show: true, label: 'Total', fontSize: '11px', color: '#9ca3af',
                    formatter: w => fmtAxis(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) },
                value: { fontSize: '15px', fontWeight: 700, color: '#111827', formatter: v => fmtAxis(v) } } } } },
            tooltip: { y: { formatter: fmtFull } },
            dataLabels: { enabled: false },
        }).render();
    }
});
</script>
@endpush
