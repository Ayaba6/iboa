@extends('layouts.erp')
@section('title', 'Modifier · ' . $constant->code)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.constantes.index') }}" class="hover:text-gray-700">Constantes</a>
    <span class="mx-1">/</span><span>{{ $constant->code }}</span>
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
                <h1 class="text-[17px] font-bold text-gray-900">Modifier <code class="text-emerald-700 font-mono text-lg">{{ $constant->code }}</code></h1>
                <p class="text-sm text-gray-500">{{ $constant->libelle }}</p>
            </div>
        </div>
        <a href="{{ route('rh.constantes.history', $constant->code) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Historique
        </a>
    </div>

    {{-- Info : mise à jour historisée --}}
    <div class="mb-5 flex items-start gap-2.5 bg-blue-50 border border-blue-100 rounded-[4px] px-3 py-1.5">
        <svg class="w-4 h-4 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-blue-700">
            Cette modification met à jour la constante existante. Pour créer une nouvelle version historisée
            (ex : nouveau taux SMIG 2025), utilisez <a href="{{ route('rh.constantes.create') }}"
            class="font-semibold underline">créer une nouvelle constante</a> avec le même code et une date de validité différente.
        </p>
    </div>

    <form method="POST" action="{{ route('rh.constantes.update', $constant) }}">
        @csrf @method('PUT')
        @include('rh.parametrage.constantes._form')

        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('rh.constantes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                ← Retour à la liste
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection
