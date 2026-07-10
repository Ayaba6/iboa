@extends('layouts.erp')
@section('title', 'Nouvelle commande')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle commande</span>
@endsection

@section('content')
<div class="max-w-6xl">
    <x-validation-errors />

    <form method="POST" action="{{ route('ventes.commandes.store') }}" enctype="multipart/form-data">
        @csrf
        <x-form-guard />
        @include('ventes.commandes._form')
    </form>
</div>
@endsection
