@extends('layouts.erp')
@section('title', 'Nouvelle commande fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.index') }}" class="hover:text-gray-700">Commandes fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle commande</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle commande fournisseur</h1>
    </div>

    {{-- [FIX persistance intermittente] Soumission native (voir edit.blade.php). --}}
    <form method="POST" action="{{ route('achats.commandes.store') }}" enctype="multipart/form-data" data-turbo="false">
        @csrf
        <x-form-guard />
        @include('achats.commandes._form')
    </form>

</div>
@endsection
