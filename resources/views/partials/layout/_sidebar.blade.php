{{--
    Sidebar gauche (navigation principale).
    Inclut : logo, navigation 9 modules (Dashboard / Ventes / Achats / Gestion /
    Stocks / Trésorerie / Comptabilité / Reports / Paramètres) et menus contextuels.

    L'overlay mobile et le conteneur flex parent sont dans layouts/erp.blade.php.
    Ce partial doit produire UNIQUEMENT l'élément <aside> et son contenu.

    État partagé via $store.sidebar (Alpine store défini dans app.js) :
      - open      : visible sur mobile (drawer)
      - collapsed : rétrécie en mode icône-seul sur desktop
--}}
    {{-- [UX] Sidebar fixe au scroll en desktop :
         - mobile : `fixed` (drawer translaté hors écran)
         - desktop (lg) : `sticky top-0` + `h-screen` → reste visible au scroll, sans casser le flex layout. --}}
    <aside :class="[
               $store.sidebar.open ? 'translate-x-0' : '-translate-x-full',
               $store.sidebar.collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64',
               'fixed inset-y-0 left-0 z-30 w-64 text-white flex flex-col overflow-hidden transition-all duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0'
           ]"
           style="background:linear-gradient(180deg,#070B15 0%,#0B1120 50%,#0E1628 100%);
                  box-shadow:4px 0 32px rgba(0,0,0,.5),inset -1px 0 0 rgba(255,255,255,.05);
                  height:100vh;">

        {{-- Logo --}}
        <div class="flex items-center justify-between px-2 flex-shrink-0"
             style="height:68px; border-bottom:1px solid rgba(255,255,255,.06);">
            <a href="{{ route('dashboard') }}" class="flex items-center min-w-0 overflow-hidden">
                {{-- Logo complet (sidebar déployée) --}}
                <img x-show="!$store.sidebar.collapsed"
                     src="{{ asset('images/logo_cropped.png') }}"
                     alt="A3 ERP"
                     x-transition:enter="transition-opacity duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     style="height:52px; width:auto; display:block; max-width:200px;">
                {{-- Icône seule (sidebar réduite) --}}
                <img x-show="$store.sidebar.collapsed"
                     src="{{ asset('images/logo_cropped.png') }}"
                     alt="A3 ERP"
                     style="height:40px; width:auto; display:none;">
            </a>
            <button @click="$store.sidebar.collapsed = !$store.sidebar.collapsed"
                    class="hidden lg:flex items-center justify-center w-7 h-7 rounded-md text-indigo-400 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-4 h-4 transition-transform duration-300" :class="$store.sidebar.collapsed ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        @php
        $ag = match(true) {
            request()->routeIs('ventes.*')                                                                                            => 'ventes',
request()->routeIs('achats.*')                                                                                            => 'achats',
            request()->routeIs('clients*','suppliers*','products*','brands*','product-families*','promotions*','product-price-tiers*') => 'gestion',
            request()->routeIs('stocks.*','stocks.warehouses*')                                                                        => 'stocks',
            request()->routeIs('reports.*')                                                                                           => 'analytique',
            request()->routeIs('tresorerie.*')                                                                                        => 'tresorerie',
            request()->routeIs('comptabilite.*')                                                                                      => 'comptabilite',
            request()->routeIs('rh.*','rh.dashboard')                                                                                  => 'rh',
            request()->routeIs('crm.*')                                                                                               => 'crm',
            request()->routeIs('users*','roles*','audit*','company*','units*')                                                        => 'parametres',
            default => null,
        };
        @endphp

        <nav class="flex-1 min-h-0 overflow-y-auto py-3 px-2 space-y-0.5 sidebar-nav"
             style="max-height: calc(100vh - 68px);"
             x-data="{ open: @js($ag) }">

            {{-- ── Dashboard ────────────────────────────── --}}
            <a href="{{ route('dashboard') }}"
               class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                      {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white shadow-sm' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                @if(request()->routeIs('dashboard'))
                <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-indigo-300 rounded-r-full"></span>
                @endif
                <svg class="w-[18px] h-[18px] flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="!$store.sidebar.collapsed" x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="truncate">
                    Tableau de bord
                </span>
                <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                    Tableau de bord
                    <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                </div>
            </a>

            @can('reports.view')
            <a href="{{ route('direction.dashboard') }}"
               class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                      {{ request()->routeIs('direction.dashboard') ? 'bg-white/15 text-white shadow-sm' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                @if(request()->routeIs('direction.dashboard'))<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-indigo-300 rounded-r-full"></span>@endif
                <svg class="w-[18px] h-[18px] flex-shrink-0 {{ request()->routeIs('direction.dashboard') ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span x-show="!$store.sidebar.collapsed" class="truncate">Direction</span>
                <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">Direction<div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div></div>
            </a>

            {{-- Chaîne de Valeur Intégrée (§3 CDC) --}}
            <a href="{{ route('chaine-valeur') }}"
               class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                      {{ request()->routeIs('chaine-valeur') ? 'bg-white/15 text-white shadow-sm' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                @if(request()->routeIs('chaine-valeur'))<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-indigo-300 rounded-r-full"></span>@endif
                <svg class="w-[18px] h-[18px] flex-shrink-0 {{ request()->routeIs('chaine-valeur') ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
                <span x-show="!$store.sidebar.collapsed" class="truncate">Chaîne de Valeur</span>
                <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">Chaîne de Valeur<div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div></div>
            </a>
            @endcan

            @php
            // Macro pour générer un groupe accordéon
            // Utilisé inline via @include ou directement en Blade
            $group = function(string $id, string $label, string $iconPath, bool $hasActive, string $activeColor = 'indigo') use (&$group) {
                return compact('id','label','iconPath','hasActive','activeColor');
            };
            @endphp

            {{-- ══════════════════════════════════════
                 Macro groupe : bouton + sous-items
                 Appelé avec @include('layouts._nav-group', [...])
                 Ici on l'écrit inline pour chaque module
            ══════════════════════════════════════ --}}

            {{-- ── VENTES ───────────────────────────────── --}}
            @canany(['quotes.view','orders.view','invoices.view','deliveries.view','credit_notes.view','sales.view_all'])
            @php $gId = 'ventes'; $gActive = request()->routeIs('ventes.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-orange-300 rounded-r-full"></span>@endif
                    <div class="w-[18px] h-[18px] flex-shrink-0 flex items-center justify-center">
                        <svg class="w-full h-full {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Ventes</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Ventes <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('invoices.view')     ? [route('ventes.dashboard'),           '📊 Tableau de bord','ventes.dashboard']         : null,
                            auth()->user()->can('quotes.view')       ? [route('ventes.devis.index'),         'Devis',            'ventes.devis*']            : null,
                            auth()->user()->can('orders.view')       ? [route('ventes.commandes.index'),     'Commandes',        'ventes.commandes*']         : null,
                            auth()->user()->can('deliveries.view')   ? [route('ventes.bons-livraison.index'),'Bons de livraison','ventes.bons-livraison*']    : null,
                            auth()->user()->can('invoices.view')     ? [route('ventes.factures.index'),      'Factures',         'ventes.factures*']          : null,
                            auth()->user()->can('credit_notes.view') ? [route('ventes.avoirs.index'),        'Avoirs',           'ventes.avoirs*']            : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-orange-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @endcanany

            {{-- ── ACHATS ───────────────────────────────── --}}
            @canany(['purchase_requests.view', 'purchase_orders.view', 'receptions.view', 'supplier_invoices.view', 'supplier_returns.view'])
            @php $gId = 'achats'; $gActive = request()->routeIs('achats.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-amber-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Achats</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Achats <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('purchase_requests.view') ? [route('achats.demandes-achat.index'),          'Demandes d\'achat', 'achats.demandes-achat*']           : null,
                            auth()->user()->can('purchase_orders.view')   ? [route('achats.commandes.index'),               'Commandes fourn.',  'achats.commandes*']                : null,
                            auth()->user()->can('receptions.view')        ? [route('achats.receptions.index'),              'Réceptions',        'achats.receptions*']               : null,
                            auth()->user()->can('supplier_invoices.view') ? [route('achats.factures-fournisseurs.index'),   'Factures fourn.',   'achats.factures-fournisseurs*']    : null,
                            auth()->user()->can('supplier_returns.view')  ? [route('achats.retours-fournisseurs.index'),    'Retours fourn.',    'achats.retours-fournisseurs*']     : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-amber-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ── GESTION ──────────────────────────────── --}}
            @canany(['clients.view','suppliers.view','products.view'])
            @php $gId = 'gestion'; $gActive = request()->routeIs('clients*','suppliers*','products*','brands*','product-families*','promotions*','product-price-tiers*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-blue-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Gestion</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Gestion <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('clients.view')   ? [route('clients.index'),       'Clients',             'clients.index']       : null,
                            auth()->user()->can('clients.view')   ? [route('clients.releve'),       'Relevé client',       'clients.releve']       : null,
                            auth()->user()->can('clients.view')   ? [route('clients.grand-livre'),  'Grand livre clients', 'clients.grand-livre']  : null,
                            auth()->user()->can('suppliers.view') ? [route('suppliers.index'),      'Fournisseurs',        'suppliers*']           : null,
                            auth()->user()->can('products.view')  ? [route('products.index'),         'Articles',             'products*']             : null,
                            auth()->user()->can('products.view')  ? [route('product-families.index'), 'Familles / Catégories','product-families*']     : null,
                            auth()->user()->can('products.view')  ? [route('brands.index'),           'Marques',              'brands*']               : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-blue-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @endcanany

            {{-- ── STOCKS ───────────────────────────────── --}}
            @canany(['stocks.view', 'stocks.adjust', 'inventory.view'])
            @php $gId = 'stocks'; $gActive = request()->routeIs('stocks.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-emerald-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Stocks</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Stocks <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('stocks.view')     ? [route('stocks.dashboard'),          'Tableau de bord',   'stocks.dashboard']                       : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.index'),              'Niveaux de stock',  'stocks.index']                           : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.movements'),          'Mouvements',        'stocks.movements,stocks.movement*']       : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.transfers.index'),    'Transferts',        'stocks.transfers*']                       : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.valuation'),          'Valorisation',      'stocks.valuation']                       : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.dashboard.restock'),  'Alertes réappro',   'stocks.dashboard.restock']               : null,
                            auth()->user()->can('inventory.view')  ? [route('stocks.inventaires.index'),  'Inventaires',       'stocks.inventaires*']                    : null,
                            auth()->user()->can('stocks.adjust')   ? [route('stocks.seuils'),             'Seuils min / max',  'stocks.seuils']                          : null,
                            auth()->user()->can('stocks.view')     ? [route('stocks.lots'),               'Lots & Traçabilité','stocks.lots']                            : null,
                            auth()->user()->can('stocks.adjust')   ? [route('stocks.warehouses.index'),   'Entrepôts',         'stocks.warehouses*']                     : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs(...explode(',', $match)); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-emerald-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ── PRODUCTION ───────────────────────────── --}}
            @can('production.view')
            @php $gId = 'production'; $gActive = request()->routeIs('production.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-orange-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Production</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Production <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            [route('production.dashboard'),      'Tableau de bord',       'production.dashboard'],
                            [route('production.orders.index'),   'Ordres de fabrication', 'production.orders*'],
                            [route('production.coils.index'),    'Bobines (matière)', 'production.coils*'],
                            [route('production.bom.index'),      'Nomenclatures',     'production.bom*'],
                            [route('production.routings.index'), 'Gammes',            'production.routings*'],
                            [route('production.machines.index'), 'Machines',          'production.machines*'],
                            [route('production.work-centers.index'), 'Centres de travail', 'production.work-centers*'],
                            [route('production.maintenance.index'), 'Maintenance',       'production.maintenance*'],
                            [route('production.lines.index'),    'Lignes',            'production.lines*'],
                            [route('production.planning'),       'Plan de charge',    'production.planning'],
                            [route('production.cutting'),        'Optimisation découpe', 'production.cutting'],
                            [route('qualite.inspections.index'), 'Contrôles qualité', 'qualite.inspections*'],
                            [route('qualite.non-conformities.index'), 'Non-conformités', 'qualite.non-conformities*'],
                            auth()->user()->can('quality.view') ? [route('qualite.certificats.index'), 'Certificats qualité', 'qualite.certificats*'] : null,
                            [route('production.mrp'),            'Réappro (MRP)',     'production.mrp'],
                            auth()->user()->can('production.cost.view') ? [route('production.treasury'), 'Prévision trésorerie', 'production.treasury'] : null,
                            auth()->user()->can('production.report.view') ? [route('production.reports'), 'Rapports', 'production.reports'] : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-orange-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endcan

            {{-- ── ANALYTIQUE ───────────────────────────── --}}
            @canany(['reports.view', 'reports.export'])
            @php $gId = 'analytique'; $gActive = request()->routeIs('reports.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-violet-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Analytique</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Analytique <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            [route('reports.index'),              'Rapports & BI',          'reports.index'],
                            [route('reports.ca'),                 'Chiffre d\'affaires',    'reports.ca'],
                            [route('reports.journal-ventes'),     'Journal des ventes',     'reports.journal-ventes'],
                            [route('reports.liste-factures'),     'Liste des factures',     'reports.liste-factures'],
                            [route('reports.liste-devis'),        'Liste des devis',        'reports.liste-devis'],
                            [route('reports.liste-commandes'),    'Liste des commandes',    'reports.liste-commandes'],
                            [route('reports.impayes'),            'Impayés clients',        'reports.impayes'],
                            [route('reports.etat-tva'),           'État de TVA',            'reports.etat-tva'],
                            [route('reports.etat-stocks'),        'État des stocks',        'reports.etat-stocks'],
                            [route('reports.margins'),            'Marges produits',        'reports.margins'],
                            [route('reports.sales-performance'),  'Performance commerciale','reports.sales-performance'],
                            // §12 CDC — Comptabilité analytique
                            auth()->user()->can('analytic.view') ? [route('analytique.centres-couts.index'), 'Centres de coûts', 'analytique.*'] : null,
                            auth()->user()->can('analytic.view') ? [route('analytique.rapport'), 'Rentabilité analytique', 'analytique.rapport'] : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-violet-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ── TRÉSORERIE ───────────────────────────── --}}
            @canany(['payments.view', 'cash_accounts.view', 'treasury.write', 'treasury.validate'])
            @php $gId = 'tresorerie'; $gActive = request()->routeIs('tresorerie.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-cyan-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Trésorerie</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Trésorerie <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('payments.view')      ? [route('tresorerie.dashboard'),             'Tableau de bord',    'tresorerie.dashboard']         : null,

                            // ── Banques & Caisses ──
                            auth()->user()->can('cash_accounts.view') ? [null, 'Banques & Caisses', null] : null,
                            auth()->user()->can('cash_accounts.view') ? [route('tresorerie.caisses.index'),       'Comptes (banques/caisses)', 'tresorerie.caisses*']  : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.operations.index'),    'Entrées / Sorties caisse', 'tresorerie.operations*'] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.clotures.index'),      'Clôtures de caisse', 'tresorerie.clotures*']       : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.virements.index'),     'Virements internes', 'tresorerie.virements*']       : null,

                            // ── Opérations ──
                            auth()->user()->can('payments.view')      ? [null, 'Opérations', null] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.encaissements.index'), 'Encaissements',      'tresorerie.encaissements*']    : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.decaissements.index'), 'Décaissements',      'tresorerie.decaissements*']  : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.demandes.index'),      'Demandes de paiement','tresorerie.demandes*']       : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.remises.index'),       'Remises en banque',  'tresorerie.remises*']        : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.effets.index'),        'Effets de commerce', 'tresorerie.effets*']         : null,

                            // ── Échéances & Recouvrement ──
                            auth()->user()->can('payments.view')      ? [null, 'Échéances & Recouvrement', null] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.echeancier-clients'),  'Échéancier clients', 'tresorerie.echeancier-clients'] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.echeancier-fournisseurs'), 'Échéancier fournisseurs', 'tresorerie.echeancier-fournisseurs'] : null,
                            auth()->user()->can('clients.view')       ? [route('relances.index'),                 'Relances',           'relances*']                  : null,
                            auth()->user()->can('clients.view')       ? [route('promesses.index'),                'Promesses de paiement', 'promesses*']              : null,
                            auth()->user()->can('clients.view')       ? [route('contentieux.index'),             'Contentieux',        'contentieux*']               : null,
                            auth()->user()->can('clients.view')       ? [route('clients.balance-agee'),           'Balance âgée tiers', 'clients.balance-agee']       : null,
                            auth()->user()->can('reports.view')       ? [route('reports.impayes'),                'Impayés clients',    'reports.impayes']            : null,

                            // ── Prévisions & Budget ──
                            auth()->user()->can('payments.view')      ? [null, 'Prévisions & Budget', null] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.previsions.index'),    'Prévisions',         'tresorerie.previsions*']     : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.simulations.index'),   'Simulations',        'tresorerie.simulations*']    : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.budgets.index'),       'Budgets trésorerie', 'tresorerie.budgets*']        : null,

                            // ── Rapprochement & Lettrage ──
                            auth()->user()->can('accounting.view')    ? [null, 'Rapprochement & Lettrage', null] : null,
                            auth()->user()->can('accounting.view')    ? [route('comptabilite.rapprochement.index'), 'Rapprochement bancaire', 'comptabilite.rapprochement*'] : null,
                            auth()->user()->can('accounting.view')    ? [route('comptabilite.lettrage.index'),    'Lettrage',           'comptabilite.lettrage*']     : null,

                            // ── Rapports & Audit ──
                            auth()->user()->can('payments.view')      ? [null, 'Rapports & Audit', null] : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.etat'),                'État de trésorerie', 'tresorerie.etat']            : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.journal'),             'Journal de trésorerie','tresorerie.journal']        : null,
                            auth()->user()->can('payments.view')      ? [route('tresorerie.alertes'),             'Alertes',            'tresorerie.alertes']         : null,
                            auth()->user()->can('audit.view')         ? [route('audit.index'),                    'Journal des actions', 'audit.index']               : null,
                        ]) as [$href, $label, $match])
                        @if($href === null)
                            <div class="px-3 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-400/60 select-none">{{ $label }}</div>
                        @else
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-cyan-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ── COMPTABILITÉ ─────────────────────────── --}}
            @canany(['accounting.view', 'accounting.write'])
            @php $gId = 'comptabilite'; $gActive = request()->routeIs('comptabilite.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-violet-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Comptabilité</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Comptabilité <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach(array_filter([
                            auth()->user()->can('accounting.view') ? [route('comptabilite.plan-comptable.index'), 'Plan comptable',      'comptabilite.plan-comptable*']    : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.journal-types.index'),   'Codes journaux',      'comptabilite.journal-types*']     : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.journaux.index'),        'Journaux',            'comptabilite.journaux*']          : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.grand-livre'),           'Grand livre',         'comptabilite.grand-livre']        : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.balance'),               'Balance générale',    'comptabilite.balance']            : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.situation-comptable'),   'Situation comptable', 'comptabilite.situation-comptable'] : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.brouillard'),            'Brouillard',          'comptabilite.brouillard']          : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.livre-journal'),         'Livre journal',       'comptabilite.livre-journal']       : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.balance-auxiliaire'),    'Balance auxiliaire',  'comptabilite.balance-auxiliaire']  : null,
                            auth()->user()->can('clients.view')   ? [route('clients.balance-agee'),               'Balance âgée tiers',  'clients.balance-agee']             : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.lettrage.index'),        'Lettrage',            'comptabilite.lettrage*']          : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.rapprochement.index'),   'Rapprochement banc.', 'comptabilite.rapprochement*']     : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.tva.index'),             'Déclarations TVA',    'comptabilite.tva*']               : null,
                            auth()->user()->can('accounting.validate') ? [route('comptabilite.periods.index'),    'Verrouillage périodes','comptabilite.periods*']           : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.immobilisations.index'), 'Immobilisations',     'comptabilite.immobilisations*']    : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.bilan'),                 'Bilan',               'comptabilite.bilan']              : null,
                            auth()->user()->can('accounting.view') ? [route('comptabilite.compte-de-resultat'),    'Compte de résultat',  'comptabilite.compte-de-resultat']  : null,
                        ]) as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-violet-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endcanany

            {{-- ── RH / PAIE ────────────────────────────── --}}
            @canany(['rh.view','rh.employees.view','rh.payroll.view','rh.payroll.manage','rh.settings','rh.portail'])
            @php $gId = 'rh'; $gActive = request()->routeIs('rh.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-rose-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">RH / Paie</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        RH / Paie <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        {{-- ── Gestion des salariés ── --}}
                        <div class="px-3 pt-2 pb-0.5">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/25">Salariés</span>
                        </div>
                        @foreach([
                            [route('rh.dashboard'),         'Tableau de bord',     'rh.dashboard'],
                            [route('rh.portail.dashboard'), 'Mon Espace RH',       'rh.portail*'],
                            [route('rh.employes.index'),    'Employés',            'rh.employes*'],
                            [route('rh.contrats.index'),    'Contrats',            'rh.contrats*'],
                            [route('rh.departments.index'), 'Départements',        'rh.departments*'],
                            [route('rh.presences.index'),   'Présences & absences','rh.presences*'],
                            [route('rh.conges.index'),      'Congés',              'rh.conges*'],
                        ] as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-rose-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach

                        {{-- ── Traitement de la paie ── --}}
                        <div class="px-3 pt-3 pb-0.5">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/25">Paie</span>
                        </div>
                        @foreach([
                            [route('rh.variables.index'),   'Variables mensuelles','rh.variables*'],
                            [route('rh.paie.create'),       'Préparation de la paie','rh.paie.create'],
                            [route('rh.paie.index'),        'Bulletins de paie',  'rh.paie*'],
                            [route('rh.paie.simulateur.index'), '🧮 Simulateur salaire','rh.paie.simulateur*'],
                            [route('rh.avances.index'),     'Avances salaire',    'rh.avances*'],
                            [route('rh.prets.index'),       'Prêts salariés',     'rh.prets*'],
                        ] as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-rose-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach

                        {{-- ── Configuration ── --}}
                        <div class="px-3 pt-3 pb-0.5">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/25">Configuration</span>
                        </div>
                        @foreach([
                            [route('rh.types-primes.index'),       'Types de primes',         'rh.types-primes*'],
                            [route('rh.rubriques.index'),          'Rubriques de paie',      'rh.rubriques*'],
                            [route('rh.plans.index'),              'Plans de paie',           'rh.plans*'],
                            [route('rh.profils.index'),            'Profils de paie',         'rh.profils*'],
                            [route('rh.constantes.index'),         'Constantes',              'rh.constantes*'],
                            [route('rh.baremes.index'),            'Barèmes fiscaux',         'rh.baremes*'],
                            [route('rh.cotisations.index'),        'Cotisations sociales',    'rh.cotisations*'],
                            [route('rh.numerotation.index'),       'Numérotation bulletins',  'rh.numerotation*'],
                            [route('rh.modeles-bulletins.index'),  'Modèles de bulletins',    'rh.modeles-bulletins*'],
                            [route('rh.periodes.index'),           'Périodes de paie',        'rh.periodes*'],
                            [route('rh.parametrage.edit'),         'Paramétrage général',     'rh.parametrage*'],
                        ] as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-rose-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach

                        {{-- ── États & comptabilité ── --}}
                        <div class="px-3 pt-3 pb-0.5">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/25">Éditions</span>
                        </div>
                        @foreach([
                            [route('rh.etats.index'),           'États de paie',        'rh.etats*'],
                            [route('rh.comptabilisation.index'),'Comptabilisation paie', 'rh.comptabilisation*'],
                        ] as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-rose-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @endcanany

            {{-- ── CRM ──────────────────────────────────── --}}
            @canany(['clients.view','sales.view_all','quotes.view'])
            @php $gId = 'crm'; $gActive = request()->routeIs('crm.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-cyan-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">CRM</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        CRM <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @foreach([
                            [route('crm.dashboard'),         'Tableau de bord',  'crm.dashboard'],
                            [route('crm.contacts.index'),    'Contacts',         'crm.contacts*'],
                            [route('crm.opportunities.index'),'Pipeline',        'crm.opportunities*'],
                            [route('crm.activities.index'),  'Activités',        'crm.activities*'],
                        ] as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-cyan-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @endcanany

            {{-- ── INTÉGRATIONS ─────────────────────────── --}}
            @can('integrations.view')
            @php $gId = 'integrations'; $gActive = request()->routeIs('integrations.*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-orange-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Intégrations</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Intégrations <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @php
                            $intLinks = [
                                [route('integrations.dashboard'), 'Tableau de bord',    'integrations.dashboard'],
                                [route('integrations.index'),     'Toutes les intégrations', 'integrations.index'],
                            ];
                            if (auth()->user()->can('integrations.manage')) {
                                $intLinks[] = [route('integrations.create'), 'Nouvelle intégration', 'integrations.create'];
                            }
                        @endphp
                        @foreach($intLinks as [$href, $label, $match])
                        @php $sub = request()->routeIs($match); @endphp
                        <a href="{{ $href }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-orange-300' : 'bg-white/20' }}"></span>
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @endcan

            {{-- ── PARAMÈTRES ───────────────────────────── --}}
            @canany(['settings.manage','users.manage','roles.manage','company.edit'])
            @php $gId = 'parametres'; $gActive = request()->routeIs('users*','roles*','audit*','company*','units*','settings.*','payment-terms*','sequences*'); @endphp
            <div class="space-y-0.5">
                <button type="button" @click="open = open === '{{ $gId }}' ? null : '{{ $gId }}'"
                        class="group relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150
                               {{ $gActive ? 'bg-white/15 text-white' : 'text-indigo-200/80 hover:bg-white/8 hover:text-white' }}">
                    @if($gActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-gray-300 rounded-r-full"></span>@endif
                    <svg class="w-[18px] h-[18px] flex-shrink-0 {{ $gActive ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="!$store.sidebar.collapsed" class="flex-1 text-left truncate">Paramètres</span>
                    <svg x-show="!$store.sidebar.collapsed" class="w-4 h-4 text-indigo-400 transition-transform duration-200 flex-shrink-0"
                         :class="open === '{{ $gId }}' ? 'rotate-180 text-white' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <div x-show="$store.sidebar.collapsed" class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 shadow-xl">
                        Paramètres <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
                    </div>
                </button>
                <div x-show="open === '{{ $gId }}' && !$store.sidebar.collapsed"
                     x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                    <div class="ml-4 pl-3 border-l border-white/10 space-y-0.5 py-1">
                        @can('users.manage')
                        @php $sub = request()->routeIs('users*'); @endphp
                        <a href="{{ route('users.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Utilisateurs
                        </a>
                        @endcan
                        @can('roles.manage')
                        @php $sub = request()->routeIs('roles*'); @endphp
                        <a href="{{ route('roles.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Rôles & Permissions
                        </a>
                        @endcan
                        @can('audit.view')
                        @php $sub = request()->routeIs('audit*'); @endphp
                        <a href="{{ route('audit.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Journal d'activité
                        </a>
                        @endcan
                        @can('settings.manage')
                        @php $sub = request()->routeIs('company*'); @endphp
                        <a href="{{ route('company.edit') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Société
                        </a>
                        @endcan
                        @php $sub = request()->routeIs('units*'); @endphp
                        <a href="{{ route('units.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Unités de mesure
                        </a>
                        @can('settings.manage')
                        @php $sub = request()->routeIs('settings.fiscal-years*'); @endphp
                        <a href="{{ route('settings.fiscal-years.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Exercices fiscaux
                        </a>
                        @php $sub = request()->routeIs('settings.currencies*'); @endphp
                        <a href="{{ route('settings.currencies.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Devises
                        </a>
                        @php $sub = request()->routeIs('settings.tax-rates*'); @endphp
                        <a href="{{ route('settings.tax-rates.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Taux de TVA
                        </a>
                        @php $sub = request()->routeIs('settings.payment-terms*'); @endphp
                        <a href="{{ route('settings.payment-terms.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Conditions de paiement
                        </a>
                        @php $sub = request()->routeIs('settings.sequences*'); @endphp
                        <a href="{{ route('settings.sequences.index') }}"
                           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-100
                                  {{ $sub ? 'bg-white/15 text-white' : 'text-indigo-300/70 hover:text-white hover:bg-white/8' }}">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $sub ? 'bg-gray-300' : 'bg-white/20' }}"></span>
                            Numérotation
                        </a>
                        @endcan
                    </div>
                </div>
            </div>

            @endcanany {{-- fin Paramètres --}}

            <div class="h-4"></div>

        </nav>

        {{-- Bottom: Command Palette shortcut --}}
        <div x-show="!$store.sidebar.collapsed"
             class="flex-shrink-0 px-3 pb-3"
             style="border-top:1px solid rgba(255,255,255,.06);">
            <button @click="$dispatch('open-palette')"
                    class="w-full mt-2 flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs text-indigo-300/70 hover:text-white hover:bg-white/8 transition-all group">
                <svg class="w-3.5 h-3.5 flex-shrink-0 text-indigo-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <span class="flex-1 text-left">Recherche rapide</span>
                <kbd class="text-[9px] font-bold bg-white/10 border border-white/10 rounded px-1.5 py-0.5">Ctrl K</kbd>
            </button>
        </div>

    </aside>

