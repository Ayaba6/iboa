@extends('layouts.erp')
@section('title', 'Nouveau mouvement de carrière')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.carriere.index') }}" class="hover:text-gray-700">Mouvements & carrière</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; @endphp
<div class="max-w-4xl space-y-3">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouveau mouvement de carrière</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('rh.carriere.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié *</label>
                <select name="employee_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $selected)==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type *</label>
                <select name="type" class="{{ $sel }}" required>
                    @foreach(\App\Models\CareerEvent::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(old('type')===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Date d'effet *</label>
                <input type="date" name="effective_date" value="{{ old('effective_date', optional($event->effective_date)->format('Y-m-d')) }}" class="{{ $inp }}" required>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Nouveau poste</label>
                <select name="to_job_position_id" class="{{ $sel }}">
                    <option value="">— inchangé —</option>
                    @foreach($positions as $p)<option value="{{ $p->id }}" @selected(old('to_job_position_id')==$p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Département</label>
                <select name="to_department_id" class="{{ $sel }}">
                    <option value="">— inchangé —</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('to_department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Catégorie</label>
                <input name="to_category" value="{{ old('to_category') }}" class="{{ $inp }}" placeholder="— inchangé —">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Fonction</label>
                <input name="to_fonction" value="{{ old('to_fonction') }}" class="{{ $inp }}" placeholder="— inchangé —">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Grade</label>
                <input name="grade" value="{{ old('grade') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Manager</label>
                <input name="manager_name" value="{{ old('manager_name') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Site</label>
                <input name="site" value="{{ old('site') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Centre de coût</label>
                <input name="cost_center" value="{{ old('cost_center') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Nouveau salaire</label>
                <input type="number" step="0.01" min="0" name="salary" value="{{ old('salary') }}" class="{{ $inp }} text-right">
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Motif</label>
            <textarea name="reason" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('reason') }}</textarea>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('notes') }}</textarea>
        </div>
        <p class="text-[11px] text-gray-400">Les champs « — inchangé — » laissés vides conservent la valeur actuelle. Le mouvement s'applique à la fiche salarié dès que la date d'effet est atteinte.</p>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Enregistrer le mouvement</button>
            <a href="{{ route('rh.carriere.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
