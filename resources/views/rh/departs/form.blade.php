@extends('layouts.erp')
@section('title', 'Déclarer un départ')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.departs.index') }}" class="hover:text-gray-700">Départs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0'; $num = $inp.' text-right'; @endphp
<div class="max-w-3xl space-y-3">
    <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Déclarer un départ</h1>

    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('rh.departs.store') }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Salarié *</label>
                <select name="employee_id" class="{{ $sel }}" required>
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id')==$e->id)>{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type *</label>
                <select name="type" class="{{ $sel }}" required>
                    @foreach(\App\Models\EmployeeDeparture::TYPES as $k => $lbl)<option value="{{ $k }}" @selected(old('type')===$k)>{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Début préavis</label>
                <input type="date" name="notice_start" value="{{ old('notice_start') }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Préavis (jours)</label>
                <input type="number" min="0" name="notice_days" value="{{ old('notice_days') }}" class="{{ $num }}">
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-gray-800 mb-1">Date de départ *</label>
                <input type="date" name="effective_date" value="{{ old('effective_date', optional($departure->effective_date)->format('Y-m-d')) }}" class="{{ $inp }}" required>
            </div>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Motif</label>
            <textarea name="reason" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('reason') }}</textarea>
        </div>

        <div class="bg-[#eef5f0] text-emerald-900 px-4 py-1.5 text-[13px] font-semibold rounded-[3px]">Solde de tout compte</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Indemnité de départ</label><input type="number" step="0.01" min="0" name="severance_amount" value="{{ old('severance_amount', 0) }}" class="{{ $num }}"></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Indemnité de préavis</label><input type="number" step="0.01" min="0" name="notice_amount" value="{{ old('notice_amount', 0) }}" class="{{ $num }}"></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Congés restants (jours)</label><input type="number" step="0.01" min="0" name="leave_balance_days" value="{{ old('leave_balance_days', 0) }}" class="{{ $num }}"></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Congés payés soldés</label><input type="number" step="0.01" min="0" name="leave_balance_amount" value="{{ old('leave_balance_amount', 0) }}" class="{{ $num }}"></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Autres (primes, rappels)</label><input type="number" step="0.01" min="0" name="other_amount" value="{{ old('other_amount', 0) }}" class="{{ $num }}"></div>
        </div>

        <div class="flex items-center gap-6 pt-1">
            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="equipment_returned" value="1" @checked(old('equipment_returned')) class="rounded border-gray-400"> Matériel restitué</label>
            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="documents_issued" value="1" @checked(old('documents_issued')) class="rounded border-gray-400"> Documents remis (certificat, attestations)</label>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Déclarer le départ</button>
            <a href="{{ route('rh.departs.index') }}" class="text-gray-600 hover:text-gray-900 text-sm px-4 py-2">Annuler</a>
        </div>
    </form>
</div>
@endsection
