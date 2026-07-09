@extends('layouts.erp')
@section('title', 'Modifier code journal ' . $journalType->code)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.journal-types.index') }}" class="hover:text-gray-700">Codes journaux</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $journalType->code }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-3">
    <h1 class="text-[16px] font-bold text-gray-900">
        Modifier le code journal <span class="font-mono text-violet-700">{{ $journalType->code }}</span>
    </h1>
    @include('comptabilite.journal-types._form')
</div>
@endsection
