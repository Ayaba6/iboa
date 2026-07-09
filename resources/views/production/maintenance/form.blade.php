@extends('layouts.erp')
@section('title', $maintenance->exists ? 'Modifier intervention' : 'Nouvelle intervention')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.maintenance.index') }}" class="hover:text-gray-700">Maintenance</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $maintenance->exists ? 'Modifier' : 'Nouvelle intervention' }}</span>
@endsection

@section('content')
@php
    $mt = $maintenance;
    $initialOps = old('operations', $mt->exists ? $mt->operations->map(fn($o)=>[
        'number'=>$o->number,'code'=>$o->code,'name'=>$o->name,'technician_id'=>$o->technician_id,
        'planned_duration_min'=>$o->planned_duration_min,
        'start_time'=>$o->start_time ? substr($o->start_time,0,5) : '',
        'end_time'=>$o->end_time ? substr($o->end_time,0,5) : '',
        'status'=>$o->status,'is_critical'=>(bool)$o->is_critical,
    ])->values()->all() : []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-6xl space-y-3">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $mt->exists ? route('production.maintenance.update', $mt) : route('production.maintenance.store') }}"
          x-data="{ tab: 'general', ops: {{ Js::from($initialOps) }} }">
        @csrf
        @if($mt->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    {{ $mt->exists ? 'Intervention : Modification' : 'Nouvelle intervention' }}
                    @if($mt->exists && $mt->code)<span class="font-mono text-emerald-700 ml-1">{{ $mt->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.maintenance.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Informations générales','operations'=>'Opérations','parametres'=>'Paramètres et sécurité','resume'=>'Résumé technique'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES ═══════════ --}}
            <div id="sec-general" class="p-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Code intervention <span class="text-red-600">*</span></label><input type="text" name="code" maxlength="30" value="{{ old('code', $mt->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="INT-2026-00018"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Équipement / Machine <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="machine_id" required class="{{ $lk }}"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id', $mt->machine_id)==$m->id)>{{ $m->code ? $m->code.' — ' : '' }}{{ $m->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $mt->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt pièces</label>
                            <div class="relative"><select name="depot_pieces_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_pieces_id', $mt->depot_pieces_id)==$w->id)>{{ $w->code }} — {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type d'intervention <span class="text-red-600">*</span></label>
                            @php $ty = old('type', $mt->type ?? 'preventive'); @endphp
                            <div class="relative"><select name="type" required class="{{ $lk }}">
                                <option value="preventive" @selected($ty==='preventive')>Préventive</option>
                                <option value="curative" @selected($ty==='curative')>Curative</option>
                                <option value="corrective" @selected($ty==='corrective')>Corrective</option>
                                <option value="ameliorative" @selected($ty==='ameliorative')>Améliorative</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Ligne de production</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id', $mt->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Source demande</label>
                            @php $rs = old('request_source', $mt->request_source ?? 'panne_machine'); @endphp
                            <div class="relative"><select name="request_source" class="{{ $lk }}">
                                <option value="panne_machine" @selected($rs==='panne_machine')>Panne machine</option>
                                <option value="plan_preventif" @selected($rs==='plan_preventif')>Plan préventif</option>
                                <option value="demande_production" @selected($rs==='demande_production')>Demande production</option>
                                <option value="controle_qualite" @selected($rs==='controle_qualite')>Contrôle qualité</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Niveau urgence</label>
                            @php $ug = old('urgency_level', $mt->urgency_level ?? 'normal'); @endphp
                            <div class="relative"><select name="urgency_level" class="{{ $lk }}">
                                <option value="normal" @selected($ug==='normal')>Normal</option>
                                <option value="degrade" @selected($ug==='degrade')>Fonctionnement dégradé</option>
                                <option value="arret_production" @selected($ug==='arret_production')>Arrêt production</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site <span class="text-red-600">*</span></label><input type="text" name="site" maxlength="20" value="{{ old('site', $mt->site ?? 'SITE01') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Demandeur</label>
                            <div class="relative"><select name="requester_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('requester_id', $mt->requester_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable maintenance <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="operator_id" class="{{ $lk }}"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('operator_id', $mt->operator_id)==$e->id)>{{ $e->full_name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date prévue <span class="text-red-600">*</span></label><input type="date" name="planned_at" value="{{ old('planned_at', optional($mt->planned_at)->format('Y-m-d') ?? date('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">N° OT / Référence</label><input type="text" name="ot_reference" maxlength="30" value="{{ old('ot_reference', $mt->ot_reference) }}" class="{{ $inp }} font-mono uppercase" placeholder="OT-2026-00112"></div>

                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Priorité <span class="text-red-600">*</span></label>
                            @php $pr = old('priorite', $mt->priorite ?? 'normale'); @endphp
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            @php $st = old('status', $mt->status ?? 'planifie'); @endphp
                            <div class="relative"><select name="status" required class="{{ $lk }}">
                                <option value="brouillon" @selected($st==='brouillon')>Brouillon</option>
                                <option value="planifie" @selected($st==='planifie')>Planifiée</option>
                                <option value="en_cours" @selected($st==='en_cours')>En cours</option>
                                <option value="termine" @selected($st==='termine')>Terminée</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Heure début prévue</label><input type="time" name="planned_start_time" value="{{ old('planned_start_time', $mt->planned_start_time ? substr($mt->planned_start_time, 0, 5) : '') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Heure fin prévue</label><input type="time" name="planned_end_time" value="{{ old('planned_end_time', $mt->planned_end_time ? substr($mt->planned_end_time, 0, 5) : '') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Intitulé <span class="text-red-600">*</span></label><input type="text" name="title" required maxlength="200" value="{{ old('title', $mt->title) }}" class="{{ $inp }} font-medium" placeholder="Arrêt profileuse — vérification moteur"></div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" maxlength="2000" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Intervention curative suite à un arrêt de la profileuse. Vérification moteur, capteurs et système hydraulique.">{{ old('notes', $mt->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPÉRATIONS [Maquette] ═══════════ --}}
            <div id="sec-operations" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Opérations</span>
                        <button type="button" @click="ops.push({number:(ops.length+1),code:'OP-'+String(ops.length+1).padStart(3,'0'),name:'',technician_id:'',planned_duration_min:'',start_time:'',end_time:'',status:'planifiee',is_critical:false})"
                                class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une opération</button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-10">N°</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-20">Code opération</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Désignation opération</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-36">Technicien</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-20">Durée prévue (min)</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-20">Début</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-20">Fin</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-24">Statut</th>
                                <th class="text-center font-bold px-2 py-1.5 border-b border-gray-300 w-12">Critique</th>
                                <th class="w-8 border-b border-gray-300"></th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(op, i) in ops" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-1 py-1"><input type="number" min="0" :name="`operations[${i}][number]`" x-model="op.number" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`operations[${i}][code]`" x-model="op.code" class="{{ $inp }} h-7 font-mono uppercase" placeholder="OP-001"></td>
                                        <td class="px-1 py-1"><input type="text" :name="`operations[${i}][name]`" x-model="op.name" class="{{ $inp }} h-7" placeholder="Diagnostic panne"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`operations[${i}][technician_id]`" x-model="op.technician_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="number" step="1" min="0" :name="`operations[${i}][planned_duration_min]`" x-model="op.planned_duration_min" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="time" :name="`operations[${i}][start_time]`" x-model="op.start_time" class="{{ $inp }} h-7 font-mono"></td>
                                        <td class="px-1 py-1"><input type="time" :name="`operations[${i}][end_time]`" x-model="op.end_time" class="{{ $inp }} h-7 font-mono"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`operations[${i}][status]`" x-model="op.status" class="{{ $inp }} h-7">
                                                <option value="planifiee">Planifiée</option>
                                                <option value="en_cours">En cours</option>
                                                <option value="terminee">Terminée</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1 text-center"><input type="hidden" :name="`operations[${i}][is_critical]`" value="0"><input type="checkbox" :name="`operations[${i}][is_critical]`" value="1" x-model="op.is_critical" class="{{ $chk }}"></td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="ops.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="ops.length === 0"><td colspan="10" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucune opération — cliquez « + Ajouter une opération ».</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#f7faf8] text-[11.5px] font-semibold text-gray-700 border-t border-gray-300">
                                    <td colspan="4" class="px-2 py-1.5" x-text="ops.length + ' opération(s)'"></td>
                                    <td colspan="6" class="px-2 py-1.5 text-right">Durée totale prévue :
                                        <span class="font-mono tabular-nums text-emerald-800" x-text="ops.reduce((s,o)=>s+(parseFloat(o.planned_duration_min)||0),0).toFixed(0) + ' min'"></span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ═══════════ PARAMÈTRES ET SÉCURITÉ [Maquette] ═══════════ --}}
            <div id="sec-parametres" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Paramètres et sécurité</div>
                    <div class="p-4 flex flex-wrap items-end gap-x-8 gap-y-3">
                        @foreach([
                            'machine_stop_required'           => ['Arrêt machine obligatoire', true],
                            'electrical_lockout'              => ['Consignation électrique', false],
                            'allow_subcontracting'            => ['Autoriser sous-traitance', false],
                            'maintenance_validation_required' => ['Validation maintenance requise', true],
                            'quality_check_after'             => ['Contrôle qualité après intervention', false],
                        ] as $opt => [$optLbl, $optDef])
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-1.5">
                            <input type="hidden" name="{{ $opt }}" value="0">
                            <input type="checkbox" name="{{ $opt }}" value="1" class="{{ $chk }}" {{ old($opt, $mt->exists ? $mt->{$opt} : $optDef) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">{{ $optLbl }}</span>
                        </label>
                        @endforeach
                        <div class="w-36"><label class="{{ $lbl }}">Coût estimé (FCFA)</label><input type="number" step="1" min="0" name="cost" value="{{ old('cost', $mt->cost) }}" class="{{ $inpR }}" placeholder="125 000"></div>
                        <div class="w-36">
                            <label class="{{ $lbl }}">Durée estimée totale</label>
                            <input type="text" readonly :value="ops.reduce((s,o)=>s+(parseFloat(o.planned_duration_min)||0),0).toFixed(0) + ' min'"
                                   class="w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[13px] bg-gray-50 text-right font-mono text-gray-600">
                        </div>
                        <div class="w-28"><label class="{{ $lbl }}">Arrêt (min)</label><input type="number" step="1" min="0" name="downtime_minutes" value="{{ old('downtime_minutes', $mt->downtime_minutes) }}" class="{{ $inpR }}"></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ RÉSUMÉ TECHNIQUE [Maquette] ═══════════ --}}
            <div id="sec-resume" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Résumé technique</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-x-4 gap-y-3">
                        <div><label class="{{ $lbl }}">Symptôme</label><input type="text" name="symptom" maxlength="150" value="{{ old('symptom', $mt->symptom) }}" class="{{ $inp }}" placeholder="Arrêt intempestif profileuse"></div>
                        <div><label class="{{ $lbl }}">Cause probable</label><input type="text" name="probable_cause" maxlength="150" value="{{ old('probable_cause', $mt->probable_cause) }}" class="{{ $inp }}" placeholder="Défaillance capteur de position"></div>
                        <div><label class="{{ $lbl }}">Pièce critique</label><input type="text" name="critical_part" maxlength="150" value="{{ old('critical_part', $mt->critical_part) }}" class="{{ $inp }}" placeholder="Capteur fin de course"></div>
                        <div><label class="{{ $lbl }}">Impact production</label><input type="text" name="production_impact" maxlength="150" value="{{ old('production_impact', $mt->production_impact) }}" class="{{ $inp }}" placeholder="Ligne arrêtée"></div>
                    </div>
                </section>
            </div>
        </div>
    </form>

    {{-- [CDC §13.8] Pièces de rechange consommées --}}
    @if($mt->exists)
    <div class="bg-white rounded-[4px] border border-gray-300">
        <div class="{{ $secH }}">Pièces de rechange consommées</div>
        <div class="p-4 space-y-4">
            @if($mt->parts->isNotEmpty())
            <table class="w-full text-[12.5px] border border-gray-200">
                <thead><tr class="bg-[#eef5f0]">
                    <th class="text-left px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-300">Article</th>
                    <th class="text-right px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-300">Qté</th>
                    <th class="text-right px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-300">Coût unit.</th>
                    <th class="text-right px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-300">Total</th>
                    <th class="border-b border-gray-300"></th>
                </tr></thead>
                <tbody>
                    @foreach($mt->parts as $p)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40">
                        <td class="px-3 py-1.5">{{ $p->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($p->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($p->unit_cost, 0, ',', ' ') }} F</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ number_format($p->quantity * $p->unit_cost, 0, ',', ' ') }} F</td>
                        <td class="px-3 py-1.5 text-right">
                            <form method="POST" action="{{ route('production.maintenance.parts.destroy', $p) }}" data-confirm="Retirer cette pièce ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">✕</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-[12.5px] text-gray-400">Aucune pièce enregistrée pour cette intervention.</p>
            @endif

            @if($mt->status !== 'termine')
            <form method="POST" action="{{ route('production.maintenance.parts.store', $mt) }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-gray-100">
                @csrf
                <div class="flex-1 min-w-[180px]">
                    <label class="{{ $lbl }}">Article</label>
                    <div class="relative"><select name="product_id" required class="{{ $lk }}">
                        <option value="">— Choisir —</option>
                        @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->reference }})</option>@endforeach
                    </select>{!! $caret !!}</div>
                </div>
                <div class="w-40">
                    <label class="{{ $lbl }}">Dépôt</label>
                    <div class="relative"><select name="warehouse_id" required class="{{ $lk }}">
                        @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                    </select>{!! $caret !!}</div>
                </div>
                <div class="w-24">
                    <label class="{{ $lbl }}">Qté</label>
                    <input type="number" name="quantity" step="0.001" min="0.001" required class="{{ $inpR }}">
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-4 py-1.5 rounded-[4px]">Ajouter</button>
            </form>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
