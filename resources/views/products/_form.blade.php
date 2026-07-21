{{--
    Formulaire article — fiche « Articles : Création complète » style SAGE X3
    avec barre d'onglets : Général · Unités · Stock · Achat · Vente ·
    Production · Qualité · Comptabilité · Documents.

    Variables : $families, $brands, $units, $taxRates, $suppliers, $accounts,
    $componentProducts, $warehouses, $familiesFlat, $typeArticleOptions,
    $costCenters, $machines, $linkables ; $product en édition.
--}}
@php
    $p = $product ?? null;
    $isEdit = isset($product);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chkLb = 'text-[12.5px] font-semibold text-gray-700 select-none';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $depotOn = function ($wh, $cap) use ($p) {
        $default = $p
            ? (optional($p->warehouses->firstWhere('id', $wh->id))->pivot->{$cap} ?? false)
            : ($wh->{$cap} ?? false);
        return old("depots.{$wh->id}.{$cap}", $default);
    };
@endphp

<form action="{{ $isEdit ? route('products.update', $p) : route('products.store') }}"
      method="POST" enctype="multipart/form-data" data-turbo="false"
      x-data="productForm({
          tab: 'general',
          manuf: {{ old('is_manufacturable', $p->is_manufacturable ?? false) ? 'true' : 'false' }},
          thickness: '{{ old('thickness', $p->thickness ?? '') }}',
          type: '{{ old('type', $p->type ?? 'simple') }}',
          purchasePrice: {{ (int) old('purchase_price', $p->purchase_price ?? 0) }},
          marginRate: {{ (float) old('margin_rate_target', $p->margin_rate_target ?? 0) }},
          salePrice: {{ (int) old('sale_price', $p->sale_price ?? 0) }},
          components: {{ Js::from(old('components', $isEdit && $p->components->isNotEmpty()
              ? $p->components->map(fn($c) => ['component_product_id' => $c->component_product_id, 'quantity' => $c->quantity])->toArray()
              : [])) }}
      })"
      class="space-y-3">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-validation-errors />

    {{-- ═══ Bandeau + onglets SAGE ═════════════════════════════════════════════ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <h2 class="text-[22px] font-bold text-gray-900 leading-tight">
                Articles : {{ $isEdit ? 'Modification' : 'Création complète' }}
                @if($isEdit)<span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $p->code_article ?: $p->reference }}</span>@endif
            </h2>
            <div class="flex items-center gap-1.5">
                <button type="submit"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
                <a href="{{ route('products.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">
                    Abandon
                </a>
                <a href="{{ route('products.create') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">
                    Créer +
                </a>
            </div>
        </div>

        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach([
                'general' => 'Général', 'unites' => 'Unités', 'stock' => 'Stock',
                'achat' => 'Achat', 'vente' => 'Vente', 'prod' => 'Production',
                'qualite' => 'Qualité', 'compta' => 'Comptabilité', 'docs' => 'Documents',
            ] as $key => $label)
            {{-- [SAGE X3] Onglet = ancre : sections toutes visibles, clic = scroll --}}
            <button type="button"
                    @click="tab = '{{ $key }}'; document.getElementById('sec-{{ $key }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">
                {{ $label }}
            </button>
            @endforeach
        </nav>

        {{-- ══════════════════ GÉNÉRAL ═════════════════════════════════════════ --}}
        <div id="sec-general" class="p-4 space-y-4 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Identification</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Site</label>
                        <div class="relative">
                            <select name="site_id" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($warehouses as $wh)<option value="{{ $wh->id }}" @selected(old('site_id', $p->site_id ?? '') == $wh->id)>{{ $wh->code }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Catégorie article <span class="text-red-600">*</span></label>
                        @php
                            $famProps = fn($f) => 'data-props=\'' . json_encode([
                                'flux'=>(array)($f->type_flux ?? []),'gs'=>(bool)$f->gestion_stock,'sneg'=>(bool)($f->stock_negatif ?? false),
                                'gl'=>(bool)$f->gestion_lot,'serie'=>(bool)$f->gestion_numero_serie,'qc'=>(bool)$f->controle_qualite,
                                'us'=>$f->unite_stock_id,'ua'=>$f->unite_achat_id,'uv'=>$f->unite_vente_id,'depot'=>$f->site_stockage_id,
                            ], JSON_HEX_APOS) . '\'';
                        @endphp
                        {{-- [X3] Catégorie de gestion : détermine le fonctionnement de l'article
                             (flux, stratégie MTO/MTS, stock, comptes). Les défauts sont appliqués
                             CÔTÉ SERVEUR à la création (CategoryDefaultsService). --}}
                        <div class="mb-3">
                            <label class="{{ $lbl }}">Catégorie de gestion</label>
                            <div class="relative">
                                <select name="item_category_id" class="{{ $lk }}">
                                    <option value="">—</option>
                                    @foreach(\App\Models\ItemCategory::where('is_active', true)->orderBy('sort_order')->get() as $ic)
                                        <option value="{{ $ic->id }}" @selected(old('item_category_id', $p->item_category_id ?? '') == $ic->id)>{{ $ic->code }} — {{ $ic->name }}</option>
                                    @endforeach
                                </select>{!! $caret !!}
                            </div>
                            <p class="text-[10.5px] text-gray-400 mt-0.5">Modèle de gestion (≠ famille). Pose les défauts : flux, MTO/MTS, stock, comptes.</p>
                        </div>
                        <div class="relative">
                            <select name="family_id" id="family_id_select" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($families as $f)
                                    <option value="{{ $f->id }}" {!! $famProps($f) !!} @selected(old('family_id', $p->family_id ?? '') == $f->id)>{{ $f->code ?: $f->name }}</option>
                                    @foreach($f->children as $child)
                                        <option value="{{ $child->id }}" {!! $famProps($child) !!} @selected(old('family_id', $p->family_id ?? '') == $child->id)>&nbsp;&nbsp;└ {{ $child->code ?: $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        {{-- [X3 §5] Sous-famille : filtrée sur la famille sélectionnée (garde serveur en plus). --}}
                        <div class="mt-2">
                            <label class="{{ $lbl }}">Sous-famille</label>
                            <div class="relative">
                                <select name="sub_family_id" id="sub_family_select" class="{{ $lk }}"
                                        onfocus="(function(s){var fam=document.getElementById('family_id_select').value;Array.from(s.options).forEach(function(o){o.hidden=o.value!=='' && o.dataset.parent!==fam;});})(this)">
                                    <option value="">—</option>
                                    @foreach(\App\Models\ProductFamily::whereNotNull('parent_id')->where('is_active', true)->orderBy('name')->get() as $sf)
                                        <option value="{{ $sf->id }}" data-parent="{{ $sf->parent_id }}" @selected(old('sub_family_id', $p->sub_family_id ?? '') == $sf->id)>{{ $sf->name }}</option>
                                    @endforeach
                                </select>{!! $caret !!}
                            </div>
                            <p class="text-[10.5px] text-gray-400 mt-0.5">Doit appartenir à la famille choisie (contrôlé côté serveur).</p>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Code article <span class="text-red-600">*</span></label>
                        <input type="text" name="code_article" maxlength="10" value="{{ old('code_article', $p->code_article ?? '') }}"
                               class="{{ $inp }} font-mono uppercase" placeholder="PRFTC0001" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Référence</label>
                        <input type="text" name="reference" maxlength="16" value="{{ old('reference', $p->reference ?? '') }}"
                               class="{{ $inp }} font-mono" placeholder="Auto si vide">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Nature article <span class="text-red-600">*</span></label>
                        <div class="relative">
                            <select name="type_article" required class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($typeArticleOptions as $val => $label)<option value="{{ $val }}" @selected(old('type_article', $p->type_article ?? '') === $val)>{{ $label }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>

                    <div class="sm:col-span-6">
                        <label class="{{ $lbl }}">Désignation 1 <span class="text-red-600">*</span></label>
                        <input type="text" name="name" maxlength="110" required value="{{ old('name', $p->name ?? '') }}"
                               class="{{ $inp }} font-medium" placeholder="TOLE BAC ALU PUR DE 70/100 AL6">
                    </div>
                    <div class="sm:col-span-6">
                        <label class="{{ $lbl }}">Désignation 2</label>
                        <input type="text" name="designation_2" maxlength="200" value="{{ old('designation_2', $p->designation_2 ?? '') }}"
                               class="{{ $inp }}" placeholder="TÔLE BAC ALUMINIUM PUR 70/100 AL6">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Statut</label>
                        <div class="relative">
                            <select name="statut" class="{{ $lk }}">
                                @php $st = old('statut', $p->statut ?? 'actif'); @endphp
                                <option value="actif" @selected($st==='actif')>Actif</option>
                                <option value="inactif" @selected($st==='inactif')>En sommeil</option>
                                <option value="bloque" @selected($st==='bloque')>Bloqué</option>
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Structure</label>
                        <div class="relative">
                            <select name="type" x-model="type" required class="{{ $lk }}">
                                <option value="simple">Simple</option>
                                <option value="service">Service</option>
                                <option value="compose">Composé (kit)</option>
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    @foreach(['famille1_id' => 'Famille 1', 'famille2_id' => 'Famille 2', 'famille3_id' => 'Famille 3'] as $fname => $flabel)
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">{{ $flabel }}</label>
                        <div class="relative">
                            <select name="{{ $fname }}" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($familiesFlat as $fam)<option value="{{ $fam->id }}" @selected(old($fname, $p->$fname ?? '') == $fam->id)>{{ $fam->code }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Marque</label>
                        <div class="relative">
                            <select name="brand_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id', $p->brand_id ?? '') == $b->id)>{{ $b->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                </div>

                <div class="px-4 pb-4">
                    <p class="{{ $lbl }} mb-2">Type de flux</p>
                    <div class="flex flex-wrap gap-x-8 gap-y-2">
                        @foreach([
                            'is_purchasable' => 'Acheté (A)', 'is_sellable' => 'Vendu (V)', 'is_manufacturable' => 'Fabriqué (F)',
                        ] as $fl => $fllabel)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $fl }}" value="0">
                            {{-- [FIX Fabriqué(F)] x-model synchronise avec la case dupliquée de l'onglet
                                 Production (qui n'a plus de name) — un seul champ soumis, plus d'écrasement. --}}
                            <input type="checkbox" name="{{ $fl }}" value="1" class="{{ $chk }}"
                                   @if($fl === 'is_manufacturable') x-model="manuf" @endif
                                   {{ old($fl, $p->{$fl} ?? ($fl !== 'is_manufacturable')) ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">{{ $fllabel }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1.5">Un article non coché dans un flux ne peut pas y être exécuté (achat / vente / OF).</p>
                </div>
            </section>
        </div>

        {{-- ══════════════════ UNITÉS ══════════════════════════════════════════ --}}
        <div id="sec-unites" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Unités</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-x-4 gap-y-3">
                    @foreach(['purchase_unit_id' => 'Unité achat (UA)', 'unit_id' => 'Unité stock (US)', 'sale_unit_id' => 'Unité vente (UV)', 'weight_unit_id' => 'Unité poids (UP)'] as $uname => $ulabel)
                    <div>
                        <label class="{{ $lbl }}">{{ $ulabel }}</label>
                        <div class="relative">
                            <select name="{{ $uname }}" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($units as $u)<option value="{{ $u->id }}" @selected(old($uname, $p->$uname ?? '') == $u->id)>{{ $u->abbreviation }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    @endforeach
                    <div><label class="{{ $lbl }}">Coef UA/US</label><input type="number" step="0.000001" min="0" name="ua_to_us_coef" id="ua_to_us_coef" value="{{ old('ua_to_us_coef', $p->ua_to_us_coef ?? 1) }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Coef UV/US</label><input type="number" step="0.000001" min="0" name="uv_to_us_coef" value="{{ old('uv_to_us_coef', $p->uv_to_us_coef ?? 1) }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Poids brut US</label><input type="number" step="0.0001" min="0" name="gross_weight_per_us" value="{{ old('gross_weight_per_us', $p->gross_weight_per_us ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Poids net US</label><input type="number" step="0.0001" min="0" name="net_weight_per_us" value="{{ old('net_weight_per_us', $p->net_weight_per_us ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Densité</label><input type="number" step="0.001" min="0" name="density" value="{{ old('density', $p->density ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Épaisseur / diamètre (mm)</label><input type="number" step="0.01" min="0" name="thickness" x-model="thickness" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Métrage (M)</label><input type="number" step="0.01" min="0" name="linear_meters" value="{{ old('linear_meters', $p->linear_meters ?? '') }}" class="{{ $inpR }}"></div>
                </div>
            </section>
        </div>

        {{-- ══════════════════ STOCK ═══════════════════════════════════════════ --}}
        <div id="sec-stock" class="p-4 pt-0 space-y-4 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Gestion stock</div>
                <div class="p-4 space-y-4">
                    <div class="flex flex-wrap gap-x-8 gap-y-2">
                        @foreach([
                            'is_stockable' => ['Géré en stock', true],
                            'allow_negative_stock' => ['Stock négatif autorisé', false],
                            'has_lot_number' => ['Géré en lot', false],
                        ] as $fl => [$fllabel, $def])
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $fl }}" value="0">
                            <input type="checkbox" name="{{ $fl }}" value="1" class="{{ $chk }}"
                                   {{ old($fl, $p->{$fl} ?? $def) ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">{{ $fllabel }}</span>
                        </label>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        @foreach(['main_warehouse_id' => 'Dépôt principal', 'production_warehouse_id' => 'Dépôt production', 'sale_warehouse_id' => 'Dépôt vente', 'quality_warehouse_id' => 'Dépôt qualité'] as $wname => $wlabel)
                        <div>
                            <label class="{{ $lbl }}">{{ $wlabel }}</label>
                            <div class="relative">
                                <select name="{{ $wname }}" class="{{ $lk }} font-mono">
                                    <option value="">—</option>
                                    @foreach($warehouses as $wh)<option value="{{ $wh->id }}" @selected(old($wname, $p->$wname ?? '') == $wh->id)>{{ $wh->code }}</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div><label class="{{ $lbl }}">Stock mini</label><input type="number" step="0.001" min="0" name="stock_min" value="{{ old('stock_min', $p->stock_min ?? 0) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Stock maxi</label><input type="number" step="0.001" min="0" name="stock_max" value="{{ old('stock_max', $p->stock_max ?? '') }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Seuil alerte</label><input type="number" step="0.001" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', $p->seuil_alerte ?? '') }}" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Valorisation</label>
                            <div class="relative">
                                <select name="valuation_method" class="{{ $lk }}">
                                    @php $vm = old('valuation_method', $p->valuation_method ?? 'cmp'); @endphp
                                    <option value="cmp" @selected($vm==='cmp')>CMP</option>
                                    <option value="fifo" @selected($vm==='fifo')>FIFO</option>
                                    <option value="lifo" @selected($vm==='lifo')>LIFO</option>
                                </select>{!! $caret !!}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

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
                                    <input type="checkbox" name="depots[{{ $wh->id }}][{{ $cap }}]" value="1" {{ $depotOn($wh, $cap) ? 'checked' : '' }} class="{{ $chk }}">
                                </td>
                                @endforeach
                            </tr>
                            @empty
                            <tr><td colspan="7" class="px-3 py-4 text-center text-gray-400">Aucun dépôt configuré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if($isEdit && $p->relationLoaded('coils') && ($p->coils->isNotEmpty() || $p->has_lot_number))
            {{-- [Bobines → article] chaque bobine physique = un lot matière de cet article --}}
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Bobines / lots matière</span>
                    @can('production.create')
                    <a href="{{ route('production.coils.create', ['product_id' => $p->id]) }}"
                       class="text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-2 py-0.5 rounded-[3px] normal-case tracking-normal">+ Nouvelle bobine</a>
                    @endcan
                </div>
                <div class="p-4">
                    @if($p->coils->isNotEmpty())
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-[#3b4248] text-white text-[11px] font-semibold uppercase whitespace-nowrap">
                            <th class="text-left px-2 py-1.5">Référence</th>
                            <th class="text-left px-2 py-1.5">Lot</th>
                            <th class="text-right px-2 py-1.5">Ép. (mm)</th>
                            <th class="text-right px-2 py-1.5">Larg. (mm)</th>
                            <th class="text-right px-2 py-1.5">Poids restant (kg)</th>
                            <th class="text-left px-2 py-1.5">Statut</th>
                            <th class="text-left px-2 py-1.5">Reçue le</th>
                        </tr></thead>
                        <tbody>
                            @foreach($p->coils as $coil)
                            <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                <td class="px-2 py-1.5 whitespace-nowrap">
                                    <a href="{{ route('production.coils.show', $coil) }}" class="font-mono text-emerald-700 hover:underline">{{ $coil->reference }}</a>
                                </td>
                                <td class="px-2 py-1.5 font-mono text-[12px] text-gray-600">{{ $coil->lot_number }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format((float) $coil->thickness, 2, ',', ' ') }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format((float) $coil->width, 0, ',', ' ') }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums font-semibold">{{ number_format((float) $coil->remaining_weight, 0, ',', ' ') }}</td>
                                <td class="px-2 py-1.5">
                                    @php $cb = match($coil->status){
                                        'disponible' => 'bg-emerald-100 text-emerald-700',
                                        'en_production', 'reservee' => 'bg-blue-100 text-blue-700',
                                        'epuisee' => 'bg-gray-200 text-gray-500',
                                        default => 'bg-amber-100 text-amber-700',
                                    }; @endphp
                                    <span class="inline-flex px-1.5 py-0.5 rounded-[2px] text-[10.5px] font-semibold {{ $cb }}">{{ ucfirst(str_replace('_', ' ', $coil->status)) }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-gray-600 tabular-nums whitespace-nowrap">{{ optional($coil->received_at)->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-2 text-right">
                        <a href="{{ route('production.coils.index', ['product_id' => $p->id]) }}" class="text-[12px] font-semibold text-emerald-700 hover:underline">Voir toutes les bobines de cet article →</a>
                    </div>
                    @else
                    <p class="text-[13px] text-gray-400 italic">Aucune bobine réceptionnée pour cet article.</p>
                    @endif
                </div>
            </section>
            @endif
        </div>

        {{-- ══════════════════ ACHAT ═══════════════════════════════════════════ --}}
        <div id="sec-achat" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Achat</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="{{ $lbl }}">Prix d'achat HT</label><input type="number" min="0" step="1" name="purchase_price" x-model.number="purchasePrice" value="{{ old('purchase_price', $p->purchase_price ?? 0) }}" class="{{ $inpR }}"></div>
                    <div>
                        <label class="{{ $lbl }}">TVA achat</label>
                        <div class="relative">
                            <select name="tax_rate_achat_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($taxRates as $tr)<option value="{{ $tr->id }}" @selected(old('tax_rate_achat_id', $p->tax_rate_achat_id ?? '') == $tr->id)>{{ $tr->rate }} %</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div><label class="{{ $lbl }}">Délai livraison (jours)</label><input type="number" min="0" max="365" name="delivery_delay_days" value="{{ old('delivery_delay_days', $p->delivery_delay_days ?? '') }}" class="{{ $inpR }}"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Fournisseur principal</label>
                        <div class="relative">
                            <select name="default_supplier_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('default_supplier_id', $p->default_supplier_id ?? '') == $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div><label class="{{ $lbl }}">Référence fournisseur</label><input type="text" maxlength="80" name="supplier_reference" value="{{ old('supplier_reference', $p->supplier_reference ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Compte d'achat (6xx)</label>
                        <div class="relative">
                            <select name="purchase_account_id" class="{{ $lk }} font-mono">
                                <option value="">— Hériter catégorie —</option>
                                @foreach($accounts->filter(fn($a)=>str_starts_with($a->code,'6')) as $a)<option value="{{ $a->id }}" @selected(old('purchase_account_id', $p->purchase_account_id ?? '') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ══════════════════ VENTE ═══════════════════════════════════════════ --}}
        <div id="sec-vente" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Vente</div>
                <div class="p-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="{{ $lbl }}">Prix vente de base</label>
                            <input type="number" min="0" step="1" name="sale_price" x-model.number="salePrice" @input="recomputeMargin()" value="{{ old('sale_price', $p->sale_price ?? 0) }}"
                                   :class="(purchasePrice > 0 && salePrice > 0 && salePrice < purchasePrice) ? '!border-red-500 !text-red-600 bg-red-50' : 'border-emerald-400'"
                                   class="{{ $inpR }} font-semibold">
                            {{-- [Vente à perte interdite] Alerte visible avant soumission ; le serveur bloque aussi. --}}
                            <p x-show="purchasePrice > 0 && salePrice > 0 && salePrice < purchasePrice" x-cloak
                               class="text-[11px] text-red-600 font-semibold mt-0.5">⚠ Vente à perte : prix de vente &lt; prix d'achat — enregistrement refusé.</p>
                        </div>
                        {{-- [FIX submit silencieux] Aucune contrainte native min/max : dans un onglet masqué,
                             une valeur hors bornes (ex. marge réelle négative recalculée après baisse de prix)
                             bloquait la soumission SANS message (le navigateur ne peut afficher la bulle sur un
                             champ caché). La validation serveur (nullable|numeric|min:0|max:999.99) reste active
                             et affiche une erreur visible via <x-validation-errors>. --}}
                        <div><label class="{{ $lbl }}">Marge cible (%)</label><input type="number" step="0.01" name="margin_rate_target" x-model.number="marginRate" @input="recomputeFromMargin()" value="{{ old('margin_rate_target', $p->margin_rate_target ?? '') }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Prix plancher</label><input type="number" min="0" step="1" name="min_sale_price" value="{{ old('min_sale_price', $p->min_sale_price ?? 0) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Prix plafond <span class="text-gray-400 font-normal">(indicatif)</span></label><input type="number" min="0" step="1" name="max_sale_price" value="{{ old('max_sale_price', $p->max_sale_price) }}" placeholder="—" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">TVA vente</label>
                            <div class="relative">
                                <select name="tax_rate_id" class="{{ $lk }}">
                                    <option value="">Exonéré</option>
                                    @foreach($taxRates as $tr)<option value="{{ $tr->id }}" @selected(old('tax_rate_id', $p->tax_rate_id ?? '') == $tr->id)>{{ $tr->rate }} %</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><label class="{{ $lbl }}">Client type / canal</label><input type="text" maxlength="60" name="client_type_canal" value="{{ old('client_type_canal', $p->client_type_canal ?? '') }}" class="{{ $inp }}" placeholder="PRO / DIR"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Compte de vente (7xx)</label>
                            <div class="relative">
                                <select name="sale_account_id" class="{{ $lk }} font-mono">
                                    <option value="">— Hériter catégorie —</option>
                                    @foreach($accounts->filter(fn($a)=>str_starts_with($a->code,'7')) as $a)<option value="{{ $a->id }}" @selected(old('sale_account_id', $p->sale_account_id ?? '') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                    </div>
                    <div class="text-[12px] text-gray-600 bg-gray-50 border border-gray-200 rounded-[3px] px-3 py-1.5 inline-flex items-center gap-2">
                        <span>Marge :</span><strong x-text="formatPercent(actualMargin())" class="text-emerald-700 font-mono"></strong>
                        <span class="text-gray-300">|</span><span>en valeur :</span><strong x-text="formatMoney(salePrice - purchasePrice)" class="text-emerald-700 font-mono"></strong>
                    </div>
                </div>
            </section>
        </div>

        {{-- ══════════════════ PRODUCTION ══════════════════════════════════════ --}}
        <div id="sec-prod" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Production tôle bac</div>
                <div class="p-4 space-y-4">
                    <div class="flex flex-wrap gap-x-8 gap-y-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            {{-- [FIX Fabriqué(F)] Miroir de la case « Type de flux » (onglet Général) : cette
                                 paire hidden+checkbox dupliquée, non cochée, écrasait la valeur soumise (« 0 »
                                 gagnait toujours). x-model synchronise, pas de name → un seul champ soumis. --}}
                            <input type="checkbox" class="{{ $chk }}" x-model="manuf">
                            <span class="{{ $chkLb }}">Fabriqué (flux F)</span>
                        </label>
                        <div class="inline-flex items-center gap-2">
                            <span class="{{ $lbl }} mb-0">Mode</span>
                            <div class="relative">
                                <select name="production_mode" class="{{ $lk }} h-7 w-32">
                                    @php $pm = old('production_mode', $p->production_mode ?? ''); @endphp
                                    <option value="">—</option>
                                    <option value="mts" @selected($pm==='mts')>MTS (sur stock)</option>
                                    <option value="mto" @selected($pm==='mto')>MTO (sur commande)</option>
                                </select>{!! $caret !!}
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div><label class="{{ $lbl }}">Article parent / nomenclature</label><input type="text" maxlength="60" name="nomenclature_ref" value="{{ old('nomenclature_ref', $p->nomenclature_ref ?? '') }}" class="{{ $inp }} font-mono" placeholder="OFOUTLB…"></div>
                        <div><label class="{{ $lbl }}">Profil</label><input type="text" maxlength="60" name="profil" value="{{ old('profil', $p->profil ?? '') }}" class="{{ $inp }}" placeholder="TBA1000-5N"></div>
                        <div><label class="{{ $lbl }}">Couleur</label><input type="text" maxlength="60" name="couleur" value="{{ old('couleur', $p->couleur ?? '') }}" class="{{ $inp }}" placeholder="ALU NATUREL"></div>
                        {{-- [FIX doublons] miroir de « Épaisseur / diamètre » (onglet Stock) — pas de name --}}
                        <div><label class="{{ $lbl }}">Épaisseur (mm)</label><input type="number" step="0.01" min="0" x-model="thickness" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Largeur utile (mm)</label><input type="number" step="0.01" min="0" name="largeur_utile" value="{{ old('largeur_utile', $p->largeur_utile ?? '') }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Longueur standard (mm)</label><input type="number" step="0.01" min="0" name="longueur_standard" value="{{ old('longueur_standard', $p->longueur_standard ?? '') }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Longueur mini fabricable (m)</label><input type="number" step="0.001" min="0" name="longueur_min" value="{{ old('longueur_min', $p->longueur_min ?? '') }}" placeholder="—" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Longueur maxi fabricable (m)</label><input type="number" step="0.001" min="0" name="longueur_max" value="{{ old('longueur_max', $p->longueur_max ?? '') }}" placeholder="—" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Machine par défaut</label>
                            <div class="relative">
                                <select name="machine_defaut_id" class="{{ $lk }} font-mono">
                                    <option value="">—</option>
                                    @foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_defaut_id', $p->machine_defaut_id ?? '') == $m->id)>{{ $m->code }} — {{ $m->name }}</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                        <div><label class="{{ $lbl }}">Rendement standard</label><input type="number" step="0.0001" min="0" max="9.9999" name="rendement_standard" value="{{ old('rendement_standard', $p->rendement_standard ?? '') }}" class="{{ $inpR }}" placeholder="0,965"></div>
                        <div><label class="{{ $lbl }}">Taux de perte</label><input type="number" step="0.0001" min="0" max="9.9999" name="taux_perte" value="{{ old('taux_perte', $p->taux_perte ?? '') }}" class="{{ $inpR }}" placeholder="0,035"></div>
                        <div>
                            <label class="{{ $lbl }}">Article avarié lié</label>
                            <div class="relative">
                                <select name="article_avarie_id" class="{{ $lk }}">
                                    <option value="">—</option>
                                    @foreach($linkables as $la)<option value="{{ $la->id }}" @selected(old('article_avarie_id', $p->article_avarie_id ?? '') == $la->id)>{{ $la->code_article }} — {{ \Illuminate\Support\Str::limit($la->name, 30) }}</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Article chute lié</label>
                            <div class="relative">
                                <select name="article_chute_id" class="{{ $lk }}">
                                    <option value="">—</option>
                                    @foreach($linkables as $la)<option value="{{ $la->id }}" @selected(old('article_chute_id', $p->article_chute_id ?? '') == $la->id)>{{ $la->code_article }} — {{ \Illuminate\Support\Str::limit($la->name, 30) }}</option>@endforeach
                                </select>{!! $caret !!}
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ══════════════════ QUALITÉ ═════════════════════════════════════════ --}}
        <div id="sec-qualite" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Qualité &amp; traçabilité</div>
                <div class="p-4 flex flex-wrap gap-x-8 gap-y-2.5">
                    @foreach([
                        'controle_qualite' => 'Contrôle qualité',
                        'has_lot_number' => 'Géré en lot',
                        'has_serial_number' => 'Numéro de série',
                        'has_expiry_date' => "Date d'expiration",
                    ] as $fl => $fllabel)
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="{{ $fl }}" value="0">
                        <input type="checkbox" name="{{ $fl }}" value="1" class="{{ $chk }}" {{ old($fl, $p->{$fl} ?? false) ? 'checked' : '' }}>
                        <span class="{{ $chkLb }}">{{ $fllabel }}</span>
                    </label>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ══════════════════ COMPTABILITÉ ════════════════════════════════════ --}}
        <div id="sec-compta" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Comptabilité</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $lbl }}">Compte de stock (3xx)</label>
                        <div class="relative">
                            <select name="stock_account_id" class="{{ $lk }} font-mono">
                                <option value="">— Hériter catégorie —</option>
                                @foreach($accounts->filter(fn($a)=>str_starts_with($a->code,'3')) as $a)<option value="{{ $a->id }}" @selected(old('stock_account_id', $p->stock_account_id ?? '') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Variation de stocks (603x)</label>
                        <div class="relative">
                            <select name="variation_stock_account_id" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($accounts->filter(fn($a)=>str_starts_with($a->code,'6')) as $a)<option value="{{ $a->id }}" @selected(old('variation_stock_account_id', $p->variation_stock_account_id ?? '') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div><label class="{{ $lbl }}">Coût standard</label><input type="number" step="0.0001" min="0" name="cout_standard" value="{{ old('cout_standard', $p->cout_standard ?? 0) }}" class="{{ $inpR }}"></div>
                    <div>
                        <label class="{{ $lbl }}">Section analytique</label>
                        <div class="relative">
                            <select name="section_analytique_id" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($costCenters as $cc)<option value="{{ $cc->id }}" @selected(old('section_analytique_id', $p->section_analytique_id ?? '') == $cc->id)>{{ $cc->code }} — {{ $cc->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Centre de coût</label>
                        <div class="relative">
                            <select name="cost_center_id" class="{{ $lk }} font-mono">
                                <option value="">—</option>
                                @foreach($costCenters as $cc)<option value="{{ $cc->id }}" @selected(old('cost_center_id', $p->cost_center_id ?? '') == $cc->id)>{{ $cc->code }} — {{ $cc->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ══════════════════ DOCUMENTS ═══════════════════════════════════════ --}}
        <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Documents / pièces jointes</div>
                <div class="p-4 space-y-4">
                    @if($isEdit && $p->attachments->isNotEmpty())
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                        </tr></thead>
                        <tbody>
                            @foreach($p->attachments as $i => $att)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-3 py-1.5 text-gray-700 font-mono">{{ $att->filename }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ $att->mime_type }}</td>
                                <td class="px-3 py-1.5 text-gray-500 tabular-nums">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                    <div>
                        <label class="{{ $lbl }}">Ajouter des pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                                      file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                      file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400 mt-1">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- Composants kit (hors onglets, si structure composé) --}}
    <div x-show="type === 'compose'" x-cloak class="bg-white border border-gray-300 rounded-[4px]">
        <div class="{{ $secH }} flex items-center justify-between">
            <span>Composants du kit</span>
            <button type="button" @click="addComponent()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter</button>
        </div>
        <div class="p-4 space-y-2">
            <template x-if="components.length === 0"><p class="text-[13px] text-gray-400 text-center py-3">Cliquez sur « + Ajouter ».</p></template>
            <template x-for="(c, i) in components" :key="i">
                <div class="flex items-end gap-3 bg-gray-50 border border-gray-200 rounded-[3px] p-2.5">
                    <div class="flex-1">
                        <label class="{{ $lbl }}">Article composant</label>
                        <select :name="`components[${i}][component_product_id]`" x-model="c.component_product_id" required class="{{ $inp }}">
                            <option value="">—</option>
                            @foreach($componentProducts as $cp)<option value="{{ $cp->id }}">{{ $cp->name }} ({{ $cp->reference }})</option>@endforeach
                        </select>
                    </div>
                    <div style="width:120px"><label class="{{ $lbl }}">Quantité</label><input type="number" :name="`components[${i}][quantity]`" x-model="c.quantity" min="0.001" step="0.001" required class="{{ $inpR }}"></div>
                    <button type="button" @click="removeComponent(i)" class="h-8 px-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-[3px]">✕</button>
                </div>
            </template>
        </div>
    </div>
</form>

{{-- ── Barre de contexte pied de page [X3] ─────────────────────────────────── --}}
<div class="mt-3 bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
    <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
    <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
    <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">{{ $isEdit ? 'Article ' . ($p->code_article ?: $p->reference) : 'Nouvel article' }}</span></span>
    <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
    <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
</div>

@push('scripts')
<script>
function productForm(init) {
    return {
        tab:           init.tab || 'general',
        manuf:         !!init.manuf,
        thickness:     init.thickness ?? '',
        type:          init.type || 'simple',
        purchasePrice: Number(init.purchasePrice) || 0,
        marginRate:    Number(init.marginRate)    || 0,
        salePrice:     Number(init.salePrice)     || 0,
        components:    init.components || [],

        addComponent()    { this.components.push({ component_product_id: '', quantity: 1 }); },
        removeComponent(i){ this.components.splice(i, 1); },

        actualMargin() {
            if (!this.salePrice) return 0;
            return ((this.salePrice - this.purchasePrice) / this.salePrice) * 100;
        },
        recomputeMargin()     { this.marginRate = Math.round(this.actualMargin() * 100) / 100; },
        recomputeFromMargin() {
            if (this.marginRate >= 100 || !this.purchasePrice) return;
            this.salePrice = Math.round(this.purchasePrice / (1 - this.marginRate / 100));
        },
        formatPercent(v) { return (Math.round(v * 100) / 100).toFixed(2) + ' %'; },
        formatMoney(v)   { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' FCFA'; },
    };
}

// ── CDC articles : héritage des propriétés de la catégorie ──────────────────
document.addEventListener('DOMContentLoaded', function () {
    const catSelect = document.getElementById('family_id_select');
    if (catSelect) {
        catSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.dataset.props) return;
            const p = JSON.parse(opt.dataset.props);
            // [FIX Fabriqué(F)] dispatch 'change' pour que les cases pilotées par x-model
            // (état Alpine) prennent la nouvelle valeur — sinon l'état écraserait le .checked.
            const setCheck = (name, val) => { const b = document.querySelector('input[type="checkbox"][name="' + name + '"]'); if (b) { b.checked = !!val; b.dispatchEvent(new Event('change')); } };
            const setSelect = (name, val) => { const s = document.querySelector('select[name="' + name + '"]'); if (s && val) s.value = val; };
            setCheck('is_stockable', p.gs);
            setCheck('allow_negative_stock', p.sneg);
            setCheck('has_lot_number', p.gl);
            setCheck('has_serial_number', p.serie);
            setCheck('controle_qualite', p.qc);
            setCheck('is_purchasable', (p.flux || []).includes('achete'));
            setCheck('is_sellable', (p.flux || []).includes('vendu'));
            setCheck('is_manufacturable', (p.flux || []).includes('fabrique'));
            setSelect('unit_id', p.us);
            setSelect('purchase_unit_id', p.ua);
            setSelect('sale_unit_id', p.uv);
            setSelect('main_warehouse_id', p.depot);
        });
    }
    // Coef UA-US auto = 1 / poids net
    const netWeight = document.querySelector('input[name="net_weight_per_us"]');
    const coefUaUs  = document.getElementById('ua_to_us_coef');
    if (netWeight && coefUaUs) {
        netWeight.addEventListener('change', function () {
            const w = parseFloat(this.value);
            if (w > 0 && (!coefUaUs.value || parseFloat(coefUaUs.value) === 1)) coefUaUs.value = (1 / w).toFixed(6);
        });
    }
});
</script>
@endpush
