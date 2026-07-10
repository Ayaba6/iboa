@extends('layouts.erp')
@section('title', 'Nouvelle rubrique de paie')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.rubriques.index') }}" class="hover:text-gray-700">Rubriques</a>
    <span class="mx-1">/</span><span>Nouvelle rubrique</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle rubrique de paie</h1>
        <a href="{{ route('rh.rubriques.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← Retour à la liste
        </a>
    </div>

    <div class="bg-white border border-gray-300 rounded-[4px] p-6">
        <form method="POST" action="{{ route('rh.rubriques.store') }}">
            @csrf

            @include('rh.rubriques._form')

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('rh.rubriques.index') }}"
                   class="px-5 py-2 border border-gray-300 rounded-[4px] text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 shadow-sm">
                    Créer la rubrique
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
