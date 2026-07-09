@extends('layouts.erp')
@section('title', 'Mes documents')
@section('breadcrumb')
    <a href="{{ route('rh.portail.dashboard') }}" class="hover:text-gray-700">Mon Espace RH</a>
    <span class="mx-1">/</span><span>Mes documents</span>
@endsection

@section('content')
<h1 class="text-[16px] font-bold text-gray-900 mb-6">Mes documents</h1>

@if($documents->isNotEmpty())
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($documents as $doc)
    @php
        $docIcons = ['cnib'=>'🪪','passeport'=>'📘','contrat'=>'📝','avenant'=>'📋','diplome'=>'🎓','attestation'=>'📄','medical'=>'🏥','cnss'=>'🏛','photo'=>'🖼','autre'=>'📎'];
        $docLabels = ['cnib'=>'CNIB','passeport'=>'Passeport','contrat'=>'Contrat','avenant'=>'Avenant','diplome'=>'Diplôme','attestation'=>'Attestation','medical'=>'Médical','cnss'=>'CNSS','photo'=>'Photo','autre'=>'Autre'];
        $isExpired = $doc->expires_at && $doc->expires_at->isPast();
        $expiringSoon = $doc->expires_at && !$isExpired && $doc->expires_at->diffInDays(now()) <= 30;
    @endphp
    <div class="bg-white rounded-[4px] border {{ $isExpired ? 'border-red-200' : ($expiringSoon ? 'border-amber-200' : 'border-gray-200') }} p-5">
        <div class="flex items-center gap-3 mb-3">
            <span class="text-2xl">{{ $docIcons[$doc->document_type] ?? '📎' }}</span>
            <div class="min-w-0">
                <p class="font-medium text-gray-800 truncate">{{ $doc->label }}</p>
                <p class="text-xs text-gray-400">{{ $docLabels[$doc->document_type] ?? $doc->document_type }}</p>
            </div>
        </div>
        @if($doc->document_date || $doc->expires_at)
        <div class="text-xs text-gray-500 space-y-0.5 mb-3">
            @if($doc->document_date)<div>Date : {{ $doc->document_date->format('d/m/Y') }}</div>@endif
            @if($doc->expires_at)
                <div class="{{ $isExpired ? 'text-red-600 font-semibold' : ($expiringSoon ? 'text-amber-600 font-semibold' : '') }}">
                    Expire : {{ $doc->expires_at->format('d/m/Y') }}
                    @if($isExpired) ⚠ Expiré @elseif($expiringSoon) ⚡ Bientôt @endif
                </div>
            @endif
        </div>
        @endif
        <a href="{{ route('rh.employes.documents.download', [$employee, $doc]) }}"
           class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#eef5f0] text-emerald-800 rounded-[4px] text-xs font-medium hover:bg-emerald-100">
            Télécharger
        </a>
    </div>
    @endforeach
</div>
@else
<div class="py-16 text-center text-gray-400">
    <p>Aucun document disponible. Contactez votre service RH.</p>
</div>
@endif
@endsection
