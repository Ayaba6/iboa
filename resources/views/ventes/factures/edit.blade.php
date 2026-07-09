@extends('layouts.erp')
@section('title', 'Modifier facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.show', $invoice) }}" class="hover:text-gray-700 font-mono">{{ $invoice->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-6xl">
    <x-validation-errors />

    {{-- [CONCURRENCE] Bandeau de verrou d'édition --}}
    <x-edit-lock-banner :model="$invoice" model-type="Invoice" :edit-lock="$editLock ?? null" />

    <form method="POST" action="{{ route('ventes.factures.update', $invoice) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        {{-- [CONCURRENCE] Protection anti-double-soumission + verrou optimiste --}}
        <x-form-guard :model="$invoice" />
        @include('ventes.factures._form')
    </form>
</div>
@endsection
