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
    <div class="flex-1 min-w-0 space-y-3">

    {{-- ═══ Bandeau SAGE X3 (même squelette que fiche/modification) ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Familles : Création</h2>
                <p class="text-[11.5px] text-gray-400">Classement commercial et statistique — la gestion des articles relève des <a href="{{ route('articles.categories.index') }}" class="underline">catégories</a></p>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="submit" form="family-form"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
                <a href="{{ route('product-families.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">
                    Abandon
                </a>
            </div>
        </div>

        <div class="p-4">
            <form id="family-form" method="POST" action="{{ route('product-families.store') }}" class="space-y-3">
                @csrf
                @include('product-families._form')
            </form>
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">Famille article</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    </div>
</div>
@endsection
