@extends('layouts.erp')
@section('title', 'Nouvel article')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.index') }}" class="hover:text-gray-700">Articles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="flex items-start gap-4">
    @include('products._selector')
    <div class="flex-1 min-w-0 max-w-6xl">
        @include('products._form')
    </div>
</div>
@endsection
