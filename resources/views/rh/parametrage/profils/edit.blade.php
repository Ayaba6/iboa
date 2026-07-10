@extends('layouts.erp')
@section('title', 'Modifier · ' . $profile->code)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.profils.index') }}" class="hover:text-gray-700">Profils</a>
    <span class="mx-1">/</span><span>{{ $profile->code }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-100 rounded-[4px] flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-[17px] font-bold text-gray-900">Modifier <code class="text-emerald-700 font-mono text-lg">{{ $profile->code }}</code></h1>
                <p class="text-sm text-gray-500">{{ $profile->libelle }}</p>
            </div>
        </div>
        <a href="{{ route('rh.profils.show', $profile) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            Gérer les rubriques →
        </a>
    </div>

    <form method="POST" action="{{ route('rh.profils.update', $profile) }}">
        @csrf @method('PUT')
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
                Enregistrer
            </button>
        </div>
    </form>
</div>
@endsection
