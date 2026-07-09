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
                    pid: '{{ old('product_id', $o->product_id ?? '') }}', bomId: '{{ old('bill_of_material_id', $o->bill_of_material_id ?? '') }}',
                    qty: '{{ old('quantity_requested', $o->quantity_requested ?? '') }}',
                    ppm: '{{ old('poids_par_metre', $o->poids_par_metre ?? '') }}',
                    saveAndSubmit: false,
                    boms: {{ Js::from($bomData) }},
                    /* ── Prévisionnel LIVE ── */
                    get totalQty() { const lq = this.lines.reduce((s, l) => s + (parseFloat(l.quantity) || 0), 0); return lq > 0 ? lq : (parseFloat(this.qty) || 0); },
                    get totalMeters() { return this.lines.reduce((s, l) => s + (parseFloat(l.length) || 0) * (parseFloat(l.quantity) || 0), 0); },
                    get bom() { return this.boms[this.bomId] || null; },
                    get comps() { return this.bom ? this.bom.components : []; },
                    need(c) { return c.coef * this.totalQty; },
                    get shortage() { return this.comps.some(c => this.need(c) > c.stock); },
                    get matCost() { return this.comps.reduce((s, c) => s + this.need(c) * c.cost, 0); },
                    get laborCost() { return this.bom ? this.bom.labor_per_unit * this.totalQty : 0; },
                    get machineMin() { return this.bom ? this.bom.machine_time * this.totalQty : 0; },
                    get ops() { return this.bom && this.bom.routing ? this.bom.routing.ops : []; },
                    get opsMin() { return this.ops.reduce((s, o) => s + o.setup + o.run * this.totalQty, 0); },
                    get theoWeight() { return (parseFloat(this.ppm) || 0) * this.totalMeters; },
                    fmt(n, d = 0) { return (n || 0).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }); } }" class="space-y-3">
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
                    <input type="hidden" name="save_and_submit" :value="saveAndSubmit ? 1 : 0">
                    <button type="submit"
                            class="text-[13px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    @if(! $isEdit)
                    {{-- type=button + $nextTick : le submit natif partirait AVANT le
                         flush du binding Alpine (:value du hidden save_and_submit). --}}
                    <button type="button" @click="saveAndSubmit = true; $nextTick(() => $el.closest('form').submit())"
                            class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer + soumettre validation</button>
                    @endif
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

            {{-- Workflow de validation (§13.3) --}}
            @php
                $wfSteps = ['brouillon' => 'Brouillon', 'attente_chef' => 'Chef Atelier', 'attente_responsable' => 'Resp. Production', 'lance' => 'Lancé', 'en_cours' => 'Production', 'termine' => 'Clôturé'];
                $wfOrder = array_keys($wfSteps);
                $wfIdx   = $isEdit ? (array_search($o->status === 'matiere_allouee' ? 'brouillon' : ($o->status === 'termine_partiellement' ? 'en_cours' : $o->status), $wfOrder) ?: 0) : 0;
            @endphp
            <div class="px-4 pt-3">
                <div class="flex items-center gap-0 flex-wrap text-[11px]">
                    @foreach($wfSteps as $i => $label)
                    @php $idx = array_search($i, $wfOrder); @endphp
                    <div class="flex items-center">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold
                            {{ $idx < $wfIdx ? 'bg-emerald-100 text-emerald-700' : ($idx === $wfIdx ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-400') }}">
                            {{ $idx <= $wfIdx ? '✓' : ($idx + 1) }} {{ $label }}
                        </span>
                        @if(! $loop->last)<span class="w-5 h-px {{ $idx < $wfIdx ? 'bg-emerald-400' : 'bg-gray-200' }}"></span>@endif
                    </div>
                    @endforeach
                </div>
            </div>

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

                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Type d'OF</label>
                            <div class="relative"><select name="of_type" class="{{ $lk }}">
                                @php $ot = old('of_type', $o->of_type ?? 'standard'); @endphp
                                @foreach(['standard' => 'Fabrication standard', 'reprise' => 'Reprise', 'retouche' => 'Retouche', 'speciale_client' => 'Spéciale client'] as $v => $l)
                                <option value="{{ $v }}" @selected($ot === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Origine OF</label>
                            <div class="relative"><select name="origin" class="{{ $lk }}">
                                @php $og = old('origin', $o->origin ?? ($o->order_id ? 'commande_client' : 'manuel')); @endphp
                                @foreach(['manuel' => 'Manuel', 'commande_client' => 'Commande client', 'stock_minimum' => 'Stock minimum', 'mrp' => 'Planification MRP'] as $v => $l)
                                <option value="{{ $v }}" @selected($og === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Atelier</label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $o->atelier) }}" class="{{ $inp }}" placeholder="Atelier profilage"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable atelier</label>
                            <div class="relative"><select name="responsable_atelier_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('responsable_atelier_id',$o->responsable_atelier_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Opérateur prévu</label>
                            <div class="relative"><select name="operateur_prevu_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('operateur_prevu_id',$o->operateur_prevu_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date début prévue</label><input type="date" name="date_debut_prevue" value="{{ old('date_debut_prevue', optional($o->date_debut_prevue)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Heure</label><input type="time" name="heure_debut_prevue" value="{{ old('heure_debut_prevue', $o->heure_debut_prevue) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date fin prévue</label><input type="date" name="date_fin_prevue" value="{{ old('date_fin_prevue', optional($o->date_fin_prevue)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Heure</label><input type="time" name="heure_fin_prevue" value="{{ old('heure_fin_prevue', $o->heure_fin_prevue) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date fabrication prévue</label><input type="date" name="date_fabrication_prevue" value="{{ old('date_fabrication_prevue', optional($o->date_fabrication_prevue)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Observation</label><input type="text" name="observation" maxlength="500" value="{{ old('observation', $o->observation) }}" class="{{ $inp }}"></div>

                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Dépôt matière première</label>
                            <div class="relative"><select name="depot_matiere_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_matiere_id',$o->depot_matiere_id)==$w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Dépôt qualité</label>
                            <div class="relative"><select name="depot_qualite_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_qualite_id',$o->depot_qualite_id)==$w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Version nomenclature</label><input type="text" name="bom_version" maxlength="20" value="{{ old('bom_version', $o->bom_version ?? 'V1') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Version gamme</label><input type="text" name="routing_version" maxlength="20" value="{{ old('routing_version', $o->routing_version ?? 'V1') }}" class="{{ $inp }} font-mono"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Équipe prévue</label><input type="text" name="equipe_prevue" maxlength="60" value="{{ old('equipe_prevue', $o->equipe_prevue) }}" class="{{ $inp }}" placeholder="Équipe A"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Nb opérateurs</label><input type="number" name="nb_operateurs" min="0" max="500" value="{{ old('nb_operateurs', $o->nb_operateurs) }}" class="{{ $inpR }}"></div>
                    </div>
                </section>

                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques tôle</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-6 gap-4">
                        <div><label class="{{ $lbl }}">Type de tôle</label><input type="text" name="sheet_type" maxlength="60" value="{{ old('sheet_type', $o->sheet_type) }}" class="{{ $inp }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Profil</label>
                            <div class="relative"><select name="profil" class="{{ $lk }}">
                                @php $pf = old('profil', $o->profil); @endphp
                                <option value="">—</option>
                                @foreach(['5_ondes' => '5 ondes', '6_ondes' => '6 ondes', '7_ondes' => '7 ondes', 'bac_alu' => 'Bac alu', 'bac_galva' => 'Bac galva', 'bac_prelaque' => 'Bac prélaqué'] as $v => $l)
                                <option value="{{ $v }}" @selected($pf === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div><label class="{{ $lbl }}">Épaisseur (mm)</label><input type="number" step="0.01" min="0" name="thickness" value="{{ old('thickness', $o->thickness) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Largeur utile (mm)</label><input type="number" step="0.1" min="0" name="usable_width" value="{{ old('usable_width', $o->usable_width) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Largeur totale (mm)</label><input type="number" step="0.1" min="0" name="largeur_totale" value="{{ old('largeur_totale', $o->largeur_totale) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Longueur standard (m)</label><input type="number" step="0.01" min="0" name="longueur_standard" value="{{ old('longueur_standard', $o->longueur_standard) }}" class="{{ $inpR }}"></div>

                        <div><label class="{{ $lbl }}">Couleur</label><input type="text" name="color" maxlength="60" value="{{ old('color', $o->color) }}" class="{{ $inp }}"></div>
                        <div><label class="{{ $lbl }}">Couleur RAL</label><input type="text" name="couleur_ral" maxlength="20" value="{{ old('couleur_ral', $o->couleur_ral) }}" class="{{ $inp }} font-mono" placeholder="RAL 3000"></div>
                        <div><label class="{{ $lbl }}">Revêtement</label><input type="text" name="revetement" maxlength="60" value="{{ old('revetement', $o->revetement) }}" class="{{ $inp }}" placeholder="Prélaqué 25 µm"></div>
                        <div>
                            <label class="{{ $lbl }}">Unité de production</label>
                            <div class="relative"><select name="unite_production" class="{{ $lk }}">
                                @php $up = old('unite_production', $o->unite_production ?? 'ML'); @endphp
                                @foreach(['ML' => 'ML (mètre linéaire)', 'M2' => 'm²', 'PIECE' => 'Pièce'] as $v => $l)
                                <option value="{{ $v }}" @selected($up === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div><label class="{{ $lbl }}">Tolérance longueur (mm)</label><input type="number" step="0.01" min="0" name="tolerance_longueur" value="{{ old('tolerance_longueur', $o->tolerance_longueur) }}" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Tolérance épaisseur (mm)</label><input type="number" step="0.001" min="0" name="tolerance_epaisseur" value="{{ old('tolerance_epaisseur', $o->tolerance_epaisseur) }}" class="{{ $inpR }}"></div>

                        <div><label class="{{ $lbl }}">Qté demandée</label><input type="number" step="0.01" min="0" name="quantity_requested" x-model="qty" class="{{ $inpR }}"></div>
                        <div><label class="{{ $lbl }}">Poids par mètre (kg/m)</label><input type="number" step="0.001" min="0" name="poids_par_metre" x-model="ppm" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Poids théorique (kg)</label>
                            <input type="hidden" name="poids_theorique" :value="theoWeight.toFixed(2)">
                            <div class="{{ $inpR }} bg-gray-50 flex items-center justify-end text-gray-700" x-text="fmt(theoWeight, 2)"></div>
                        </div>
                        <div class="sm:col-span-3">
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
                        <div><label class="{{ $lbl }}">Temps de réglage (min)</label><input type="number" step="1" min="0" name="temps_reglage" value="{{ old('temps_reglage', $o->temps_reglage) }}" class="{{ $inpR }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Autoriser clôture partielle</label>
                            <input type="hidden" name="autoriser_cloture_partielle" value="0">
                            <div class="relative"><select name="autoriser_cloture_partielle" class="{{ $lk }}">
                                <option value="1" @selected(old('autoriser_cloture_partielle', $o->autoriser_cloture_partielle ?? true))>Oui</option>
                                <option value="0" @selected(! old('autoriser_cloture_partielle', $o->autoriser_cloture_partielle ?? true))>Non</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Autoriser dépassement qté</label>
                            <input type="hidden" name="autoriser_depassement_qte" value="0">
                            <div class="relative"><select name="autoriser_depassement_qte" class="{{ $lk }}">
                                <option value="0" @selected(! old('autoriser_depassement_qte', $o->autoriser_depassement_qte ?? false))>Non</option>
                                <option value="1" @selected(old('autoriser_depassement_qte', $o->autoriser_depassement_qte ?? false))>Oui</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Gamme opératoire</label>
                            <div class="{{ $inp }} bg-gray-50 flex items-center text-gray-600 truncate" x-text="bom && bom.routing ? bom.routing.name : 'Suit la nomenclature'"></div>
                        </div>
                    </div>
                </section>

                {{-- Prévisionnel LIVE (calculs automatiques) --}}
                <section class="border border-emerald-200 rounded-[4px]" x-show="totalQty > 0" x-cloak>
                    <div class="px-4 py-1.5 border-b border-emerald-200 bg-emerald-50 text-[13px] font-bold text-emerald-900">Prévisionnel (calcul automatique)</div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 text-[12px]">
                        <div><p class="{{ $lbl }}">Qté totale</p><p class="font-mono font-bold text-gray-900" x-text="fmt(totalQty, 2)"></p></div>
                        <div><p class="{{ $lbl }}">Métrage total</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(totalMeters, 2)"></span> m</p></div>
                        <div><p class="{{ $lbl }}">Poids théorique</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(theoWeight, 2)"></span> kg</p></div>
                        <div><p class="{{ $lbl }}">Coût matière est.</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(matCost)"></span> F</p></div>
                        <div><p class="{{ $lbl }}">Temps machine</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(machineMin)"></span> min</p></div>
                        <div><p class="{{ $lbl }}">Temps gamme</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(opsMin)"></span> min</p></div>
                        <div><p class="{{ $lbl }}">MO estimée</p><p class="font-mono font-bold text-gray-900"><span x-text="fmt(laborCost)"></span> F</p></div>
                        <div><p class="{{ $lbl }}">Coût prévisionnel</p><p class="font-mono font-bold text-emerald-700"><span x-text="fmt(matCost + laborCost)"></span> F</p></div>
                    </div>
                    <p class="px-4 pb-3 text-[11px] text-gray-400">Basé sur la nomenclature sélectionnée (coefficients × quantité, CMP courant) et la gamme. Le coût réel est calculé à la clôture de l'OF.</p>
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

            {{-- ═══════════ COMPOSANTS ═══════════ --}}
            <div id="sec-composants" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Composants &amp; allocation matière</div>
                    <div class="p-4 space-y-3">
                        {{-- Alerte stock insuffisant --}}
                        <div x-show="bomId && shortage" x-cloak class="bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded-[4px] text-[12.5px] font-semibold">
                            ⚠ Stock matière insuffisant pour au moins un composant — le lancement sera bloqué (dérogation valideur possible).
                        </div>

                        {{-- Tableau des composants de la nomenclature (besoin vs stock, LIVE) --}}
                        <div x-show="bomId && comps.length" x-cloak>
                            <table class="w-full text-[12.5px] border border-gray-200">
                                <thead><tr class="bg-[#3b4248] text-white text-[11px]">
                                    <th class="text-left font-semibold px-2 py-1.5">Composant</th>
                                    <th class="text-left font-semibold px-2 py-1.5">Unité</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Coef / u. produite</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Qté prévue</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Stock disponible</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Coût estimé</th>
                                    <th class="text-center font-semibold px-2 py-1.5">Statut</th>
                                </tr></thead>
                                <tbody>
                                    <template x-for="c in comps" :key="c.name">
                                        <tr class="border-b border-gray-100 last:border-0" :class="need(c) > c.stock ? 'bg-red-50' : ''">
                                            <td class="px-2 py-1.5 text-gray-900" x-text="c.name"></td>
                                            <td class="px-2 py-1.5 text-gray-600" x-text="c.unit"></td>
                                            <td class="px-2 py-1.5 text-right font-mono" x-text="c.coef"></td>
                                            <td class="px-2 py-1.5 text-right font-mono font-bold" x-text="fmt(need(c), 2)"></td>
                                            <td class="px-2 py-1.5 text-right font-mono" :class="need(c) > c.stock ? 'text-red-700 font-bold' : 'text-gray-700'" x-text="fmt(c.stock, 2)"></td>
                                            <td class="px-2 py-1.5 text-right font-mono" x-text="fmt(need(c) * c.cost) + ' F'"></td>
                                            <td class="px-2 py-1.5 text-center">
                                                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium"
                                                      :class="need(c) > c.stock ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                                      x-text="need(c) > c.stock ? 'Insuffisant' : 'Disponible'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot><tr class="font-semibold bg-gray-50">
                                    <td class="text-right text-gray-500 px-2 py-1.5" colspan="5">Coût matière estimé</td>
                                    <td class="text-right font-mono px-2 py-1.5" x-text="fmt(matCost) + ' F'"></td>
                                    <td></td>
                                </tr></tfoot>
                            </table>
                            <p class="text-[11px] text-gray-400 mt-1.5">Consommation automatique à la déclaration de production (coef × quantité déclarée, valorisée au CMP). Les bobines se consomment via le Suivi de fabrication. L'allocation matière (réservation lots/bobines) se fait au lancement.</p>
                        </div>
                        <p x-show="!bomId" x-cloak class="text-[13px] text-gray-500">Sélectionnez une <strong>nomenclature</strong> (onglet Entête) pour charger les composants.</p>

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
                        @endif
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPÉRATIONS ═══════════ --}}
            <div id="sec-operations" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Opérations / gamme</div>
                    <div class="p-4 space-y-3">
                        {{-- Tableau prévisionnel de la gamme liée à la nomenclature (LIVE) --}}
                        <div x-show="bomId && ops.length" x-cloak>
                            <p class="text-[12px] text-gray-600 mb-1.5">Gamme : <strong x-text="bom && bom.routing ? bom.routing.name : ''"></strong> — les opérations seront générées automatiquement au lancement de l'OF.</p>
                            <table class="w-full text-[12.5px] border border-gray-200">
                                <thead><tr class="bg-[#3b4248] text-white text-[11px]">
                                    <th class="text-left font-semibold px-2 py-1.5 w-12">Séq.</th>
                                    <th class="text-left font-semibold px-2 py-1.5">Opération</th>
                                    <th class="text-left font-semibold px-2 py-1.5">Poste / Machine</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Réglage (min)</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Temps / unité (min)</th>
                                    <th class="text-right font-semibold px-2 py-1.5">Temps total prévu (min)</th>
                                </tr></thead>
                                <tbody>
                                    <template x-for="op in ops" :key="op.seq">
                                        <tr class="border-b border-gray-100 last:border-0">
                                            <td class="px-2 py-1.5 font-mono text-gray-600" x-text="op.seq"></td>
                                            <td class="px-2 py-1.5 text-gray-900" x-text="op.name"></td>
                                            <td class="px-2 py-1.5 text-gray-700" x-text="op.center"></td>
                                            <td class="px-2 py-1.5 text-right font-mono" x-text="fmt(op.setup)"></td>
                                            <td class="px-2 py-1.5 text-right font-mono" x-text="op.run"></td>
                                            <td class="px-2 py-1.5 text-right font-mono font-bold" x-text="fmt(op.setup + op.run * totalQty)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot><tr class="font-semibold bg-gray-50">
                                    <td class="text-right text-gray-500 px-2 py-1.5" colspan="5">Temps gamme total</td>
                                    <td class="text-right font-mono px-2 py-1.5" x-text="fmt(opsMin) + ' min'"></td>
                                </tr></tfoot>
                            </table>
                            <p class="text-[11px] text-gray-400 mt-1.5">La clôture de l'OF est bloquée tant que les opérations ne sont pas terminées (pointage via Suivi de fabrication ou fiche OF — dérogation valideur possible).</p>
                        </div>
                        <p x-show="bomId && !ops.length" x-cloak class="text-[13px] text-gray-500">Aucune gamme opératoire liée à cette nomenclature.</p>
                        <p x-show="!bomId" x-cloak class="text-[13px] text-gray-500">Sélectionnez une <strong>nomenclature</strong> pour afficher sa gamme.</p>

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
