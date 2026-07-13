@extends('layouts.erp')
@section('title', $plan->exists ? 'Modifier le plan' : 'Nouveau plan de contrôle')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.control-plans.index') }}" class="hover:text-gray-700">Plans de contrôle</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $plan->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $cell = 'w-full h-7 px-1.5 border border-gray-300 rounded-[3px] text-[12px] bg-white focus:outline-none focus:border-emerald-600';
    $existing = old('characteristics', $plan->exists
        ? $plan->characteristics->map(fn ($c) => [
            'name' => $c->name, 'method' => $c->method, 'unit' => $c->unit, 'frequency' => $c->frequency,
            'sampling' => $c->sampling, 'target_value' => $c->target_value, 'tolerance_min' => $c->tolerance_min,
            'tolerance_max' => $c->tolerance_max, 'is_critical' => (bool) $c->is_critical, 'responsible' => $c->responsible,
        ])->values()->all()
        : []);
@endphp

<div class="space-y-4" x-data="{ rows: {{ Illuminate\Support\Js::from($existing) }},
    add() { this.rows.push({name:'',method:'',unit:'',frequency:'',sampling:'',target_value:'',tolerance_min:'',tolerance_max:'',is_critical:false,responsible:''}); },
    remove(i) { this.rows.splice(i,1); } }"
    x-init="if(rows.length===0) add()">

    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $plan->exists ? 'Modifier le plan de contrôle' : 'Nouveau plan de contrôle' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $plan->exists ? route('qualite.control-plans.update', $plan) : route('qualite.control-plans.store') }}" class="space-y-4">
        @csrf
        @if($plan->exists)@method('PUT')@endif

        {{-- Entête --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <label class="{{ $lbl }}">Nom du plan *</label>
                <input name="name" value="{{ old('name', $plan->name) }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="{{ $lbl }}">Référence</label>
                <input name="reference" value="{{ old('reference', $plan->reference) }}" class="{{ $inp }}" placeholder="PC-2026-001">
            </div>
            <div>
                <label class="{{ $lbl }}">Article</label>
                <select name="product_id" class="{{ $inp }}">
                    <option value="">— tous —</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $plan->product_id)==$p->id)>{{ $p->code ? $p->code.' — ' : '' }}{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Famille d'article</label>
                <select name="product_family_id" class="{{ $inp }}">
                    <option value="">— toutes —</option>
                    @foreach($families as $f)<option value="{{ $f->id }}" @selected(old('product_family_id', $plan->product_family_id)==$f->id)>{{ $f->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}">Étape *</label>
                <select name="stage" class="{{ $inp }}" required>
                    @foreach(\App\Modules\Quality\Models\ControlPlan::STAGES as $k => $v)<option value="{{ $k }}" @selected(old('stage', $plan->stage)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-[13px] text-gray-700 h-8">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded border-gray-400"> Actif
                </label>
            </div>
            <div class="sm:col-span-3">
                <label class="{{ $lbl }}">Description</label>
                <textarea name="description" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px]">{{ old('description', $plan->description) }}</textarea>
            </div>
        </div>

        {{-- Caractéristiques --}}
        <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold flex items-center justify-between">
                <span>Caractéristiques à contrôler</span>
                <button type="button" @click="add()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1 rounded-[3px]">+ Ligne</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px] border-collapse">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="px-2 py-1.5 text-left font-bold">Caractéristique *</th>
                            <th class="px-2 py-1.5 text-left font-bold">Méthode</th>
                            <th class="px-2 py-1.5 text-left font-bold">Unité</th>
                            <th class="px-2 py-1.5 text-left font-bold">Fréquence</th>
                            <th class="px-2 py-1.5 text-left font-bold">Échantillon</th>
                            <th class="px-2 py-1.5 text-right font-bold">Cible</th>
                            <th class="px-2 py-1.5 text-right font-bold">Tol. min</th>
                            <th class="px-2 py-1.5 text-right font-bold">Tol. max</th>
                            <th class="px-2 py-1.5 text-center font-bold">Crit.</th>
                            <th class="px-2 py-1.5 text-left font-bold">Responsable</th>
                            <th class="px-2 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(r, i) in rows" :key="i">
                            <tr class="border-b border-gray-100">
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][name]`" x-model="r.name" class="{{ $cell }}" placeholder="Épaisseur"></td>
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][method]`" x-model="r.method" class="{{ $cell }}" placeholder="Pied à coulisse"></td>
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][unit]`" x-model="r.unit" class="{{ $cell }} w-16" placeholder="mm"></td>
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][frequency]`" x-model="r.frequency" class="{{ $cell }}" placeholder="Chaque lot"></td>
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][sampling]`" x-model="r.sampling" class="{{ $cell }} w-20" placeholder="5 pièces"></td>
                                <td class="px-1.5 py-1"><input type="number" step="any" :name="`characteristics[${i}][target_value]`" x-model="r.target_value" class="{{ $cell }} text-right w-20"></td>
                                <td class="px-1.5 py-1"><input type="number" step="any" :name="`characteristics[${i}][tolerance_min]`" x-model="r.tolerance_min" class="{{ $cell }} text-right w-20"></td>
                                <td class="px-1.5 py-1"><input type="number" step="any" :name="`characteristics[${i}][tolerance_max]`" x-model="r.tolerance_max" class="{{ $cell }} text-right w-20"></td>
                                <td class="px-1.5 py-1 text-center"><input type="checkbox" :name="`characteristics[${i}][is_critical]`" value="1" x-model="r.is_critical" class="rounded border-gray-400"></td>
                                <td class="px-1.5 py-1"><input :name="`characteristics[${i}][responsible]`" x-model="r.responsible" class="{{ $cell }}" placeholder="Opérateur"></td>
                                <td class="px-1.5 py-1 text-center"><button type="button" @click="remove(i)" class="text-red-500 hover:text-red-700 font-bold">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-5 py-2 rounded-[4px]">{{ $plan->exists ? 'Enregistrer' : 'Créer le plan' }}</button>
            <a href="{{ route('qualite.control-plans.index') }}" class="text-gray-600 hover:text-gray-900 text-[13px] px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
