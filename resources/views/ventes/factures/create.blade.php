@extends('layouts.erp')
@section('title', 'Nouvelle facture')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle facture</span>
@endsection

@section('content')
<div class="max-w-6xl">
    <x-validation-errors />

    <form method="POST" action="{{ route('ventes.factures.store') }}" enctype="multipart/form-data">
        @csrf
        <x-form-guard />
        @include('ventes.factures._form')
    </form>
</div>
@endsection
