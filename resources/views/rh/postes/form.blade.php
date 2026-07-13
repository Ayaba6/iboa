@extends('layouts.erp')
@section('title', $position->exists ? 'Modifier le poste' : 'Nouveau poste')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.postes.index') }}" class="hover:text-gray-700">Postes & grades</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $position->exists ? $position->name : 'Nouveau' }}</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[12px] font-semibold text-gray-800 mb-1';
    $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $sec = 'px-4 py-1.5 border-b border-t border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp

<div class="w-full space-y-3">

    <form method="POST" action="{{ $position->exists ? route('rh.postes.update', $position) : route('rh.postes.store') }}" class="space-y-3">
        @csrf
        @if($position->exists) @method('PUT') @endif

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Postes : {{ $position->exists ? 'Modification' : 'Création' }}
                @if($position->exists)<span class="text-gray-400 font-normal font-mono text-[16px]">{{ $position->code }}</span>@endif
            </h1>
            <div class="flex items-center gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Enregistrer</button>
                <a href="{{ route('rh.postes.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-semibold px-5 py-2 rounded-[4px]">Abandon</a>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="{{ $sec }}">1. Identification & rattachement</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><label class="{{ $lbl }}">Code <span class="text-red-500">*</span></label><input type="text" name="code" maxlength="40" value="{{ old('code', $position->code) }}" class="{{ $inp }} font-mono uppercase" placeholder="POSTE-001"></div>
                <div class="sm:col-span-3"><label class="{{ $lbl }}">Intitulé du poste <span class="text-red-500">*</span></label><input type="text" name="name" maxlength="150" value="{{ old('name', $position->name) }}" class="{{ $inp }}" placeholder="Ex. Opérateur profilage"></div>
                <div><label class="{{ $lbl }}">Département</label>
                    <select name="department_id" class="{{ $inp }} py-0">
                        <option value="">—</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id',$position->department_id)==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div><label class="{{ $lbl }}">Grade</label><input type="text" name="grade" maxlength="60" value="{{ old('grade', $position->grade) }}" class="{{ $inp }}" placeholder="Ex. Échelon 3"></div>
                <div><label class="{{ $lbl }}">Catégorie</label><input type="text" name="category" maxlength="60" value="{{ old('category', $position->category) }}" class="{{ $inp }}" placeholder="Ouvrier / Agent de maîtrise / Cadre"></div>
                <div><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="40" value="{{ old('cost_center', $position->cost_center) }}" class="{{ $inp }}"></div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="{{ $sec }}">2. Rémunération & effectif</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><label class="{{ $lbl }}">Salaire min (FCFA)</label><input type="number" step="1" min="0" name="salary_min" value="{{ old('salary_min', $position->salary_min) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div><label class="{{ $lbl }}">Salaire max (FCFA)</label><input type="number" step="1" min="0" name="salary_max" value="{{ old('salary_max', $position->salary_max) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div><label class="{{ $lbl }}">Effectif cible</label><input type="number" step="1" min="0" name="headcount_target" value="{{ old('headcount_target', $position->headcount_target) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="flex items-end"><label class="inline-flex items-center gap-2 text-[13px] text-gray-800"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $position->is_active ?? true)) class="w-4 h-4 rounded border-gray-300"> Poste actif</label></div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="{{ $sec }}">3. Description & missions</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="{{ $lbl }}">Description</label><textarea name="description" rows="4" class="w-full border border-gray-400 rounded-[3px] px-2 py-1.5 text-[13px]">{{ old('description', $position->description) }}</textarea></div>
                <div><label class="{{ $lbl }}">Missions principales</label><textarea name="missions" rows="4" class="w-full border border-gray-400 rounded-[3px] px-2 py-1.5 text-[13px]">{{ old('missions', $position->missions) }}</textarea></div>
            </div>
        </div>
    </form>

</div>
@endsection
