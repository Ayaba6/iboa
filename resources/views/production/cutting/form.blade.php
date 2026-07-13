@extends('layouts.erp')
@section('title', $optimization->exists ? 'Modifier optimisation' : 'Nouvelle optimisation de découpe')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.cutting') }}" class="hover:text-gray-700">Optimisation de découpe</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $optimization->exists ? 'Modifier' : 'Création' }}</span>
@endsection

@section('content')
@php
    $o = $optimization;
    $initialLines = old('lines', $o->exists ? $o->lines->map(fn($l)=>[
        'order_reference'=>$l->order_reference,'client'=>$l->client,'product_id'=>$l->product_id,
        'requested_length_m'=>$l->requested_length_m,'quantity'=>$l->quantity,
        'priorite'=>$l->priorite,'delivery_date'=>optional($l->delivery_date)->format('Y-m-d'),'status'=>$l->status,
    ])->values()->all() : []);
    $plan = $o->plan;

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-7xl space-y-3">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ $o->exists ? route('production.cutting.update', $o) : route('production.cutting.store') }}"
          x-data="{ tab: 'demandes', rows: {{ Js::from($initialLines) }}, importing: false,
                    async importOrders() {
                        this.importing = true;
                        try {
                            const pid = document.querySelector('[name=product_id]')?.value || '';
                            const res = await fetch('{{ route('production.cutting.import-lines') }}' + (pid ? '?product_id=' + pid : ''), { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            if (!data.length) { alert('Aucune ligne de commande en cours à importer.'); return; }
                            data.forEach(l => this.rows.push(l));
                        } finally { this.importing = false; }
                    } }">
        @csrf
        @if($o->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Optimisation de découpe : {{ $o->exists ? 'Modification' : 'Création complète' }}
                    @if($o->exists && $o->code)<span class="font-mono text-emerald-700 ml-1">{{ $o->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.cutting') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['demandes'=>'Demandes','resultats'=>'Résultats','plan'=>'Plan de coupe','parametres'=>'Paramètres'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES ═══════════ --}}
            <div class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Code optimisation <span class="text-red-600">*</span></label><input type="text" name="code" maxlength="30" value="{{ old('code', $o->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="OPT-2026-00014"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Bobine / matière <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="coil_id" class="{{ $lk }}"><option value="">—</option>@foreach($coils as $cl)<option value="{{ $cl->id }}" @selected(old('coil_id', $o->coil_id)==$cl->id)>{{ $cl->reference }} ({{ $cl->width }} mm / {{ $cl->thickness }} mm)</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Largeur utile (mm) <span class="text-red-600">*</span></label><input type="number" step="0.01" min="0" name="useful_width" value="{{ old('useful_width', $o->useful_width) }}" class="{{ $inpR }}" placeholder="1000"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            @php $st = old('status', $o->status ?? 'brouillon'); @endphp
                            <div class="relative"><select name="status" class="{{ $lk }}">
                                <option value="brouillon" @selected($st==='brouillon')>Brouillon</option>
                                <option value="optimisee" @selected($st==='optimisee')>Optimisée</option>
                                <option value="validee" @selected($st==='validee')>Validée</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Priorité <span class="text-red-600">*</span></label>
                            @php $pr = old('priorite', $o->priorite ?? 'normale'); @endphp
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site <span class="text-red-600">*</span></label><input type="text" name="site" maxlength="20" value="{{ old('site', $o->site ?? 'SITE01') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-4">
                            <label class="{{ $lbl }}">Produit à fabriquer <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $o->product_id)==$p->id)>{{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Longueur standard (m) <span class="text-red-600">*</span></label><input type="number" step="0.01" min="0" name="standard_length" value="{{ old('standard_length', $o->standard_length ?? 6) }}" class="{{ $inpR }}" placeholder="6,00"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Méthode <span class="text-red-600">*</span></label>
                            @php $me = old('method', $o->method ?? 'automatique'); @endphp
                            <div class="relative"><select name="method" class="{{ $lk }}">
                                <option value="automatique" @selected($me==='automatique')>Optimisation automatique</option>
                                <option value="manuelle" @selected($me==='manuelle')>Manuelle</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date d'exécution <span class="text-red-600">*</span></label><input type="date" name="execution_date" value="{{ old('execution_date', optional($o->execution_date)->format('Y-m-d') ?? date('Y-m-d')) }}" class="{{ $inp }}"></div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $o->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Ligne de production <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $li)<option value="{{ $li->id }}" @selected(old('production_line_id', $o->production_line_id)==$li->id)>{{ $li->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Type d'optimisation <span class="text-red-600">*</span></label>
                            @php $to = old('type_optimisation', $o->type_optimisation ?? 'decoupe_bobines'); @endphp
                            <div class="relative"><select name="type_optimisation" class="{{ $lk }}">
                                <option value="decoupe_bobines" @selected($to==='decoupe_bobines')>Découpe bobines</option>
                                <option value="refendage" @selected($to==='refendage')>Refendage</option>
                                <option value="coupe_longueur" @selected($to==='coupe_longueur')>Coupe à longueur</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="{{ $lbl }}">Profil <span class="text-red-600">*</span></label>
                            @php $pf = old('profil', $o->profil ?? '5_ondes'); @endphp
                            <div class="relative"><select name="profil" class="{{ $lk }}">
                                <option value="5_ondes" @selected($pf==='5_ondes')>5 ondes</option>
                                <option value="7_ondes" @selected($pf==='7_ondes')>7 ondes</option>
                                <option value="plane" @selected($pf==='plane')>Plane</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Épaisseur (mm) <span class="text-red-600">*</span></label><input type="number" step="0.01" min="0" name="thickness" value="{{ old('thickness', $o->thickness) }}" class="{{ $inpR }}" placeholder="0,50"></div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Largeur bobine (mm) <span class="text-red-600">*</span></label><input type="number" step="0.01" min="0" name="coil_width" value="{{ old('coil_width', $o->coil_width) }}" class="{{ $inpR }}" placeholder="1250"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="1" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Optimisation du plan de coupe pour commandes clients et stock.">{{ old('notes', $o->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DEMANDES [Maquette] ═══════════ --}}
            <div id="sec-demandes" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Demandes</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="rows.push({order_reference:'',client:'',product_id:'',requested_length_m:'',quantity:'',priorite:'normale',delivery_date:'',status:'planifiee'})"
                                    class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une ligne</button>
                            <button type="button" @click="importOrders()" :disabled="importing"
                                    class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]" x-text="importing ? 'Import…' : '⇪ Importer'"></button>
                            @if($o->exists)
                            <button type="submit" form="run-form" class="text-[12px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-1 rounded-[3px]">▶ Lancer optimisation</button>
                            @endif
                            @if($o->exists && in_array($o->status, ['optimisee','validee','planifiee']))
                            <button type="submit" form="close-form"
                                    onclick="return confirm('Clôturer la découpe ?{{ $o->valorize_offcuts && (float) $o->reusable_offcut_m > 0 ? ' '.number_format((float) $o->reusable_offcut_m, 2, ',', ' ').' m de chute réutilisable seront ré-entrés en stock (dépôt Chutes).' : '' }}');"
                                    class="text-[12px] font-semibold text-white bg-[#3b4248] hover:bg-black px-3 py-1 rounded-[3px]">✔ Clôturer</button>
                            @elseif($o->status === 'cloturee')
                            <span class="text-[12px] font-semibold text-emerald-700">✔ Clôturée</span>
                            @endif
                        </div>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-10">N°</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-32">Référence commande</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-36">Client</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Article</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-24">Longueur demandée (m)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-16">Quantité</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300 w-20">Total métrage (m)</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-20">Priorité</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-28">Date livraison</th>
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300 w-24">Statut</th>
                                <th class="w-8 border-b border-gray-300"></th>
                            </tr></thead>
                            <tbody>
                                <template x-for="(r, i) in rows" :key="i">
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="px-2 py-1 text-gray-500 tabular-nums" x-text="i+1"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="40" :name="`lines[${i}][order_reference]`" x-model="r.order_reference" class="{{ $inp }} h-7 font-mono" placeholder="CMD-2026-01587"></td>
                                        <td class="px-1 py-1"><input type="text" maxlength="100" :name="`lines[${i}][client]`" x-model="r.client" class="{{ $inp }} h-7" placeholder="Client"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][product_id]`" x-model="r.product_id" class="{{ $inp }} h-7">
                                                <option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="number" step="0.01" min="0" :name="`lines[${i}][requested_length_m]`" x-model="r.requested_length_m" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1"><input type="number" step="1" min="0" :name="`lines[${i}][quantity]`" x-model="r.quantity" class="{{ $inpR }} h-7"></td>
                                        <td class="px-1 py-1 text-right font-mono tabular-nums text-gray-700" x-text="((parseFloat(r.requested_length_m)||0)*(parseInt(r.quantity)||0)).toFixed(2)"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][priorite]`" x-model="r.priorite" class="{{ $inp }} h-7">
                                                <option value="haute">Haute</option>
                                                <option value="normale">Normale</option>
                                                <option value="basse">Basse</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1"><input type="date" :name="`lines[${i}][delivery_date]`" x-model="r.delivery_date" class="{{ $inp }} h-7"></td>
                                        <td class="px-1 py-1">
                                            <select :name="`lines[${i}][status]`" x-model="r.status" class="{{ $inp }} h-7">
                                                <option value="planifiee">Planifiée</option>
                                                <option value="confirmee">Confirmée</option>
                                            </select>
                                        </td>
                                        <td class="px-1 py-1 text-center"><button type="button" @click="rows.splice(i,1)" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                                <tr x-show="rows.length === 0"><td colspan="11" class="px-3 py-4 text-center text-gray-400 text-[12px]">Aucune demande — cliquez « + Ajouter une ligne ».</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#f7faf8] text-[11.5px] font-semibold text-gray-700 border-t border-gray-300">
                                    <td colspan="4" class="px-2 py-1.5" x-text="rows.length + ' demande(s)'"></td>
                                    <td colspan="7" class="px-2 py-1.5 text-right">Métrage demandé total :
                                        <span class="font-mono tabular-nums text-emerald-800" x-text="rows.reduce((s,r)=>s+((parseFloat(r.requested_length_m)||0)*(parseInt(r.quantity)||0)),0).toFixed(2) + ' m'"></span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ═══════════ RÉSULTAT + SCHÉMA [Maquette] ═══════════ --}}
            @if($plan)
            <div id="sec-resultats" class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-2 gap-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Résultat d'optimisation</div>
                    <div class="p-4">
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-4">
                            @foreach([
                                ['Métrage demandé total', number_format((float) $o->total_requested_m, 2, ',', ' ').' m', 'text-gray-900'],
                                ['Métrage optimisé', number_format((float) $o->optimized_m, 2, ',', ' ').' m', 'text-gray-900'],
                                ['Rendement matière', number_format((float) $o->material_yield, 1, ',', ' ').' %', 'text-emerald-700'],
                                ['Chute réutilisable', number_format((float) $o->reusable_offcut_m, 2, ',', ' ').' m', 'text-blue-600'],
                                ['Rebut', number_format((float) $o->scrap_m, 2, ',', ' ').' m', 'text-orange-600'],
                                ['Nombre de coupes', number_format((int) $o->cuts_count, 0, ',', ' '), 'text-gray-900'],
                                ['Bobines utilisées', number_format((int) $o->coils_used, 0, ',', ' '), 'text-gray-900'],
                                ['Bandes / bobine (refente)', (int) $o->strips_per_coil ?: '—', 'text-gray-900'],
                                ['Rendement largeur', (float) $o->width_yield > 0 ? number_format((float) $o->width_yield, 1, ',', ' ').' %' : '—', 'text-emerald-700'],
                            ] as [$kl, $kv, $kc])
                            <div class="border border-gray-200 rounded-[4px] px-2 py-2">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide leading-tight">{{ $kl }}</p>
                                <p class="text-[14px] font-bold {{ $kc }} tabular-nums mt-0.5">{{ $kv }}</p>
                            </div>
                            @endforeach
                        </div>

                        {{-- Détail bobines (maquette) --}}
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-2 py-1.5 border-b border-gray-300">Bobine</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300">Largeur (mm)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300">Métrage dispo (m)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300">Métrage utilisé (m)</th>
                                <th class="text-right font-bold px-2 py-1.5 border-b border-gray-300">Chute (m)</th>
                            </tr></thead>
                            <tbody>
                                @foreach(array_slice($plan['bars'] ?? [], 0, 10) as $bi => $bar)
                                <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40">
                                    <td class="px-2 py-1.5 font-mono text-gray-700">{{ $o->coil?->reference ? $o->coil->reference.' #'.($bi+1) : 'Bobine '.($bi+1) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format((float) ($o->coil_width ?: 0), 0, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format((float) $plan['stock_length'], 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold">{{ number_format((float) $bar['used'], 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums {{ ($bar['offcut_type'] ?? '') === 'reutilisable' ? 'text-blue-600' : ($bar['waste'] > 0 ? 'text-orange-600' : 'text-emerald-600') }}">
                                        {{ number_format((float) $bar['waste'], 2, ',', ' ') }}
                                        @if(($bar['offcut_type'] ?? '') === 'reutilisable')<span class="text-[10px] text-blue-500">♻</span>@endif
                                    </td>
                                </tr>
                                @endforeach
                                @if(count($plan['bars'] ?? []) > 10)
                                <tr><td colspan="5" class="px-2 py-1.5 text-[11px] text-gray-400">… {{ count($plan['bars']) - 10 }} autre(s) bobine(s)</td></tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#f7faf8] text-[11.5px] font-bold text-gray-800 border-t border-gray-300">
                                    <td class="px-2 py-1.5" colspan="2">Total ({{ $plan['bars_count'] }} bobine{{ $plan['bars_count'] > 1 ? 's' : '' }})</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($plan['bars_count'] * (float) $plan['stock_length'], 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format((float) $plan['used'], 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-orange-600">{{ number_format((float) $plan['waste'], 2, ',', ' ') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section id="sec-plan" class="border border-gray-200 rounded-[4px] scroll-mt-28">
                    <div class="{{ $secH }}">Schéma de coupe</div>
                    <div class="p-4 space-y-3">
                        @foreach(array_slice($plan['bars'] ?? [], 0, 8) as $idx => $bar)
                        <div>
                            <div class="flex items-center justify-between text-[11.5px] text-gray-500 mb-1">
                                <span class="font-semibold">Bobine {{ $idx+1 }} ({{ number_format((float) ($o->coil_width ?: 0), 0, ',', ' ') }} mm)</span>
                                <span>Chute : <span class="{{ $bar['waste']>0 ? 'text-orange-600 font-semibold' : 'text-emerald-600' }} tabular-nums">{{ number_format($bar['waste'], 2, ',', ' ') }} m</span></span>
                            </div>
                            <div class="flex h-8 rounded-[3px] overflow-hidden border border-gray-300">
                                @foreach($bar['cuts'] as $c)
                                <div class="border-r border-white flex items-center justify-center text-[10px] text-emerald-900 font-medium" style="width: {{ max(4, $c / $plan['stock_length'] * 100) }}%; background:#bbf7d0;">{{ number_format($c, 2, ',', ' ') }} m</div>
                                @endforeach
                                @if($bar['waste'] > 0)
                                <div class="flex items-center justify-center text-[9.5px] text-orange-800" style="width: {{ max(3, $bar['waste'] / $plan['stock_length'] * 100) }}%; background:#fed7aa;">Chute {{ number_format($bar['waste'], 2, ',', ' ') }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @if(count($plan['bars'] ?? []) > 8)
                        <p class="text-[11px] text-gray-400">… {{ count($plan['bars']) - 8 }} autre(s) bobine(s)</p>
                        @endif
                        <div class="flex items-center gap-4 pt-1 text-[11px] text-gray-500">
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-[2px]" style="background:#bbf7d0"></span> Coupe utile</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-[2px]" style="background:#fed7aa"></span> Chute</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-[2px]" style="background:#bfdbfe"></span> Réserve</span>
                        </div>
                    </div>
                </section>
            </div>
            @endif

            {{-- ═══════════ PARAMÈTRES ET CONTRAINTES [Maquette] ═══════════ --}}
            <div id="sec-parametres" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Paramètres et contraintes</div>
                    <div class="p-4 flex flex-wrap items-end gap-x-8 gap-y-3">
                        @foreach([
                            'allow_order_mixing'        => ['Autoriser mélange de commandes', true],
                            'respect_client_lot'        => ['Respect lot client', false],
                            'group_by_color'            => ['Grouper par couleur', true],
                            'optimize_by_delivery_date' => ['Optimiser par date livraison', true],
                            'valorize_offcuts'          => ['Valoriser chutes', true],
                        ] as $opt => [$optLbl, $optDef])
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-1.5">
                            <input type="hidden" name="{{ $opt }}" value="0">
                            <input type="checkbox" name="{{ $opt }}" value="1" class="{{ $chk }}" {{ old($opt, $o->exists ? $o->{$opt} : $optDef) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">{{ $optLbl }}</span>
                        </label>
                        @endforeach
                        <div class="w-44">
                            <label class="{{ $lbl }}">Longueur mini chute réutilisable (m)</label>
                            <input type="number" step="0.01" min="0" name="min_reusable_offcut" value="{{ old('min_reusable_offcut', $o->min_reusable_offcut ?? 1) }}" class="{{ $inpR }}" placeholder="1,00">
                        </div>
                        <div class="w-36">
                            <label class="{{ $lbl }}">Tolérance coupe (mm)</label>
                            <input type="number" step="0.01" min="0" name="cut_tolerance_mm" value="{{ old('cut_tolerance_mm', $o->cut_tolerance_mm ?? 5) }}" class="{{ $inpR }}" placeholder="5">
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>

    @if($o->exists)
    <form id="run-form" method="POST" action="{{ route('production.cutting.run', $o) }}">@csrf</form>
    @if($o->exists)<form id="close-form" method="POST" action="{{ route('production.cutting.close', $o) }}">@csrf</form>@endif
    @endif
</div>
@endsection
