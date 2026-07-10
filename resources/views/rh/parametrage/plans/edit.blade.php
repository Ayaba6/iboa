@extends('layouts.erp')
@section('title', 'Modifier · ' . $plan->code)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.plans.index') }}" class="hover:text-gray-700">Plans</a>
    <span class="mx-1">/</span><span>{{ $plan->code }}</span>
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
                <h1 class="text-[17px] font-bold text-gray-900">Modifier <code class="text-emerald-700 font-mono text-lg">{{ $plan->code }}</code></h1>
                <p class="text-sm text-gray-500">{{ $plan->libelle }}</p>
            </div>
        </div>
        <a href="{{ route('rh.plans.show', $plan) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-[4px] text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Voir les rubriques
        </a>
    </div>

    <form method="POST" action="{{ route('rh.plans.update', $plan) }}">
        @csrf @method('PUT')
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
                Enregistrer
            </button>
        </div>
    </form>
</div>
@endsection
