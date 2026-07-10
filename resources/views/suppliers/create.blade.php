@extends('layouts.erp')
@section('title', 'Nouveau fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau fournisseur</span>
@endsection

@section('content')
<div class="max-w-6xl">
    @include('suppliers._form')
</div>
@endsection
