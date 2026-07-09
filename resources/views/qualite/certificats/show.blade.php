@extends('layouts.erp')
@section('title', 'Certificat ' . $certificate->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.certificats.index') }}" class="hover:text-gray-700">Certificats Qualité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $certificate->number }}</span>
@endsection

@section('content')
@php
    $rc = $certificate->resultat === 'conforme' ? 'bg-green-100 text-green-700 border-green-200' :
         ($certificate->resultat === 'non_conforme' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-amber-100 text-amber-700 border-amber-200');
@endphp
<div class="space-y-3">

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <p class="text-xs font-medium text-gray-500 mb-1">{{ $certificate->typeLabel() }}</p>
            <h1 class="text-[16px] font-bold text-gray-900">{{ $certificate->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $certificate->date_certificat?->format('d/m/Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center px-3 py-1.5 rounded-[4px] text-sm font-semibold border {{ $rc }}">
                {{ $certificate->resultatLabel() }}
            </span>
            <a href="{{ route('qualite.certificats.pdf', $certificate) }}" target="_blank"
               class="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm hover:bg-gray-50">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                PDF
            </a>
            @can('quality.manage')
            @if(!$certificate->validated_at)
            <form method="POST" action="{{ route('qualite.certificats.approve', $certificate) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white rounded-[4px] text-sm hover:bg-green-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Valider
                </button>
            </form>
            @endif
            <a href="{{ route('qualite.certificats.edit', $certificate) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm hover:bg-emerald-800">
                Modifier
            </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Données générales --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Données générales</h2>
            <dl class="space-y-3">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">N° Lot</dt>
                    <dd class="font-mono font-medium text-gray-900">{{ $certificate->lot_number ?? '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Fournisseur</dt>
                    <dd class="text-gray-900">{{ $certificate->fournisseur ?? '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Date réception</dt>
                    <dd class="text-gray-900">{{ $certificate->date_reception?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Norme</dt>
                    <dd class="text-gray-900">{{ $certificate->norme ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Caractéristiques physiques --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Caractéristiques physiques</h2>
            <dl class="space-y-3">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Poids réel</dt>
                    <dd class="font-medium text-gray-900">{{ $certificate->poids_reel ? number_format($certificate->poids_reel, 3).' t' : '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Largeur</dt>
                    <dd class="font-medium text-gray-900">{{ $certificate->largeur_mm ? number_format($certificate->largeur_mm, 2).' mm' : '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Épaisseur</dt>
                    <dd class="font-medium text-gray-900">{{ $certificate->epaisseur_mm ? number_format($certificate->epaisseur_mm, 3).' mm' : '—' }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">Couleur</dt>
                    <dd class="text-gray-900">{{ $certificate->couleur ?? '—' }}</dd>
                </div>
            </dl>
        </div>

    </div>

    {{-- Observations --}}
    @if($certificate->observations)
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Observations</h2>
        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $certificate->observations }}</p>
    </div>
    @endif

    {{-- Validation --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Signatures</h2>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contrôleur</p>
                <p class="text-sm font-medium text-gray-900">{{ $certificate->controleur?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Validateur</p>
                <p class="text-sm font-medium text-gray-900">{{ $certificate->validateur?->name ?? '—' }}</p>
                @if($certificate->validated_at)
                <p class="text-xs text-gray-400 mt-0.5">{{ $certificate->validated_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
