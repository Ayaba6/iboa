@extends('layouts.erp')
@section('title', 'Modifier le modèle ' . $template->libelle)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.modeles-bulletins.index') }}" class="hover:text-gray-700">Modèles de bulletins</a>
    <span class="mx-1">/</span><span>{{ $template->libelle }}</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">{{ $template->libelle }}</h1>
        <p class="text-sm text-gray-500 mt-1 font-mono">{{ $template->code }}</p>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
    @endif

    @if($template->items_count > 0)
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-[4px] p-4 text-sm text-blue-700 flex gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <strong class="font-semibold">{{ $template->items_count }} bulletin(s)</strong> utilisent ce modèle.
            Les modifications s'appliqueront uniquement aux prochaines générations — les PDF déjà produits ne sont pas affectés.
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('rh.modeles-bulletins.update', $template) }}">
        @csrf @method('PUT')
        @include('rh.parametrage.modeles-bulletins._form', ['isEdit' => true])
    </form>
</div>
@endsection
