@extends('layouts.erp')
@section('title', 'Nouvelle unité de mesure')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('units.index') }}" class="hover:text-gray-700">Unités de mesure</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Création</span>
@endsection

@section('content')
@php $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900'; @endphp
<div class="max-w-5xl space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Unité de mesure : Création</h1>
            <p class="text-[11.5px] text-gray-500">Ex : Pièce, Kilogramme, Litre, Mètre…</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-unit" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('units.index') }}" class="text-[13px] font-semibold text-gray-500 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-full transition-colors">Abandon</a>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-unit" method="POST" action="{{ route('units.store') }}" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf
        <div class="{{ $secH }}">Informations générales</div>
        <div class="p-4">
            @include('units._form', ['unit' => null])
        </div>
    </form>

    {{-- Conversions — disponibles après création --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">Conversions</div>
        <p class="text-[12px] text-gray-400 px-3 py-1.5">Les unités équivalentes (rattachées à celle-ci via « Unité parente ») apparaîtront ici après enregistrement.</p>
    </div>

    <p class="text-[11.5px] text-gray-400 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Une unité sans unité parente est une unité de base. Le facteur exprime la valeur d'une unité dans son unité parente (ex : 1 g = 0,001 kg).
    </p>

</div>
@endsection
