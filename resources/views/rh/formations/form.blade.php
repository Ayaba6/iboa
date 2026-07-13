@extends('layouts.erp')
@section('title', 'Nouvelle session de formation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.formations.index') }}" class="hover:text-gray-700">Formation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; @endphp
<div class="max-w-3xl space-y-3">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvelle session de formation</h1>

    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('rh.formations.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Intitulé *</label>
                <input name="title" value="{{ old('title') }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Statut *</label>
                <select name="status" class="{{ $sel }}" required>
                    @foreach(\App\Models\TrainingSession::STATUSES as $k => $lbl)<option value="{{ $k }}" @selected(old('status','planifiee')===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Compétence visée</label>
                <input name="competence" value="{{ old('competence') }}" class="{{ $inp }}" placeholder="Sécurité machine">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Organisme</label>
                <input name="provider" value="{{ old('provider') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Lieu</label>
                <input name="location" value="{{ old('location') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Date début</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Date fin</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Coût total (F CFA)</label>
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost') }}" class="{{ $inp }} text-right">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Places max</label>
                <input type="number" min="1" name="max_participants" value="{{ old('max_participants') }}" class="{{ $inp }} text-right">
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Description</label>
            <textarea name="description" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('description') }}</textarea>
        </div>
        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Créer la session</button>
            <a href="{{ route('rh.formations.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
