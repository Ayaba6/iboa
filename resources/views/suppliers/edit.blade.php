@extends('layouts.erp')
@section('title', 'Modifier — '.$supplier->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.show', $supplier) }}" class="hover:text-gray-700">{{ $supplier->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-6xl">
    @include('suppliers._form')
</div>
@endsection
