{{--
  Formulaire catégorie — fiche « Catégories : Création complète » style SAGE X3
  avec barre d'onglets Général / Stock / Achat / Vente / Production / Comptabilité / Documents.
  Variables : $parents, $accounts, $units, $warehouses, $typeFluxOptions,
              $costCenters, $taxRates, $familles, $typeCategorieOptions ; $family en édition.
--}}
@php
    $f = $family ?? null;
    $selectedFlux = (array) old('type_flux', $f?->type_flux ?? []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chkLb = 'text-[12.5px] font-semibold text-gray-700 select-none';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    // caret pour lookups SAGE
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    // Capacité dépôt pré-cochée (pivot en édition, flag dépôt par défaut en création)
    $depotOn = function ($wh, $cap) use ($f) {
        $default = $f
            ? (optional($f->warehouses->firstWhere('id', $wh->id))->pivot->{$cap} ?? false)
            : ($wh->{$cap} ?? false);
        return old("depots.{$wh->id}.{$cap}", $default);
    };
@endphp

<div x-data="{ tab: 'general', stockOn: {{ old('gestion_stock', $f?->gestion_stock) ? 'true' : 'false' }},
        ua: '{{ old('unite_achat_id', $f?->unite_achat_id) }}', uv: '{{ old('unite_vente_id', $f?->unite_vente_id) }}' }"
     class="bg-white border border-gray-300 rounded-[4px]">

    {{-- ═══ Barre d'onglets SAGE ═══════════════════════════════════════════════ --}}
    <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
        @foreach([
            'general'  => 'Général',
            'stock'    => 'Stock',
            'achat'    => 'Achat',
            'vente'    => 'Vente',
            'prod'     => 'Production',
            'compta'   => 'Comptabilité',
            'docs'     => 'Documents',
        ] as $key => $label)
        {{-- [SAGE X3] Onglet = ancre : toutes les sections restent visibles, le clic scrolle --}}
        <button type="button"
                @click="tab = '{{ $key }}'; document.getElementById('sec-{{ $key }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">
            {{ $label }}
        </button>
        @endforeach
    </nav>

    {{-- ══════════════════════════ ONGLET GÉNÉRAL ══════════════════════════════ --}}
    <div id="sec-general" class="p-4 space-y-4 scroll-mt-28">

        {{-- Identification --}}
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Identification</div>
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Site</label>
                        <div class="relative">
                            <select name="site_id" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('site_id', $f?->site_id) == $wh->id)>{{ $wh->code ?: $wh->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Code catégorie <span class="text-red-600">*</span></label>
                        <input type="text" name="code" maxlength="13" value="{{ old('code', $f?->code) }}"
                               class="{{ $inp }} font-mono uppercase @error('code') border-red-400 @enderror"
                               placeholder="MPTBC" oninput="this.value = this.value.toUpperCase()">
                        @error('code')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Intitulé catégorie <span class="text-red-600">*</span></label>
                        <input type="text" name="name" maxlength="100" required value="{{ old('name', $f?->name) }}"
                               class="{{ $inp }} @error('name') border-red-400 @enderror"
                               placeholder="MATIERE PREMIERE TOLE BAC">
                        @error('name')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-5">
                        <label class="{{ $lbl }}">Désignation longue</label>
                        <input type="text" name="designation_longue" maxlength="255" value="{{ old('designation_longue', $f?->designation_longue) }}"
                               class="{{ $inp }}" placeholder="Catégorie des bobines et matières premières…">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-x-4 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Statut</label>
                        <div class="relative">
                            <select name="is_active" class="{{ $lk }}">
                                <option value="1" @selected(old('is_active', $f?->is_active ?? true))>Actif</option>
                                <option value="0" @selected(! old('is_active', $f?->is_active ?? true))>En sommeil</option>
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Catégorie parente</label>
                        <div class="relative">
                            <select name="parent_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($parents as $parent)
                                    <option value="{{ $parent->id }}" @selected(old('parent_id', $f?->parent_id) == $parent->id)>{{ $parent->code ?: $parent->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Famille principale</label>
                        <div class="relative">
                            <select name="famille_principale_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($familles as $fam)
                                    <option value="{{ $fam->id }}" @selected(old('famille_principale_id', $f?->famille_principale_id) == $fam->id)>{{ $fam->code }} — {{ $fam->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Type catégorie</label>
                        <div class="relative">
                            <select name="type_categorie" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($typeCategorieOptions as $val => $label)
                                    <option value="{{ $val }}" @selected(old('type_categorie', $f?->type_categorie) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $lbl }}">Description courte / commentaire</label>
                    <input type="text" name="description" maxlength="500" value="{{ old('description', $f?->description) }}"
                           class="{{ $inp }}" placeholder="Commentaire…">
                </div>
            </div>
        </section>

        {{-- Propriétés de gestion --}}
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Propriétés de gestion</div>
            <div class="p-4 flex flex-wrap gap-x-8 gap-y-2.5">
                @php
                    $props = [
                        ['gestion_stock', 'Géré en stock', false],
                        ['stock_negatif', 'Stock négatif autorisé', true],
                        ['gestion_lot', 'Géré en lot', false],
                        ['lot_obligatoire', 'Lot obligatoire', false],
                        ['suivi_bobine', 'Suivi bobine', false],
                        ['gestion_numero_serie', 'N° de série', false],
                        ['controle_qualite', 'Contrôle qualité', false],
                        ['utilisable_production', 'Utilisable en production', false],
                        ['actif_tous_sites', 'Actif sur tous les sites', false],
                    ];
                @endphp
                @foreach($props as [$name, $label, $needsStock])
                <label class="inline-flex items-center gap-2 cursor-pointer" @if($needsStock) :class="stockOn ? '' : 'opacity-40 pointer-events-none'" @endif>
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" name="{{ $name }}" value="1" class="{{ $chk }}"
                           @if($name === 'gestion_stock') x-model="stockOn" @endif
                           {{ old($name, $f?->{$name} ?? ($name === 'actif_tous_sites')) ? 'checked' : '' }}>
                    <span class="{{ $chkLb }}">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </section>

        {{-- Flux & règles métier --}}
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Flux &amp; règles métier</div>
            <div class="p-4 space-y-4">
                <div>
                    <p class="{{ $lbl }} mb-2">Type de flux</p>
                    <div class="flex flex-wrap gap-x-8 gap-y-2">
                        @foreach($typeFluxOptions as $value => $label)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="type_flux[]" value="{{ $value }}" class="{{ $chk }}"
                                   {{ in_array($value, $selectedFlux) ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1.5">Un article non coché dans un flux ne peut pas être exécuté dans ce flux.</p>
                </div>
                <div class="border-t border-gray-100"></div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $lbl }}">Préfixe code article</label>
                        <input type="text" name="code_prefix" maxlength="10" value="{{ old('code_prefix', $f?->code_prefix) }}"
                               class="{{ $inp }} font-mono uppercase" placeholder="MPTBC" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    @foreach([
                        'numerotation_auto'        => 'Numérotation automatique',
                        'cq_entree'                => 'Contrôle qualité entrée',
                        'cq_sortie'                => 'Contrôle qualité sortie',
                        'prix_plancher_obligatoire'=> 'Prix plancher obligatoire',
                        'autoriser_surcharge'      => 'Autoriser surcharge article',
                    ] as $name => $label)
                    <div>
                        <label class="{{ $lbl }}">{{ $label }}</label>
                        <input type="hidden" name="{{ $name }}" value="0">
                        <div class="relative">
                            <select name="{{ $name }}" class="{{ $lk }}">
                                <option value="1" @selected(old($name, $f?->{$name} ?? ($name === 'numerotation_auto' || $name === 'autoriser_surcharge')))>Oui</option>
                                <option value="0" @selected(! old($name, $f?->{$name} ?? ($name === 'numerotation_auto' || $name === 'autoriser_surcharge')))>Non</option>
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET STOCK ════════════════════════════════ --}}
    <div id="sec-stock" class="p-4 pt-0 space-y-4 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Unités par défaut <span class="font-normal text-emerald-700/70">— héritées par l'article</span></div>
            <div class="p-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-x-4 gap-y-3">
                @foreach(['unite_achat_id' => 'Unité achat (UA)', 'unite_stock_id' => 'Unité stock (US)', 'unite_vente_id' => 'Unité vente (UV)', 'unite_poids_id' => 'Unité poids (UP)'] as $uname => $ulabel)
                <div>
                    <label class="{{ $lbl }}">{{ $ulabel }}</label>
                    <div class="relative">
                        <select name="{{ $uname }}" @if($uname==='unite_achat_id') x-model="ua" @elseif($uname==='unite_vente_id') x-model="uv" @endif class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}" @selected(old($uname, $f?->$uname) == $u->id)>{{ $u->abbreviation }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                @endforeach
                <div><label class="{{ $lbl }}">Coef UA/US</label><input type="number" step="0.000001" min="0" name="coef_ua_us" value="{{ old('coef_ua_us', $f?->coef_ua_us) }}" placeholder="0,571429" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Coef UV/US</label><input type="number" step="0.000001" min="0" name="coef_uv_us" value="{{ old('coef_uv_us', $f?->coef_uv_us) }}" placeholder="1,000000" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Densité</label><input type="number" step="0.001" min="0" name="densite" value="{{ old('densite', $f?->densite) }}" placeholder="7,850" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Poids brut US</label><input type="number" step="0.0001" min="0" name="poids_brut" value="{{ old('poids_brut', $f?->poids_brut) }}" placeholder="1,750" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Poids net US</label><input type="number" step="0.0001" min="0" name="poids_net" value="{{ old('poids_net', $f?->poids_net) }}" placeholder="1,751" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Épaisseur / diamètre (mm)</label><input type="number" step="0.01" min="0" name="epaisseur" value="{{ old('epaisseur', $f?->epaisseur) }}" placeholder="0,250" class="{{ $inpR }}"></div>
                <div><label class="{{ $lbl }}">Métrage (M)</label><input type="number" step="0.01" min="0" name="metrage" value="{{ old('metrage', $f?->metrage) }}" placeholder="1,000" class="{{ $inpR }}"></div>
                <div>
                    <label class="{{ $lbl }}">Site de stockage</label>
                    <div class="relative">
                        <select name="site_stockage_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected(old('site_stockage_id', $f?->site_stockage_id) == $wh->id)>{{ $wh->code ?: $wh->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
            </div>
        </section>

        {{-- Dépôts autorisés — éditable (pivot par catégorie) --}}
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Dépôts autorisés</div>
            <div class="p-4">
                <table class="w-full text-[12.5px] border border-gray-200">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Code dépôt</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Intitulé</th>
                            <th class="text-center font-bold px-3 py-1.5 border-b border-gray-200">Production</th>
                            <th class="text-center font-bold px-3 py-1.5 border-b border-gray-200">Vente</th>
                            <th class="text-center font-bold px-3 py-1.5 border-b border-gray-200">Achat</th>
                            <th class="text-center font-bold px-3 py-1.5 border-b border-gray-200">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouses as $i => $wh)
                        <tr class="border-b border-gray-100 last:border-0">
                            <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-1.5 font-mono font-semibold text-gray-700">{{ $wh->code }}</td>
                            <td class="px-3 py-1.5 text-gray-700">{{ $wh->name }}</td>
                            @foreach(['can_production', 'can_sale', 'can_purchase', 'can_stock'] as $cap)
                            <td class="px-3 py-1.5 text-center">
                                <input type="hidden" name="depots[{{ $wh->id }}][{{ $cap }}]" value="0">
                                <input type="checkbox" name="depots[{{ $wh->id }}][{{ $cap }}]" value="1"
                                       {{ $depotOn($wh, $cap) ? 'checked' : '' }}
                                       class="{{ $chk }}">
                            </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-3 py-4 text-center text-gray-400">Aucun dépôt configuré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="text-[11px] text-gray-500 mt-2">Cochez ce que chaque dépôt permet pour les articles de cette catégorie.</p>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET ACHAT ════════════════════════════════ --}}
    <div id="sec-achat" class="p-4 pt-0 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Paramètres d'achat</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $lbl }}">Compte d'achat <span class="font-normal text-gray-400">(6xx)</span></label>
                    <div class="relative">
                        <select name="purchase_account_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($accounts['purchase'] as $acc)
                                <option value="{{ $acc->id }}" @selected(old('purchase_account_id', $f?->purchase_account_id) == $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Code taxe / TVA achat</label>
                    <div class="relative">
                        <select name="tax_rate_achat_id" class="{{ $lk }}">
                            <option value="">—</option>
                            @foreach($taxRates as $tr)
                                <option value="{{ $tr->id }}" @selected(old('tax_rate_achat_id', $f?->tax_rate_achat_id) == $tr->id)>{{ $tr->rate }} %</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Unité d'achat (UA)</label>
                    <div class="relative">
                        {{-- Miroir de l'unité définie dans « Unités par défaut » (onglet Général) :
                             x-model synchronise, pas de name → un seul champ soumis (évite l'écrasement). --}}
                        <select x-model="ua" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->abbreviation }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5">Synchronisé avec l'onglet Général (Unités par défaut).</p>
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET VENTE ════════════════════════════════ --}}
    <div id="sec-vente" class="p-4 pt-0 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Paramètres de vente</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $lbl }}">Compte de vente <span class="font-normal text-gray-400">(7xx)</span></label>
                    <div class="relative">
                        <select name="sale_account_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($accounts['sale'] as $acc)
                                <option value="{{ $acc->id }}" @selected(old('sale_account_id', $f?->sale_account_id) == $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Code taxe / TVA vente</label>
                    <div class="relative">
                        <select name="tax_rate_vente_id" class="{{ $lk }}">
                            <option value="">—</option>
                            @foreach($taxRates as $tr)
                                <option value="{{ $tr->id }}" @selected(old('tax_rate_vente_id', $f?->tax_rate_vente_id) == $tr->id)>{{ $tr->rate }} %</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Unité de vente (UV)</label>
                    <div class="relative">
                        {{-- Miroir de l'unité définie dans « Unités par défaut » (onglet Général). --}}
                        <select x-model="uv" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}">{{ $u->abbreviation }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5">Prix plancher / surcharge : onglet Général.</p>
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET PRODUCTION ═══════════════════════════ --}}
    <div id="sec-prod" class="p-4 pt-0 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Production</div>
            <div class="p-4 space-y-4">
                <div class="flex flex-wrap gap-x-8 gap-y-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="utilisable_production" value="0">
                        <input type="checkbox" name="utilisable_production" value="1" class="{{ $chk }}"
                               {{ old('utilisable_production', $f?->utilisable_production) ? 'checked' : '' }}>
                        <span class="{{ $chkLb }}">Utilisable en production</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="suivi_bobine" value="0">
                        <input type="checkbox" name="suivi_bobine" value="1" class="{{ $chk }}"
                               {{ old('suivi_bobine', $f?->suivi_bobine) ? 'checked' : '' }}>
                        <span class="{{ $chkLb }}">Suivi bobine</span>
                    </label>
                </div>
                <p class="text-[11px] text-gray-500">Caractéristiques techniques matière (épaisseur, densité, métrage) : onglet Stock. Flux « Fabriqué » : onglet Général.</p>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET COMPTABILITÉ ═════════════════════════ --}}
    <div id="sec-compta" class="p-4 pt-0 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Comptabilité <span class="font-normal text-emerald-700/70">— SYSCOHADA, optionnel</span></div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $lbl }}">Compte de stock <span class="font-normal text-gray-400">(3xx)</span></label>
                    <div class="relative">
                        <select name="stock_account_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($accounts['stock'] as $acc)
                                <option value="{{ $acc->id }}" @selected(old('stock_account_id', $f?->stock_account_id) == $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Section analytique</label>
                    <div class="relative">
                        <select name="section_analytique_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($costCenters as $cc)
                                <option value="{{ $cc->id }}" @selected(old('section_analytique_id', $f?->section_analytique_id) == $cc->id)>{{ $cc->code }} — {{ $cc->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Centre de coût</label>
                    <div class="relative">
                        <select name="cost_center_id" class="{{ $lk }} font-mono">
                            <option value="">—</option>
                            @foreach($costCenters as $cc)
                                <option value="{{ $cc->id }}" @selected(old('cost_center_id', $f?->cost_center_id) == $cc->id)>{{ $cc->code }} — {{ $cc->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════════ ONGLET DOCUMENTS ════════════════════════════ --}}
    <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
        <section class="border border-gray-200 rounded-[4px]">
            <div class="{{ $secH }}">Documents / pièces jointes</div>
            <div class="p-4 space-y-4">
                @if($f && $f->attachments->isNotEmpty())
                <table class="w-full text-[12.5px] border border-gray-200">
                    <thead>
                        <tr class="bg-[#eef5f0] text-emerald-900">
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-300 w-10">#</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-300">Type document</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-300">Fichier</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-300">Date</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-300">Taille</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($f->attachments as $i => $att)
                        <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                            <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-1.5 text-gray-500 uppercase text-[11px]">{{ str_contains($att->mime_type, 'pdf') ? 'PDF' : (str_contains($att->mime_type, 'image') ? 'IMAGE' : 'DOCUMENT') }}</td>
                            <td class="px-3 py-1.5 text-gray-700 font-mono">{{ $att->filename }}</td>
                            <td class="px-3 py-1.5 text-gray-500">{{ $att->created_at?->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5 text-gray-500 tabular-nums">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
                <div>
                    <label class="{{ $lbl }}">Ajouter des pièces jointes</label>
                    <input type="file" name="documents[]" multiple
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                                  file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                  file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                    <p class="text-[11px] text-gray-400 mt-1">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
                    @error('documents.*')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>
</div>
