@extends('layouts.erp')
@section('title', 'Modifier compte ' . $account->code)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.plan-comptable.index') }}" class="hover:text-gray-700">Plan comptable</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $account->code }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-[16px] font-bold text-gray-900 mb-6">Modifier le compte {{ $account->code }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm mb-5">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('comptabilite.plan-comptable.update', $account) }}" method="POST">
        @csrf @method('PUT')
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">

            <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-[4px]">
                <span class="text-sm text-gray-500">Code :</span>
                <span class="font-mono font-semibold text-violet-700">{{ $account->code }}</span>
                <span class="text-xs text-gray-400">(non modifiable)</span>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Classe <span class="text-red-500">*</span></label>
                <select name="account_class_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    @foreach($classes as $class)
                    <option value="{{ $class->id }}" {{ old('account_class_id', $account->account_class_id) == $class->id ? 'selected' : '' }}>
                        {{ $class->number }} — {{ $class->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                        @foreach(['actif','passif','charge','produit','bilan','resultat'] as $t)
                        <option value="{{ $t }}" {{ old('type', $account->type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte parent</label>
                    <select name="parent_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                        <option value="">Aucun</option>
                        @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id', $account->parent_id) == $parent->id ? 'selected' : '' }}>
                            {{ $parent->code }} — {{ $parent->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_detail" value="1" {{ old('is_detail', $account->is_detail) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                    <span class="text-sm text-gray-700">Compte saisissable</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                    <span class="text-sm text-gray-700">Actif</span>
                </label>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-3">
            <a href="{{ route('comptabilite.plan-comptable.index') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                Enregistrer
            </button>
        </div>
    </form>
</div>
@endsection
