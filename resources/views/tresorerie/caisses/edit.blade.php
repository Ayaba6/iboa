@extends('layouts.erp')
@section('title', 'Modifier ' . $caisse->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Comptes</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.show', $caisse) }}" class="hover:text-gray-700">{{ $caisse->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-3">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier — {{ $caisse->name }}</h1>
        <a href="{{ route('tresorerie.caisses.show', $caisse) }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.caisses.update', $caisse) }}" class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-3"
          x-data="{ type: '{{ old('type', $caisse->type) }}' }">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $caisse->name) }}" required maxlength="100"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $caisse->code) }}" required maxlength="30"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" x-model="type" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    <option value="banque"       {{ old('type', $caisse->type) === 'banque'       ? 'selected' : '' }}>Banque</option>
                    <option value="caisse"       {{ old('type', $caisse->type) === 'caisse'       ? 'selected' : '' }}>Caisse</option>
                    <option value="mobile_money" {{ old('type', $caisse->type) === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                <select name="payment_method_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    <option value="">— Aucun —</option>
                    @foreach($paymentMethods as $pm)
                    <option value="{{ $pm->id }}" {{ old('payment_method_id', $caisse->payment_method_id) == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                <input type="text" name="currency_code" value="{{ old('currency_code', $caisse->currency_code) }}" required maxlength="3"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte solde faible (FCFA)</label>
                <input type="number" name="min_balance" value="{{ old('min_balance', $caisse->min_balance) }}" min="0"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <p class="text-xs text-gray-400 mt-1">0 = pas d'alerte</p>
            </div>
            <div class="flex flex-col gap-2 justify-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $caisse->is_default) ? 'checked' : '' }} class="rounded text-emerald-700">
                    <span class="text-sm text-gray-700">Compte par défaut</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $caisse->is_active) ? 'checked' : '' }} class="rounded text-emerald-700">
                    <span class="text-sm text-gray-700">Compte actif</span>
                </label>
            </div>
        </div>

        {{-- Coordonnées bancaires (comptes de type Banque) --}}
        <div x-show="type === 'banque'" x-cloak class="border-t border-gray-100 pt-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Coordonnées bancaires</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banque</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $caisse->bank_name) }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agence</label>
                    <input type="text" name="bank_branch" value="{{ old('bank_branch', $caisse->bank_branch) }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">N° de compte (RIB)</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $caisse->account_number) }}" maxlength="50"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code SWIFT / BIC</label>
                    <input type="text" name="swift_bic" value="{{ old('swift_bic', $caisse->swift_bic) }}" maxlength="11"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                    <input type="text" name="iban" value="{{ old('iban', $caisse->iban) }}" maxlength="34"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="500"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none">{{ old('notes', $caisse->notes) }}</textarea>
        </div>
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.caisses.show', $caisse) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
