@extends('layouts.erp')
@section('title', 'Effet ' . $effet->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.effets.index') }}" class="hover:text-gray-700">Effets</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $effet->number }}</span>
@endsection

@section('content')
<div x-data="{ modal: '' }" class="space-y-3 max-w-3xl">

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $effet->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $effet->typeLabel() }} · {{ $effet->directionLabel() }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
            @php
                $statusColors = [
                    'en_attente'  => 'bg-yellow-100 text-yellow-700',
                    'accepte'     => 'bg-blue-100 text-blue-700',
                    'remis_banque'=> 'bg-emerald-100 text-emerald-800',
                    'encaisse'    => 'bg-green-100 text-green-700',
                    'rejete'      => 'bg-red-100 text-red-700',
                    'proteste'    => 'bg-red-100 text-red-700',
                    'annule'      => 'bg-gray-100 text-gray-500',
                ];
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$effet->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $effet->statusLabel() }}
            </span>

            @can('treasury.write')
            @if($effet->status === 'en_attente')
            <form method="POST" action="{{ route('tresorerie.effets.accept', $effet) }}">@csrf
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-medium px-3 py-1.5 rounded-[4px]">Accepter</button>
            </form>
            <button @click="modal = 'cancel'" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
            @endif
            @if(in_array($effet->status, ['accepte', 'remis_banque']))
            <button @click="modal = 'encaisse'" class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1.5 rounded-[4px]">Encaisser</button>
            <button @click="modal = 'rejete'" class="bg-red-500 hover:bg-red-600 text-white text-xs font-medium px-3 py-1.5 rounded-[4px]">Rejeter</button>
            <button @click="modal = 'proteste'" class="bg-red-700 hover:bg-red-800 text-white text-xs font-medium px-3 py-1.5 rounded-[4px]">Protester</button>
            @endif
            @endcan
        </div>
    </div>

    {{-- Main info --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-4 gap-x-8 text-sm">
            <div><p class="text-xs text-gray-500">Montant</p><p class="text-[16px] font-bold tabular-nums text-emerald-800">{{ number_format($effet->amount, 0, ',', ' ') }} {{ $effet->currency_code }}</p></div>
            <div><p class="text-xs text-gray-500">Date émission</p><p class="font-medium">{{ $effet->issue_date?->format('d/m/Y') }}</p></div>
            <div><p class="text-xs text-gray-500">Échéance</p>
                <p class="font-medium {{ $effet->isDue() ? 'text-red-600 font-bold' : '' }}">
                    {{ $effet->due_date?->format('d/m/Y') ?? '—' }}
                    @if($effet->isDue())<span class="text-xs">⚠ Échu</span>@endif
                </p>
            </div>
            <div><p class="text-xs text-gray-500">Tiers</p><p class="font-medium">{{ $effet->client?->name ?? $effet->supplier?->name ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Tireur</p><p class="font-medium">{{ $effet->drawer ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Tiré</p><p class="font-medium">{{ $effet->drawee ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Bénéficiaire</p><p class="font-medium">{{ $effet->payee ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Banque</p><p class="font-medium">{{ $effet->bank_name ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-500">Référence</p><p class="font-mono font-medium">{{ $effet->reference ?? '—' }}</p></div>
            @if($effet->bankDeposit)
            <div><p class="text-xs text-gray-500">Remise banque</p>
                <a href="{{ route('tresorerie.remises.show', $effet->bankDeposit) }}" class="font-medium text-emerald-700 hover:underline">{{ $effet->bankDeposit->number }}</a>
            </div>
            @endif
            @if($effet->payment_date)
            <div><p class="text-xs text-gray-500">Date encaissement</p><p class="font-medium text-green-700">{{ $effet->payment_date?->format('d/m/Y') }}</p></div>
            @endif
            @if($effet->rejection_reason)
            <div class="col-span-3"><p class="text-xs text-gray-500">Motif</p><p class="font-medium text-red-700">{{ $effet->rejection_reason }}</p></div>
            @endif
        </div>
        @if($effet->notes)
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Notes</p>
            <p class="text-sm text-gray-700">{{ $effet->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Lifecycle --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Cycle de vie</h3>
        @php
            $steps = [
                ['en_attente',  'En attente'],
                ['accepte',     'Accepté'],
                ['remis_banque','Remis banque'],
                ['encaisse',    'Encaissé'],
            ];
            $currentOrder = array_search($effet->status, array_column($steps, 0));
        @endphp
        <div class="flex items-center gap-0">
            @foreach($steps as $i => [$s, $lbl])
            @php $done = $i <= $currentOrder; @endphp
            <div class="flex items-center {{ $i < count($steps) - 1 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $done ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-400' }}">
                        {{ $done ? '✓' : ($i + 1) }}
                    </div>
                    <p class="text-xs mt-1 text-center {{ $done ? 'text-emerald-800 font-medium' : 'text-gray-400' }}">{{ $lbl }}</p>
                </div>
                @if($i < count($steps) - 1)
                <div class="flex-1 h-0.5 mx-1 mb-4 {{ $i < $currentOrder ? 'bg-emerald-700' : 'bg-gray-200' }}"></div>
                @endif
            </div>
            @endforeach
        </div>
        @if(in_array($effet->status, ['rejete', 'proteste', 'annule']))
        <p class="mt-3 text-sm font-semibold {{ in_array($effet->status, ['rejete', 'proteste']) ? 'text-red-600' : 'text-gray-500' }}">
            Statut final : {{ $effet->statusLabel() }}
        </p>
        @endif
    </div>

</div>

{{-- Modals --}}
<div x-show="modal === 'encaisse'" x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm p-4">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Encaisser l'effet</h3>
        <form method="POST" action="{{ route('tresorerie.effets.encaisse', $effet) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date d'encaissement</label>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="modal = ''" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">Encaisser</button>
            </div>
        </form>
    </div>
</div>

<div x-show="modal === 'rejete'" x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm p-4">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Rejeter l'effet</h3>
        <form method="POST" action="{{ route('tresorerie.effets.reject', $effet) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif de rejet</label>
                <textarea name="rejection_reason" rows="3" required
                          class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none"></textarea>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="modal = ''" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">Rejeter</button>
            </div>
        </form>
    </div>
</div>

<div x-show="modal === 'proteste'" x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm p-4">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Protester l'effet</h3>
        <form method="POST" action="{{ route('tresorerie.effets.protest', $effet) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif du protêt</label>
                <textarea name="rejection_reason" rows="3" required
                          class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none"></textarea>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="modal = ''" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                <button type="submit" class="bg-red-800 hover:bg-red-900 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">Protester</button>
            </div>
        </form>
    </div>
</div>

<div x-show="modal === 'cancel'" x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-full max-w-sm p-4">
        <h3 class="text-[16px] font-bold text-gray-900 mb-4">Annuler l'effet</h3>
        <p class="text-sm text-gray-600 mb-4">Cette action est irréversible.</p>
        <form method="POST" action="{{ route('tresorerie.effets.cancel', $effet) }}" class="flex gap-3 justify-end">
            @csrf
            <button type="button" @click="modal = ''" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Retour</button>
            <button type="submit" class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler l'effet</button>
        </form>
    </div>
</div>
@endsection
