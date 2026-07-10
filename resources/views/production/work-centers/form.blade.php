@extends('layouts.erp')
@section('title', $center->exists ? 'Modifier poste de charge' : 'Nouveau poste de charge')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.work-centers.index') }}" class="hover:text-gray-700">Postes de charge</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $center->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $c = $center;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div class="max-w-5xl">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $c->exists ? route('production.work-centers.update', $c) : route('production.work-centers.store') }}"
          x-data="{ tab: 'general' }" class="space-y-3">
        @csrf
        @if($c->exists)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Poste de charge : Création complète
                    @if($c->exists)<span class="font-mono text-emerald-700 ml-1">{{ $c->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.work-centers.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Général','caracteristiques'=>'Caractéristiques','options'=>'Options','docs'=>'Documents'] as $tk => $tl)
                <button type="button" @click="tab = '{{ $tk }}'; document.getElementById('sec-{{ $tk }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                        :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
                @endforeach
            </nav>

            {{-- ═══════════ GÉNÉRAL ═══════════ --}}
            <div id="sec-general" class="p-4 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Code poste de charge <span class="text-red-600">*</span></label><input type="text" name="code" required maxlength="30" value="{{ old('code', $c->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="PC-2026-00018"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type de poste <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="type" class="{{ $lk }}">
                                @php $ty = old('type', $c->type ?? 'production'); @endphp
                                <option value="production" @selected($ty==='production')>Production</option>
                                <option value="machine" @selected($ty==='machine')>Machine</option>
                                <option value="ligne" @selected($ty==='ligne')>Ligne</option>
                                <option value="poste" @selected($ty==='poste')>Poste</option>
                                <option value="manutention" @selected($ty==='manutention')>Manutention</option>
                                <option value="controle" @selected($ty==='controle')>Contrôle</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt de production <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="depot_production_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_production_id', $c->depot_production_id)==$w->id)>{{ $w->code }} — {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Priorité</label>
                            @php $pr = old('priorite', $c->priorite ?? 'normale'); @endphp
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Désignation <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="120" value="{{ old('name', $c->name) }}" class="{{ $inp }} font-medium" placeholder="Découpage Tôle Bac"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Catégorie <span class="text-red-600">*</span></label><input type="text" name="category" maxlength="40" value="{{ old('category', $c->category) }}" class="{{ $inp }}" placeholder="Découpage"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Calendrier de travail <span class="text-red-600">*</span></label><input type="text" name="work_calendar" maxlength="30" value="{{ old('work_calendar', $c->work_calendar) }}" class="{{ $inp }} font-mono uppercase" placeholder="CAL-STD-08H"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site</label><input type="text" name="site" maxlength="20" value="{{ old('site', $c->site ?? 'OUTLB') }}" class="{{ $inp }} font-mono uppercase"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $c->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Localisation</label><input type="text" name="location" maxlength="100" value="{{ old('location', $c->location) }}" class="{{ $inp }}" placeholder="Hall Production - Zone Découpage"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Ligne / Secteur</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id', $c->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Groupe de postes</label><input type="text" name="poste_group" maxlength="60" value="{{ old('poste_group', $c->poste_group) }}" class="{{ $inp }}" placeholder="Découpage"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable</label>
                            <div class="relative"><select name="responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('responsible_id', $c->responsible_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Machine associée</label>
                            <div class="relative"><select name="machine_id" class="{{ $lk }}"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id', $c->machine_id)==$m->id)>{{ $m->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Poste similaire</label>
                            <div class="relative"><select name="similar_work_center_id" class="{{ $lk }}"><option value="">—</option>@foreach($workCenters as $wc)<option value="{{ $wc->id }}" @selected(old('similar_work_center_id', $c->similar_work_center_id)==$wc->id)>{{ $wc->code }} — {{ $wc->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Coût horaire</label><input type="number" step="0.01" min="0" name="cost_per_hour" value="{{ old('cost_per_hour', $c->cost_per_hour) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-1 flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="{{ $chk }}" {{ old('is_active', $c->is_active ?? true) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Actif</span>
                            </label>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Capacité (h/jour)</label><input type="number" step="0.01" min="0" max="24" name="capacity_hours_per_day" value="{{ old('capacity_hours_per_day', $c->capacity_hours_per_day) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Efficience (%)</label><input type="number" step="0.01" min="0" max="100" name="efficiency_rate" value="{{ old('efficiency_rate', $c->efficiency_rate) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-8"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Poste de charge pour les opérations de découpe des bobines sur ligne Tôle Bac.">{{ old('notes', $c->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ CARACTÉRISTIQUES [Maquette] ═══════════ --}}
            <div id="sec-caracteristiques" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-5 gap-x-6 gap-y-4">
                        {{-- Capacité --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Capacité</div>
                            <div>
                                <label class="{{ $lbl }}">Capacité nominale</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.01" min="0" name="nominal_capacity" value="{{ old('nominal_capacity', $c->nominal_capacity) }}" class="{{ $inpR }}" placeholder="1 200,00">
                                    <input type="text" name="capacity_unit" maxlength="15" value="{{ old('capacity_unit', $c->capacity_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="ML/heure">
                                </div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Capacité théorique</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.01" min="0" name="theoretical_capacity" value="{{ old('theoretical_capacity', $c->theoretical_capacity) }}" class="{{ $inpR }}" placeholder="9 600,00">
                                    <input type="text" name="theoretical_capacity_unit" maxlength="15" value="{{ old('theoretical_capacity_unit', $c->theoretical_capacity_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="ML/8h">
                                </div>
                            </div>
                            <div><label class="{{ $lbl }}">Taux d'utilisation standard (%)</label><input type="number" step="0.01" min="0" max="100" name="utilization_rate" value="{{ old('utilization_rate', $c->utilization_rate) }}" class="{{ $inpR }}" placeholder="85,00"></div>
                            <div><label class="{{ $lbl }}">TRS standard (%)</label><input type="number" step="0.01" min="0" max="100" name="trs_standard" value="{{ old('trs_standard', $c->trs_standard) }}" class="{{ $inpR }}" placeholder="75,00"></div>
                        </div>
                        {{-- Temps --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Temps</div>
                            <div>
                                <label class="{{ $lbl }}">Temps de cycle standard</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.001" min="0" name="cycle_time" value="{{ old('cycle_time', $c->cycle_time) }}" class="{{ $inpR }}" placeholder="2,40">
                                    <input type="text" name="cycle_time_unit" maxlength="15" value="{{ old('cycle_time_unit', $c->cycle_time_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="min/ML">
                                </div>
                            </div>
                            <div><label class="{{ $lbl }}">Temps d'installation (min)</label><input type="number" step="0.01" min="0" name="setup_time_min" value="{{ old('setup_time_min', $c->setup_time_min) }}" class="{{ $inpR }}" placeholder="30"></div>
                            <div><label class="{{ $lbl }}">Temps de réglage (min)</label><input type="number" step="0.01" min="0" name="adjustment_time_min" value="{{ old('adjustment_time_min', $c->adjustment_time_min) }}" class="{{ $inpR }}" placeholder="20"></div>
                            <div><label class="{{ $lbl }}">Temps de transfert (min)</label><input type="number" step="0.01" min="0" name="transfer_time_min" value="{{ old('transfer_time_min', $c->transfer_time_min) }}" class="{{ $inpR }}" placeholder="10"></div>
                        </div>
                        {{-- Organisation --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Organisation</div>
                            <div><label class="{{ $lbl }}">Nombre d'opérateurs</label><input type="number" step="1" min="0" max="1000" name="operators_count" value="{{ old('operators_count', $c->operators_count) }}" class="{{ $inpR }}" placeholder="2"></div>
                            <div><label class="{{ $lbl }}">Équipe par défaut</label><input type="text" name="default_team" maxlength="30" value="{{ old('default_team', $c->default_team) }}" class="{{ $inp }}" placeholder="Équipe A"></div>
                            <div>
                                <label class="{{ $lbl }}">Mode de fonctionnement</label>
                                @php $om = old('operating_mode', $c->operating_mode ?? 'continu'); @endphp
                                <div class="relative"><select name="operating_mode" class="{{ $lk }}">
                                    <option value="continu" @selected($om==='continu')>Continu</option>
                                    <option value="discontinu" @selected($om==='discontinu')>Discontinu</option>
                                    <option value="poste" @selected($om==='poste')>Par poste</option>
                                </select>{!! $caret !!}</div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
                                <input type="hidden" name="parallel_work" value="0">
                                <input type="checkbox" name="parallel_work" value="1" class="{{ $chk }}" {{ old('parallel_work', $c->parallel_work) ? 'checked' : '' }}>
                                <span class="text-[12px] font-semibold text-gray-700">Travail en parallèle</span>
                            </label>
                        </div>
                        {{-- Contrôle --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Contrôle</div>
                            <div><label class="{{ $lbl }}">Point de contrôle qualité</label><input type="text" name="quality_control_point" maxlength="60" value="{{ old('quality_control_point', $c->quality_control_point) }}" class="{{ $inp }}" placeholder="PC02 - Contrôle dimensionnel"></div>
                            <div>
                                <label class="{{ $lbl }}">Fréquence de contrôle</label>
                                @php $cf = old('control_frequency', $c->control_frequency ?? 'chaque_lot'); @endphp
                                <div class="relative"><select name="control_frequency" class="{{ $lk }}">
                                    <option value="chaque_lot" @selected($cf==='chaque_lot')>Chaque lot</option>
                                    <option value="echantillon" @selected($cf==='echantillon')>Échantillon</option>
                                    <option value="horaire" @selected($cf==='horaire')>Horaire</option>
                                    <option value="journalier" @selected($cf==='journalier')>Journalier</option>
                                </select>{!! $caret !!}</div>
                            </div>
                            <div><label class="{{ $lbl }}">Documentation associée</label><input type="text" name="documentation_ref" maxlength="60" value="{{ old('documentation_ref', $c->documentation_ref) }}" class="{{ $inp }} font-mono uppercase" placeholder="PROC-DEC-001"></div>
                            <div>
                                <label class="{{ $lbl }}">Criticité</label>
                                @php $cr = old('criticality', $c->criticality ?? 'moyenne'); @endphp
                                <div class="relative"><select name="criticality" class="{{ $lk }}">
                                    <option value="faible" @selected($cr==='faible')>Faible</option>
                                    <option value="moyenne" @selected($cr==='moyenne')>Moyenne</option>
                                    <option value="haute" @selected($cr==='haute')>Haute</option>
                                </select>{!! $caret !!}</div>
                            </div>
                        </div>
                        {{-- Identification --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Identification</div>
                            <div><label class="{{ $lbl }}">Code à barres / QR Code</label><input type="text" name="barcode" maxlength="60" value="{{ old('barcode', $c->barcode ?: $c->code) }}" class="{{ $inp }} font-mono" placeholder="PC-2026-00018"></div>
                            <div><label class="{{ $lbl }}">N° de série / Référence</label><input type="text" name="serial_number" maxlength="60" value="{{ old('serial_number', $c->serial_number) }}" class="{{ $inp }} font-mono" placeholder="—"></div>
                            <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ optional($c->created_at)->format('d/m/Y') ?? now()->format('d/m/Y') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                            <div><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ $c->createdBy->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPTIONS ET PARAMÈTRES [Maquette] ═══════════ --}}
            <div id="sec-options" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Options et paramètres</div>
                    <div class="p-4 flex flex-wrap items-center gap-x-8 gap-y-3">
                        @foreach([
                            'include_in_capacity'  => ['Prise en compte dans la capacité chargée', true],
                            'allow_overload'       => ['Autoriser surcharges', true],
                            'scrap_management'     => ['Gestion des rebuts', true],
                            'require_time_entry'   => ['Saisie obligatoire des temps', false],
                            'auto_cost_allocation' => ['Imputation automatique des coûts', true],
                            'required_on_of'       => ['Obligatoire sur OF', true],
                        ] as $opt => [$optLbl, $optDef])
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $opt }}" value="0">
                            <input type="checkbox" name="{{ $opt }}" value="1" class="{{ $chk }}" {{ old($opt, $c->exists ? $c->{$opt} : $optDef) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">{{ $optLbl }}</span>
                        </label>
                        @endforeach
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($c->exists && $c->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($c->attachments as $i => $att)
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
