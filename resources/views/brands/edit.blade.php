@extends('layouts.erp')
@section('title', 'Modifier la marque')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('brands.index') }}" class="hover:text-gray-700">Marques</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier — {{ $brand->name }}</span>
@endsection

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier la marque</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $brand->name }}</p>
    </div>

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-[4px] text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('brands.update', $brand) }}" class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-3">
        @csrf
        @method('PUT')

        {{-- Nom --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                Nom <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $brand->name) }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-[4px] text-sm focus:outline-none focus:ring-1 focus:ring-gray-400 focus:border-transparent @error('name') border-red-300 @enderror">
            @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-[4px] text-sm focus:outline-none focus:ring-1 focus:ring-gray-400 focus:border-transparent resize-none">{{ old('description', $brand->description) }}</textarea>
        </div>

        {{-- Statut actif --}}
        <div class="flex items-center gap-3 py-3 border-t border-gray-100">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', $brand->is_active) ? 'checked' : '' }}
                   class="w-4 h-4 text-gray-700 border-gray-300 rounded focus:ring-gray-400">
            <label for="is_active" class="text-sm font-medium text-gray-700">Marque active</label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit"
                    class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                Enregistrer les modifications
            </button>
            <a href="{{ route('brands.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-[4px] hover:bg-gray-100 transition-colors">
                Annuler
            </a>
        </div>
    </form>

</div>
@endsection
