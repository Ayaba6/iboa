@extends('layouts.erp')
@section('title', $machine->exists ? 'Modifier machine' : 'Nouvelle machine')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.machines.index') }}" class="hover:text-gray-700">Machines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $machine->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $m = $machine;
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
          action="{{ $m->exists ? route('production.machines.update', $m) : route('production.machines.store') }}"
          x-data="{ tab: 'general' }" class="space-y-3">
        @csrf
        @if($m->exists)@method('PUT')@endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]"><ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="bg-white border border-gray-300 rounded-[4px]">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">
                    Machine : Création complète
                    @if($m->exists)<span class="font-mono text-emerald-700 ml-1">{{ $m->code }}</span>@endif
                </h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('production.machines.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
                @foreach(['general'=>'Général','technique'=>'Technique','couts'=>'Coûts & maintenance','docs'=>'Documents'] as $tk => $tl)
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
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Code machine <span class="text-red-600">*</span></label><input type="text" name="code" required maxlength="30" value="{{ old('code', $m->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="MAC-2026-00015"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Désignation <span class="text-red-600">*</span></label><input type="text" name="name" required maxlength="120" value="{{ old('name', $m->name) }}" class="{{ $inp }} font-medium" placeholder="Profileuse 5 ondes"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Type de machine <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="type" required class="{{ $lk }}">
                                @php $ty = old('type', $m->type); @endphp
                                @foreach(['profilage'=>'Machine de profilage','decoupe'=>'Machine de découpe','mixte'=>'Mixte'] as $tv=>$tl2)
                                <option value="{{ $tv }}" @selected($ty===$tv)>{{ $tl2 }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Catégorie <span class="text-red-600">*</span></label><input type="text" name="category" maxlength="40" value="{{ old('category', $m->category) }}" class="{{ $inp }}" placeholder="Profileuse"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Atelier</label><input type="text" name="atelier" maxlength="60" value="{{ old('atelier', $m->atelier) }}" class="{{ $inp }}" placeholder="Atelier Tôle Bac"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Secteur / Ligne</label>
                            <div class="relative"><select name="production_line_id" class="{{ $lk }}"><option value="">—</option>@foreach($lines as $l)<option value="{{ $l->id }}" @selected(old('production_line_id', $m->production_line_id)==$l->id)>{{ $l->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Localisation</label><input type="text" name="location" maxlength="100" value="{{ old('location', $m->location) }}" class="{{ $inp }}" placeholder="Hall Production - Ligne 1"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Pays d'origine</label><input type="text" name="country_origin" maxlength="40" value="{{ old('country_origin', $m->country_origin) }}" class="{{ $inp }}" placeholder="Chine"></div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Constructeur / Fournisseur</label><input type="text" name="manufacturer" maxlength="80" value="{{ old('manufacturer', $m->manufacturer) }}" class="{{ $inp }}" placeholder="BMS Machines"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Marque</label><input type="text" name="brand" maxlength="60" value="{{ old('brand', $m->brand) }}" class="{{ $inp }}" placeholder="BMS"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Modèle</label><input type="text" name="model" maxlength="80" value="{{ old('model', $m->model) }}" class="{{ $inp }} font-mono" placeholder="BMS-5ONDES-1200"></div>
                        <div class="sm:col-span-4"><label class="{{ $lbl }}">Numéro de série</label><input type="text" name="serial_number" maxlength="80" value="{{ old('serial_number', $m->serial_number) }}" class="{{ $inp }} font-mono" placeholder="SN-BMS-5O-1200-025"></div>

                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">État <span class="text-red-600">*</span></label>
                            <div class="relative"><select name="status" required class="{{ $lk }}">
                                @php $st = old('status', $m->status ?? 'active'); @endphp
                                @foreach(['active'=>'En service','maintenance'=>'En maintenance','arret'=>'À l\'arrêt'] as $sv=>$sl)
                                <option value="{{ $sv }}" @selected($st===$sv)>{{ $sl }}</option>
                                @endforeach
                            </select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Responsable</label>
                            <div class="relative"><select name="responsible_id" class="{{ $lk }}"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('responsible_id', $m->responsible_id)==$u->id)>{{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Capacité nominale</label><input type="number" step="0.01" min="0" name="nominal_capacity" value="{{ old('nominal_capacity', $m->nominal_capacity) }}" class="{{ $inpR }}" placeholder="20,00"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Unité capacité</label><input type="text" name="capacity_unit" maxlength="15" value="{{ old('capacity_unit', $m->capacity_unit) }}" class="{{ $inp }} font-mono" placeholder="ML/min"></div>
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Unité principale</label>
                            <div class="relative"><select name="unit_id" class="{{ $lk }}"><option value="">—</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id', $m->unit_id)==$u->id)>{{ $u->abbreviation }} — {{ $u->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Tension / Alimentation</label><input type="text" name="power_supply" maxlength="60" value="{{ old('power_supply', $m->power_supply) }}" class="{{ $inp }}" placeholder="380 V - Triphasé - 50 Hz"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Date de mise en service</label><input type="date" name="commissioned_at" value="{{ old('commissioned_at', optional($m->commissioned_at)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Coût d'acquisition (FCFA)</label><input type="number" step="1" min="0" name="acquisition_cost" value="{{ old('acquisition_cost', $m->acquisition_cost) }}" class="{{ $inpR }}" placeholder="125 000 000"></div>
                        <div class="sm:col-span-2"><label class="{{ $lbl }}">Poids (kg)</label><input type="number" step="0.01" min="0" name="weight_kg" value="{{ old('weight_kg', $m->weight_kg) }}" class="{{ $inpR }}" placeholder="5 800,00"></div>
                        <div class="sm:col-span-1"><label class="{{ $lbl }}">Site</label><input type="text" name="site" maxlength="20" value="{{ old('site', $m->site ?? 'OUTLB') }}" class="{{ $inp }} font-mono uppercase"></div>
                        <div class="sm:col-span-1 flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="{{ $chk }}" {{ old('is_active', $m->exists ? $m->is_active : true) ? 'checked' : '' }}>
                                <span class="text-[12.5px] font-semibold text-gray-700">Actif</span>
                            </label>
                        </div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" maxlength="1000" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none" placeholder="Machine de profilage pour la production de tôle bac 5. Largeur utile 1000 mm.">{{ old('notes', $m->notes) }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ TECHNIQUE ═══════════ --}}
            <div id="sec-technique" class="p-4 pt-0 scroll-mt-28 space-y-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Caractéristiques techniques</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-5 gap-x-6 gap-y-4">
                        {{-- Dimensions --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Dimensions</div>
                            <div><label class="{{ $lbl }}">Longueur (mm)</label><input type="number" step="0.01" min="0" name="length_mm" value="{{ old('length_mm', $m->length_mm) }}" class="{{ $inpR }}" placeholder="6 500"></div>
                            <div><label class="{{ $lbl }}">Largeur (mm)</label><input type="number" step="0.01" min="0" name="width_mm" value="{{ old('width_mm', $m->width_mm) }}" class="{{ $inpR }}" placeholder="1 800"></div>
                            <div><label class="{{ $lbl }}">Hauteur (mm)</label><input type="number" step="0.01" min="0" name="height_mm" value="{{ old('height_mm', $m->height_mm) }}" class="{{ $inpR }}" placeholder="1 650"></div>
                            <div><label class="{{ $lbl }}">Encombrement (m³)</label><input type="number" step="0.01" min="0" name="footprint_m3" value="{{ old('footprint_m3', $m->footprint_m3) }}" class="{{ $inpR }}" placeholder="11,70"></div>
                        </div>
                        {{-- Performances --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Performances</div>
                            <div><label class="{{ $lbl }}">Vitesse maximale</label><input type="number" step="0.01" min="0" name="max_speed" value="{{ old('max_speed', $m->max_speed) }}" class="{{ $inpR }}" placeholder="25,00"></div>
                            <div><label class="{{ $lbl }}">Vitesse nominale</label><input type="number" step="0.01" min="0" name="nominal_speed" value="{{ old('nominal_speed', $m->nominal_speed) }}" class="{{ $inpR }}" placeholder="20,00"></div>
                            <div><label class="{{ $lbl }}">Largeur utile (mm)</label><input type="number" step="0.01" min="0" name="useful_width_mm" value="{{ old('useful_width_mm', $m->useful_width_mm) }}" class="{{ $inpR }}" placeholder="1 000"></div>
                            <div><label class="{{ $lbl }}">Épaisseur min (mm)</label><input type="number" step="0.001" min="0" name="thickness_min" value="{{ old('thickness_min', $m->thickness_min) }}" class="{{ $inpR }}" placeholder="0,25"></div>
                            <div><label class="{{ $lbl }}">Épaisseur max (mm)</label><input type="number" step="0.001" min="0" name="thickness_max" value="{{ old('thickness_max', $m->thickness_max) }}" class="{{ $inpR }}" placeholder="0,80"></div>
                            <div><label class="{{ $lbl }}">Nombre d'ondes</label><input type="number" step="1" min="0" max="100" name="waves_count" value="{{ old('waves_count', $m->waves_count) }}" class="{{ $inpR }}" placeholder="5"></div>
                            <div><label class="{{ $lbl }}">Diamètre arbres (mm)</label><input type="number" step="0.01" min="0" name="shaft_diameter_mm" value="{{ old('shaft_diameter_mm', $m->shaft_diameter_mm) }}" class="{{ $inpR }}" placeholder="90"></div>
                            <div><label class="{{ $lbl }}">Puissance installée (kW)</label><input type="number" step="0.01" min="0" name="power_kw" value="{{ old('power_kw', $m->power_kw) }}" class="{{ $inpR }}" placeholder="22,00"></div>
                        </div>
                        {{-- Équipements --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Équipements</div>
                            <div><label class="{{ $lbl }}">Type de motorisation</label><input type="text" name="motor_type" maxlength="30" value="{{ old('motor_type', $m->motor_type) }}" class="{{ $inp }}" placeholder="Électrique"></div>
                            <div><label class="{{ $lbl }}">Réducteur</label><input type="text" name="reducer" maxlength="60" value="{{ old('reducer', $m->reducer) }}" class="{{ $inp }}" placeholder="SEW - R87"></div>
                            <div><label class="{{ $lbl }}">Système de coupe</label><input type="text" name="cutting_system" maxlength="30" value="{{ old('cutting_system', $m->cutting_system) }}" class="{{ $inp }}" placeholder="Hydraulique"></div>
                            <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
                                <input type="hidden" name="integrated_decoiler" value="0">
                                <input type="checkbox" name="integrated_decoiler" value="1" class="{{ $chk }}" {{ old('integrated_decoiler', $m->integrated_decoiler) ? 'checked' : '' }}>
                                <span class="text-[12px] font-semibold text-gray-700">Dérouleur intégré</span>
                            </label>
                        </div>
                        {{-- Raccordements --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Raccordements</div>
                            <div><label class="{{ $lbl }}">Alimentation électrique</label><input type="text" value="{{ old('power_supply', $m->power_supply) }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly title="Renseigné dans Informations générales"></div>
                            <div><label class="{{ $lbl }}">Puissance (kVA)</label><input type="number" step="0.01" min="0" name="power_kva" value="{{ old('power_kva', $m->power_kva) }}" class="{{ $inpR }}" placeholder="25,00"></div>
                            <div><label class="{{ $lbl }}">Pression air (bar)</label><input type="number" step="0.01" min="0" name="air_pressure_bar" value="{{ old('air_pressure_bar', $m->air_pressure_bar) }}" class="{{ $inpR }}" placeholder="6,00"></div>
                            <div><label class="{{ $lbl }}">Pression hydraulique (bar)</label><input type="number" step="0.01" min="0" name="hydraulic_pressure_bar" value="{{ old('hydraulic_pressure_bar', $m->hydraulic_pressure_bar) }}" class="{{ $inpR }}" placeholder="120,00"></div>
                        </div>
                        {{-- Condition d'utilisation --}}
                        <div class="space-y-3">
                            <div class="text-[12px] font-bold text-gray-800 border-b border-gray-200 pb-1">Condition d'utilisation</div>
                            <div><label class="{{ $lbl }}">Température min (°C)</label><input type="number" step="0.1" name="temp_min" value="{{ old('temp_min', $m->temp_min) }}" class="{{ $inpR }}" placeholder="5,00"></div>
                            <div><label class="{{ $lbl }}">Température max (°C)</label><input type="number" step="0.1" name="temp_max" value="{{ old('temp_max', $m->temp_max) }}" class="{{ $inpR }}" placeholder="45,00"></div>
                            <div><label class="{{ $lbl }}">Humidité max (%)</label><input type="number" step="0.1" min="0" max="100" name="humidity_max" value="{{ old('humidity_max', $m->humidity_max) }}" class="{{ $inpR }}" placeholder="85,00"></div>
                            <div>
                                <label class="{{ $lbl }}">Environnement</label>
                                @php $env = old('environment', $m->environment ?? 'interieur'); @endphp
                                <div class="relative"><select name="environment" class="{{ $lk }}">
                                    <option value="interieur" @selected($env==='interieur')>Intérieur</option>
                                    <option value="exterieur" @selected($env==='exterieur')>Extérieur</option>
                                    <option value="abrite" @selected($env==='abrite')>Abrité</option>
                                </select>{!! $caret !!}</div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- [Maquette Machine] Disponibilités et affectation --}}
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Disponibilités et affectation</div>
                    <div class="p-4 flex flex-wrap items-end gap-x-8 gap-y-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-1.5">
                            <input type="hidden" name="assigned_to_atelier" value="0">
                            <input type="checkbox" name="assigned_to_atelier" value="1" class="{{ $chk }}" {{ old('assigned_to_atelier', $m->exists ? $m->assigned_to_atelier : true) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Affectée à l'atelier</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-1.5">
                            <input type="hidden" name="assigned_to_line" value="0">
                            <input type="checkbox" name="assigned_to_line" value="1" class="{{ $chk }}" {{ old('assigned_to_line', $m->assigned_to_line) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Affectée à une ligne</span>
                        </label>
                        <div class="w-40"><label class="{{ $lbl }}">Calendrier de travail</label><input type="text" name="work_calendar" maxlength="30" value="{{ old('work_calendar', $m->work_calendar) }}" class="{{ $inp }} font-mono uppercase" placeholder="CAL-STD-08H"></div>
                        <div class="w-56">
                            <label class="{{ $lbl }}">Poste de travail principal</label>
                            <div class="relative"><select name="work_center_id" class="{{ $lk }}"><option value="">—</option>@foreach($workCenters as $wc)<option value="{{ $wc->id }}" @selected(old('work_center_id', $m->work_center_id)==$wc->id)>{{ $wc->code }} — {{ $wc->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="w-36"><label class="{{ $lbl }}">Équipe par défaut</label><input type="text" name="default_team" maxlength="30" value="{{ old('default_team', $m->default_team) }}" class="{{ $inp }}" placeholder="Équipe A"></div>
                        <div class="w-32">
                            <label class="{{ $lbl }}">Priorité</label>
                            @php $pr = old('priorite', $m->priorite ?? 'normale'); @endphp
                            <div class="relative"><select name="priorite" class="{{ $lk }}">
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ COÛTS & MAINTENANCE ═══════════ --}}
            <div id="sec-couts" class="p-4 pt-0 scroll-mt-28 space-y-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Coûts horaires (XOF)</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Coût horaire machine</label><input type="number" step="1" min="0" name="hourly_cost" value="{{ old('hourly_cost', $m->hourly_cost) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Coût énergie / h</label><input type="number" step="1" min="0" name="energy_cost_per_hour" value="{{ old('energy_cost_per_hour', $m->energy_cost_per_hour) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Coût maintenance / h</label><input type="number" step="1" min="0" name="maintenance_cost_per_hour" value="{{ old('maintenance_cost_per_hour', $m->maintenance_cost_per_hour) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Coût total / h</label>
                            <input type="text" value="{{ number_format((int) old('hourly_cost', $m->hourly_cost ?? 0) + (int) old('energy_cost_per_hour', $m->energy_cost_per_hour ?? 0) + (int) old('maintenance_cost_per_hour', $m->maintenance_cost_per_hour ?? 0), 0, ',', ' ') }}"
                                   class="w-full h-8 px-2 border border-gray-200 rounded-[3px] text-[13px] bg-gray-50 text-right font-mono text-gray-600" readonly>
                        </div>
                    </div>
                </section>
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Maintenance préventive</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Fréquence (jours)</label><input type="number" step="1" min="0" max="3650" name="maintenance_frequency_days" value="{{ old('maintenance_frequency_days', $m->maintenance_frequency_days) }}" class="{{ $inpR }}"></div>
                        <div class="sm:col-span-9 flex items-end pb-1"><p class="text-[11.5px] text-gray-400">Intervalle entre deux maintenances préventives. Les interventions sont générées automatiquement par le plan préventif.</p></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ DOCUMENTS ═══════════ --}}
            <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Documents / pièces jointes</div>
                    <div class="p-4 space-y-4">
                        @if($m->exists && $m->attachments->isNotEmpty())
                        <table class="w-full text-[12.5px] border border-gray-200">
                            <thead><tr class="bg-[#eef5f0] text-emerald-900">
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                                <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                            </tr></thead>
                            <tbody>
                                @foreach($m->attachments as $i => $att)
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
                            <p class="text-[11px] text-gray-400 mt-1">Manuel constructeur, certificats, schémas — max 5 Mo par fichier.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</div>
@endsection
