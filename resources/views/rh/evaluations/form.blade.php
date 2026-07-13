@extends('layouts.erp')
@section('title', 'Nouvelle évaluation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.evaluations.index') }}" class="hover:text-gray-700">Évaluations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; @endphp
<div class="max-w-3xl space-y-3" x-data="{ rows: {{ Illuminate\Support\Js::from(old('criteria', [['label'=>'','weight'=>25]])) }},
    add(){ this.rows.push({label:'',weight:25}); }, remove(i){ this.rows.splice(i,1); } }">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvelle évaluation</h1>

    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('rh.evaluations.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié *</label>
                <select name="employee_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id')==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Campagne *</label>
                <input name="campaign" value="{{ old('campaign', 'Évaluation annuelle') }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Année *</label>
                <input type="number" name="period_year" value="{{ old('period_year', now()->year) }}" class="{{ $inp }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Évaluateur (manager)</label>
                <input name="evaluator_name" value="{{ old('evaluator_name') }}" class="{{ $inp }}">
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Objectifs de la période</label>
            <textarea name="objectives" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('objectives') }}</textarea>
        </div>

        <div class="border border-gray-200 rounded-[4px] overflow-hidden">
            <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold flex items-center justify-between">
                <span>Critères / objectifs évalués</span>
                <button type="button" @click="add()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1 rounded-[3px]">+ Critère</button>
            </div>
            <table class="w-full text-[12.5px]">
                <thead class="bg-gray-100 text-gray-600"><tr>
                    <th class="px-2 py-1.5 text-left font-bold">Critère</th>
                    <th class="px-2 py-1.5 text-right font-bold w-28">Pondération (%)</th>
                    <th class="px-2 py-1.5 w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(r,i) in rows" :key="i">
                        <tr class="border-b border-gray-100">
                            <td class="px-2 py-1"><input :name="`criteria[${i}][label]`" x-model="r.label" class="w-full h-7 px-1.5 border border-gray-300 rounded-[3px] text-[12px]" placeholder="Qualité du travail"></td>
                            <td class="px-2 py-1"><input type="number" min="1" max="100" :name="`criteria[${i}][weight]`" x-model="r.weight" class="w-full h-7 px-1.5 border border-gray-300 rounded-[3px] text-[12px] text-right"></td>
                            <td class="px-2 py-1 text-center"><button type="button" @click="remove(i)" class="text-red-500 hover:text-red-700">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Créer l'évaluation</button>
            <a href="{{ route('rh.evaluations.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
