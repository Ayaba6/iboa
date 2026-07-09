@extends('layouts.erp')
@section('title', 'Virement ' . $virement->number)

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.virements.index') }}" class="hover:text-gray-700">Virements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $virement->number }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900 font-mono">{{ $virement->number }}</h1>
            <p class="text-sm text-gray-500">{{ $virement->transfer_date?->format('d/m/Y') }}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium
            {{ $virement->status === 'valide' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
            {{ ucfirst($virement->status) }}
        </span>
    </div>

    {{-- Flux --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1 text-center">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Source</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $virement->fromAccount?->name }}</p>
                <p class="text-xs text-gray-500">{{ ucfirst($virement->fromAccount?->type ?? '') }}</p>
            </div>
            <div class="flex flex-col items-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                <p class="font-bold font-mono text-emerald-800 mt-1">{{ number_format($virement->amount, 0, ',', ' ') }} F</p>
            </div>
            <div class="flex-1 text-center">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Destination</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $virement->toAccount?->name }}</p>
                <p class="text-xs text-gray-500">{{ ucfirst($virement->toAccount?->type ?? '') }}</p>
            </div>
        </div>
    </div>

    {{-- Détails --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-3 text-sm">
        @if($virement->reference)
        <div class="flex justify-between"><span class="text-gray-500">Référence</span><span class="text-gray-900">{{ $virement->reference }}</span></div>
        @endif
        <div class="flex justify-between"><span class="text-gray-500">Créé par</span><span class="text-gray-900">{{ $virement->createdBy?->name ?? '—' }}</span></div>
        <div class="flex justify-between"><span class="text-gray-500">Le</span><span class="text-gray-900">{{ $virement->created_at?->format('d/m/Y à H:i') }}</span></div>
        @if($virement->journalEntry)
        <div class="flex justify-between">
            <span class="text-gray-500">Écriture comptable</span>
            <a href="{{ route('comptabilite.journaux.show', $virement->journalEntry) }}" class="text-emerald-700 hover:underline font-mono">{{ $virement->journalEntry->number }}</a>
        </div>
        @endif
        @if($virement->notes)
        <div class="pt-2 border-t border-gray-100">
            <p class="text-gray-500 mb-1">Notes</p>
            <p class="text-gray-700">{{ $virement->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Annulation --}}
    @can('treasury.write')
    @if($virement->isCancellable())
    <div x-data="{ open: false }" class="bg-white rounded-[4px] border border-red-200 shadow-sm overflow-hidden">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-3 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
            <span>Annuler ce virement</span>
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-5 border-t border-red-100">
            <p class="text-xs text-gray-500 my-3">L'annulation recrédite la source, redébite la destination et contre-passe l'écriture comptable. Échoue si les fonds ont déjà été dépensés.</p>
            <form method="POST" action="{{ route('tresorerie.virements.cancel', $virement) }}"
                  data-confirm="Annuler le virement {{ $virement->number }} ? Les fonds seront restaurés et l'écriture contre-passée."
                  data-confirm-title="Annuler le virement">
                @csrf
                <textarea name="motif" rows="2" required minlength="5" maxlength="500" placeholder="Motif de l'annulation (obligatoire)…"
                          class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm mb-3 focus:ring-1 focus:ring-red-300"></textarea>
                <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700">Confirmer l'annulation</button>
            </form>
        </div>
    </div>
    @endif
    @endcan

    <a href="{{ route('tresorerie.virements.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour à la liste
    </a>

</div>
@endsection
