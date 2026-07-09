@extends('layouts.erp')
@section('title', $center->exists ? 'Modifier centre' : 'Nouveau centre')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('analytique.centres-couts.index') }}" class="hover:text-gray-700">Centres de coûts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $center->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-[17px] font-bold text-gray-900 mb-6">{{ $center->exists ? 'Modifier le centre' : 'Nouveau centre de coûts/profit' }}</h1>

    <x-validation-errors />

    <form method="POST" action="{{ $center->exists ? route('analytique.centres-couts.update', $center) : route('analytique.centres-couts.store') }}"
          class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
        @csrf
        @if($center->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-rose-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $center->code) }}"
                       class="input-field" placeholder="EX: CC-PROD-001" required maxlength="20">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-rose-500">*</span></label>
                <select name="type" class="input-field" required>
                    <option value="cost"       {{ old('type', $center->type) === 'cost'       ? 'selected' : '' }}>Centre de coûts</option>
                    <option value="profit"     {{ old('type', $center->type) === 'profit'     ? 'selected' : '' }}>Centre de profit</option>
                    <option value="investment" {{ old('type', $center->type) === 'investment' ? 'selected' : '' }}>Centre d'investissement</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-rose-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $center->name) }}"
                   class="input-field" placeholder="Ex: Production Tôles Bac" required maxlength="120">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Centre parent</label>
            <select name="parent_id" class="input-field">
                <option value="">— Aucun (racine) —</option>
                @foreach($parents as $p)
                <option value="{{ $p->id }}" {{ old('parent_id', $center->parent_id) == $p->id ? 'selected' : '' }}>
                    {{ $p->code }} — {{ $p->name }}
                </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="2" class="input-field" maxlength="500">{{ old('description', $center->description) }}</textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" id="is_active"
                   {{ old('is_active', $center->is_active ?? true) ? 'checked' : '' }}
                   class="w-4 h-4 text-emerald-700 rounded">
            <label for="is_active" class="text-sm text-gray-700">Centre actif</label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('analytique.centres-couts.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</div>
@endsection
