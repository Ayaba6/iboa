@extends('layouts.erp')
@section('title', 'Crédit — '.$client->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $client->name }} — Crédit</span>
@endsection

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' F';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $inp = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[13px]'; $sel = $inp.' py-0';
@endphp
<div class="max-w-4xl space-y-4">

    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Crédit client — {{ $client->name }}</h1>
        <p class="text-sm text-gray-500">Plafond, encours, disponible, blocage et historique des décisions.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 text-[13px] rounded-[4px] px-4 py-2"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- État courant --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Plafond</p><p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($client->credit_limit) }}</p></div>
        <div class="bg-white border border-gray-200 rounded-[4px] p-4"><p class="text-xs text-gray-500 uppercase">Encours (balance)</p><p class="text-xl font-bold text-gray-900 tabular-nums mt-1">{{ $fmt($client->balance) }}</p></div>
        <div class="bg-white border border-emerald-200 rounded-[4px] p-4"><p class="text-xs text-emerald-600 uppercase">Disponible</p><p class="text-xl font-bold text-emerald-700 tabular-nums mt-1">{{ $fmt($client->available_credit) }}</p></div>
        <div class="bg-white border {{ $client->is_blocked ? 'border-red-200' : 'border-gray-200' }} rounded-[4px] p-4">
            <p class="text-xs {{ $client->is_blocked ? 'text-red-600' : 'text-gray-500' }} uppercase">Statut</p>
            <p class="text-lg font-bold tabular-nums mt-1 {{ $client->is_blocked ? 'text-red-700' : 'text-emerald-700' }}">{{ $client->is_blocked ? '● Bloqué' : '✓ Actif' }}</p>
            @if($client->isOverCreditLimit())<p class="text-[10px] text-red-600 mt-0.5">Plafond dépassé</p>@endif
        </div>
    </div>

    {{-- Nouvelle décision --}}
    @can('clients.edit')
    <div class="bg-[#eef5f0] text-emerald-900 rounded-t-[4px] px-4 py-2 text-[13px] font-semibold">Nouvelle décision de crédit</div>
    <form method="POST" action="{{ route('clients.credit.store', $client) }}" class="bg-white border border-t-0 border-gray-200 rounded-b-[4px] p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
          x-data="{ type: '{{ old('type', 'blocage') }}' }">
        @csrf
        <div>
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Type *</label>
            <select name="type" x-model="type" class="{{ $sel }}" required>
                @foreach(\App\Models\CreditDecision::TYPES as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div x-show="type === 'relevement_plafond' || type === 'reduction_plafond'">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Nouveau plafond *</label>
            <input type="number" step="1" min="0" name="new_limit" value="{{ old('new_limit', $client->credit_limit) }}" class="{{ $inp }} text-right">
        </div>
        <div x-show="type === 'derogation'">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Montant dérogé *</label>
            <input type="number" step="1" min="0" name="amount" value="{{ old('amount') }}" class="{{ $inp }} text-right">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-[12px] font-semibold text-gray-800 mb-1">Motif</label>
            <input name="reason" value="{{ old('reason') }}" class="{{ $inp }}">
        </div>
        <div><button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold h-8 rounded-[3px]">Enregistrer</button></div>
    </form>
    @endcan

    {{-- Historique --}}
    <div class="bg-white rounded-[4px] border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#3b4248] text-white">
                <tr>
                    <th class="{{ $th }} text-left">Date</th>
                    <th class="{{ $th }} text-left">Décision</th>
                    <th class="{{ $th }} text-right">Ancien plafond</th>
                    <th class="{{ $th }} text-right">Nouveau plafond</th>
                    <th class="{{ $th }} text-right">Montant</th>
                    <th class="{{ $th }} text-left">Motif</th>
                    <th class="{{ $th }} text-left">Décideur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($client->creditDecisions as $d)
                <tr>
                    <td class="px-3 py-1.5 tabular-nums whitespace-nowrap">{{ $d->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ $d->typeLabel() }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $d->previous_limit !== null ? $fmt($d->previous_limit) : '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $d->new_limit !== null ? $fmt($d->new_limit) : '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $d->amount !== null ? $fmt($d->amount) : '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $d->reason ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $d->decidedBy?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune décision de crédit enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Décisions : <span class="text-white font-semibold">{{ $client->creditDecisions->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
