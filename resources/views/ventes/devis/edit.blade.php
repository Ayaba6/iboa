@extends('layouts.erp')
@section('title', 'Modifier devis '.$quote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.devis.index') }}" class="hover:text-gray-700">Devis</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.devis.show', $quote) }}" class="hover:text-gray-700">{{ $quote->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="space-y-1">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier le devis {{ $quote->number }}</h1>
        <a href="{{ route('ventes.devis.show', $quote) }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Annuler
        </a>
    </div>

    <form method="POST" action="{{ route('ventes.devis.update', $quote) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-form-guard :model="$quote" />
        @include('ventes.devis._form')
    </form>
</div>
@endsection
