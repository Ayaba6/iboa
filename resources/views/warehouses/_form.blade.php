{{-- SAGE X3 « Dépôt : Création complète » — fiche à onglets partagée create/edit --}}
@php
    $w = $warehouse;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[13px] bg-gray-50 text-gray-500';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12.5px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $whList = \App\Models\Warehouse::when($w->exists, fn($q) => $q->where('id', '!=', $w->id))->orderBy('name')->get(['id', 'name', 'code']);
    $flows  = \App\Models\Warehouse::FLOWS;
@endphp

{{-- Toggle switch (SAGE X3) : @toggle('name', bool, 'Libellé') --}}
@php
    $toggle = function (string $name, bool $on, string $label) {
        return '<label class="flex items-center justify-between gap-3 cursor-pointer py-0.5">'
            . '<span class="text-[12.5px] text-gray-700">' . e($label) . '</span>'
            . '<span class="relative inline-flex items-center">'
            . '<input type="hidden" name="' . $name . '" value="0">'
            . '<input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . ' class="sr-only peer">'
            . '<span class="w-9 h-5 bg-gray-300 rounded-full relative transition-colors peer-checked:bg-emerald-500 '
            . 'after:content-[\'\'] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></span>'
            . '</span></label>';
    };
@endphp

<div class="max-w-[1400px]">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $w->exists ? route('stocks.warehouses.update', $w) : route('stocks.warehouses.store') }}"
          x-data="{ tab: 'general' }" class="space-y-3">
        @csrf
        @if($w->exists)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Header bar --}}
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight flex items-center gap-1.5">
                    Dépôt : {{ $w->exists ? 'Modification' : 'Création complète' }}
                    @if($w->exists)<span class="font-mono text-emerald-700 text-[18px]">{{ $w->code }}</span>@endif
                    <span class="text-amber-400 text-[16px]">★</span>
                </h2>
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Enregistrer</button>
                    <button type="button" onclick="window.print()"
                            class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
                    <a href="{{ route('stocks.warehouses.index') }}" class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            {{-- Tabs --}}
            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Général','stock'=>'Stock','flux'=>'Autorisations & flux','compta'=>'Comptabilité','docs'=>'Documents'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ GÉNÉRAL ═══════════ --}}
            <div x-show="tab === 'general'" class="p-4">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

                    {{-- Identification --}}
                    <section class="border border-gray-200 rounded-[4px]">
                        <div class="{{ $secH }}">Identification</div>
                        <div class="p-3 grid grid-cols-12 gap-x-3 gap-y-2.5">
                            <div class="col-span-6"><label class="{{ $lbl }}">Société</label><input type="text" value="{{ optional(currentCompany())->name ?? optional(currentCompany())->code }}" class="{{ $inpRo }}" readonly></div>
                            <div class="col-span-6"><label class="{{ $lbl }}">Site</label><input type="text" name="site" maxlength="20" value="{{ old('site', $w->site) }}" class="{{ $inp }} font-mono uppercase" placeholder="SITE01"></div>
                            <div class="col-span-4"><label class="{{ $lbl }}">Code dépôt <span class="text-red-600">*</span></label><input type="text" name="code" required maxlength="20" value="{{ old('code', $w->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="DEP-01" oninput="this.value=this.value.toUpperCase()"></div>
                            <div class="col-span-8"><label class="{{ $lbl }}">Intitulé dépôt <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="120" value="{{ old('name', $w->name) }}" class="{{ $inp }} font-medium" placeholder="Dépôt principal"></div>
                            <div class="col-span-12"><label class="{{ $lbl }}">Désignation longue</label><input type="text" name="long_name" maxlength="255" value="{{ old('long_name', $w->long_name) }}" class="{{ $inp }}" placeholder="Dépôt principal – Plateforme logistique Nord"></div>
                            <div class="col-span-6 relative">
                                <label class="{{ $lbl }}">Type de dépôt</label>
                                <select name="type" class="{{ $lk }}">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Warehouse::TYPES as $tv => $tlabel)
                                    <option value="{{ $tv }}" @selected(old('type', $w->type)===$tv)>{{ $tlabel }}</option>
                                    @endforeach
                                </select>{!! $caret !!}
                            </div>
                            <div class="col-span-6 relative">
                                <label class="{{ $lbl }}">Dépôt parent</label>
                                <select name="parent_id" class="{{ $lk }}">
                                    <option value="">—</option>
                                    @foreach($whList as $pw)
                                    <option value="{{ $pw->id }}" @selected((int) old('parent_id', $w->parent_id)===$pw->id)>{{ $pw->code }} — {{ $pw->name }}</option>
                                    @endforeach
                                </select>{!! $caret !!}
                            </div>
                            <div class="col-span-12"><label class="{{ $lbl }}">Responsable</label><input type="text" name="manager_name" maxlength="100" value="{{ old('manager_name', $w->manager_name) }}" class="{{ $inp }}" placeholder="Nom du magasinier"></div>
                        </div>
                    </section>

                    {{-- Adresse et contact --}}
                    <section class="border border-gray-200 rounded-[4px]">
                        <div class="{{ $secH }}">Adresse et contact</div>
                        <div class="p-3 grid grid-cols-12 gap-x-3 gap-y-2.5">
                            <div class="col-span-12"><label class="{{ $lbl }}">Adresse</label><input type="text" name="address" maxlength="255" value="{{ old('address', $w->address) }}" class="{{ $inp }}" placeholder="Rue, quartier, secteur…"></div>
                            <div class="col-span-12"><input type="text" name="address_complement" maxlength="255" value="{{ old('address_complement', $w->address_complement) }}" class="{{ $inp }}" placeholder="Complément d'adresse"></div>
                            <div class="col-span-4"><label class="{{ $lbl }}">Code postal</label><input type="text" name="postal_code" maxlength="20" value="{{ old('postal_code', $w->postal_code) }}" class="{{ $inp }}"></div>
                            <div class="col-span-8"><label class="{{ $lbl }}">Ville</label><input type="text" name="city" maxlength="80" value="{{ old('city', $w->city) }}" class="{{ $inp }}" placeholder="Ouagadougou"></div>
                            <div class="col-span-12"><label class="{{ $lbl }}">Pays</label><input type="text" name="country" maxlength="60" value="{{ old('country', $w->country ?? 'Burkina Faso') }}" class="{{ $inp }}"></div>
                            <div class="col-span-6"><label class="{{ $lbl }}">Téléphone</label><input type="text" name="phone" maxlength="30" value="{{ old('phone', $w->phone) }}" class="{{ $inp }}" placeholder="+226 70 00 00 00"></div>
                            <div class="col-span-6"><label class="{{ $lbl }}">Email</label><input type="email" name="email" maxlength="120" value="{{ old('email', $w->email) }}" class="{{ $inp }}" placeholder="depot@iboa.bf"></div>
                            <div class="col-span-5"><label class="{{ $lbl }}">Latitude</label><input type="number" step="0.0000001" name="latitude" value="{{ old('latitude', $w->latitude) }}" class="{{ $inp }} tabular-nums" placeholder="12.36566"></div>
                            <div class="col-span-5"><label class="{{ $lbl }}">Longitude</label><input type="number" step="0.0000001" name="longitude" value="{{ old('longitude', $w->longitude) }}" class="{{ $inp }} tabular-nums" placeholder="-1.53388"></div>
                            <div class="col-span-2 flex items-end"><span class="inline-flex items-center justify-center w-8 h-8 rounded-[3px] bg-emerald-50 text-emerald-600 border border-emerald-200" title="Localisation GPS">📍</span></div>
                        </div>
                    </section>

                    {{-- Paramètres de gestion --}}
                    <section class="border border-gray-200 rounded-[4px]">
                        <div class="{{ $secH }}">Paramètres de gestion</div>
                        <div class="p-3 divide-y divide-gray-100">
                            {!! $toggle('is_active',                (bool) old('is_active', $w->exists ? $w->is_active : true),      'Dépôt actif') !!}
                            {!! $toggle('can_stock',                (bool) old('can_stock', $w->can_stock ?? true),                 'Autoriser stock') !!}
                            {!! $toggle('can_purchase',             (bool) old('can_purchase', $w->can_purchase ?? true),           'Autoriser achat') !!}
                            {!! $toggle('can_sale',                 (bool) old('can_sale', $w->can_sale ?? false),                  'Autoriser vente') !!}
                            {!! $toggle('can_production',           (bool) old('can_production', $w->can_production ?? false),      'Autoriser production') !!}
                            {!! $toggle('can_delivery',             (bool) old('can_delivery', $w->can_delivery ?? false),          'Autoriser livraison') !!}
                            {!! $toggle('can_transfer',             (bool) old('can_transfer', $w->can_transfer ?? true),           'Autoriser transferts inter-dépôts') !!}
                            {!! $toggle('allow_negative_stock',     (bool) old('allow_negative_stock', $w->allow_negative_stock ?? false), 'Stock négatif autorisé') !!}
                            {!! $toggle('requires_quality_control', (bool) old('requires_quality_control', $w->requires_quality_control ?? false), 'Contrôle qualité requis') !!}
                            {!! $toggle('is_default',               (bool) old('is_default', $w->is_default ?? false),              'Dépôt principal') !!}
                        </div>
                    </section>
                </div>
            </div>

            {{-- ═══════════ STOCK ═══════════ --}}
            <div x-show="tab === 'stock'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px] max-w-2xl">
                    <div class="{{ $secH }}">Règles stock</div>
                    <div class="p-3 grid grid-cols-12 gap-x-3 gap-y-2.5">
                        <div class="col-span-6 relative">
                            <label class="{{ $lbl }}">Méthode de sortie</label>
                            <select name="issue_method" class="{{ $lk }}">
                                @foreach(\App\Models\Warehouse::ISSUE_METHODS as $mv => $ml)
                                <option value="{{ $mv }}" @selected(old('issue_method', $w->issue_method ?? 'fifo')===$mv)>{{ $ml }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        <div class="col-span-6 relative">
                            <label class="{{ $lbl }}">Priorité de sortie</label>
                            <select name="issue_priority" class="{{ $lk }}">
                                @foreach(\App\Models\Warehouse::ISSUE_PRIORITIES as $pv => $pl)
                                <option value="{{ $pv }}" @selected(old('issue_priority', $w->issue_priority ?? 'oldest')===$pv)>{{ $pl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        <div class="col-span-6"><label class="{{ $lbl }}">Emplacement par défaut</label><input type="text" name="default_location" maxlength="60" value="{{ old('default_location', $w->default_location) }}" class="{{ $inp }} font-mono" placeholder="R01-A-01"></div>
                        <div class="col-span-6"></div>
                        <div class="col-span-6 relative">
                            <label class="{{ $lbl }}">Dépôt qualité lié</label>
                            <select name="quality_warehouse_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($whList as $pw)
                                <option value="{{ $pw->id }}" @selected((int) old('quality_warehouse_id', $w->quality_warehouse_id)===$pw->id)>{{ $pw->code }} — {{ $pw->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        <div class="col-span-6 relative">
                            <label class="{{ $lbl }}">Dépôt rebut lié</label>
                            <select name="scrap_warehouse_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($whList as $pw)
                                <option value="{{ $pw->id }}" @selected((int) old('scrap_warehouse_id', $w->scrap_warehouse_id)===$pw->id)>{{ $pw->code }} — {{ $pw->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        <div class="col-span-6">
                            <label class="{{ $lbl }}">Capacité maximale</label>
                            <div class="flex gap-1.5">
                                <input type="number" step="0.01" name="max_capacity" value="{{ old('max_capacity', $w->max_capacity) }}" class="{{ $inp }} text-right tabular-nums" placeholder="5 000">
                                <input type="text" name="capacity_unit" maxlength="10" value="{{ old('capacity_unit', $w->capacity_unit ?? 'm²') }}" class="w-16 h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-center">
                            </div>
                        </div>
                        <div class="col-span-6">
                            <label class="{{ $lbl }}">Alerte de surcharge</label>
                            <div class="relative">
                                <input type="number" step="0.01" name="overload_alert_percent" value="{{ old('overload_alert_percent', $w->overload_alert_percent) }}" class="{{ $inp }} text-right tabular-nums pr-7" placeholder="90">
                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[12px] text-gray-400">%</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ AUTORISATIONS & FLUX ═══════════ --}}
            <div x-show="tab === 'flux'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Autorisations et flux</div>
                    <div class="p-0">
                        <p class="px-3 py-2 text-[11.5px] text-gray-500">La colonne <b>Autorisé</b> reflète les paramètres de gestion (onglet Général). Cochez <b>Validation requise</b> pour imposer un contrôle avant chaque mouvement.</p>
                        <table class="w-full text-[12.5px] border-collapse">
                            <thead class="bg-[#3b4248] text-[11px] font-semibold text-white uppercase tracking-wide">
                                <tr>
                                    <th class="px-3 py-1.5 text-left">Flux</th>
                                    <th class="px-3 py-1.5 text-center w-24">Autorisé</th>
                                    <th class="px-3 py-1.5 text-center w-32">Validation requise</th>
                                    <th class="px-3 py-1.5 text-left">Observations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($flows as $fk => [$flabel, $fdesc, $flagCol])
                                @php $allowed = (bool) old($flagCol, $w->$flagCol ?? in_array($flagCol, ['can_purchase','can_stock'])); @endphp
                                <tr class="odd:bg-white even:bg-gray-50/40">
                                    <td class="px-3 py-1 font-medium text-gray-800">{{ $flabel }}</td>
                                    <td class="px-3 py-1 text-center">
                                        @if($allowed)<span class="text-emerald-600 font-bold">✓</span>@else<span class="text-gray-300">✕</span>@endif
                                    </td>
                                    <td class="px-3 py-1 text-center">
                                        <input type="hidden" name="flow_settings[{{ $fk }}][validation]" value="0">
                                        <input type="checkbox" name="flow_settings[{{ $fk }}][validation]" value="1"
                                               @checked((bool) old("flow_settings.$fk.validation", data_get($w->flow_settings, "$fk.validation")))
                                               class="{{ $chk }}">
                                    </td>
                                    <td class="px-3 py-1 text-gray-500">{{ $fdesc }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ═══════════ COMPTABILITÉ ═══════════ --}}
            <div x-show="tab === 'compta'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px] max-w-xl">
                    <div class="{{ $secH }}">Imputation comptable &amp; analytique</div>
                    <div class="p-3 grid grid-cols-12 gap-x-3 gap-y-2.5">
                        <div class="col-span-6"><label class="{{ $lbl }}">Compte stock</label><input type="text" name="stock_account" maxlength="20" value="{{ old('stock_account', $w->stock_account) }}" class="{{ $inp }} font-mono" placeholder="370000"></div>
                        <div class="col-span-6"><label class="{{ $lbl }}">Journal stock</label><input type="text" name="stock_journal" maxlength="20" value="{{ old('stock_journal', $w->stock_journal) }}" class="{{ $inp }} font-mono" placeholder="STK"></div>
                        <div class="col-span-6"><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="30" value="{{ old('cost_center', $w->cost_center) }}" class="{{ $inp }} font-mono" placeholder="LOG11"></div>
                        <div class="col-span-6"><label class="{{ $lbl }}">Section analytique</label><input type="text" name="analytic_section" maxlength="30" value="{{ old('analytic_section', $w->analytic_section) }}" class="{{ $inp }} font-mono" placeholder="ANALY1"></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div x-show="tab === 'docs'" x-cloak class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($w->exists && $w->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#3b4248] text-white text-[11px]">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($w->attachments as $i => $att)
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
    </form>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="mt-3 bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">{{ $w->exists ? 'Dépôt ' . $w->code : 'Nouveau dépôt' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
