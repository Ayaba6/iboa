@extends('layouts.erp')
@section('title', 'Nouveau modèle de bulletin')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.modeles-bulletins.index') }}" class="hover:text-gray-700">Modèles de bulletins</a>
    <span class="mx-1">/</span><span>Nouveau modèle</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">Nouveau modèle de bulletin</h1>
        <p class="text-sm text-gray-500 mt-1">Configurez la mise en page et les sections à afficher dans les bulletins PDF.</p>
    </div>

    <form method="POST" action="{{ route('rh.modeles-bulletins.store') }}">
        @csrf
        @include('rh.parametrage.modeles-bulletins._form', ['isEdit' => false])
    </form>
</div>
@endsection
