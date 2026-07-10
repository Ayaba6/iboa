@extends('layouts.erp')
@section('title', 'Nouveau plan de paie')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.plans.index') }}" class="hover:text-gray-700">Plans</a>
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
            <h1 class="text-[17px] font-bold text-gray-900">Nouveau plan de paie</h1>
            <p class="text-sm text-gray-500">Créez un ensemble de rubriques pour un pays ou type d'employé</p>
        </div>
    </div>

    <form method="POST" action="{{ route('rh.plans.store') }}">
        @csrf
        @include('rh.parametrage.plans._form')

        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('rh.plans.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                ← Retour à la liste
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Créer le plan
            </button>
        </div>
    </form>
</div>
@endsection
