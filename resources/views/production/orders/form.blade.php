@extends('layouts.erp')
@section('title', $order->exists ? 'Modifier OF '.$order->number : 'Nouvel ordre de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Ordres de fabrication</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $order->exists ? $order->number : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $o = $order;
    $isEdit = $order->exists;
    $initialLines = old('lines', $isEdit ? $order->lines->map(fn($l)=>[
        'length'=>$l->length,'quantity'=>$l->quantity,'unit_id'=>$l->unit_id,'label'=>$l->label,
    ])->values()->all() : []);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chkLb = 'text-[12.5px] font-semibold text-gray-700 select-none';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-6xl">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $isEdit ? route('production.orders.update', $o) : route('production.orders.store') }}"
          x-data="{ tab: 'entete', lines: {{ Js::from($initialLines) }},
                    pid: '{{ old('product_id', $o->product_id ?? '') }}', bomId: '{{ old('bill_of_material_id', $o->bill_of_material_id ?? '') }}' }" class="space-y-3">
        @csrf
        @if($isEdit)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Ordres de fabrication : Création complète
                    @if($isEdit)<span class="font-mono text-emerald-700 ml-1">{{ $o->number }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.orders.index') }}"
                       class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach([
                    'entete' => 'Entête', 'composants' => 'Composants', 'operations' => 'Opérations',
                    'documents' => 'Documents', 'suivi' => 'Suivi', 'qualite' => 'Qualité',
                ] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'; document.getElementById('sec-{{ $key }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $label }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ ENTÊTE ═══════════ --}}
            <div id="sec-entete" class="p-4 space-y-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Entête</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site planification</label><input type="text" name="site_planification" maxlength="20" value="{{ old('site_planification', $o->site_planification) }}" class="{{ $inp }} font-mono uppercase" placeholder="OUTLB"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site production</label><input type="text" name="site_production" maxlength="20" value="{{ old('site_production', $o->site_production) }}" class="{{ $inp }} font-mono uppercase" placeholder="OUTLB"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Numéro O.F.</label><input type="text" value="{{ $o->number ?: 'Auto à la création' }}" class="{{ $inp }} font-mono bg-gray-50 text-gray-500" readonly></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Numéro optimisation</label><input type="text" name="numero_optimisation" maxlength="30" value="{{ old('numero_optimisation', $o->numero_optimisation) }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Préparation fabrication</label><input type="text" name="prepa_fabrication" maxlength="60" value="{{ old('prepa_fabrication', $o->prepa_fabrication) }}" class="{{ $inp }}"></div>

                        <div class="sm:col-span-5"><label class="{{ $lbl }}">Désignation 1</label><input type="text" name="designation" maxlength="200" value="{{ old('designation', $o->designation) }}" class="{{ $inp }} font-medium" placeholder="TÔLE BAC ALU PUR DE 70/100 AL6"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Référence OF</label><input type="text" name="reference_of" maxlength="60" value="{{ old('reference_of', $o->reference_of) }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Mode lancement</label>
                            <div class="relative"><select name="mode_lancement" class="{{ $lk }}">
                                @php $ml = old('mode_lancement', $o->mode_lancement ?? 'complet'); @endphp
                                <option value="complet" @selected($ml==='complet')>Complet</option>
                                <option value="partiel" @selected($ml==='partiel')>Partiel</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Priorité</label>
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                @php $pr = old('priorite', $o->priorite ?? 'normale'); @endphp
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="urgente" @selected($pr==='urgente')>Urgente</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Client</label>
                            <div class="relative"><select name="client_id" class="{{ $lk }}"><option value="">—</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id',$o->client_id)==$c->id)>{{ $c->trade_name ?? $c->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Commande vente</label>
                            <div class="relative"><select name="order_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($salesOrders as $so)<option value="{{ $so->id }}" @selected(old('order_id',$o->order_id)==$so->id)>{{ $so->number }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Article à lancer (produit fini)</label>
                            <div class="relative"><select name="product_id" x-model="pid" @change="bomId = ''" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id',$o->product_id)==$p->id)>{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable production</label>
                            <div class="relative"><select name="responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('responsible_id',$o->responsible_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date fabrication prévue</label><input type="date" name="date_fabrication_prevue" value="{{ old('date_fabrication_prevue', optional($o->date_fabrication_prevue)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-9"><label class="{{ $lbl }}">Observation</label><input type="text" name="observation" maxlength="500" value="{{ old('observation', $o->observation) }}" class="{{ $inp }}"></div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques tôle</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-6 gap-4">
                        <div><label class="{{ $lbl }}">Type de tôle</label><input type="text" name="sheet_type" maxlength="60" value="{{ old('sheet_type', $o->sheet_type) }}" class="{{ $inp }}"></div>
                        <div><label class="{{ $lbl }}">Épaisseur (mm)</label><input type="number" step="0.01" min="0" name="thickness" value="{{ old('thickness', $o->thickness) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Couleur</label><input type="text" name="color" maxlength="60" value="{{ old('color', $o->color) }}" class="{{ $inp }}"></div>
                        <div><label class="{{ $lbl }}">Largeur utile (mm)</label><input type="number" step="0.1" min="0" name="usable_width" value="{{ old('usable_width', $o->usable_width) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Qté demandée</label><input type="number" step="0.01" min="0" name="quantity_requested" value="{{ old('quantity_requested', $o->quantity_requested) }}" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Nomenclature</label>
                            {{-- [FIX cohérence] Seules les nomenclatures de l'article sélectionné (ou sans article)
                                 sont proposées ; changer d'article réinitialise le choix. Garde serveur en plus. --}}
                            <div class="relative"><select name="bill_of_material_id" x-model="bomId" class="{{ $lk }}"><option value="">—</option>@foreach($boms as $b)<option value="{{ $b->id }}" x-show="!pid || '{{ $b->product_id ?? '' }}' === '' || '{{ $b->product_id }}' === String(pid)">{{ $b->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Paramètres de production</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="{{ $lbl }}">Machine / ligne</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id',$o->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div><label class="{{ $lbl }}">Rendement standard (%)</label><input type="number" step="0.0001" min="0" max="9.9999" name="rendement_standard" value="{{ old('rendement_standard', $o->rendement_standard) }}" class="{{ $inpR }}" placeholder="0,9650"></div>
                        <div><label class="{{ $lbl }}">Taux de perte (%)</label><input type="number" step="0.0001" min="0" max="9.9999" name="taux_perte" value="{{ old('taux_perte', $o->taux_perte) }}" class="{{ $inpR }}" placeholder="0,0350"></div>
                        <div>
                            <label class="{{ $lbl }}">Contrôle qualité obligatoire</label>
                            <input type="hidden" name="controle_qualite_obligatoire" value="0">
                            <div class="relative"><select name="controle_qualite_obligatoire" class="{{ $lk }}">
                                <option value="1" @selected(old('controle_qualite_obligatoire', $o->controle_qualite_obligatoire ?? true))>Oui</option>
                                <option value="0" @selected(! old('controle_qualite_obligatoire', $o->controle_qualite_obligatoire ?? true))>Non</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Dépôt produit fini</label>
                            <div class="relative"><select name="depot_produit_fini_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_produit_fini_id',$o->depot_produit_fini_id)==$w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Dépôt rebut</label>
                            <div class="relative"><select name="depot_rebut_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_rebut_id',$o->depot_rebut_id)==$w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div><label class="{{ $lbl }}">Date lancement</label><input type="date" name="date_lancement" value="{{ old('date_lancement', optional($o->date_lancement)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div><label class="{{ $lbl }}">Heure lancement</label><input type="time" name="heure_lancement" value="{{ old('heure_lancement', $o->heure_lancement) }}" class="{{ $inp }}"></div>
                    </div>
                </section>

                {{-- Détail des coupes (éditable) --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Détail des coupes</span>
                        <button type="button" @click="lines.push({length:'',quantity:'',unit_id:'',label:''})" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter</button>
                    </div>
                    <div class="p-4">
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Libellé</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200">Longueur (m)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200">Quantité</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200">Total m</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Unité</th>
                                <th class="w-8 border-b border-gray-200"></th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(line, i) in lines" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-2 py-1"><input type="text" :name="`lines[${i}][label]`" x-model="line.label" class="{{ $inp }} h-7" placeholder="Bac 6m"></td>
                                        <td class="px-2 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][length]`" x-model="line.length" class="{{ $inpR }} h-7"></td>
                                        <td class="px-2 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][quantity]`" x-model="line.quantity" class="{{ $inpR }} h-7"></td>
                                        <td class="px-2 py-1 text-right font-mono tabular-nums text-gray-700" x-text="((parseFloat(line.length)||0)*(parseFloat(line.quantity)||0)).toFixed(2)"></td>
                                        <td class="px-2 py-1">
                                            <select :name="`lines[${i}][unit_id]`" x-model="line.unit_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->abbreviation ?? $u->name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-1 text-center"><button type="button" @click="lines.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="lines.length === 0"><td colspan="6" class="px-3 py-3 text-center text-gray-400 text-[12px]">Aucune coupe — la quantité demandée est prise du champ ci-dessus.</td></tr>
                            </tbody>
                            <tfoot x-show="lines.length > 0"><tr class="font-semibold bg-gray-50">
                                <td class="text-right text-gray-500 px-2 py-1.5" colspan="3">Total mètres</td>
                                <td class="text-right font-mono px-2 py-1.5" x-text="lines.reduce((s,l)=>s+(parseFloat(l.length)||0)*(parseFloat(l.quantity)||0),0).toFixed(2)"></td>
                                <td colspan="2"></td>
                            </tr></tfoot>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ═══════════ COMPOSANTS (lecture) ═══════════ --}}
            <div id="sec-composants" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Composants &amp; allocation matière</div>
                    <div class="p-4">
                        @if($isEdit && $o->relationLoaded('consumptions') && $o->consumptions->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Bobine</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200">Poids consommé</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-200">Coût</th>
                            </tr></thead>
                            <tbody>
                                @foreach($o->consumptions as $cons)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1.5 font-mono">{{ $cons->coil?->reference }}</td>
                                    <td class="px-2 py-1.5 text-right font-mono">{{ number_format($cons->weight_consumed, 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right font-mono">{{ number_format($cons->cost, 0, ',', ' ') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <p class="text-[13px] text-gray-500">Les composants sont issus de la <strong>nomenclature</strong> sélectionnée (onglet Entête). L'allocation matière (lots / bobines) se fait au lancement de l'OF.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPÉRATIONS (lecture) ═══════════ --}}
            <div id="sec-operations" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Opérations / gamme</div>
                    <div class="p-4">
                        @if($isEdit && $o->relationLoaded('operations') && $o->operations->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Poste de charge</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Opérateur</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Statut</th>
                            </tr></thead>
                            <tbody>
                                @foreach($o->operations as $op)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1.5">{{ $op->workCenter?->name }}</td>
                                    <td class="px-2 py-1.5">{{ $op->operator?->name }}</td>
                                    <td class="px-2 py-1.5">{{ $op->status }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <p class="text-[13px] text-gray-500">Les opérations sont générées depuis la gamme de production associée à la nomenclature.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div id="sec-documents" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($isEdit && $o->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($o->attachments as $i => $att)
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

            {{-- ═══════════ SUIVI (lecture) ═══════════ --}}
            <div id="sec-suivi" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Suivi de production</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-[13px]">
                        <div><p class="{{ $lbl }}">Statut</p><p class="font-semibold text-gray-800">{{ $isEdit ? ucfirst(str_replace('_',' ', $o->status)) : 'Brouillon (à créer)' }}</p></div>
                        <div><p class="{{ $lbl }}">Qté produite</p><p class="font-mono">{{ $isEdit ? number_format($o->quantity_produced, 2, ',', ' ') : '—' }}</p></div>
                        <div><p class="{{ $lbl }}">Lancé le</p><p>{{ optional($o->launched_at)->format('d/m/Y') ?: '—' }}</p></div>
                        <div><p class="{{ $lbl }}">Terminé le</p><p>{{ optional($o->finished_at)->format('d/m/Y') ?: '—' }}</p></div>
                    </div>
                    <p class="px-4 pb-4 text-[11px] text-gray-500">Workflow §13.3 : brouillon → validation Chef Atelier → Responsable Production → lancement. Le statut évolue via les actions de l'OF (page de détail).</p>
                </section>
            </div>

            {{-- ═══════════ QUALITÉ (lecture) ═══════════ --}}
            <div id="sec-qualite" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Contrôle qualité</div>
                    <div class="p-4">
                        @if($isEdit && $o->relationLoaded('qualityControls') && $o->qualityControls->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Contrôleur</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Résultat</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Date</th>
                            </tr></thead>
                            <tbody>
                                @foreach($o->qualityControls as $qc)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1.5">{{ $qc->controller?->name }}</td>
                                    <td class="px-2 py-1.5">{{ $qc->result ?? $qc->status }}</td>
                                    <td class="px-2 py-1.5">{{ optional($qc->created_at)->format('d/m/Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <p class="text-[13px] text-gray-500">Contrôle qualité obligatoire : <strong>{{ old('controle_qualite_obligatoire', $o->controle_qualite_obligatoire ?? true) ? 'Oui' : 'Non' }}</strong>. Les contrôles sont enregistrés pendant / après la production.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
