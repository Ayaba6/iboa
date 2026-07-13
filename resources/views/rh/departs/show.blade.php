@extends('layouts.erp')
@section('title', 'Départ — '.$departure->employee?->full_name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.departs.index') }}" class="hover:text-gray-700">Départs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $departure->employee?->full_name }}</span>
@endsection

@section('content')
@php $num = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px] text-right'; $final = $departure->status === 'cloture'; @endphp
<div class="max-w-3xl space-y-4">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $departure->employee?->full_name }} — {{ $departure->typeLabel() }}</h1>
            <p class="text-sm text-gray-500">Départ le {{ optional($departure->effective_date)->format('d/m/Y') }} · Préavis {{ $departure->notice_days ?? '—' }} j · Statut : <span class="font-semibold">{{ $departure->statusLabel() }}</span></p>
        </div>
        <div class="text-right">
            <p class="text-[28px] font-bold text-emerald-800 leading-none tabular-nums">{{ number_format((float) $departure->total_stc, 0, ',', ' ') }} <span class="text-[14px] text-gray-400">F</span></p>
            <p class="text-xs text-gray-500">Solde de tout compte</p>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($departure->reason)<div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-[11px] font-semibold text-gray-400 uppercase mb-1">Motif</p><p class="text-sm text-gray-700 whitespace-pre-line">{{ $departure->reason }}</p></div>@endif

    <form method="POST" action="{{ route('rh.departs.update', $departure) }}" class="bg-white border border-gray-200 rounded-[4px] p-5 space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="employee_id" value="{{ $departure->employee_id }}">
        <input type="hidden" name="type" value="{{ $departure->type }}">
        <input type="hidden" name="effective_date" value="{{ optional($departure->effective_date)->format('Y-m-d') }}">
        <input type="hidden" name="notice_start" value="{{ optional($departure->notice_start)->format('Y-m-d') }}">
        <input type="hidden" name="notice_days" value="{{ $departure->notice_days }}">
        <input type="hidden" name="reason" value="{{ $departure->reason }}">

        <div class="bg-[#eef5f0] text-emerald-900 px-4 py-1.5 text-[13px] font-semibold rounded-[3px]">Solde de tout compte</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Indemnité de départ</label><input type="number" step="0.01" min="0" name="severance_amount" value="{{ $departure->severance_amount }}" class="{{ $num }}" @disabled($final)></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Indemnité de préavis</label><input type="number" step="0.01" min="0" name="notice_amount" value="{{ $departure->notice_amount }}" class="{{ $num }}" @disabled($final)></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Congés restants (j)</label><input type="number" step="0.01" min="0" name="leave_balance_days" value="{{ $departure->leave_balance_days }}" class="{{ $num }}" @disabled($final)></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Congés payés soldés</label><input type="number" step="0.01" min="0" name="leave_balance_amount" value="{{ $departure->leave_balance_amount }}" class="{{ $num }}" @disabled($final)></div>
            <div><label class="block text-[12px] font-semibold text-gray-800 mb-1">Autres</label><input type="number" step="0.01" min="0" name="other_amount" value="{{ $departure->other_amount }}" class="{{ $num }}" @disabled($final)></div>
        </div>
        <div class="flex items-center gap-6">
            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="equipment_returned" value="1" @checked($departure->equipment_returned) class="rounded border-gray-400" @disabled($final)> Matériel restitué</label>
            <label class="inline-flex items-center gap-2 text-[13px] text-gray-700"><input type="checkbox" name="documents_issued" value="1" @checked($departure->documents_issued) class="rounded border-gray-400" @disabled($final)> Documents remis</label>
        </div>
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[13px]" @disabled($final)>{{ $departure->notes }}</textarea>
        </div>

        @can('rh.employees.manage')
        @unless($final)
        <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2 rounded-[4px]">Enregistrer le STC</button>
        </div>
        @endunless
        @endcan
    </form>

    @can('rh.employees.manage')
    @unless($final)
    <form method="POST" action="{{ route('rh.departs.finalize', $departure) }}" onsubmit="return confirm('Clôturer le départ ? Le salarié sera marqué sorti et le STC figé.');">@csrf
        <button class="bg-[#3b4248] hover:bg-black text-white text-sm font-semibold px-5 py-2 rounded-[4px]">✔ Clôturer le départ</button>
    </form>
    @else
    <p class="text-emerald-700 text-sm font-semibold">✓ Départ clôturé le {{ optional($departure->finalized_at)->format('d/m/Y') }} — salarié marqué sorti.</p>
    @endunless
    @endcan

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">STC : <span class="text-white font-semibold">{{ number_format((float) $departure->total_stc, 0, ',', ' ') }} F</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
