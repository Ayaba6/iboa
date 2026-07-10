@extends('layouts.erp')
@section('title', $nc->exists ? 'Modifier non-conformité' : 'Nouvelle non-conformité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.non-conformities.index') }}" class="hover:text-gray-700">Non-conformités</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $nc->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $initialChars = old('characteristics', $nc->exists ? $nc->characteristics->map(fn($c)=>[
        'name'=>$c->name,'spec_min'=>$c->spec_min,'spec_max'=>$c->spec_max,'unit'=>$c->unit,
        'measured_value'=>$c->measured_value,'result'=>$c->result,
    ])->values()->all() : []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono tabular-nums';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $txa   = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-7xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $nc->exists ? route('qualite.non-conformities.update', $nc) : route('qualite.non-conformities.store') }}"
          x-data="{ chars: {{ Js::from($initialChars) }} }">
        @csrf
        @if($nc->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Non-conformité : {{ $nc->exists ? 'Modification' : 'Création' }}
                    @if($nc->exists)<span class="font-mono text-emerald-700 ml-1">{{ $nc->reference }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('qualite.non-conformities.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES [Maquette] ═══════════ --}}
            <div class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">N° non-conformité <span class="text-red-600">*</span></label><input type="text" name="reference" maxlength="30" value="{{ old('reference', $nc->reference) }}" class="{{ $inp }} font-mono uppercase" placeholder="NC-2026-00078 (auto si vide)"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type de non-conformité <span class="text-red-600">*</span></label>
                            @php $nt = old('nc_type', $nc->nc_type ?? 'produit'); @endphp
                            <div class="relative"><select name="nc_type" class="{{ $lk }}">
                                <option value="produit" @selected($nt==='produit')>Produit</option>
                                <option value="process" @selected($nt==='process')>Process</option>
                                <option value="systeme" @selected($nt==='systeme')>Système</option>
                                <option value="fournisseur" @selected($nt==='fournisseur')>Fournisseur</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $nc->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Norme / Référence</label><input type="text" name="norm_reference" maxlength="60" value="{{ old('norm_reference', $nc->norm_reference) }}" class="{{ $inp }} font-mono" placeholder="NF EN 10169:2022"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Origine <span class="text-red-600">*</span></label>
                            @php $og = old('origin', $nc->origin ?? 'controle_qualite'); @endphp
                            <div class="relative"><select name="origin" class="{{ $lk }}">
                                <option value="controle_qualite" @selected($og==='controle_qualite')>Contrôle qualité</option>
                                <option value="production" @selected($og==='production')>Production</option>
                                <option value="reclamation_client" @selected($og==='reclamation_client')>Réclamation client</option>
                                <option value="reception" @selected($og==='reception')>Réception fournisseur</option>
                                <option value="audit" @selected($og==='audit')>Audit</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Catégorie <span class="text-red-600">*</span></label>
                            @php $cg = old('category', $nc->category ?? 'dimensionnelle'); @endphp
                            <div class="relative"><select name="category" class="{{ $lk }}">
                                <option value="dimensionnelle" @selected($cg==='dimensionnelle')>Dimensionnelle</option>
                                <option value="aspect" @selected($cg==='aspect')>Aspect</option>
                                <option value="fonctionnelle" @selected($cg==='fonctionnelle')>Fonctionnelle</option>
                                <option value="documentaire" @selected($cg==='documentaire')>Documentaire</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Ligne de production</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id', $nc->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Exigence concernée</label><input type="text" name="requirement" maxlength="150" value="{{ old('requirement', $nc->requirement) }}" class="{{ $inp }}" placeholder="Largeur utile : 995 à 1005 mm"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Référence source (contrôle qualité) <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="quality_inspection_id" class="{{ $lk }}"><option value="">—</option>@foreach($inspections as $i)<option value="{{ $i->id }}" @selected(old('quality_inspection_id', $nc->quality_inspection_id)==$i->id)>{{ $i->reference }}{{ $i->inspected_at ? ' — '.$i->inspected_at->format('d/m/Y') : '' }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Criticité <span class="text-red-600">*</span></label>
                            @php $sv = old('severity', $nc->severity ?? 'mineure'); @endphp
                            <div class="relative"><select name="severity" required class="{{ $lk }}">
                                <option value="mineure" @selected($sv==='mineure')>Mineure</option>
                                <option value="majeure" @selected($sv==='majeure')>Majeure</option>
                                <option value="critique" @selected($sv==='critique')>Critique</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Poste de charge</label>
                            <div class="relative"><select name="work_center_id" class="{{ $lk }}"><option value="">—</option>@foreach($workCenters as $wc)<option value="{{ $wc->id }}" @selected(old('work_center_id', $nc->work_center_id)==$wc->id)>{{ $wc->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Valeur mesurée</label><input type="text" name="measured_value" maxlength="30" value="{{ old('measured_value', $nc->measured_value) }}" class="{{ $inpR }}" placeholder="1008 mm"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Produit <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $nc->product_id)==$p->id)>{{ $p->reference ? $p->reference.' — ' : '' }}{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Quantité non conforme <span class="text-red-600">*</span></label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" min="0" name="nc_quantity" value="{{ old('nc_quantity', $nc->nc_quantity) }}" class="{{ $inpR }}" placeholder="120,000">
                                <input type="text" name="nc_quantity_unit" maxlength="15" value="{{ old('nc_quantity_unit', $nc->nc_quantity_unit) }}" class="{{ $inp }} w-16 font-mono" placeholder="MTL">
                            </div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Machine</label>
                            <div class="relative"><select name="machine_id" class="{{ $lk }}"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id', $nc->machine_id)==$m->id)>{{ $m->code ? $m->code.' — ' : '' }}{{ $m->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Écart</label>
                            <div class="flex gap-1">
                                <input type="text" name="deviation" maxlength="20" value="{{ old('deviation', $nc->deviation) }}" class="{{ $inpR }}" placeholder="+3">
                                <input type="text" name="deviation_unit" maxlength="15" value="{{ old('deviation_unit', $nc->deviation_unit) }}" class="{{ $inp }} w-16 font-mono" placeholder="mm">
                            </div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Lot / N° série</label><input type="text" name="lot_number" maxlength="60" value="{{ old('lot_number', $nc->lot_number) }}" class="{{ $inp }} font-mono uppercase" placeholder="LOT-2026-0706-01"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date de détection <span class="text-red-600">*</span></label><input type="date" name="detected_at" value="{{ old('detected_at', optional($nc->detected_at)->format('Y-m-d') ?? date('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Détectée par</label>
                            <div class="relative"><select name="detected_by_id" class="{{ $lk }}"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('detected_by_id', $nc->detected_by_id)==$e->id)>{{ $e->full_name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            @php $st = old('status', $nc->status ?? 'ouverte'); @endphp
                            <div class="relative"><select name="status" required class="{{ $lk }}">
                                <option value="ouverte" @selected($st==='ouverte')>Ouverte</option>
                                <option value="en_cours" @selected($st==='en_cours')>En cours</option>
                                <option value="cloturee" @selected($st==='cloturee')>Clôturée</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Commentaires</label><input type="text" name="comments" maxlength="300" value="{{ old('comments', $nc->comments) }}" class="{{ $inp }}" placeholder="Mesure effectuée au pied à coulisse sur 3 points."></div>

                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Intitulé <span class="text-red-600">*</span></label><input type="text" name="title" required maxlength="200" value="{{ old('title', $nc->title) }}" class="{{ $inp }} font-medium" placeholder="Largeur hors tolérance"></div>
                        <div class="sm:col-span-8"><label class="{{ $lbl }}">Description de la non-conformité <span class="text-red-600">*</span></label><textarea name="description" rows="2" maxlength="2000" class="{{ $txa }}" placeholder="Largeur mesurée hors tolérance supérieure à la spécification.">{{ old('description', $nc->description) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DÉTAILS [Maquette] : caractéristiques + évaluation + classification ═══════════ --}}
            <div class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-12 gap-4">
                {{-- Caractéristiques en défaut --}}
                <section class="border border-gray-200 rounded-[4px] xl:col-span-7">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Caractéristiques en défaut</span>
                        <button type="button" @click="chars.push({name:'',spec_min:'',spec_max:'',unit:'',measured_value:'',result:''})"
                                class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une caractéristique</button>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead>
                                <tr class="bg-[#eef5f0] text-emerald-900">
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Caractéristique</th>
                                    <th colspan="2" class="text-center font-bold px-2 py-1 border-b border-gray-200">Spécification</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-14">Unité</th>
                                    <th rowspan="2" class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-24">Valeur mesurée</th>
                                    <th rowspan="2" class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-32">Résultat</th>
                                    <th rowspan="2" class="w-8 border-b border-gray-300"></th>
                                </tr>
                                <tr class="bg-[#eef5f0] text-emerald-900">
                                    <th class="text-right font-bold px-2 py-1 border-b border-gray-300 w-16">Min</th>
                                    <th class="text-right font-bold px-2 py-1 border-b border-gray-300 w-16">Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(c, i) in chars" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-1 py-1"><input type="text" maxlength="100" :name="`characteristics[${i}][name]`" x-model="c.name" class="{{ $inp }} h-7" placeholder="Largeur utile"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`characteristics[${i}][spec_min]`" x-model="c.spec_min" class="{{ $inpR }} h-7" placeholder="995"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="20" :name="`characteristics[${i}][spec_max]`" x-model="c.spec_max" class="{{ $inpR }} h-7" placeholder="1005"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="15" :name="`characteristics[${i}][unit]`" x-model="c.unit" class="{{ $inp }} h-7 font-mono" placeholder="mm"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="30" :name="`characteristics[${i}][measured_value]`" x-model="c.measured_value" class="{{ $inpR }} h-7" placeholder="1008"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`characteristics[${i}][result]`" x-model="c.result" class="{{ $inp }} h-7"
                                                    :class="c.result === 'conforme' ? 'text-emerald-700 font-semibold' : (c.result === 'non_conforme' ? 'text-red-600 font-semibold' : '')">
                                                <option value="">—</option>
                                                <option value="conforme">✓ Conforme</option>
                                                <option value="non_conforme">✗ Non conforme</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="chars.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="chars.length === 0"><td colspan="7" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucune caractéristique — cliquez « + Ajouter une caractéristique ».</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- Évaluation --}}
                <section class="border border-gray-200 rounded-[4px] xl:col-span-2">
                    <div class="{{ $secH }}">Évaluation</div>
                    <div class="p-4 space-y-3">
                        @foreach([
                            'impact_quality' => ['Impact qualité', 'eleve'],
                            'impact_cost'    => ['Impact coût', 'moyen'],
                            'impact_delay'   => ['Impact délai', 'moyen'],
                            'safety_risk'    => ['Risque sécurité', 'faible'],
                        ] as $f => [$fl, $fd])
                        <div>
                            <label class="{{ $lbl }}">{{ $fl }}</label>
                            @php $v = old($f, $nc->{$f} ?? $fd); @endphp
                            <div class="relative"><select name="{{ $f }}" class="{{ $lk }}">
                                <option value="eleve" @selected($v==='eleve')>Élevé</option>
                                <option value="moyen" @selected($v==='moyen')>Moyen</option>
                                <option value="faible" @selected($v==='faible')>Faible</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        @endforeach
                    </div>
                </section>

                {{-- Classification --}}
                <section class="border border-gray-200 rounded-[4px] xl:col-span-3">
                    <div class="{{ $secH }}">Classification</div>
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="{{ $lbl }}">Classification NC</label>
                            @php $cl = old('classification', $nc->classification ?? 'interne'); @endphp
                            <div class="relative"><select name="classification" class="{{ $lk }}">
                                <option value="interne" @selected($cl==='interne')>Interne</option>
                                <option value="externe" @selected($cl==='externe')>Externe</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        @foreach([
                            'client_claim'       => 'Réclamation client',
                            'production_stopped' => 'Arrêt de production',
                            'isolation_needed'   => 'Besoin d\'isolement',
                            'product_isolated'   => 'Produit isolé',
                        ] as $b => $bl)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $b }}" value="0">
                            <input type="checkbox" name="{{ $b }}" value="1" class="{{ $chk }}" {{ old($b, $nc->{$b}) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">{{ $bl }}</span>
                        </label>
                        @endforeach
                    </div>
                </section>
            </div>

            {{-- ═══════════ DISPOSITION IMMÉDIATE [Maquette] ═══════════ --}}
            <div class="p-4 pt-0">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Disposition immédiate</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Action immédiate</label>
                            @php $ia = old('immediate_action', $nc->immediate_action ?? 'isolement_du_lot'); @endphp
                            <div class="relative"><select name="immediate_action" class="{{ $lk }}">
                                <option value="isolement_du_lot" @selected($ia==='isolement_du_lot')>Isolement du lot</option>
                                <option value="tri" @selected($ia==='tri')>Tri</option>
                                <option value="retouche" @selected($ia==='retouche')>Retouche</option>
                                <option value="rebut" @selected($ia==='rebut')>Rebut</option>
                                <option value="derogation" @selected($ia==='derogation')>Acceptation dérogatoire</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Quantité isolée</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" min="0" name="isolated_quantity" value="{{ old('isolated_quantity', $nc->isolated_quantity) }}" class="{{ $inpR }}" placeholder="120,000">
                                <input type="text" name="isolated_quantity_unit" maxlength="15" value="{{ old('isolated_quantity_unit', $nc->isolated_quantity_unit) }}" class="{{ $inp }} w-16 font-mono" placeholder="MTL">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Lieu d'isolement</label>
                            <input type="text" name="isolation_location" maxlength="60" value="{{ old('isolation_location', $nc->isolation_location) }}" class="{{ $inp }}" placeholder="Zone quarantaine">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Responsable</label>
                            <div class="relative"><select name="disposition_responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('disposition_responsible_id', $nc->disposition_responsible_id)==$e->id)>{{ $e->full_name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date d'isolement</label><input type="date" name="isolated_at" value="{{ old('isolated_at', optional($nc->isolated_at)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Commentaires disposition</label><input type="text" name="disposition_comments" maxlength="300" value="{{ old('disposition_comments', $nc->disposition_comments) }}" class="{{ $inp }}" placeholder="Lot identifié et étiqueté."></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ ACTION CORRECTIVE (CAPA) ═══════════ --}}
            <div class="p-4 pt-0">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Action corrective (CAPA)</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-8"><label class="{{ $lbl }}">Action corrective <span class="text-[10px] text-gray-400">(obligatoire pour clôturer)</span></label><textarea name="corrective_action" rows="2" maxlength="2000" class="{{ $txa }}">{{ old('corrective_action', $nc->corrective_action) }}</textarea></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Responsable CAPA</label>
                            <div class="relative"><select name="responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('responsible_id', $nc->responsible_id)==$e->id)>{{ $e->full_name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Échéance</label><input type="date" name="due_date" value="{{ old('due_date', optional($nc->due_date)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
