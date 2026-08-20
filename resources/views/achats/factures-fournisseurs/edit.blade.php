@extends('layouts.erp')
@section('title', 'Modifier facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.factures-fournisseurs.index') }}" class="hover:text-gray-700">Factures fournisseurs</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.factures-fournisseurs.show', $invoice) }}" class="hover:text-gray-700 font-mono">{{ $invoice->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-[4px] p-4">
    <ul class="text-[13px] text-red-700 space-y-1 list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ route('achats.factures-fournisseurs.update', $invoice) }}" class="max-w-6xl">
    @csrf
    @method('PUT')
    @include('achats.factures-fournisseurs._form')
</form>
@endsection
