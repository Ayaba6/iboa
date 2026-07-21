@extends('layouts.erp')
@section('title', 'Nouvelle famille')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('product-families.index') }}" class="hover:text-gray-700">Familles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle famille</span>
@endsection

@section('content')
<div class="flex items-start gap-4">
    @include('product-families._selector')
    <div class="flex-1 min-w-0">

    <form method="POST" action="{{ route('product-families.store') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf

        {{-- Barre d'en-tête façon SAGE X3 : titre fiche + actions à droite --}}
        <div class="flex items-center justify-between bg-white border border-gray-300 rounded-[4px] px-3 py-2.5">
            <div>
                <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Familles : Création</h1>
                <p class="text-[12px] text-gray-500">Classement commercial et statistique — la gestion des articles relève des catégories</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
                <a href="{{ route('product-families.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">
                    Abandon
                </a>
                <a href="{{ route('product-families.create') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">
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

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="mt-3 bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">Famille article</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    </div>
</div>
@endsection
