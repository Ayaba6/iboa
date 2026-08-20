{{--
    Top bar (header).
    Contient : breadcrumb, recherche globale, date, sélecteur entreprise,
    notifications (cloche), profil utilisateur, bouton hamburger mobile.
--}}
        {{-- [UX] sticky top-0 z-20 : header fixe au scroll. backdrop-filter:blur conserve l'effet glassmorphism. --}}
        {{-- [SAGE X3] fond sombre #11181F --}}
        <header class="sticky top-0 z-20 flex-shrink-0 topbar-sage" x-data="{ ms: false }"
                style="background:#11181F;
                       border-bottom:1px solid rgba(255,255,255,.08);
                       box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <div class="h-12 flex items-center gap-3 px-4 lg:px-6">
            {{-- Mobile menu --}}
            <button @click="$store.sidebar.open = !$store.sidebar.open"
                    class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Breadcrumb --}}
            <nav class="hidden sm:flex items-center gap-1 text-[12px] text-gray-400 min-w-0">
                @yield('breadcrumb')
            </nav>

            {{-- ── Command Palette trigger (Ctrl+K) ─────────────────────────── --}}
            {{-- [SAGE X3] recherche globale — saisie directe + dropdown groupé --}}
            <div class="hidden md:flex flex-1 justify-center px-4">
                <div x-data="sageSearch()" data-search-url="{{ route('search') }}"
                     @click.outside="close()"
                     @keydown.ctrl.k.window.prevent="focusBar()"
                     @keydown.meta.k.window.prevent="focusBar()"
                     class="relative w-full max-w-xl">
                    <div class="flex items-center gap-2.5 h-8 px-3 bg-[#f4f6f5] rounded-lg transition-all"
                         :class="open ? 'bg-white ring-2 ring-emerald-500/60 shadow-lg' : 'hover:bg-white hover:shadow-sm'">
                        <svg x-show="!loading" class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <svg x-show="loading" class="w-4 h-4 flex-shrink-0 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24" style="display:none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <input type="text" x-ref="sageInput" x-model="q"
                               @focus="onFocus()"
                               @input.debounce.250ms="search()"
                               @keydown.arrow-down.prevent="moveDown()"
                               @keydown.arrow-up.prevent="moveUp()"
                               @keydown.enter.prevent="selectActive()"
                               @keydown.escape="close(); $refs.sageInput.blur()"
                               placeholder="Rechercher une fonction ou un document…"
                               autocomplete="off" spellcheck="false"
                               class="flex-1 bg-transparent border-none outline-none focus:ring-0 text-[12px] text-gray-800 placeholder-gray-400 p-0">
                        <kbd class="flex-shrink-0 hidden lg:inline-flex items-center text-[11px] font-semibold bg-white border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 shadow-sm">Ctrl K</kbd>
                    </div>

                    {{-- Dropdown résultats façon SAGE X3 --}}
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute top-full left-0 right-0 mt-1.5 bg-white rounded-[4px] shadow-2xl border border-gray-200 z-50 overflow-hidden"
                         style="max-height:70vh">
                        <div class="overflow-y-auto" style="max-height:calc(70vh - 40px)">

                            {{-- Fonctions (navigation) --}}
                            <template x-if="matchedFunctions.length > 0">
                                <div class="py-1.5">
                                    <p class="px-4 py-1 text-[11px] font-bold text-gray-400 uppercase tracking-widest">Fonctions</p>
                                    <template x-for="fn in matchedFunctions" :key="fn.url">
                                        <a :href="fn.url" @click="close()" @mouseenter="setActive(fn)"
                                           :class="isActive(fn) ? 'bg-[#eef5f0]' : 'hover:bg-gray-50'"
                                           class="flex items-center gap-3 px-4 py-2 cursor-pointer transition-colors">
                                            <svg class="w-4 h-4 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                            </svg>
                                            <span class="flex-1 text-[13px] font-medium text-gray-800 truncate" x-text="fn.label"></span>
                                            <span class="flex-shrink-0 text-[11px] text-gray-400" x-text="fn.section"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Données groupées par catégorie --}}
                            <template x-for="(items, type) in grouped" :key="type">
                                <div class="py-1.5 border-t border-gray-100">
                                    <p class="px-4 py-1 text-[11px] font-bold text-emerald-800 uppercase tracking-widest" x-text="type"></p>
                                    <template x-for="item in items" :key="item.url + item.label">
                                        <a :href="item.url" @click="close()" @mouseenter="setActive(item)"
                                           :class="isActive(item) ? 'bg-[#eef5f0]' : 'hover:bg-gray-50'"
                                           class="flex items-center gap-3 px-4 py-2 cursor-pointer transition-colors">
                                            <span class="flex-shrink-0 w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-bold"
                                                  :style="colorStyle(item.color)">
                                                <span x-text="type.charAt(0).toUpperCase()"></span>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-[13px] font-semibold text-gray-900 truncate" x-text="item.label"></p>
                                                <p class="text-[11px] text-gray-500 truncate" x-text="item.sublabel || ''"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- Aucun résultat --}}
                            <template x-if="q.length >= 2 && !loading && results.length === 0 && matchedFunctions.length === 0">
                                <div class="py-8 text-center text-[13px] text-gray-400">
                                    Aucun résultat pour « <span class="font-semibold text-gray-600" x-text="q"></span> »
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center gap-4 px-4 py-2 border-t border-gray-100 bg-gray-50/70 text-[11px] text-gray-400">
                            <span><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">↑↓</kbd> Naviguer</span>
                            <span><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">↵</kbd> Ouvrir</span>
                            <span><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">Échap</kbd> Fermer</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right side --}}
            <div class="flex items-center gap-2">
                {{-- Mobile search toggle --}}
                <button @click="ms = !ms"
                        class="md:hidden flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                        :class="ms ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                </button>
                {{-- Dark mode toggle --}}
                <button @click="$store.darkMode.toggle()"
                        :title="$store.darkMode.dark ? 'Mode clair' : 'Mode sombre'"
                        class="flex items-center justify-center w-7 h-7 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                    <svg x-show="!$store.darkMode.dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="$store.darkMode.dark" class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.592-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.592z"/>
                    </svg>
                </button>

                {{-- Date --}}
                <span class="hidden xl:block text-[11px] text-gray-300 bg-white/10 px-2.5 py-1 rounded-full border border-white/10">
                    {{ now()->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                </span>

                {{-- Notification Bell --}}
                <div class="relative" x-data="{ open: false, unread: 0, items: [] }"
                     x-init="fetch('{{ route('notifications.recent') }}').then(r=>r.ok?r.json():null).then(d=>{ if(d){ unread=d.unread; items=d.items; } }).catch(()=>{})"
                     @click.outside="open = false">
                    <button @click="open = !open; if(open) fetch('{{ route('notifications.recent') }}').then(r=>r.ok?r.json():null).then(d=>{ if(d){ unread=d.unread; items=d.items; } }).catch(()=>{})"
                            class="relative w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span x-show="unread > 0" x-text="unread > 9 ? '9+' : unread"
                              class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 bg-red-500 text-white text-[11px] font-bold rounded-full flex items-center justify-center px-0.5 leading-none"></span>
                    </button>
                    {{-- Dropdown --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <span class="text-[13px] font-bold text-gray-900">Validations</span>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('validations.index') }}" class="text-[12px] text-indigo-600 hover:text-indigo-800 font-medium">Mes validations</a>
                                <a href="{{ route('notifications.index') }}" class="text-[12px] text-gray-400 hover:text-gray-600 font-medium" title="Historique complet des notifications">Historique</a>
                            </div>
                        </div>
                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                            <template x-if="items.length === 0">
                                <div class="py-8 text-center text-[13px] text-gray-400">Aucune validation en attente</div>
                            </template>
                            <template x-for="n in items" :key="n.id">
                                {{-- [CDC §Workflow] Lien DIRECT vers le document à valider — la
                                     notification reste tant que le document est en attente. --}}
                                <a :href="n.url || '#'"
                                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors"
                                   :class="n.read ? '' : 'bg-indigo-50/40'">
                                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-[12px]"
                                         :class="{
                                             'bg-red-100 text-red-600': n.color==='red',
                                             'bg-orange-100 text-orange-600': n.color==='orange',
                                             'bg-green-100 text-green-600': n.color==='green',
                                             'bg-indigo-100 text-indigo-600': n.color==='indigo',
                                         }">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[12px] font-semibold text-gray-900 truncate" x-text="n.title"></p>
                                        <p class="text-[12px] text-gray-500 mt-0.5 line-clamp-2 leading-snug" x-text="n.message"></p>
                                        <p class="text-[11px] text-gray-400 mt-1" x-text="n.created_at"></p>
                                    </div>
                                    <span x-show="!n.read" class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0 mt-1.5"></span>
                                </a>
                            </template>
                        </div>
                        {{-- [CDC §Workflow] Pas de « marquer comme lu » : une validation en
                             attente reste affichée tant que le document n'est pas traité. --}}
                        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50">
                            <a href="{{ route('validations.index') }}"
                               class="block text-[12px] text-indigo-600 hover:text-indigo-800 font-medium w-full text-center transition-colors">
                                Voir toutes mes validations →
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Quick add --}}
                @can('invoices.create')
                <a href="{{ route('ventes.factures.create') }}"
                   {{-- [Charte X3] Gradient violet proscrit → émeraude plein --}}
                   class="flex items-center gap-1.5 px-2.5 py-1.5 text-white text-[12px] font-semibold rounded-[4px] bg-emerald-700 hover:bg-emerald-800 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden sm:inline">Facture</span>
                </a>
                @endcan

                {{-- Profil utilisateur (dropdown) --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open"
                            class="flex items-center gap-2 rounded-xl px-2 py-1 hover:bg-white/10 transition-colors focus:outline-none">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                             style="background:linear-gradient(135deg,#6366F1,#8B5CF6);box-shadow:0 0 0 2px rgba(99,102,241,.2),0 2px 8px rgba(99,102,241,.3);">
                            <span class="text-[11px] font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                        </div>
                        <div class="hidden lg:block text-left">
                            <p class="text-[11px] font-semibold text-white leading-tight">{{ auth()->user()->name }}</p>
                            <p class="text-[11px] text-gray-400 leading-tight">{{ auth()->user()->getRoleNames()->first() ?? 'Utilisateur' }}</p>
                        </div>
                        <svg class="hidden lg:block w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden"
                         style="display:none;">
                        {{-- Entête profil --}}
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                     style="background:linear-gradient(135deg,#6366F1,#8B5CF6);">
                                    <span class="text-[13px] font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                                    <p class="text-[12px] text-gray-400 truncate">{{ auth()->user()->getRoleNames()->first() ?? 'Utilisateur' }}</p>
                                </div>
                            </div>
                        </div>
                        {{-- Mon profil --}}
                        <div class="p-2">
                            <a href="{{ route('profile.edit') }}"
                               class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[13px] font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Mon profil
                            </a>
                            <a href="{{ route('profile.edit') }}#password"
                               class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[13px] font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Changer mot de passe
                            </a>
                        </div>
                        {{-- Déconnexion --}}
                        <div class="p-2 border-t border-gray-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[13px] font-medium text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Déconnexion
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /h-14 row --}}

        {{-- Barre de recherche mobile (rétractable) --}}
        <div x-show="ms"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="md:hidden border-t border-white/10 px-4 py-3"
             style="display:none;"
             x-data="{
                 q: '', results: [], open: false, loading: false,
                 async search() {
                     if (this.q.length < 2) { this.results = []; this.open = false; return; }
                     this.loading = true;
                     try {
                         const r = await fetch('{{ route('search') }}?q=' + encodeURIComponent(this.q), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                         if (!r.ok) { return; }
                         const d = await r.json();
                         this.results = d.results ?? [];
                         this.open = this.results.length > 0;
                     } catch (_) {}
                     finally { this.loading = false; }
                 }
             }"
             @click.outside="open = false">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg x-show="!loading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <svg x-show="loading" class="w-4 h-4 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <input type="search" x-model="q" @input.debounce.300ms="search()"
                       placeholder="Rechercher…"
                       x-ref="mobileInput"
                       x-effect="ms && $nextTick(() => $refs.mobileInput?.focus())"
                       class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[13px] focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:bg-white transition-all"
                       style="font-size:16px;">
                {{-- Résultats --}}
                <div x-show="open" class="absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden max-h-72 overflow-y-auto">
                    <template x-for="item in results" :key="item.url + item.label">
                        <a :href="item.url" @click="ms = false; open = false"
                           class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                            <span class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-[12px] font-bold"
                                  :class="{
                                      'bg-blue-100 text-blue-700': item.color==='blue',
                                      'bg-orange-100 text-orange-700': item.color==='orange',
                                      'bg-emerald-100 text-emerald-700': item.color==='emerald',
                                      'bg-indigo-100 text-indigo-700': item.color==='indigo',
                                      'bg-violet-100 text-violet-700': item.color==='violet',
                                      'bg-red-100 text-red-700': item.color==='red',
                                  }"
                                  x-text="item.type.charAt(0)"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-medium text-gray-900 truncate" x-text="item.label"></p>
                                <p class="text-[12px] text-gray-500 truncate" x-text="item.sublabel || item.type"></p>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
        </header>

