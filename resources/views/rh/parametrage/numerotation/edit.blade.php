@extends('layouts.erp')
@section('title', 'Modifier la règle ' . $numbering->libelle)
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.numerotation.index') }}" class="hover:text-gray-700">Numérotation</a>
    <span class="mx-1">/</span><span>{{ $numbering->libelle }}</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">{{ $numbering->libelle }}</h1>
        <p class="text-sm text-gray-500 mt-1 font-mono">{{ $numbering->code }}</p>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Avertissement modification --}}
    <div class="mb-6 bg-amber-50 border border-amber-200 rounded-[4px] p-4 text-sm text-amber-700 flex gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <div>
            <strong class="font-semibold">Attention.</strong>
            Modifier le format d'une règle déjà utilisée n'affectera pas les bulletins existants (leurs numéros sont stockés définitivement), mais les prochains bulletins utiliseront le nouveau format.
        </div>
    </div>

    <form method="POST" action="{{ route('rh.numerotation.update', $numbering) }}">
        @csrf @method('PUT')
        @include('rh.parametrage.numerotation._form', ['isEdit' => true])
    </form>
</div>
@endsection
