@extends('layouts.erp')
@section('title', 'Clôture ' . $cloture->number)

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.clotures.index') }}" class="hover:text-gray-700">Clôtures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $cloture->number }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900 font-mono">{{ $cloture->number }}</h1>
            <p class="text-sm text-gray-500">{{ $cloture->cashAccount?->name }} · {{ $cloture->closure_date?->format('d/m/Y') }}</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $cloture->status === 'valide' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
            {{ $cloture->status === 'valide' ? 'Validée' : 'Brouillon' }}
        </span>
    </div>

    {{-- Comparatif --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 grid grid-cols-3 gap-4 text-center">
        <div>
            <p class="text-xs text-gray-400 uppercase">Théorique</p>
            <p class="font-bold font-mono text-gray-700 text-lg mt-1">{{ number_format($cloture->theoretical_balance, 0, ',', ' ') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase">Compté</p>
            <p class="font-bold font-mono text-gray-900 text-lg mt-1">{{ number_format($cloture->counted_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-xs text-gray-400 uppercase">Écart</p>
            <p class="font-bold font-mono text-lg mt-1 {{ $cloture->difference == 0 ? 'text-gray-400' : ($cloture->difference > 0 ? 'text-emerald-600' : 'text-red-600') }}">
                {{ $cloture->difference == 0 ? '0' : ($cloture->difference > 0 ? '+' : '') . number_format($cloture->difference, 0, ',', ' ') }}
            </p>
        </div>
    </div>

    @if($cloture->hasDifference())
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-sm">
        <p class="font-medium text-amber-800">Écart constaté</p>
        @if($cloture->difference_reason)
        <p class="text-amber-700 mt-1">{{ $cloture->difference_reason }}</p>
        @else
        <p class="text-amber-600 mt-1 italic">Aucun motif renseigné — requis pour valider.</p>
        @endif
    </div>
    @endif

    {{-- Détails --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-gray-500">Créé par</span><span class="text-gray-900">{{ $cloture->createdBy?->name ?? '—' }}</span></div>
        @if($cloture->status === 'valide')
        <div class="flex justify-between"><span class="text-gray-500">Validée par</span><span class="text-gray-900">{{ $cloture->validatedBy?->name ?? '—' }} le {{ $cloture->validated_at?->format('d/m/Y à H:i') }}</span></div>
        @endif
        @if($cloture->journalEntry)
        <div class="flex justify-between">
            <span class="text-gray-500">Écriture d'écart</span>
            <a href="{{ route('comptabilite.journaux.show', $cloture->journalEntry) }}" class="text-emerald-700 hover:underline font-mono">{{ $cloture->journalEntry->number }}</a>
        </div>
        @endif
        @if($cloture->notes)
        <div class="pt-2 border-t border-gray-100"><p class="text-gray-500 mb-1">Notes</p><p class="text-gray-700">{{ $cloture->notes }}</p></div>
        @endif
    </div>

    {{-- Validation --}}
    @can('treasury.write')
    @if($cloture->isValidatable())
    <div class="bg-white rounded-[4px] border border-emerald-200 shadow-sm p-4">
        <p class="text-sm text-gray-600 mb-3">La validation ajuste le solde de la caisse au montant compté @if($cloture->hasDifference()) et génère l'écriture d'écart comptable.@else.@endif</p>
        <form method="POST" action="{{ route('tresorerie.clotures.validate', $cloture) }}"
              data-confirm="Valider la clôture {{ $cloture->number }} ?@if($cloture->hasDifference()) L'écart sera comptabilisé.@endif"
              data-confirm-title="Valider la clôture"
              data-confirm-label="Valider"
              data-confirm-danger="false">
            @csrf
            @if($cloture->hasDifference() && !$cloture->difference_reason)
            <textarea name="difference_reason" rows="2" required placeholder="Motif de l'écart (obligatoire)…"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm mb-3 focus:ring-1 focus:ring-amber-300"></textarea>
            @endif
            <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-700">
                Valider la clôture
            </button>
        </form>
    </div>
    @endif
    @endcan

    <a href="{{ route('tresorerie.clotures.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour à la liste
    </a>

</div>
@endsection
