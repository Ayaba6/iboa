@extends('layouts.erp')
@section('title', $line->exists ? 'Modifier ligne' : 'Nouvelle ligne')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.lines.index') }}" class="hover:text-gray-700">Lignes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $line->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $l = $line;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="max-w-6xl">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $l->exists ? route('production.lines.update', $l) : route('production.lines.store') }}"
          x-data="{ tab: 'general' }">
        @csrf
        @if($l->exists)@method('PUT')@endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Ligne de production : {{ $l->exists ? 'Modification' : 'Création complète' }}
                    @if($l->exists)<span class="font-mono text-emerald-700 ml-1">{{ $l->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.lines.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Général','caracteristiques'=>'Caractéristiques','options'=>'Options'] as $tk => $tl)
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
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Code ligne <span class="text-red-600">*</span></label><input type="text" name="code" required maxlength="30" value="{{ old('code', $l->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="LIG-2026-00008"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type de ligne <span class="text-red-600">*</span></label>
                            @php $tg = old('type_ligne', $l->type_ligne ?? 'production'); @endphp
                            <div class="relative"><select name="type_ligne" class="{{ $lk }}">
                                <option value="production" @selected($tg==='production')>Production</option>
                                <option value="assemblage" @selected($tg==='assemblage')>Assemblage</option>
                                <option value="conditionnement" @selected($tg==='conditionnement')>Conditionnement</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt de production <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="depot_production_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_production_id', $l->depot_production_id)==$w->id)>{{ $w->code }} — {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Localisation</label><input type="text" name="location" maxlength="100" value="{{ old('location', $l->location) }}" class="{{ $inp }}" placeholder="Hall Production - Ligne 1"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Désignation <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="120" value="{{ old('name', $l->name) }}" class="{{ $inp }} font-medium" placeholder="Ligne Tôle Bac 5 Ondes"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Produit principal <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="product_id" class="{{ $lk }}"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $l->product_id)==$p->id)>{{ $p->reference }} — {{ $p->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Capacité nominale <span class="text-red-600">*</span></label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" min="0" name="nominal_capacity" value="{{ old('nominal_capacity', $l->nominal_capacity) }}" class="{{ $inpR }}" placeholder="1 000,00">
                                <input type="text" name="capacity_unit" maxlength="15" value="{{ old('capacity_unit', $l->capacity_unit) }}" class="{{ $inp }} w-24 font-mono" placeholder="ML/heure">
                            </div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Groupe de lignes</label><input type="text" name="line_group" maxlength="60" value="{{ old('line_group', $l->line_group) }}" class="{{ $inp }}" placeholder="Lignes Tôle Bac"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier <span class="text-red-600">*</span></label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $l->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Calendrier de travail <span class="text-red-600">*</span></label><input type="text" name="work_calendar" maxlength="30" value="{{ old('work_calendar', $l->work_calendar) }}" class="{{ $inp }} font-mono uppercase" placeholder="CAL-STD-08H"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date de mise en service</label><input type="date" name="commissioned_at" value="{{ old('commissioned_at', optional($l->commissioned_at)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Machine associée</label>
                            <div class="relative"><select name="machine_id" class="{{ $lk }}"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id', $l->machine_id)==$m->id)>{{ $m->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Site <span class="text-red-600">*</span></label><input type="text" name="site" maxlength="20" value="{{ old('site', $l->site ?? 'SITE01') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable</label>
                            <div class="relative"><select name="responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('responsible_id', $l->responsible_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Statut <span class="text-red-600">*</span></label>
                            @php $st = old('status', $l->status ?? 'active'); @endphp
                            <div class="relative"><select name="status" class="{{ $lk }}">
                                <option value="active" @selected($st==='active')>Active</option>
                                <option value="maintenance" @selected($st==='maintenance')>En maintenance</option>
                                <option value="arret" @selected($st==='arret')>À l'arrêt</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-1 flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="{{ $chk }}" {{ old('is_active', $l->is_active ?? true) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Active</span>
                            </label>
                        </div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Ligne dédiée à la production de tôles bac 5 ondes.">{{ old('notes', $l->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ CARACTÉRISTIQUES [Maquette] ═══════════ --}}
            <div id="sec-caracteristiques" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-5 gap-x-6 gap-y-4">
                        {{-- Capacité et performances --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Capacité et performances</div>
                            <div>
                                <label class="{{ $lbl }}">Capacité théorique</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.01" min="0" name="theoretical_capacity" value="{{ old('theoretical_capacity', $l->theoretical_capacity) }}" class="{{ $inpR }}" placeholder="1 200,00">
                                    <input type="text" name="theoretical_capacity_unit" maxlength="15" value="{{ old('theoretical_capacity_unit', $l->theoretical_capacity_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="ML/heure">
                                </div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Capacité pratique</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.01" min="0" name="practical_capacity" value="{{ old('practical_capacity', $l->practical_capacity) }}" class="{{ $inpR }}" placeholder="1 000,00">
                                    <input type="text" name="practical_capacity_unit" maxlength="15" value="{{ old('practical_capacity_unit', $l->practical_capacity_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="ML/heure">
                                </div>
                            </div>
                            <div><label class="{{ $lbl }}">TRS cible (%)</label><input type="number" step="0.01" min="0" max="100" name="trs_target" value="{{ old('trs_target', $l->trs_target) }}" class="{{ $inpR }}" placeholder="85,00"></div>
                            <div>
                                <label class="{{ $lbl }}">Temps de cycle moyen</label>
                                <div class="flex gap-1">
                                    <input type="number" step="0.001" min="0" name="cycle_time" value="{{ old('cycle_time', $l->cycle_time) }}" class="{{ $inpR }}" placeholder="2,40">
                                    <input type="text" name="cycle_time_unit" maxlength="15" value="{{ old('cycle_time_unit', $l->cycle_time_unit) }}" class="{{ $inp }} w-20 font-mono" placeholder="min/ML">
                                </div>
                            </div>
                        </div>
                        {{-- Organisation --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Organisation</div>
                            <div><label class="{{ $lbl }}">Nombre d'équipes</label><input type="number" step="1" min="0" max="10" name="teams_count" value="{{ old('teams_count', $l->teams_count) }}" class="{{ $inpR }}" placeholder="2"></div>
                            <div><label class="{{ $lbl }}">Équipe par défaut</label><input type="text" name="default_team" maxlength="30" value="{{ old('default_team', $l->default_team) }}" class="{{ $inp }}" placeholder="Équipe A"></div>
                            <div><label class="{{ $lbl }}">Nombre d'opérateurs par équipe</label><input type="number" step="1" min="0" max="1000" name="operators_per_team" value="{{ old('operators_per_team', $l->operators_per_team) }}" class="{{ $inpR }}" placeholder="4"></div>
                            <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
                                <input type="hidden" name="continuous_work" value="0">
                                <input type="checkbox" name="continuous_work" value="1" class="{{ $chk }}" {{ old('continuous_work', $l->continuous_work) ? 'checked' : '' }}>
                                <span class="text-[12px] font-semibold text-gray-700">Travail en continu</span>
                            </label>
                        </div>
                        {{-- Plages de production --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Plages de production</div>
                            <div><label class="{{ $lbl }}">Heure de début</label><input type="time" name="start_time" value="{{ old('start_time', $l->start_time ? substr($l->start_time, 0, 5) : '') }}" class="{{ $inp }} font-mono"></div>
                            <div><label class="{{ $lbl }}">Heure de fin</label><input type="time" name="end_time" value="{{ old('end_time', $l->end_time ? substr($l->end_time, 0, 5) : '') }}" class="{{ $inp }} font-mono"></div>
                            <div>
                                <label class="{{ $lbl }}">Pause 1 (début - fin)</label>
                                <div class="flex items-center gap-1">
                                    <input type="time" name="break1_start" value="{{ old('break1_start', $l->break1_start ? substr($l->break1_start, 0, 5) : '') }}" class="{{ $inp }} font-mono">
                                    <span class="text-gray-400">—</span>
                                    <input type="time" name="break1_end" value="{{ old('break1_end', $l->break1_end ? substr($l->break1_end, 0, 5) : '') }}" class="{{ $inp }} font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Pause 2 (début - fin)</label>
                                <div class="flex items-center gap-1">
                                    <input type="time" name="break2_start" value="{{ old('break2_start', $l->break2_start ? substr($l->break2_start, 0, 5) : '') }}" class="{{ $inp }} font-mono">
                                    <span class="text-gray-400">—</span>
                                    <input type="time" name="break2_end" value="{{ old('break2_end', $l->break2_end ? substr($l->break2_end, 0, 5) : '') }}" class="{{ $inp }} font-mono">
                                </div>
                            </div>
                        </div>
                        {{-- Contrôle et suivi --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Contrôle et suivi</div>
                            <div><label class="{{ $lbl }}">Point de contrôle qualité principal</label><input type="text" name="quality_control_point" maxlength="60" value="{{ old('quality_control_point', $l->quality_control_point) }}" class="{{ $inp }} font-mono uppercase" placeholder="PC-LIG-TB-01"></div>
                            <div>
                                <label class="{{ $lbl }}">Fréquence de contrôle</label>
                                @php $cf = old('control_frequency', $l->control_frequency ?? 'chaque_heure'); @endphp
                                <div class="relative"><select name="control_frequency" class="{{ $lk }}">
                                    <option value="chaque_heure" @selected($cf==='chaque_heure')>Chaque heure</option>
                                    <option value="chaque_lot" @selected($cf==='chaque_lot')>Chaque lot</option>
                                    <option value="echantillon" @selected($cf==='echantillon')>Échantillon</option>
                                    <option value="journalier" @selected($cf==='journalier')>Journalier</option>
                                </select>{!! $caret !!}</div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
                                <input type="hidden" name="require_production_entry" value="0">
                                <input type="checkbox" name="require_production_entry" value="1" class="{{ $chk }}" {{ old('require_production_entry', $l->exists ? $l->require_production_entry : true) ? 'checked' : '' }}>
                                <span class="text-[12px] font-semibold text-gray-700">Saisie de production obligatoire</span>
                            </label>
                        </div>
                        {{-- Identification --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Identification</div>
                            <div><label class="{{ $lbl }}">Code à barres / QR Code</label><input type="text" name="barcode" maxlength="60" value="{{ old('barcode', $l->barcode ?: $l->code) }}" class="{{ $inp }} font-mono" placeholder="LIG-2026-00008"></div>
                            <div><label class="{{ $lbl }}">N° de série</label><input type="text" name="serial_number" maxlength="60" value="{{ old('serial_number', $l->serial_number) }}" class="{{ $inp }} font-mono" placeholder="—"></div>
                            <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ optional($l->created_at)->format('d/m/Y') ?? now()->format('d/m/Y') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                            <div><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ $l->createdBy->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ OPTIONS ET PARAMÈTRES [Maquette] ═══════════ --}}
            <div id="sec-options" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Options et paramètres</div>
                    <div class="p-4 flex flex-wrap items-end gap-x-8 gap-y-3">
                        @foreach([
                            'allow_of_start'        => ['Autoriser démarrage OF', true],
                            'allow_interline'       => ['Autoriser interligne', true],
                            'scrap_management'      => ['Gestion des rebuts', true],
                            'auto_cost_allocation'  => ['Imputation automatique des coûts', true],
                            'block_if_unavailable'  => ['Blocage si indisponibilité', false],
                            'track_stoppages'       => ['Suivi des arrêts', true],
                        ] as $opt => [$optLbl, $optDef])
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-1.5">
                            <input type="hidden" name="{{ $opt }}" value="0">
                            <input type="checkbox" name="{{ $opt }}" value="1" class="{{ $chk }}" {{ old($opt, $l->exists ? $l->{$opt} : $optDef) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">{{ $optLbl }}</span>
                        </label>
                        @endforeach
                        <div class="w-32">
                            <label class="{{ $lbl }}">Priorité</label>
                            @php $pr = old('priorite', $l->priorite ?? 'normale'); @endphp
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
