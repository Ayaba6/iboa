@extends('layouts.erp')
@section('title', 'Modifier — '.$purchaseOrder->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.index') }}" class="hover:text-gray-700">Commandes fournisseurs</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.show', $purchaseOrder) }}" class="hover:text-gray-700 font-mono">{{ $purchaseOrder->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier la commande <span class="font-mono text-amber-600">{{ $purchaseOrder->number }}</span></h1>
    </div>

    {{-- [CONCURRENCE] Bandeau de verrou d'édition --}}
    <x-edit-lock-banner :model="$purchaseOrder" model-type="PurchaseOrder" :edit-lock="$editLock ?? null" />

    {{-- [FIX persistance intermittente] data-turbo="false" : soumission native (pas d'interception
         Turbo sur un formulaire piloté par Alpine) → les champs (dépôt réception, livraison, conditions
         de paiement) sont toujours envoyés à jour dès le 1er enregistrement. --}}
    <form method="POST" action="{{ route('achats.commandes.update', $purchaseOrder) }}" enctype="multipart/form-data" data-turbo="false">
        @csrf
        @method('PUT')
        {{-- [CONCURRENCE] Protection anti-double-soumission + verrou optimiste --}}
        <x-form-guard :model="$purchaseOrder" />
        @include('achats.commandes._form')
    </form>

</div>
@endsection
