@extends('layouts.erp')
@section('title', 'Suivi de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.trackings.index') }}" class="hover:text-gray-700">Suivi de fabrication</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Suivi complet</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    // py-0 : neutralise le py-2 du plugin @tailwindcss/forms sur <select> (texte tronqué en h-8 sinon)
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel = 'bg-white border border-gray-200 rounded-[4px]';
    $chk   = 'w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600 focus:ring-emerald-500';
    $thD   = 'px-3 py-1.5 text-left';
@endphp

<div class="max-w-[1400px]"
     x-data="{ tab: 'entete', saveAndNew: false, submitting: false,
               trackOps: {{ old('track_operations', $order && $order->operations->isNotEmpty() ? '1' : '0') ? 'true' : 'false' }},
               trackProd: {{ old('track_production', '1') ? 'true' : 'false' }},
               trackMat: {{ old('track_materials', '0') ? 'true' : 'false' }} }">

    <form method="POST" action="{{ route('production.trackings.store') }}" x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">
        <input type="hidden" name="track_operations" :value="trackOps ? 1 : 0">
        <input type="hidden" name="track_production" :value="trackProd ? 1 : 0">
        <input type="hidden" name="track_materials" :value="trackMat ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Suivi de fabrication <span class="text-gray-400 font-normal">: Suivi complet</span></h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <a href="{{ route('production.trackings.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @php $tabFlag = ['operations' => 'trackOps', 'production' => 'trackProd', 'composants' => 'trackMat']; @endphp
            @foreach(['entete' => 'Entête', 'operations' => 'Opérations', 'production' => 'Production', 'composants' => 'Composants'] as $tk => $tl)
            @php
                // Activer un onglet coche aussi sa case de suivi → la section correspondante s'affiche.
                $click = "tab = '{$tk}';";
                if (isset($tabFlag[$tk])) $click .= " {$tabFlag[$tk]} = true;";
                $click .= " \$nextTick(() => \$refs['sec_{$tk}']?.scrollIntoView({behavior:'smooth',block:'start'}))";
            @endphp
            <button type="button" x-on:click="{!! $click !!}"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">
            <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- ═══ Entête ═══ --}}
        <section x-ref="sec_entete" class="{{ $panel }} p-4">
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Site production <span class="text-red-500">*</span></label>
                    <input type="text" name="site" maxlength="40" value="{{ old('site', $order?->site_production ?? '01') }}" class="{{ $inp }}">
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Numéro suivi</label>
                    <input type="text" value="{{ $nextNumber }}" class="{{ $inpRo }} font-mono" readonly>
                </div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">No ordre (OF) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="production_order_id" required class="{{ $lk }} @error('production_order_id') border-red-500 @enderror"
                                onchange="window.location = '{{ route('production.trackings.create') }}?order_id=' + this.value">
                            <option value="">— OF en cours —</option>
                            @foreach($orders as $o)
                            <option value="{{ $o->id }}" {{ ($order?->id ?? old('production_order_id')) == $o->id ? 'selected' : '' }}>
                                {{ $o->number }} ({{ rtrim(rtrim(number_format($o->quantity_produced, 2, ',', ' '), '0'), ',') }}/{{ rtrim(rtrim(number_format($o->quantity_requested, 2, ',', ' '), '0'), ',') }})
                            </option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                    @error('production_order_id')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Date suivi</label>
                    <input type="date" name="tracking_date" value="{{ old('tracking_date', date('Y-m-d')) }}" class="{{ $inp }}">
                </div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Observations</label>
                    <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" class="{{ $inp }}">
                </div>

                <div class="col-span-12 flex flex-wrap items-center gap-x-6 gap-y-1 pt-1">
                    <label class="flex items-center gap-1.5 text-[13px] font-semibold text-gray-800 cursor-pointer">
                        <input type="checkbox" x-model="trackOps" class="{{ $chk }}"> Suivi opérations
                    </label>
                    <label class="flex items-center gap-1.5 text-[13px] font-semibold text-gray-800 cursor-pointer">
                        <input type="checkbox" x-model="trackProd" class="{{ $chk }}"> Déclaration production
                    </label>
                    <label class="flex items-center gap-1.5 text-[13px] font-semibold text-gray-800 cursor-pointer">
                        <input type="checkbox" x-model="trackMat" class="{{ $chk }}"> Suivi matière
                    </label>
                </div>
            </div>
        </section>

        {{-- ═══ Opérations ═══ --}}
        <section x-ref="sec_operations" class="{{ $panel }}" x-show="trackOps" x-cloak>
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <h2 class="{{ $secH }}">Opérations</h2>
                <span class="text-[12px] text-gray-400">Minutes réelles saisies → opération terminée</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="{{ $thD }} w-14">Séq.</th>
                            <th class="{{ $thD }}">Opé std</th>
                            <th class="{{ $thD }}">Poste réalisé</th>
                            <th class="{{ $thD }}">Désignation</th>
                            <th class="px-3 py-1.5 text-right">Prévu (min)</th>
                            <th class="px-3 py-1.5 text-right">M-O réalisé (min)</th>
                            <th class="px-3 py-1.5 text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse(($order?->operations ?? collect()) as $i => $op)
                        <tr>
                            <td class="px-3 py-1 tabular-nums text-gray-600">{{ $op->sequence }}</td>
                            <td class="px-3 py-1 font-mono text-[12px] text-emerald-700">OP-{{ str_pad($op->sequence, 3, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-3 py-1 text-gray-700">{{ $op->workCenter?->name ?? '—' }}</td>
                            <td class="px-3 py-1 text-gray-900">{{ $op->name }}</td>
                            <td class="px-3 py-1 text-right tabular-nums text-gray-600">{{ number_format($op->planned_minutes, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1 text-right">
                                <input type="hidden" name="operations[{{ $i }}][id]" value="{{ $op->id }}">
                                @if($op->status === 'done')
                                <span class="tabular-nums text-gray-900">{{ number_format($op->real_minutes, 0, ',', ' ') }}</span>
                                @else
                                <input type="number" name="operations[{{ $i }}][real_minutes]" step="1" min="0"
                                       value="{{ old('operations.'.$i.'.real_minutes') }}" placeholder="{{ number_format($op->planned_minutes, 0) }}"
                                       class="w-24 h-7 px-2 border border-gray-400 rounded-[3px] text-[14px] text-right tabular-nums focus:outline-none focus:border-emerald-600">
                                @endif
                            </td>
                            <td class="px-3 py-1 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $op->status === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $op->status === 'done' ? 'Réalisée' : 'À faire' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-[13px]">
                            {{ $order ? 'Aucune opération de gamme sur cet OF.' : 'Sélectionnez un OF pour charger ses opérations.' }}
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ═══ Production ═══ --}}
        <section x-ref="sec_production" class="{{ $panel }} p-4" x-show="trackProd" x-cloak>
            <h2 class="{{ $secH }} mb-3">Production</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Article</label>
                    <input type="text" value="{{ $order?->product?->name ?? '—' }}" class="{{ $inpRo }}" readonly>
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Quantité réalisée <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" step="0.01" min="0" value="{{ old('quantity') }}" class="{{ $inp }} text-right tabular-nums @error('quantity') border-red-500 @enderror">
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Longueur (m)</label>
                    <input type="number" name="length" step="0.01" min="0" value="{{ old('length', $order?->length) }}" class="{{ $inp }} text-right tabular-nums">
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Dépôt d'entrée</label>
                    <div class="relative">
                        <select name="warehouse_id" class="{{ $lk }}">
                            <option value="">— Défaut —</option>
                            @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id', $order?->depot_produit_fini_id) == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-1">
                    <label class="{{ $lbl }}">Coût unit.</label>
                    <input type="number" name="unit_cost" step="1" min="0" value="{{ old('unit_cost') }}" class="{{ $inp }} text-right tabular-nums">
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">N° lot</label>
                    <input type="text" name="lot_number" maxlength="60" value="{{ old('lot_number') }}" class="{{ $inp }} font-mono">
                </div>
                <div class="col-span-12">
                    <p class="text-[12px] text-gray-500">La déclaration fait entrer le produit fini en stock et consomme automatiquement les composants de la nomenclature. Visa chef d'équipe requis avant clôture de l'OF.</p>
                </div>
            </div>
        </section>

        {{-- ═══ Composants / Matière ═══ --}}
        <section x-ref="sec_composants" class="{{ $panel }} p-4" x-show="trackMat" x-cloak>
            <h2 class="{{ $secH }} mb-3">Composants — consommation bobine</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-4">
                    <label class="{{ $lbl }}">Bobine <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="coil_id" class="{{ $lk }} @error('coil_id') border-red-500 @enderror">
                            <option value="">— Choisir —</option>
                            @foreach($coils as $c)
                            <option value="{{ $c->id }}" {{ old('coil_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->reference }} ({{ number_format($c->remaining_weight, 0, ',', ' ') }} kg · {{ number_format($c->cost_per_kg, 0, ',', ' ') }} F/kg)
                            </option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                    @error('coil_id')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Poids consommé (kg) <span class="text-red-500">*</span></label>
                    <input type="number" name="weight_consumed" step="0.01" min="0" value="{{ old('weight_consumed') }}" class="{{ $inp }} text-right tabular-nums">
                </div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Longueur (m)</label>
                    <input type="number" name="length_consumed" step="0.01" min="0" value="{{ old('length_consumed') }}" class="{{ $inp }} text-right tabular-nums">
                </div>
                <div class="col-span-4 flex items-end pb-1">
                    <p class="text-[12px] text-gray-500">Les composants BOM (hors bobines) sont consommés automatiquement à la déclaration de production.</p>
                </div>
            </div>

            @if($order && $order->billOfMaterial)
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-[14px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="{{ $thD }}">Type article</th>
                            <th class="{{ $thD }}">Article</th>
                            <th class="{{ $thD }}">Unité</th>
                            <th class="px-3 py-1.5 text-right">Coef / unité produite</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($order->billOfMaterial->lines as $line)
                        <tr>
                            <td class="px-3 py-1 text-gray-500 text-[12px] uppercase">Composant</td>
                            <td class="px-3 py-1 text-gray-900">{{ $line->product?->name ?? '—' }}</td>
                            <td class="px-3 py-1 text-gray-600">{{ $line->product?->unit?->abbreviation ?? $line->product?->unit?->name ?? 'u' }}</td>
                            <td class="px-3 py-1 text-right tabular-nums text-gray-900">{{ rtrim(rtrim(number_format($line->quantity_per_meter, 4, ',', ' '), '0'), ',') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

    </form>
</div>
@endsection
