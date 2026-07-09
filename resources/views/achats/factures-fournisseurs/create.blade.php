@extends('layouts.erp')
@section('title', 'Nouvelle facture fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.factures-fournisseurs.index') }}" class="hover:text-gray-700">Factures fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle facture</span>
@endsection

@section('content')
<x-validation-errors />

<form method="POST" enctype="multipart/form-data" action="{{ route('achats.factures-fournisseurs.store') }}" class="max-w-6xl">
    @csrf
    @include('achats.factures-fournisseurs._form')
</form>
@endsection
