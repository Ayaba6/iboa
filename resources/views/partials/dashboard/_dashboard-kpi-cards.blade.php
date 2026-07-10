{{--
    Dashboard — 5 cartes KPI avec sparklines :
    CA aujourd'hui · CA ce mois · Encaissements · Trésorerie · Alertes.
--}}
{{-- ══════════════════════════════════════════════════════════════
     KPI CARDS — 5 métriques clés avec sparklines
══════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">

    {{-- CA Aujourd'hui --}}
    <div x-data="kpiCounter({{ $revenueJour }})" x-init="init()"
         class="kpi-card fade-up bg-white rounded-[4px] border border-gray-300 overflow-hidden kpi-accent-sky" style="animation-delay:.04s">
        <div class="p-5">
            <div class="flex items-start justify-between gap-2 mb-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 leading-tight">CA aujourd'hui</p>
                <div class="w-9 h-9 rounded-xl bg-sky-500 shadow-md shadow-sky-200 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4.5 h-4.5 text-white" style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <p id="kpi-rev-jour" data-raw="{{ $revenueJour }}"
               class="text-2xl 2xl:text-3xl font-black text-gray-900 tabular-nums leading-none tracking-tight whitespace-nowrap" x-text="formatted()">0</p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-400">FCFA</p>
                @if($trendJour['value'] !== null)
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold
                            {{ $trendJour['direction'] === 'up' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($trendJour['direction'] === 'up')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"/>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/>
                        @endif
                    </svg>
                    {{ $trendJour['direction'] === 'up' ? '+' : '-' }}{{ $trendJour['value'] }}%
                </div>
                @else
                <span class="text-xs text-gray-300 font-medium">vs hier</span>
                @endif
            </div>
        </div>
        <div id="spark-jour" class="-mt-2"></div>
    </div>

    {{-- CA Ce Mois --}}
    <div x-data="kpiCounter({{ $revenueMois }})" x-init="init()"
         class="kpi-card fade-up bg-white rounded-[4px] border border-gray-300 overflow-hidden kpi-accent-indigo" style="animation-delay:.08s">
        <div class="p-5">
            <div class="flex items-start justify-between gap-2 mb-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 leading-tight">CA ce mois</p>
                <div class="w-9 h-9 rounded-xl bg-emerald-700 shadow-md shadow-indigo-200 flex items-center justify-center flex-shrink-0">
                    <svg style="width:18px;height:18px" class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
            <p id="kpi-rev-mois" data-raw="{{ $revenueMois }}"
               class="text-2xl 2xl:text-3xl font-black text-gray-900 tabular-nums leading-none tracking-tight whitespace-nowrap" x-text="formatted()">0</p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-400">FCFA · {{ $nbFacturesMois }} facture(s)</p>
                @if($trendRevenue['value'] !== null)
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0
                            {{ $trendRevenue['direction'] === 'up' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($trendRevenue['direction'] === 'up')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"/>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/>
                        @endif
                    </svg>
                    {{ $trendRevenue['direction'] === 'up' ? '+' : '-' }}{{ $trendRevenue['value'] }}%
                </div>
                @else
                <span class="text-xs text-gray-300 font-medium flex-shrink-0">vs mois préc.</span>
                @endif
            </div>
        </div>
        <div id="spark-ca" class="-mt-2"></div>
    </div>

    {{-- Encaissements --}}
    <div x-data="kpiCounter({{ $encaissementsMois }})" x-init="init()"
         class="kpi-card fade-up bg-white rounded-[4px] border border-gray-300 overflow-hidden kpi-accent-emerald" style="animation-delay:.12s">
        <div class="p-5">
            <div class="flex items-start justify-between gap-2 mb-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 leading-tight">Encaissements</p>
                <div class="w-9 h-9 rounded-xl bg-emerald-500 shadow-md shadow-emerald-200 flex items-center justify-center flex-shrink-0">
                    <svg style="width:18px;height:18px" class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <p id="kpi-enc-mois" data-raw="{{ $encaissementsMois }}"
               class="text-2xl 2xl:text-3xl font-black text-gray-900 tabular-nums leading-none tracking-tight whitespace-nowrap" x-text="formatted()">0</p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-400">FCFA ce mois</p>
                @if($trendEncaissements['value'] !== null)
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0
                            {{ $trendEncaissements['direction'] === 'up' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if($trendEncaissements['direction'] === 'up')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7"/>
                        @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/>
                        @endif
                    </svg>
                    {{ $trendEncaissements['direction'] === 'up' ? '+' : '-' }}{{ $trendEncaissements['value'] }}%
                </div>
                @else
                <span class="text-xs text-gray-300 font-medium flex-shrink-0">vs mois préc.</span>
                @endif
            </div>
        </div>
        <div id="spark-enc" class="-mt-2"></div>
    </div>

    {{-- Trésorerie --}}
    <div x-data="kpiCounter({{ $soldeTresorerie }})" x-init="init()"
         class="kpi-card fade-up bg-white rounded-[4px] border border-gray-300 overflow-hidden kpi-accent-violet" style="animation-delay:.16s">
        <div class="p-5">
            <div class="flex items-start justify-between gap-2 mb-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 leading-tight">Trésorerie</p>
                <div class="w-9 h-9 rounded-xl bg-violet-600 shadow-md shadow-violet-200 flex items-center justify-center flex-shrink-0">
                    <svg style="width:18px;height:18px" class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
            </div>
            <p id="kpi-solde-tresorerie" data-raw="{{ $soldeTresorerie }}"
               class="text-2xl 2xl:text-3xl font-black text-gray-900 tabular-nums leading-none tracking-tight whitespace-nowrap" x-text="formatted()">0</p>
            <p class="mt-2 text-xs font-medium text-gray-400">FCFA solde total</p>

            @if($cashAccounts->isNotEmpty())
            <div class="mt-3 pt-3 border-t border-gray-50 space-y-1.5">
                @foreach($cashAccounts->take(2) as $ca)
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 truncate max-w-[55%]">{{ $ca->name }}</span>
                    <span class="text-xs font-bold text-gray-700 tabular-nums">{{ number_format($ca->current_balance, 0, ',', ' ') }} F</span>
                </div>
                @endforeach
                @if($cashAccounts->count() > 2)
                <a href="{{ route('tresorerie.caisses.index') }}" class="block text-xs text-emerald-500 hover:text-emerald-700 font-semibold mt-1">
                    +{{ $cashAccounts->count() - 2 }} autre(s) →
                </a>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Alertes --}}
    <div class="kpi-card fade-up bg-white rounded-[4px] border border-gray-300 p-5 kpi-accent-rose" style="animation-delay:.2s">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Alertes</p>

        {{-- Factures retard --}}
        <a href="{{ route('ventes.factures.index', $facturesEnRetard > 0 ? ['overdue' => 1] : []) }}"
           class="flex items-center gap-3 rounded-xl p-3 mb-2.5 transition-colors
                  {{ $facturesEnRetard > 0 ? 'bg-rose-50 hover:bg-rose-100' : 'bg-gray-50 hover:bg-gray-100' }}">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 rounded-lg {{ $facturesEnRetard > 0 ? 'bg-rose-100' : 'bg-gray-100' }} flex items-center justify-center">
                    <svg class="w-[18px] h-[18px] {{ $facturesEnRetard > 0 ? 'text-rose-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                @if($facturesEnRetard > 0)
                <span class="pulse-dot absolute -top-1 -right-1 w-3 h-3 rounded-full bg-rose-500 border-2 border-white"></span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold {{ $facturesEnRetard > 0 ? 'text-rose-700' : 'text-gray-400' }} truncate">
                    @if($facturesEnRetard > 0)
                        <span id="kpi-factures-retard" data-raw="{{ $facturesEnRetard }}">{{ $facturesEnRetard }}</span> fact. en retard
                    @else
                        Aucun retard
                    @endif
                </p>
                @if($facturesEnRetard > 0)
                <p class="text-xs text-rose-400 tabular-nums">
                    <span id="kpi-montant-retard" data-raw="{{ $montantEnRetard }}">{{ number_format($montantEnRetard, 0, ',', ' ') }}</span> FCFA
                </p>
                @endif
            </div>
        </a>

        {{-- Ruptures stock --}}
        <a href="{{ route('stocks.index') }}"
           class="flex items-center gap-3 rounded-xl p-3 transition-colors
                  {{ $ruptureStock > 0 ? 'bg-amber-50 hover:bg-amber-100' : 'bg-gray-50 hover:bg-gray-100' }}">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 rounded-lg {{ $ruptureStock > 0 ? 'bg-amber-100' : 'bg-gray-100' }} flex items-center justify-center">
                    <svg class="w-[18px] h-[18px] {{ $ruptureStock > 0 ? 'text-amber-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                @if($ruptureStock > 0)
                <span class="pulse-dot absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber-400 border-2 border-white"></span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold {{ $ruptureStock > 0 ? 'text-amber-700' : 'text-gray-400' }} truncate">
                    @if($ruptureStock > 0)
                        <span id="kpi-rupture-stock" data-raw="{{ $ruptureStock }}">{{ $ruptureStock }}</span> rupture(s)
                    @else
                        Stock OK
                    @endif
                </p>
                <p class="text-xs {{ $ruptureStock > 0 ? 'text-amber-400' : 'text-gray-300' }}">
                    {{ $ruptureStock > 0 ? 'Réapprovisionnement requis' : 'Aucune rupture' }}
                </p>
            </div>
        </a>
    </div>

</div>

