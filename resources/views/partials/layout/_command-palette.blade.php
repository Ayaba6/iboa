{{--
    Command Palette — Cmd+K / Ctrl+K
    Recherche globale + accès rapide aux modules + actions fréquentes
    Alpine.js + API /search existante
--}}
<div x-data="commandPalette()"
     x-show="open"
     x-cloak
     data-search-url="{{ route('search') }}"
     @keydown.escape.window="close()"
     @keydown.ctrl.k.window.prevent="toggle()"
     @keydown.meta.k.window.prevent="toggle()"
     @open-palette.window="open_()"
     class="fixed inset-0 z-[9999] flex items-start justify-center pt-[12vh] px-4"
     style="display:none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
         @click="close()"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-xl bg-white rounded-[4px] shadow-2xl overflow-hidden border border-gray-200/80"
         style="max-height:70vh; box-shadow:0 32px 64px -16px rgba(15,23,42,.35),0 0 0 1px rgba(255,255,255,.5);"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {{-- Search input --}}
        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" x-show="!loading" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 animate-spin" x-show="loading" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <input type="text"
                   x-model="query"
                   x-ref="searchInput"
                   @input.debounce.250ms="search()"
                   @keydown.arrow-down.prevent="moveDown()"
                   @keydown.arrow-up.prevent="moveUp()"
                   @keydown.enter.prevent="selectActive()"
                   placeholder="Rechercher ou accéder à…"
                   class="flex-1 text-sm text-gray-900 placeholder-gray-400 bg-transparent border-none outline-none focus:ring-0 font-medium"
                   autocomplete="off"
                   spellcheck="false">
            <kbd class="flex-shrink-0 text-[10px] font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5">Échap</kbd>
        </div>

        {{-- Results --}}
        <div class="overflow-y-auto" style="max-height:calc(70vh - 64px);">

            {{-- Search results --}}
            <template x-if="query.length >= 2 && results.length > 0">
                <div class="py-2">
                    <p class="px-4 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Résultats</p>
                    <template x-for="(item, i) in results" :key="item.url + item.label">
                        <a :href="item.url"
                           @click="close()"
                           @mouseenter="activeIndex = i"
                           :class="activeIndex === i ? 'bg-[#eef5f0]' : 'hover:bg-gray-50'"
                           class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors">
                            <span class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold"
                                  :style="colorStyle(item.color)">
                                <span x-text="item.type.charAt(0).toUpperCase()"></span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="item.label"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="item.sublabel || item.type"></p>
                            </div>
                            <span class="flex-shrink-0 text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full" x-text="item.type"></span>
                        </a>
                    </template>
                </div>
            </template>

            {{-- No results --}}
            <template x-if="query.length >= 2 && !loading && results.length === 0">
                <div class="py-12 text-center">
                    <p class="text-sm text-gray-500">Aucun résultat pour « <span class="font-semibold" x-text="query"></span> »</p>
                </div>
            </template>

            {{-- Default: quick actions + recently visited --}}
            <template x-if="query.length < 2">
                <div>
                    {{-- Quick Actions --}}
                    <div class="py-2">
                        <p class="px-4 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Actions rapides</p>
                        <template x-for="(action, i) in quickActions" :key="action.url">
                            <a :href="action.url"
                               @click="close()"
                               @mouseenter="activeIndex = i"
                               :class="activeIndex === i ? 'bg-[#eef5f0]' : 'hover:bg-gray-50'"
                               class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors">
                                <span class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-sm"
                                      :style="colorStyle(action.color)">
                                    <span x-text="action.icon"></span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate" x-text="action.label"></p>
                                    <p class="text-xs text-gray-500 truncate" x-text="action.section"></p>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </template>
                    </div>

                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center gap-4 px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[10px] text-gray-400">
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">↑↓</kbd> Naviguer</span>
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">↵</kbd> Ouvrir</span>
            <span class="flex items-center gap-1"><kbd class="bg-white border border-gray-200 rounded px-1 py-0.5 font-semibold">Échap</kbd> Fermer</span>
            <span class="ml-auto">Ctrl+K</span>
        </div>
    </div>
</div>

{{--
    Quick actions rendues côté serveur en JSON — lues par app.js lors de l'init du composant.
    Pas de @push('scripts') : le composant commandPalette est enregistré dans app.js
    (Alpine.data('commandPalette', ...)) avant Alpine.start() → zéro risque de timing.
--}}
<script type="application/json" id="cp-quick-actions">
{{ json_encode(array_values(array_filter([
    Auth::user()?->can('invoices.create')       ? ['label'=>'Nouvelle facture',    'section'=>'Ventes',     'icon'=>'🧾', 'color'=>'indigo',  'url'=>route('ventes.factures.create')]       : null,
    Auth::user()?->can('quotes.create')         ? ['label'=>'Nouveau devis',       'section'=>'Ventes',     'icon'=>'📋', 'color'=>'violet',  'url'=>route('ventes.devis.create')]          : null,
    Auth::user()?->can('purchase_orders.create')? ['label'=>'Bon de commande',     'section'=>'Achats',     'icon'=>'🛒', 'color'=>'amber',   'url'=>route('achats.commandes.create')]      : null,
    ['label'=>'Nouveau contact CRM',  'section'=>'CRM',        'icon'=>'👤', 'color'=>'cyan',    'url'=>route('crm.contacts.create')],
    ['label'=>'Nouvelle opportunité', 'section'=>'CRM',        'icon'=>'💡', 'color'=>'emerald', 'url'=>route('crm.opportunities.create')],
    ['label'=>'Tableau de bord',      'section'=>'Navigation', 'icon'=>'🏠', 'color'=>'gray',    'url'=>route('dashboard')],
    ['label'=>'Pipeline CRM',         'section'=>'CRM',        'icon'=>'📊', 'color'=>'blue',    'url'=>route('crm.opportunities.index')],
    ['label'=>'Clients',              'section'=>'Gestion',    'icon'=>'🏢', 'color'=>'emerald', 'url'=>route('clients.index')],
])), JSON_UNESCAPED_UNICODE) }}
</script>
