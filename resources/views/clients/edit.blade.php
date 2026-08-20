@extends('layouts.erp')
@section('title', 'Modifier — '.$client->displayName())

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.show', $client) }}" class="hover:text-gray-700">{{ $client->displayName() }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="w-full">

    {{-- Archive form — OUTSIDE la fiche pour éviter la collision _method --}}
    <form id="archiveClientForm" action="{{ route('clients.destroy', $client) }}" method="POST"
          data-confirm="Archiver {{ addslashes($client->displayName()) }} ?" class="hidden">
        @csrf @method('DELETE')
    </form>

    @include('clients._form')
</div>
@endsection
