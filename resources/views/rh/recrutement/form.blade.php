@extends('layouts.erp')
@section('title', $recruitment->exists ? 'Modifier le besoin' : 'Nouveau besoin')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.recrutement.index') }}" class="hover:text-gray-700">Recrutement</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $recruitment->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; @endphp

<div class="max-w-4xl space-y-3">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $recruitment->exists ? 'Modifier le besoin' : 'Nouveau besoin de recrutement' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $recruitment->exists ? route('rh.recrutement.update', $recruitment) : route('rh.recrutement.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        @if($recruitment->exists)@method('PUT')@endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Intitulé du poste *</label>
                <input type="text" name="title" value="{{ old('title', $recruitment->title) }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Référence</label>
                <input type="text" name="reference" value="{{ old('reference', $recruitment->reference) }}" class="{{ $inp }}" placeholder="REC-2026-001">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Poste de référence</label>
                <select name="job_position_id" class="{{ $sel }}">
                    <option value="">—</option>
                    @foreach($positions as $p)<option value="{{ $p->id }}" @selected(old('job_position_id', $recruitment->job_position_id)==$p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Département</label>
                <select name="department_id" class="{{ $sel }}">
                    <option value="">—</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $recruitment->department_id)==$d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type de contrat *</label>
                <select name="contract_type" class="{{ $sel }}" required>
                    @foreach(\App\Models\Recruitment::CONTRACT_TYPES as $k => $lbl)<option value="{{ $k }}" @selected(old('contract_type', $recruitment->contract_type)===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Nombre de postes *</label>
                <input type="number" name="positions_count" value="{{ old('positions_count', $recruitment->positions_count ?? 1) }}" min="1" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut *</label>
                <select name="status" class="{{ $sel }}" required>
                    @foreach(\App\Models\Recruitment::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(old('status', $recruitment->status)===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Ouvert le</label>
                <input type="date" name="opened_at" value="{{ old('opened_at', optional($recruitment->opened_at)->format('Y-m-d')) }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Clôturé le</label>
                <input type="date" name="closed_at" value="{{ old('closed_at', optional($recruitment->closed_at)->format('Y-m-d')) }}" class="{{ $inp }}">
            </div>
        </div>

        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Description du poste</label>
            <textarea name="description" rows="3" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('description', $recruitment->description) }}</textarea>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Profil recherché / exigences</label>
            <textarea name="requirements" rows="3" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('requirements', $recruitment->requirements) }}</textarea>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">{{ $recruitment->exists ? 'Enregistrer' : 'Créer le besoin' }}</button>
            <a href="{{ route('rh.recrutement.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
