{{--
    Dashboard — Hero (header avec salutation, horloge live et actions rapides).
--}}
{{-- ══════════════════════════════════════════════════════════════
     HERO — Header avec salutation, horloge live, actions rapides
══════════════════════════════════════════════════════════════ --}}
<div class="fade-up relative overflow-hidden rounded-[4px] text-white hero-bg"
     x-data="dashboardHero()">

    {{-- Grid overlay --}}
    <div class="pointer-events-none absolute inset-0 opacity-30"
         style="background-image:radial-gradient(circle at 1px 1px,rgba(255,255,255,.08) 1px,transparent 0);background-size:24px 24px;"></div>

    {{-- Glow orbs --}}
    <div class="pointer-events-none absolute -top-24 -right-24 w-96 h-96 rounded-full"
         style="background:radial-gradient(circle,rgba(129,140,248,.35) 0%,transparent 65%);filter:blur(50px)"></div>
    <div class="pointer-events-none absolute -bottom-20 left-1/3 w-72 h-72 rounded-full"
         style="background:radial-gradient(circle,rgba(16,185,129,.2) 0%,transparent 65%);filter:blur(50px)"></div>

    <div class="relative px-6 py-6 lg:px-8 lg:py-7">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">

            {{-- LEFT — Greeting & date --}}
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-3">
                    {{-- Avatar --}}
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white text-sm font-bold shadow-lg ring-2 ring-white/20">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-xs font-medium" style="color:rgba(255,255,255,.5)">
                            {{ Auth::user()->name }}
                        </p>
                        <p class="text-xs" style="color:rgba(255,255,255,.35)">
                            {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                        </p>
                    </div>
                    {{-- Live clock --}}
                    <div class="ml-auto lg:hidden flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold tabular-nums"
                         style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7)">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                        <span x-text="clock"></span>
                    </div>
                </div>

                <div class="flex items-end gap-6">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-white leading-tight">
                            Tableau de bord
                        </h1>
                        <p class="mt-1 text-sm" style="color:rgba(255,255,255,.5)">
                            CA annuel :
                            <span class="font-bold text-white">{{ number_format($revenueAnnee, 0, ',', ' ') }} FCFA</span>
                            @if($facturesEnRetard > 0)
                            &nbsp;·&nbsp;
                            <span style="color:#fca5a5;font-weight:600">
                                <svg class="inline w-3.5 h-3.5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $facturesEnRetard }} facture(s) en retard
                            </span>
                            @endif
                        </p>
                    </div>

                    {{-- Desktop clock --}}
                    <div class="hidden lg:flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold tabular-nums mb-1"
                         style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.1)">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span x-text="clock"></span>
                    </div>
                </div>
            </div>

            {{-- RIGHT — Quick actions --}}
            <div class="flex flex-wrap items-center gap-2">
                @can('invoices.create')
                <a href="{{ route('ventes.factures.create') }}"
                   class="inline-flex items-center gap-2 bg-white text-emerald-800 text-xs font-bold px-4 py-2.5 rounded-xl transition-all hover:bg-emerald-50 shadow-lg whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle facture
                </a>
                @endcan
                @can('quotes.create')
                <a href="{{ route('ventes.devis.create') }}"
                   class="inline-flex items-center gap-2 text-xs font-medium px-4 py-2.5 rounded-xl transition-all whitespace-nowrap"
                   style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Devis
                </a>
                @endcan
                @can('clients.create')
                <a href="{{ route('clients.create') }}"
                   class="inline-flex items-center gap-2 text-xs font-medium px-4 py-2.5 rounded-xl transition-all whitespace-nowrap"
                   style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Nouveau client
                </a>
                @endcan
                <a href="{{ route('reports.index') }}"
                   class="inline-flex items-center gap-2 text-xs font-medium px-4 py-2.5 rounded-xl transition-all whitespace-nowrap"
                   style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Rapports
                </a>
            </div>
        </div>
    </div>
</div>

