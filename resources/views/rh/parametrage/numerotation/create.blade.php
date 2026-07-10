@extends('layouts.erp')
@section('title', 'Nouvelle règle de numérotation')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.numerotation.index') }}" class="hover:text-gray-700">Numérotation</a>
    <span class="mx-1">/</span><span>Nouvelle règle</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle règle de numérotation</h1>
        <p class="text-sm text-gray-500 mt-1">Définissez le format et la séquence des numéros de bulletins.</p>
    </div>

    <form method="POST" action="{{ route('rh.numerotation.store') }}">
        @csrf
        @include('rh.parametrage.numerotation._form', ['isEdit' => false])
    </form>
</div>
@endsection
