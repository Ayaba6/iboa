@extends('layouts.erp')
@section('title', 'Nouvelle catégorie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('product-families.index') }}" class="hover:text-gray-700">Catégories</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle catégorie</span>
@endsection

@section('content')
<div class="flex items-start gap-4">
    @include('product-families._selector')
    <div class="flex-1 min-w-0 max-w-6xl">

    <form method="POST" action="{{ route('product-families.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf

        {{-- Barre d'en-tête façon SAGE X3 : titre fiche + actions à droite --}}
        <div class="flex items-center justify-between bg-white border border-gray-300 rounded-[4px] px-3 py-2.5">
            <div>
                <h1 class="text-[16px] font-bold text-gray-900 leading-tight">Catégories : Création complète</h1>
                <p class="text-[12px] text-gray-500">L'article hérite des propriétés de sa catégorie — flux, gestion de stock, unités, comptes</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
                <a href="{{ route('product-families.index') }}"
                   class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">
                    Abandon
                </a>
                <a href="{{ route('product-families.create') }}"
                   class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-full transition-colors">
                    Créer +
                </a>
            </div>
        </div>

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        @include('product-families._form')
    </form>

    </div>
</div>
@endsection
