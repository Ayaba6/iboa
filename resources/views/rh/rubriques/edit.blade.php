@extends('layouts.erp')
@section('title', "Modifier la rubrique {$rubric->code}")
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.rubriques.index') }}" class="hover:text-gray-700">Rubriques</a>
    <span class="mx-1">/</span><span>{{ $rubric->code }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">
                Modifier la rubrique
                <code class="text-xl bg-gray-100 px-2 py-0.5 rounded font-mono text-gray-700">{{ $rubric->code }}</code>
            </h1>
            <p class="text-sm text-gray-500 mt-1">{{ $rubric->libelle }}</p>
        </div>
        <a href="{{ route('rh.rubriques.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← Retour à la liste
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-[4px] text-emerald-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border border-gray-300 rounded-[4px] p-6">
        <form method="POST" action="{{ route('rh.rubriques.update', $rubric) }}">
            @csrf
            @method('PUT')

            @include('rh.rubriques._form')

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('rh.rubriques.index') }}"
                   class="px-5 py-2 border border-gray-300 rounded-[4px] text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 shadow-sm">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