{{-- [SAGE X3] Fonctions accessibles depuis la recherche globale (filtrées côté client) --}}
<script type="application/json" id="sage-search-functions">
{!! json_encode(array_values(array_filter([
    ['label' => 'Tableau de bord',            'section' => 'Accueil',       'url' => route('dashboard')],
    Auth::user()?->can('quotes.view')            ? ['label' => 'Devis',                  'section' => 'Ventes',      'url' => route('ventes.devis.index')] : null,
    Auth::user()?->can('quotes.create')          ? ['label' => 'Nouveau devis',          'section' => 'Ventes',      'url' => route('ventes.devis.create')] : null,
    Auth::user()?->can('orders.view')            ? ['label' => 'Commandes clients',      'section' => 'Ventes',      'url' => route('ventes.commandes.index')] : null,
    Auth::user()?->can('invoices.view')          ? ['label' => 'Factures de vente',      'section' => 'Ventes',      'url' => route('ventes.factures.index')] : null,
    Auth::user()?->can('invoices.create')        ? ['label' => 'Nouvelle facture',       'section' => 'Ventes',      'url' => route('ventes.factures.create')] : null,
    Auth::user()?->can('delivery_notes.view')    ? ['label' => 'Bons de livraison',      'section' => 'Ventes',      'url' => route('ventes.bons-livraison.index')] : null,
    Auth::user()?->can('credit_notes.view')      ? ['label' => 'Avoirs clients',         'section' => 'Ventes',      'url' => route('ventes.avoirs.index')] : null,
    Auth::user()?->can('purchase_requests.view') ? ['label' => "Demandes d'achat",       'section' => 'Achats',      'url' => route('achats.demandes-achat.index')] : null,
    Auth::user()?->can('purchase_orders.view')   ? ['label' => 'Commandes fournisseurs', 'section' => 'Achats',      'url' => route('achats.commandes.index')] : null,
    Auth::user()?->can('receptions.view')        ? ['label' => 'Réceptions',             'section' => 'Achats',      'url' => route('achats.receptions.index')] : null,
    Auth::user()?->can('supplier_invoices.view') ? ['label' => 'Factures fournisseurs',  'section' => 'Achats',      'url' => route('achats.factures-fournisseurs.index')] : null,
    Auth::user()?->can('clients.view')           ? ['label' => 'Clients',                'section' => 'Gestion',     'url' => route('clients.index')] : null,
    Auth::user()?->can('suppliers.view')         ? ['label' => 'Fournisseurs',           'section' => 'Gestion',     'url' => route('suppliers.index')] : null,
    Auth::user()?->can('products.view')          ? ['label' => 'Articles',               'section' => 'Gestion',     'url' => route('products.index')] : null,
    Auth::user()?->can('stocks.view')            ? ['label' => 'Niveaux de stock',       'section' => 'Stocks',      'url' => route('stocks.index')] : null,
    Auth::user()?->can('production_orders.view') ? ['label' => 'Ordres de fabrication',  'section' => 'Production',  'url' => route('production.orders.index')] : null,
    Auth::user()?->can('settings.manage')        ? ['label' => 'Paramétrage société',    'section' => 'Paramétrage', 'url' => route('company.edit')] : null,
    Auth::user()?->can('settings.manage')        ? ['label' => 'Taux de TVA',            'section' => 'Paramétrage', 'url' => route('settings.tax-rates.index')] : null,
    Auth::user()?->can('settings.manage')        ? ['label' => 'Unités de mesure',       'section' => 'Paramétrage', 'url' => route('units.index')] : null,
    Auth::user()?->can('settings.manage')        ? ['label' => 'Exercices fiscaux',      'section' => 'Paramétrage', 'url' => route('settings.fiscal-years.index')] : null,
    Auth::user()?->can('settings.manage')        ? ['label' => 'Numérotation des documents', 'section' => 'Paramétrage', 'url' => route('settings.sequences.index')] : null,
    Auth::user()?->can('roles.manage')           ? ['label' => 'Rôles & permissions',    'section' => 'Paramétrage', 'url' => route('roles.index')] : null,
])), JSON_UNESCAPED_UNICODE) !!}
</script>
