{{--
    Combobox produit — recherche instantanée, positionnement intelligent (flip),
    surbrillance, recherche multi-critères (réf, désignation, code-barres, catégorie).
    ─────────────────────────────────────────────────────────────────────────────
    Usage dans une <td> à l'intérieur d'un <template x-for="(item, index) in items"> :

        @include('ventes.partials._product_combobox', [
            'accentColor' => 'indigo',   // indigo | blue
            'formName'    => 'invoice',  // préfixe unique des IDs DOM
        ])

    Prérequis dans le composant Alpine parent (item) :
        product_id, _ps_open(false), _ps_search(''), _ps_rect(null)
    + variables : products[], onProductChange(index), formatFcfa(n)
    + helpers globaux (app.js) : window.psFilter, window.psHighlight
--}}
@php
    $accent   = $accentColor ?? 'indigo';
    $pfx      = $formName ?? 'f';
@endphp

<td class="px-2 py-1">

    {{-- Valeur soumise --}}
    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">

    {{-- Champ déclencheur (reste toujours visible) --}}
    <button type="button"
            @click="
                if (item._ps_open) { item._ps_open = false; return; }
                items.forEach((it, i) => { if (i !== index) it._ps_open = false; });
                /* [POSITION] Calcul du placement : sous le champ par défaut,
                   au-dessus uniquement si l'espace en bas est insuffisant ET qu'il
                   y a plus de place au-dessus. Le dropdown ne recouvre jamais le champ. */
                const r = $event.currentTarget.getBoundingClientRect();
                const GAP = 6, MIN_H = 200, DESIRED = 360;
                const below = window.innerHeight - r.bottom - GAP;
                const above = r.top - GAP;
                const up = below < MIN_H && above > below;
                item._ps_rect = {
                    up,
                    left:  r.left,
                    width: Math.max(r.width, 300),
                    maxH:  Math.max(160, Math.min(DESIRED, up ? above : below)),
                    top:    up ? null : Math.round(r.bottom + GAP),
                    bottom: up ? Math.round(window.innerHeight - r.top + GAP) : null,
                };
                item._ps_open = true;
                item._ps_search = '';
                $nextTick(() => { const el = document.getElementById('ps_{{ $pfx }}_' + item._key); if (el) el.focus(); });
            "
            class="w-full h-8 flex items-center justify-between gap-1 border border-gray-300 rounded-[3px] px-2 py-0 text-[13px] bg-white hover:border-{{ $accent }}-400 focus:outline-none focus:ring-1 focus:ring-{{ $accent }}-500 min-w-[124px] transition-colors"
            :class="item._ps_open ? 'border-{{ $accent }}-500 ring-1 ring-{{ $accent }}-500' : ''">
        <span class="truncate text-left"
              :class="item.product_id ? 'text-gray-900' : 'text-gray-400'"
              x-text="item.product_id
                  ? (products.find(p => String(p.id) === String(item.product_id))?.name || '— Produit —')
                  : '— Produit —'"></span>
        <svg class="w-3 h-3 text-gray-400 flex-shrink-0 transition-transform duration-150"
             :class="item._ps_open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- [TELEPORT] Le dropdown est téléporté dans <body> pour échapper à tout
         ancêtre transformé (animations fade-in-up) qui casserait position:fixed. --}}
    <template x-teleport="body">
    <div>

    {{-- Overlay (clic extérieur = fermeture) --}}
    <div x-show="item._ps_open" x-cloak
         @click="item._ps_open = false"
         class="fixed inset-0 z-40" style="background:transparent;"></div>

    {{-- Dropdown flottant — position:fixed, ferme au scroll de la page pour rester aligné --}}
    <div x-show="item._ps_open" x-cloak
         @scroll.window="item._ps_open = false"
         @keydown.escape.window="item._ps_open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
         class="fixed z-50 flex flex-col bg-white rounded-[4px] shadow-lg ring-1 ring-gray-900/5 border border-gray-100 overflow-hidden"
         :style="item._ps_rect
            ? 'left:' + item._ps_rect.left + 'px; width:' + item._ps_rect.width + 'px; max-height:' + item._ps_rect.maxH + 'px;'
              + (item._ps_rect.up ? 'bottom:' + item._ps_rect.bottom + 'px;' : 'top:' + item._ps_rect.top + 'px;')
            : ''">

        {{-- Barre de recherche (toujours visible, ne scrolle pas) --}}
        <div class="p-2.5 border-b border-gray-100 bg-gray-50/80 flex-shrink-0">
            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-[4px] px-2.5 py-1.5 shadow-sm
                        focus-within:border-{{ $accent }}-400 focus-within:ring-1 focus-within:ring-{{ $accent }}-300 transition">
                <svg class="w-3.5 h-3.5 text-{{ $accent }}-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input :id="'ps_{{ $pfx }}_' + item._key"
                       x-model="item._ps_search"
                       type="text"
                       placeholder="Réf, désignation, code-barres, catégorie..."
                       class="w-full bg-transparent text-sm focus:outline-none text-gray-700 placeholder-gray-400"
                       @keydown.escape.stop="item._ps_open = false"
                       @keydown.enter.prevent="">
                <button x-show="item._ps_search" type="button" @click.stop="item._ps_search = ''"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Liste (scroll interne) --}}
        <ul class="flex-1 overflow-y-auto divide-y divide-gray-50 overscroll-contain">

            {{-- Réinitialiser --}}
            <li @click="item.product_id = ''; item._ps_open = false; item._ps_search = ''; onProductChange(index)"
                class="flex items-center gap-2 px-3 py-2.5 text-sm cursor-pointer hover:bg-gray-50 transition-colors"
                :class="!item.product_id ? 'bg-{{ $accent }}-50 text-{{ $accent }}-600' : 'text-gray-500'">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="italic">— Aucun produit —</span>
            </li>

            {{-- Résultats filtrés (réf / nom / code-barres / catégorie) --}}
            <template x-for="p in window.psFilter(products, item._ps_search).slice(0, 60)" :key="p.id">
                <li @click="item.product_id = String(p.id); item._ps_open = false; item._ps_search = ''; onProductChange(index)"
                    class="px-3 py-2.5 cursor-pointer hover:bg-{{ $accent }}-50/70 transition-colors"
                    :class="String(item.product_id) === String(p.id) ? 'bg-{{ $accent }}-50' : ''">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            {{-- Nom avec surbrillance --}}
                            <p class="text-sm font-medium text-gray-900 truncate leading-tight"
                               x-html="window.psHighlight(p.name, item._ps_search)"></p>
                            {{-- Méta : réf • catégorie • Stock N • prix --}}
                            <div class="flex items-center flex-wrap gap-x-1.5 gap-y-0.5 text-xs mt-0.5 leading-tight">
                                <span class="text-gray-400" x-show="p.reference"
                                      x-html="window.psHighlight(p.reference, item._ps_search)"></span>
                                <span class="text-gray-300" x-show="p.reference && p.family && p.family.name">•</span>
                                <span class="text-gray-400" x-show="p.family && p.family.name"
                                      x-html="window.psHighlight(p.family ? p.family.name : '', item._ps_search)"></span>
                                <span class="text-gray-300" x-show="(p.reference || (p.family && p.family.name)) && p.is_stockable">•</span>
                                <span x-show="p.is_stockable" class="font-medium"
                                      :class="(parseFloat(p.stock_qty) || 0) > 0 ? 'text-emerald-600' : 'text-rose-500'"
                                      x-text="'Stock ' + (parseFloat(p.stock_qty) || 0)"></span>
                                <span class="text-gray-300">•</span>
                                <span class="text-gray-500" x-text="formatFcfa(p.sale_price)"></span>
                            </div>
                        </div>
                        <svg x-show="String(item.product_id) === String(p.id)"
                             class="w-4 h-4 text-{{ $accent }}-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </li>
            </template>

            {{-- Aucun résultat --}}
            <li x-show="item._ps_search && window.psFilter(products, item._ps_search).length === 0"
                class="px-4 py-6 text-sm text-gray-400 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Aucun produit pour «&nbsp;<span x-text="item._ps_search"></span>&nbsp;»
            </li>
        </ul>

        {{-- Pied : compteur (ne scrolle pas) --}}
        <div class="px-3 py-1.5 border-t border-gray-100 bg-gray-50/80 flex items-center justify-between flex-shrink-0"
             x-show="item._ps_search">
            <p class="text-xs text-gray-400">
                <span x-text="window.psFilter(products, item._ps_search).length"></span> résultat(s)
            </p>
            <button type="button" @click.stop="item._ps_search = ''"
                    class="text-xs text-{{ $accent }}-500 hover:opacity-70">Effacer</button>
        </div>
    </div>

    </div>
    </template>
</td>
