@extends('layouts.erp')
@section('title', $inspection->exists ? 'Modifier contrôle' : 'Nouveau contrôle qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.inspections.index') }}" class="hover:text-gray-700">Contrôles qualité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $inspection->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $q = $inspection;
    $initialChars = old('characteristics', $q->exists ? $q->characteristics->map(fn($c)=>[
        'number'=>$c->number,'name'=>$c->name,'spec_min'=>$c->spec_min,'spec_max'=>$c->spec_max,
        'unit'=>$c->unit,'control_method'=>$c->control_method,'frequency'=>$c->frequency,
        'result'=>$c->result,'conformity'=>$c->conformity,
    ])->values()->all() : []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono tabular-nums';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-7xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $q->exists ? route('qualite.inspections.update', $q) : route('qualite.inspections.store') }}"
          x-data="{ chars: {{ Js::from($initialChars) }},
                    get conformes() { return this.chars.filter(c => c.conformity === 'conforme').length },
                    get nonConformes() { return this.chars.filter(c => c.conformity === 'non_conforme').length },
                    get derogations() { return this.chars.filter(c => c.conformity === 'derogation').length },
                    get conclusion() { if (!this.chars.length) return '—'; if (this.nonConformes > 0) return 'NON CONFORME'; if (this.derogations > 0) return 'ACCEPTÉ AVEC DÉROGATION'; return 'CONFORME'; } }">
        @csrf
        @if($q->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Contrôle qualité : {{ $q->exists ? 'Modification' : 'Création' }}
                    @if($q->exists)<span class="font-mono text-emerald-700 ml-1">{{ $q->reference }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('qualite.inspections.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto" x-data="{ tab: 'general' }">
                @foreach(['general'=>'Informations générales','caracteristiques'=>'Caractéristiques contrôlées','synthese'=>'Synthèse'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES [Maquette] ═══════════ --}}
            <div id="sec-general" class="p-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">N° contrôle <span class="text-red-600">*</span></label><input type="text" name="reference" maxlength="30" value="{{ old('reference', $q->reference) }}" class="{{ $inp }} font-mono uppercase" placeholder="CQ-2026-00056 (auto si vide)"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $q->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type d'inspection <span class="text-red-600">*</span></label>
                            @php $ist = old('inspection_stage', $q->inspection_stage ?? 'finale'); @endphp
                            <div class="relative"><select name="inspection_stage" class="{{ $lk }}">
                                <option value="initiale" @selected($ist==='initiale')>Initiale</option>
                                <option value="en_cours" @selected($ist==='en_cours')>En cours</option>
                                <option value="finale" @selected($ist==='finale')>Finale</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date du contrôle <span class="text-red-600">*</span></label><input type="datetime-local" name="inspected_at" value="{{ old('inspected_at', optional($q->inspected_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" class="{{ $inp }}"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type de contrôle <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="type" required class="{{ $lk }}">
                                @foreach(['produit_fini'=>'Produit fini','en_cours'=>'En cours','reception'=>'Réception'] as $k=>$v)<option value="{{ $k }}" @selected(old('type', $q->type)===$k)>{{ $v }}</option>@endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Ligne de production</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id', $q->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Poste de charge</label>
                            <div class="relative"><select name="work_center_id" class="{{ $lk }}"><option value="">—</option>@foreach($workCenters as $wc)<option value="{{ $wc->id }}" @selected(old('work_center_id', $q->work_center_id)==$wc->id)>{{ $wc->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Contrôleur <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="controller_id" class="{{ $lk }}"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('controller_id', $q->controller_id)==$e->id)>{{ $e->full_name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Référence source (OF) <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="production_order_id" class="{{ $lk }}"><option value="">—</option>@foreach($orders as $of)<option value="{{ $of->id }}" @selected(old('production_order_id', $q->production_order_id)==$of->id)>{{ $of->number }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Réception (si contrôle réception)</label>
                            <div class="relative"><select name="reception_id" class="{{ $lk }}"><option value="">—</option>@foreach($receptions as $r)<option value="{{ $r->id }}" @selected(old('reception_id', $q->reception_id)==$r->id)>{{ $r->number }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Quantité contrôlée <span class="text-red-600">*</span></label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" min="0" name="quantity_checked" value="{{ old('quantity_checked', $q->quantity_checked) }}" class="{{ $inpR }}" placeholder="250,000">
                                <input type="text" name="quantity_unit" maxlength="15" value="{{ old('quantity_unit', $q->quantity_unit) }}" class="{{ $inp }} w-16 font-mono" placeholder="MTL">
                            </div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Qté rejetée</label><input type="number" step="0.01" min="0" name="quantity_rejected" value="{{ old('quantity_rejected', $q->quantity_rejected) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            @php $st = old('status', $q->status ?? 'en_cours'); @endphp
                            <div class="relative"><select name="status" required class="{{ $lk }}">
                                <option value="en_cours" @selected($st==='en_cours')>En cours</option>
                                <option value="conforme" @selected($st==='conforme')>Conforme</option>
                                <option value="partiel" @selected($st==='partiel')>Partiel</option>
                                <option value="non_conforme" @selected($st==='non_conforme')>Non conforme</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Produit <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $q->product_id)==$p->id)>{{ $p->reference ? $p->reference.' — ' : '' }}{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Lot / N° série</label><input type="text" name="lot_number" maxlength="60" value="{{ old('lot_number', $q->lot_number) }}" class="{{ $inp }} font-mono uppercase" placeholder="LOT-2026-0706-01"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Échantillonnage</label>
                            @php $sm = old('sampling_method', $q->sampling_method ?? 'par_attributs'); @endphp
                            <div class="relative"><select name="sampling_method" class="{{ $lk }}">
                                <option value="par_attributs" @selected($sm==='par_attributs')>Par attributs</option>
                                <option value="par_variables" @selected($sm==='par_variables')>Par variables</option>
                                <option value="controle_100" @selected($sm==='controle_100')>Contrôle 100 %</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Norme / Référence</label><input type="text" name="norm_reference" maxlength="60" value="{{ old('norm_reference', $q->norm_reference) }}" class="{{ $inp }} font-mono" placeholder="NF EN 10169:2022"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Résultat global</label>
                            <input type="text" readonly :value="conclusion"
                                   :class="conclusion === 'CONFORME' ? 'text-emerald-700' : (conclusion === 'NON CONFORME' ? 'text-red-600' : 'text-orange-600')"
                                   class="w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[13px] bg-gray-50 font-bold">
                        </div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Commentaires</label><textarea name="notes" rows="2" maxlength="2000" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Contrôle qualité final avant expédition.">{{ old('notes', $q->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ CARACTÉRISTIQUES CONTRÔLÉES [Maquette] ═══════════ --}}
            <div id="sec-caracteristiques" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Caractéristiques contrôlées</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="chars.push({number:(chars.length+1),name:'',spec_min:'',spec_max:'',unit:'',control_method:'',frequency:'chaque_lot',result:'',conformity:''})"
                                    class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une caractéristique</button>
                            <button type="button" title="Importer le plan de contrôle standard tôle bac"
                                    @click="[
                                        {name:'Épaisseur',spec_min:'0,48',spec_max:'0,52',unit:'mm',control_method:'Pied à coulisse'},
                                        {name:'Largeur utile',spec_min:'995',spec_max:'1005',unit:'mm',control_method:'Mètre ruban'},
                                        {name:'Longueur',spec_min:'',spec_max:'',unit:'mm',control_method:'Mètre ruban'},
                                        {name:'Poids par mètre linéaire',spec_min:'4,70',spec_max:'5,30',unit:'kg/ml',control_method:'Balance électronique'},
                                        {name:'Aspect de surface',spec_min:'',spec_max:'',unit:'—',control_method:'Visuel'},
                                        {name:'Revêtement zinc',spec_min:'AZ100',spec_max:'AZ150',unit:'g/m²',control_method:'Épaisseur revêtement'},
                                    ].forEach(t => chars.push({number:(chars.length+1),...t,frequency:'chaque_lot',result:'',conformity:''}))"
                                    class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇪ Importer</button>
                        </div>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead>
                                <tr class="bg-[#eef5f0] text-emerald-900">
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-10">N°</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Caractéristique</th>
                                    <th colspan="2" class="text-center font-bold px-2 py-1 border-b border-gray-200">Spécification</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-16">Unité</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-40">Méthode de contrôle</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-28">Fréquence</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-24">Résultat</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-32">Conformité</th>
                                    <th rowspan="2" class="w-8 border-b border-gray-300"></th>
                                </tr>
                                <tr class="bg-[#eef5f0] text-emerald-900">
                                    <th class="text-right font-bold px-2 py-1 border-b border-gray-300 w-20">Min</th>
                                    <th class="text-right font-bold px-2 py-1 border-b border-gray-300 w-20">Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(c, i) in chars" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-1 py-1"><input type="number" min="0" :name="`characteristics[${i}][number]`" x-model="c.number" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="100" :name="`characteristics[${i}][name]`" x-model="c.name" class="{{ $inp }} h-7" placeholder="Épaisseur"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`characteristics[${i}][spec_min]`" x-model="c.spec_min" class="{{ $inpR }} h-7" placeholder="0,48"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`characteristics[${i}][spec_max]`" x-model="c.spec_max" class="{{ $inpR }} h-7" placeholder="0,52"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="15" :name="`characteristics[${i}][unit]`" x-model="c.unit" class="{{ $inp }} h-7 font-mono" placeholder="mm"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="60" :name="`characteristics[${i}][control_method]`" x-model="c.control_method" class="{{ $inp }} h-7" placeholder="Pied à coulisse"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`characteristics[${i}][frequency]`" x-model="c.frequency" class="{{ $inp }} h-7">
                                                <option value="chaque_lot">Chaque lot</option>
                                                <option value="chaque_heure">Chaque heure</option>
                                                <option value="echantillon">Échantillon</option>
                                                <option value="controle_100">Contrôle 100 %</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="text" maxlength="30" :name="`characteristics[${i}][result]`" x-model="c.result" class="{{ $inpR }} h-7" placeholder="0,50"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`characteristics[${i}][conformity]`" x-model="c.conformity" class="{{ $inp }} h-7"
                                                    :class="c.conformity === 'conforme' ? 'text-emerald-700 font-semibold' : (c.conformity === 'non_conforme' ? 'text-red-600 font-semibold' : (c.conformity === 'derogation' ? 'text-orange-600 font-semibold' : ''))">
                                                <option value="">—</option>
                                                <option value="conforme">✓ Conforme</option>
                                                <option value="non_conforme">✗ Non conforme</option>
                                                <option value="derogation">! Dérogation</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="chars.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="chars.length === 0"><td colspan="10" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucune caractéristique — cliquez « + Ajouter une caractéristique ».</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ═══════════ SYNTHÈSE [Maquette] ═══════════ --}}
            <div id="sec-synthese" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Synthèse</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Total caractéristiques</p>
                            <p class="text-[16px] font-bold text-gray-900 tabular-nums mt-0.5" x-text="chars.length"></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Conformes</p>
                            <p class="text-[16px] font-bold text-emerald-700 tabular-nums mt-0.5"><span x-text="'✓ ' + conformes"></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Non conformes</p>
                            <p class="text-[16px] font-bold text-red-600 tabular-nums mt-0.5"><span x-text="'✗ ' + nonConformes"></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Acceptées avec dérogation</p>
                            <p class="text-[16px] font-bold text-orange-600 tabular-nums mt-0.5"><span x-text="'! ' + derogations"></span></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Conclusion</p>
                            <p class="inline-flex px-3 py-1 rounded-[4px] text-[13px] font-bold mt-1"
                               :class="conclusion === 'CONFORME' ? 'bg-emerald-100 text-emerald-800' : (conclusion === 'NON CONFORME' ? 'bg-red-100 text-red-700' : (conclusion === '—' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'))"
                               x-text="conclusion"></p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
