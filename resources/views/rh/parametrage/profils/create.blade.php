@extends('layouts.erp')
@section('title', 'Nouveau profil de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.profils.index') }}" class="hover:text-gray-700">Profils</a>
    <span class="mx-1">/</span><span>Nouveau</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-emerald-100 rounded-[4px] flex items-center justify-center">
            <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </div>
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Nouveau profil de paie</h1>
            <p class="text-sm text-gray-500">Configurez les rubriques applicables à une catégorie d'employés</p>
        </div>
    </div>

    {{-- Explication --}}
    <div class="mb-5 flex items-start gap-2.5 bg-blue-50 border border-blue-100 rounded-[4px] px-3 py-1.5">
        <svg class="w-4 h-4 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-xs text-blue-700">
            <p class="font-semibold mb-0.5">Comment ça marche ?</p>
            <p>Sélectionnez un plan de paie → les rubriques du plan sont héritées automatiquement. Vous pourrez ensuite, depuis la fiche du profil, désactiver certaines rubriques ou surcharger leurs montants/taux pour cette catégorie d'employés.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('rh.profils.store') }}">
        @csrf
        @include('rh.parametrage.profils._form')

        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('rh.profils.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                ← Retour à la liste
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Créer le profil
            </button>
        </div>
    </form>
</div>
@endsection
