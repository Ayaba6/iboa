@extends('layouts.erp')
@section('title', $routing->exists ? 'Modifier gamme' : 'Nouvelle gamme opératoire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.routings.index') }}" class="hover:text-gray-700">Gammes opératoires</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $routing->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $r = $routing;
    $initialOps = old('operations', $r->exists ? $r->operations->map(fn($o)=>[
        'sequence'=>$o->sequence,'operation_number'=>$o->operation_number,'name'=>$o->name,'work_center_id'=>$o->work_center_id,
        'setup_minutes'=>$o->setup_minutes,'run_minutes_per_unit'=>$o->run_minutes_per_unit,'labor_minutes'=>$o->labor_minutes,
        'quantite_base'=>$o->quantite_base,'uo'=>$o->uo,'rendement'=>$o->rendement,
        'controle_qualite'=>(bool)$o->controle_qualite,'sous_traitance'=>(bool)$o->sous_traitance,'statut'=>$o->statut,
        'code'=>$o->code,'type_operation'=>$o->type_operation,'waiting_minutes'=>$o->waiting_minutes,
        'point_controle'=>$o->point_controle,'is_critical'=>(bool)$o->is_critical,
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
    <form method="POST" enctype="multipart/form-data"
          action="{{ $r->exists ? route('production.routings.update', $r) : route('production.routings.store') }}"
          x-data="{ tab: 'entete', ops: {{ Js::from($initialOps) }} }" class="space-y-3">
        @csrf
        @if($r->exists)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Gamme opératoire : Création complète
                    @if($r->exists)<span class="font-mono text-emerald-700 ml-1">{{ $r->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.routings.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['entete'=>'Entête','operations'=>'Opérations','postes'=>'Postes de charge','docs'=>'Documents'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ ENTÊTE ═══════════ --}}
            <div id="sec-entete" class="p-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Code gamme <span class="text-red-600">*</span></label><input type="text" name="code" required maxlength="30" value="{{ old('code', $r->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="GOP-TB-0001"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Article parent</label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $r->product_id)==$p->id)>{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Désignation <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="150" value="{{ old('name', $r->name) }}" class="{{ $inp }} font-medium" placeholder="TOLE BAC ALU PUR 701/100 AL6"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Nomenclature</label>
                            <div class="relative"><select name="bill_of_material_id" class="{{ $lk }}"><option value="">—</option>@foreach($boms as $b)<option value="{{ $b->id }}" @selected(old('bill_of_material_id', $r->bill_of_material_id)==$b->id)>{{ $b->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        {{-- [Maquette Gamme] type, unité, suivi, dépôt --}}
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type <span class="text-red-600">*</span></label>
                            @php $tg = old('type_gamme', $r->type_gamme ?? 'fabrication'); @endphp
                            <div class="relative"><select name="type_gamme" class="{{ $lk }}">
                                <option value="fabrication" @selected($tg==='fabrication')>Fabrication</option>
                                <option value="sous_traitance" @selected($tg==='sous_traitance')>Sous-traitance</option>
                                <option value="controle" @selected($tg==='controle')>Contrôle</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Unité</label>
                            <div class="relative"><select name="unite_gestion_id" class="{{ $lk }}"><option value="">—</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unite_gestion_id', $r->unite_gestion_id)==$u->id)>{{ $u->abbreviation }} — {{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Méthode de suivi <span class="text-red-600">*</span></label>
                            @php $ms = old('methode_suivi', $r->methode_suivi ?? 'par_operation'); @endphp
                            <div class="relative"><select name="methode_suivi" class="{{ $lk }}">
                                <option value="par_operation" @selected($ms==='par_operation')>Par opération</option>
                                <option value="globale" @selected($ms==='globale')>Globale</option>
                                <option value="par_lot" @selected($ms==='par_lot')>Par lot</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt de production</label>
                            <div class="relative"><select name="depot_production_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_production_id', $r->depot_production_id)==$w->id)>{{ $w->code }} — {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site <span class="text-red-600">*</span></label><input type="text" name="site" maxlength="20" value="{{ old('site', $r->site ?? 'OUTLB') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Alternative</label><input type="text" name="alternative" maxlength="5" value="{{ old('alternative', $r->alternative ?? '0') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Version majeure <span class="text-red-600">*</span></label><input type="text" name="version_majeure" maxlength="5" value="{{ old('version_majeure', $r->version_majeure ?? 'A') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Version min.</label><input type="text" name="version_mineure" maxlength="5" value="{{ old('version_mineure', $r->version_mineure ?? '0') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-1 flex items-end pb-1.5">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                <input type="hidden" name="version_active" value="0">
                                <input type="checkbox" name="version_active" value="1" class="{{ $chk }}" {{ old('version_active', $r->version_active ?? true) ? 'checked' : '' }}>
                                <span class="text-[11.5px] font-semibold text-gray-700">Version active</span>
                            </label>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date référence</label><input type="date" name="date_reference" value="{{ old('date_reference', optional($r->date_reference)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="statut" class="{{ $lk }}">
                                @php $st = old('statut', $r->statut ?? 'elaboration'); @endphp
                                <option value="elaboration" @selected($st==='elaboration')>Élaboration</option>
                                <option value="exploitation" @selected($st==='exploitation')>Exploitation</option>
                                <option value="obsolete" @selected($st==='obsolete')>Obsolète</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date début validité</label><input type="date" name="date_debut_validite" value="{{ old('date_debut_validite', optional($r->date_debut_validite)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date fin validité</label><input type="date" name="date_fin_validite" value="{{ old('date_fin_validite', optional($r->date_fin_validite)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Unité de temps <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="unite_temps" class="{{ $lk }}">
                                @php $ut = old('unite_temps', $r->unite_temps ?? 'minute'); @endphp
                                <option value="minute" @selected($ut==='minute')>Minute</option>
                                <option value="heure" @selected($ut==='heure')>Heure</option>
                                <option value="seconde" @selected($ut==='seconde')>Seconde</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Quantité base <span class="text-red-600">*</span></label><input type="number" step="0.001" min="0" name="quantite_base" value="{{ old('quantite_base', $r->quantite_base ?? 1) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Contrôle qualité obligatoire</label>
                            <input type="hidden" name="controle_qualite" value="0">
                            <div class="relative"><select name="controle_qualite" class="{{ $lk }}">
                                <option value="1" @selected(old('controle_qualite', $r->controle_qualite))>Oui</option>
                                <option value="0" @selected(! old('controle_qualite', $r->controle_qualite))>Non</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Rendement standard (%)</label><input type="number" step="0.01" min="0" max="100" name="rendement_standard" value="{{ old('rendement_standard', $r->rendement_standard) }}" class="{{ $inpR }}" placeholder="96,50"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Temps total théorique</label>
                            <input type="text" readonly x-init="$el.value = ops.reduce((s,o)=>s + (parseFloat(o.setup_minutes)||0) + (parseFloat(o.run_minutes_per_unit)||0) + (parseFloat(o.labor_minutes)||0), 0).toFixed(2) + ' min'"
                                   :value="ops.reduce((s,o)=>s + (parseFloat(o.setup_minutes)||0) + (parseFloat(o.run_minutes_per_unit)||0) + (parseFloat(o.labor_minutes)||0), 0).toFixed(2) + ' min'"
                                   class="{{ $inp }} font-mono bg-gray-50 text-gray-600">
                        </div>
                        <div class="sm:col-span-6 flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="{{ $chk }}" {{ old('is_active', $r->is_active ?? true) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Active</span>
                            </label>
                        </div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes', $r->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPÉRATIONS ═══════════ --}}
            <div id="sec-operations" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Opérations</span>
                        <button type="button" @click="ops.push({sequence:(ops.length+1),operation_number:(ops.length+1)*10,code:'OP'+((ops.length+1)*10),name:'',work_center_id:'',type_operation:'fabrication',setup_minutes:'',run_minutes_per_unit:'',labor_minutes:'',waiting_minutes:'',quantite_base:1,uo:'',rendement:'',controle_qualite:false,sous_traitance:false,point_controle:'',is_critical:false,statut:'elaboration'})"
                                class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une opération</button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-12">N°</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-16">Code op.</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Désignation opération</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-36">Poste de travail / Machine</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-24">Type</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-16">Prépar. (min)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-16">Exéc. (min)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-14">Total (min)</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-12">Unité</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-14">Attente (min)</th>
                                <th class="text-center font-bold px-2 py-1.5 border-b border-gray-300 w-8" title="Contrôle qualité">CQ</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-16">Point de contrôle</th>
                                <th class="text-center font-bold px-2 py-1.5 border-b border-gray-300 w-8" title="Opération critique">Crit.</th>
                                <th class="w-8 border-b border-gray-300"></th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(op, i) in ops" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-1 py-1"><input type="number" min="0" :name="`operations[${i}][operation_number]`" x-model="op.operation_number" class="{{ $inpR }} h-7"><input type="hidden" :name="`operations[${i}][sequence]`" :value="op.operation_number"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`operations[${i}][code]`" x-model="op.code" class="{{ $inp }} h-7 font-mono uppercase" placeholder="OP10"></td>
                                        <td class="px-1 py-1"><input type="text" :name="`operations[${i}][name]`" x-model="op.name" class="{{ $inp }} h-7" placeholder="Profilage 5 ondes"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`operations[${i}][work_center_id]`" x-model="op.work_center_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($centers as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1">
                                            <select :name="`operations[${i}][type_operation]`" x-model="op.type_operation" class="{{ $inp }} h-7">
                                                <option value="fabrication">Fabrication</option>
                                                <option value="manutention">Manutention</option>
                                                <option value="controle">Contrôle</option>
                                                <option value="sous_traitance">Sous-traitance</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" :name="`operations[${i}][setup_minutes]`" x-model="op.setup_minutes" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" :name="`operations[${i}][run_minutes_per_unit]`" x-model="op.run_minutes_per_unit" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1 text-right font-mono tabular-nums text-gray-700" x-text="((parseFloat(op.setup_minutes)||0) + (parseFloat(op.run_minutes_per_unit)||0)).toFixed(0)"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="10" :name="`operations[${i}][uo]`" x-model="op.uo" class="{{ $inp }} h-7 font-mono" placeholder="MTL"></td>
                                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" :name="`operations[${i}][waiting_minutes]`" x-model="op.waiting_minutes" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1 text-center"><input type="hidden" :name="`operations[${i}][controle_qualite]`" value="0"><input type="checkbox" :name="`operations[${i}][controle_qualite]`" value="1" x-model="op.controle_qualite" class="{{ $chk }}"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`operations[${i}][point_controle]`" x-model="op.point_controle" class="{{ $inp }} h-7 font-mono uppercase" placeholder="PC01"></td>
                                        <td class="px-1 py-1 text-center"><input type="hidden" :name="`operations[${i}][is_critical]`" value="0"><input type="checkbox" :name="`operations[${i}][is_critical]`" value="1" x-model="op.is_critical" class="{{ $chk }}"></td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="ops.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="ops.length === 0"><td colspan="14" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucune opération — cliquez « + Ajouter une opération ».</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#f7faf8] text-[11.5px] font-semibold text-gray-700 border-t border-gray-300">
                                    <td colspan="2" class="px-2 py-1.5" x-text="ops.length + ' opération(s)'"></td>
                                    <td colspan="3" class="px-2 py-1.5 text-right">Total temps préparation :
                                        <span class="font-mono tabular-nums" x-text="ops.reduce((s,o)=>s+(parseFloat(o.setup_minutes)||0),0).toFixed(0) + ' min'"></span></td>
                                    <td colspan="3" class="px-2 py-1.5 text-right">Total temps exécution :
                                        <span class="font-mono tabular-nums" x-text="ops.reduce((s,o)=>s+(parseFloat(o.run_minutes_per_unit)||0),0).toFixed(0) + ' min'"></span></td>
                                    <td colspan="6" class="px-2 py-1.5 text-right">Total temps gamme :
                                        <span class="font-mono tabular-nums text-emerald-800" x-text="ops.reduce((s,o)=>s+(parseFloat(o.setup_minutes)||0)+(parseFloat(o.run_minutes_per_unit)||0)+(parseFloat(o.waiting_minutes)||0),0).toFixed(0) + ' min'"></span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                {{-- [Maquette Gamme] Propriétés d'exécution --}}
                <section class="border border-gray-200 rounded-[4px] mt-3">
                    <div class="{{ $secH }}">Propriétés</div>
                    <div class="p-4 flex flex-wrap items-end gap-x-8 gap-y-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="allow_time_overrun" value="0">
                            <input type="checkbox" name="allow_time_overrun" value="1" class="{{ $chk }}" {{ old('allow_time_overrun', $r->allow_time_overrun) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Autoriser temps réel supérieur au standard</span>
                        </label>
                        <div class="w-36">
                            <label class="{{ $lbl }}">Tolérance rendement (%)</label>
                            <input type="number" step="0.01" min="-100" max="100" name="tolerance_rendement" value="{{ old('tolerance_rendement', $r->tolerance_rendement) }}" class="{{ $inpR }}" placeholder="-2,00">
                        </div>
                        <div class="w-36">
                            <label class="{{ $lbl }}">Gestion des rebuts</label>
                            <input type="hidden" name="gestion_rebuts" value="0">
                            <div class="relative"><select name="gestion_rebuts" class="{{ $lk }}">
                                <option value="1" @selected(old('gestion_rebuts', $r->gestion_rebuts ?? true))>Oui</option>
                                <option value="0" @selected(! old('gestion_rebuts', $r->gestion_rebuts ?? true))>Non</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="auto_transfer" value="0">
                            <input type="checkbox" name="auto_transfer" value="1" class="{{ $chk }}" {{ old('auto_transfer', $r->auto_transfer) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Transfert automatique entre opérations</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="block_on_control_fail" value="0">
                            <input type="checkbox" name="block_on_control_fail" value="1" class="{{ $chk }}" {{ old('block_on_control_fail', $r->block_on_control_fail) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Blocage si point de contrôle KO</span>
                        </label>
                    </div>
                </section>
            </div>

            {{-- ═══════════ POSTES DE CHARGE (lecture) ═══════════ --}}
            <div id="sec-postes" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Postes de charge associés</div>
                    <div class="p-4">
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Code poste</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Désignation</th>
                                <th class="text-right font-bold px-3 py-1.5 border-b border-gray-200">Capacité (h/j)</th>
                                <th class="text-right font-bold px-3 py-1.5 border-b border-gray-200">Efficience (%)</th>
                                <th class="text-center font-bold px-3 py-1.5 border-b border-gray-200">Actif</th>
                            </tr></thead>
                            <tbody>
                                @forelse($centers as $c)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-3 py-1.5 font-mono font-semibold text-gray-700">{{ $c->code }}</td>
                                    <td class="px-3 py-1.5 text-gray-700">{{ $c->name }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ $c->capacity_hours_per_day }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ $c->efficiency_rate }}</td>
                                    <td class="px-3 py-1.5 text-center"><input type="checkbox" disabled {{ $c->is_active ? 'checked' : '' }} class="w-[15px] h-[15px] rounded-[2px] text-emerald-600 border-gray-300"></td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">Aucun poste de charge configuré.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <p class="text-[11px] text-gray-500 mt-2">Les postes de charge se gèrent dans Production → Centres de travail.</p>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($r->exists && $r->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($r->attachments as $i => $att)
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
</div>
@endsection
