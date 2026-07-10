@extends('layouts.erp')
@section('title', 'Nouveau devis')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.devis.index') }}" class="hover:text-gray-700">Devis</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Nouveau devis</h1>
            <p class="text-sm text-gray-500 mt-0.5">Créez un devis et imputez-le sur un client</p>
        </div>
        <a href="{{ route('ventes.devis.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour
        </a>
    </div>

    <form method="POST" action="{{ route('ventes.devis.store') }}" enctype="multipart/form-data">
        @csrf
        <x-form-guard />
        @include('ventes.devis._form')
    </form>
</div>
@endsection
