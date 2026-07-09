@extends('layouts.erp')
@section('title', 'Nouvelle promotion')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('promotions.index') }}" class="hover:text-gray-700">Promotions</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle promotion</h1>
    </div>

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-[4px] text-sm">
        <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('promotions.store') }}" x-data="{ type: '{{ old('type', 'pourcentage') }}' }">
        @csrf
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-3">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500 focus:border-purple-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de remise <span class="text-red-500">*</span></label>
                    <select name="type" x-model="type" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                        <option value="pourcentage">Pourcentage (%)</option>
                        <option value="montant_fixe">Montant fixe (FCFA)</option>
                        <option value="prix_special">Prix spécial</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Valeur
                        <span x-text="type === 'pourcentage' ? '(%)' : '(FCFA)'" class="text-gray-400 font-normal"></span>
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="value" value="{{ old('value', 0) }}" min="0" step="0.01" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500 text-right">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Article (optionnel)</label>
                    <select name="product_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                        <option value="">-- Tous les articles --</option>
                        @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ old('product_id', request('product_id')) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Famille (optionnel)</label>
                    <select name="family_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
                        <option value="">-- Toutes les familles --</option>
                        @foreach($families as $f)
                        <option value="{{ $f->id }}" {{ old('family_id') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantité minimum</label>
                <input type="number" name="min_quantity" value="{{ old('min_quantity', 1) }}" min="0" step="0.01"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500">
            </div>

            <div class="flex items-center gap-3 py-3 border-t border-gray-100">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', '1') ? 'checked' : '' }}
                       class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="is_active" class="text-sm font-medium text-gray-700">Promotion active</label>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                    Créer la promotion
                </button>
                <a href="{{ route('promotions.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-[4px] hover:bg-gray-100 transition-colors">Annuler</a>
            </div>
        </div>
    </form>
</div>
@endsection
