@extends('layouts.erp')
@section('title', $bom->exists ? 'Modifier nomenclature' : 'Nouvelle nomenclature')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.bom.index') }}" class="hover:text-gray-700">Nomenclatures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $bom->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $b = $bom;
    $initialLines = old('lines', $bom->exists ? $bom->lines->map(fn($l)=>[
        'sequence'=>$l->sequence,'groupe'=>$l->groupe,'type_composant'=>$l->type_composant,
        'product_id'=>$l->product_id,'label'=>$l->label,'unit_id'=>$l->unit_id,
        'quantity_per_meter'=>$l->quantity_per_meter,'coef'=>$l->coef,'waste_rate'=>$l->waste_rate,
        'depot_sortie_id'=>$l->depot_sortie_id,'lot_obligatoire'=>(bool)$l->lot_obligatoire,'statut'=>$l->statut,
    ])->values()->all() : []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-6xl">
    <form method="POST" action="{{ $b->exists ? route('production.bom.update', $b) : route('production.bom.store') }}"
          x-data="{ tab: 'entete', lines: {{ Js::from($initialLines) }} }" class="space-y-3">
        @csrf
        @if($b->exists)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Nomenclature : Création complète
                    @if($b->exists)<span class="font-mono text-emerald-700 ml-1">{{ $b->name }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.bom.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['entete'=>'Entête','composants'=>'Composants'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ ENTÊTE ═══════════ --}}
            <div id="sec-entete" class="p-4 space-y-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Article composé</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Désignation nomenclature <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="150" value="{{ old('name', $b->name) }}" class="{{ $inp }} font-medium" placeholder="TOLE BAC ALU PUR 70/100 AL6"></div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Article parent <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id',$b->product_id)==$p->id)>{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site</label><input type="text" name="site" maxlength="20" value="{{ old('site', $b->site) }}" class="{{ $inp }} font-mono uppercase" placeholder="OUTLB"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Alternative</label><input type="text" name="alternative" maxlength="5" value="{{ old('alternative', $b->alternative ?? 'A') }}" class="{{ $inp }} font-mono uppercase"></div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Version majeure</label><input type="text" name="version_majeure" maxlength="5" value="{{ old('version_majeure', $b->version_majeure ?? 'A') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Version mineure</label><input type="text" name="version_mineure" maxlength="5" value="{{ old('version_mineure', $b->version_mineure ?? '0') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Unité de gestion</label>
                            <div class="relative"><select name="unite_gestion_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unite_gestion_id', $b->unite_gestion_id)==$u->id)>{{ $u->abbreviation }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Quantité de base</label><input type="number" step="0.001" min="0" name="quantite_base" value="{{ old('quantite_base', $b->quantite_base ?? 1) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut</label>
                            <div class="relative"><select name="statut" class="{{ $lk }}">
                                @php $st = old('statut', $b->statut ?? 'exploitation'); @endphp
                                <option value="exploitation" @selected($st==='exploitation')>Exploitation</option>
                                <option value="etude" @selected($st==='etude')>À l'étude</option>
                                <option value="obsolete" @selected($st==='obsolete')>Obsolète</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        {{-- [Maquette Nomenclature] code, type, dépôt production, valorisation, priorité --}}
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Code nomenclature</label><input type="text" name="code" maxlength="30" value="{{ old('code', $b->code) }}" class="{{ $inp }} font-mono" placeholder="NOM-2026-00045"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type</label>
                            <div class="relative"><select name="type_nomenclature" class="{{ $lk }}">
                                @php $tn = old('type_nomenclature', $b->type_nomenclature ?? 'produit_fabrique'); @endphp
                                <option value="produit_fabrique" @selected($tn==='produit_fabrique')>Produit fabriqué</option>
                                <option value="semi_fini" @selected($tn==='semi_fini')>Semi-fini</option>
                                <option value="kit" @selected($tn==='kit')>Kit / assemblage</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt de production</label>
                            <div class="relative"><select name="depot_production_id" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_production_id', $b->depot_production_id)==$w->id)>{{ $w->code ? $w->code.' — ' : '' }}{{ $w->name }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Méthode de valorisation</label>
                            <div class="relative"><select name="valuation_method" class="{{ $lk }}">
                                @php $vm = old('valuation_method', $b->valuation_method ?? 'cout_standard'); @endphp
                                <option value="cout_standard" @selected($vm==='cout_standard')>Coût standard</option>
                                <option value="cump" @selected($vm==='cump')>Coût moyen pondéré</option>
                                <option value="fifo" @selected($vm==='fifo')>FIFO</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Priorité</label>
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                @php $pr = old('priorite', $b->priorite ?? 'normale'); @endphp
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2 flex items-end pb-1.5">
                            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700">
                                <input type="checkbox" name="version_active" value="1" @checked(old('version_active', $b->version_active ?? true)) class="{{ $chk }}">
                                Version active
                            </label>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date référence</label><input type="date" name="date_reference" value="{{ old('date_reference', optional($b->date_reference)->format('Y-m-d')) }}" class="{{ $inp }}"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date début validité</label><input type="date" name="date_debut_validite" value="{{ old('date_debut_validite', optional($b->date_debut_validite)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date fin validité</label><input type="date" name="date_fin_validite" value="{{ old('date_fin_validite', optional($b->date_fin_validite)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Rendement &amp; qualité</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        {{-- [FIX placeholders] « Ex : » explicite — un placeholder nu (« 97,00 ») donnait
                             l'illusion d'une valeur pré-remplie alors que le champ était vide. --}}
                        <div><label class="{{ $lbl }}">Rendement standard (%)</label><input type="number" step="0.01" min="0" max="100" name="rendement_standard" value="{{ old('rendement_standard', $b->rendement_standard) }}" class="{{ $inpR }}" placeholder="Ex : 97"></div>
                        <div><label class="{{ $lbl }}">Taux de rebut théorique (%)</label><input type="number" step="0.01" min="0" max="100" name="standard_waste_rate" value="{{ old('standard_waste_rate', $b->standard_waste_rate) }}" class="{{ $inpR }}" placeholder="Ex : 3"></div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="controle_qualite" value="0">
                                <input type="checkbox" name="controle_qualite" value="1" class="{{ $chk }}" {{ old('controle_qualite', $b->controle_qualite) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Contrôle qualité obligatoire</span>
                            </label>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="{{ $chk }}" {{ old('is_active', $b->is_active ?? true) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Caractéristiques tôle + coûts standard (spécifique IBOA — repliable, ouvert par défaut) --}}
                <section x-data="{ open: true }" class="border border-gray-200 rounded-[4px]">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between {{ $secH }}">
                        <span>Caractéristiques tôle &amp; coûts standards <span class="font-normal text-emerald-700/70">— comparaison coût std/réel</span></span>
                        <svg class="w-3.5 h-3.5 text-emerald-700" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="p-4 space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                            <div><label class="{{ $lbl }}">Type de tôle</label><input type="text" name="sheet_type" maxlength="60" value="{{ old('sheet_type', $b->sheet_type) }}" class="{{ $inp }}"></div>
                            <div><label class="{{ $lbl }}">Épaisseur (mm)</label><input type="number" step="0.01" min="0" name="thickness" value="{{ old('thickness', $b->thickness) }}" class="{{ $inpR }}"></div>
                            <div><label class="{{ $lbl }}">Largeur bobine (mm)</label><input type="number" step="0.1" min="0" name="coil_width" value="{{ old('coil_width', $b->coil_width) }}" class="{{ $inpR }}"></div>
                            <div><label class="{{ $lbl }}">Largeur utile (mm)</label><input type="number" step="0.1" min="0" name="usable_width" value="{{ old('usable_width', $b->usable_width) }}" class="{{ $inpR }}"></div>
                            <div><label class="{{ $lbl }}">Conso / mètre (kg)</label><input type="number" step="0.0001" min="0" name="consumption_per_meter" value="{{ old('consumption_per_meter', $b->consumption_per_meter) }}" class="{{ $inpR }}"></div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div><label class="{{ $lbl }}">Temps machine / u (min)</label><input type="number" step="0.01" min="0" name="machine_time_per_unit" value="{{ old('machine_time_per_unit', $b->machine_time_per_unit) }}" class="{{ $inpR }}"></div>
                            <div><label class="{{ $lbl }}">MO / u</label><input type="number" step="1" min="0" name="labor_per_unit" value="{{ old('labor_per_unit', $b->labor_per_unit) }}" class="{{ $inpR }}"></div>
                            <div><label class="{{ $lbl }}">Emballage / u</label><input type="number" step="1" min="0" name="packaging_per_unit" value="{{ old('packaging_per_unit', $b->packaging_per_unit) }}" class="{{ $inpR }}"></div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach(['std_material_cost'=>'Matière std','std_labor_cost'=>'MO std','std_machine_cost'=>'Machine std','std_energy_cost'=>'Énergie std','std_maintenance_cost'=>'Maintenance std','std_packaging_cost'=>'Emballage std','std_overhead_cost'=>'Indirect std'] as $cf=>$cl)
                            <div><label class="{{ $lbl }}">{{ $cl }}</label><input type="number" step="1" min="0" name="{{ $cf }}" value="{{ old($cf, $b->$cf) }}" class="{{ $inpR }}"></div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- [Maquette Nomenclature] Propriétés --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Propriétés</div>
                    <div class="p-4 flex flex-wrap items-center gap-x-8 gap-y-3">
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="multi_niveaux" value="1" @checked(old('multi_niveaux', $b->multi_niveaux)) class="{{ $chk }}"> Nomenclature à niveaux multiples</label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="allow_sub_bom" value="1" @checked(old('allow_sub_bom', $b->allow_sub_bom)) class="{{ $chk }}"> Autoriser sous-nomenclatures</label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="lot_management" value="1" @checked(old('lot_management', $b->lot_management)) class="{{ $chk }}"> Gestion des lots</label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="serial_tracking" value="1" @checked(old('serial_tracking', $b->serial_tracking)) class="{{ $chk }}"> Suivi par numéro de série</label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="lock_modification" value="1" @checked(old('lock_modification', $b->lock_modification)) class="{{ $chk }}"> Bloquer en modification</label>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Commentaires</div>
                    <div class="p-4">
                        <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Nomenclature pour la fabrication de tôle bac alu pur 70/100 AL6…">{{ old('notes', $b->notes) }}</textarea>
                    </div>
                </section>
            </div>

            {{-- ═══════════ COMPOSANTS ═══════════ --}}
            <div id="sec-composants" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Composants</span>
                        <button type="button" @click="lines.push({sequence:(lines.length+1)*10,groupe:'',type_composant:'matiere',product_id:'',label:'',unit_id:'',quantity_per_meter:'',coef:1,waste_rate:'',depot_sortie_id:'',lot_obligatoire:false,statut:'actif'})"
                                class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter</button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-14">Séq</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-16">Groupe</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-28">Type</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Composant</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Désignation</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-16">Unité</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200 w-20">Qté nécess.</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200 w-20">Coef.</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200 w-16">Perte %</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-20">Dépôt</th>
                                <th class="text-center font-bold px-2 py-1.5 border-b border-gray-200 w-10">Lot</th>
                                <th class="w-8 border-b border-gray-200"></th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(line, i) in lines" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-1 py-1"><input type="number" min="0" :name="`lines[${i}][sequence]`" x-model="line.sequence" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="text" :name="`lines[${i}][groupe]`" x-model="line.groupe" class="{{ $inp }} h-7"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][type_composant]`" x-model="line.type_composant" class="{{ $inp }} h-7">
                                                <option value="matiere">Matière</option><option value="consommable">Consommable</option><option value="accessoire">Accessoire</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][product_id]`" x-model="line.product_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="text" :name="`lines[${i}][label]`" x-model="line.label" class="{{ $inp }} h-7"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][unit_id]`" x-model="line.unit_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->abbreviation ?? $u->name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="number" step="0.0001" min="0" :name="`lines[${i}][quantity_per_meter]`" x-model="line.quantity_per_meter" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="number" step="0.000001" min="0" :name="`lines[${i}][coef]`" x-model="line.coef" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][waste_rate]`" x-model="line.waste_rate" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][depot_sortie_id]`" x-model="line.depot_sortie_id" class="{{ $inp }} h-7 font-mono">
                                                <option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1 text-center"><input type="hidden" :name="`lines[${i}][lot_obligatoire]`" value="0"><input type="checkbox" :name="`lines[${i}][lot_obligatoire]`" value="1" x-model="line.lot_obligatoire" class="{{ $chk }}"></td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="lines.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="lines.length === 0"><td colspan="12" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucun composant — cliquez « + Ajouter ».</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
