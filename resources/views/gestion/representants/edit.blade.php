@extends('layouts.erp')
@section('title', 'Modifier — '.$representant->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('representants.index') }}" class="hover:text-gray-700">Représentants</a>
    <span class="mx-1">/</span>
    <a href="{{ route('representants.show', $representant) }}" class="hover:text-gray-700">{{ $representant->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="w-full">
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form action="{{ route('representants.update', $representant) }}" method="POST">
        @csrf
        @method('PUT')
        @include('gestion.representants._form')
    </form>
</div>
@endsection
