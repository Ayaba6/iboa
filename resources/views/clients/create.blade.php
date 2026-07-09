@extends('layouts.erp')
@section('title', 'Nouveau client')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau client</span>
@endsection

@section('content')
<div class="max-w-6xl">
    @include('clients._form')
</div>
@endsection
