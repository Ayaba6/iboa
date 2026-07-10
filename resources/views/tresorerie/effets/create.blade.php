@extends('layouts.erp')
@section('title', 'Nouvel effet de commerce')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.effets.index') }}" class="hover:text-gray-700">Effets</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div x-data="{ direction: '{{ old('direction', 'a_recevoir') }}' }" class="max-w-3xl space-y-3">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvel effet de commerce</h1>
        <a href="{{ route('tresorerie.effets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.effets.store') }}" class="space-y-3">
        @csrf

        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Identification</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="cheque"       {{ old('type') === 'cheque'       ? 'selected' : '' }}>Chèque</option>
                        <option value="lcr"          {{ old('type', 'lcr') === 'lcr'   ? 'selected' : '' }}>LCR</option>
                        <option value="billet_ordre" {{ old('type') === 'billet_ordre' ? 'selected' : '' }}>Billet à ordre</option>
                        <option value="traite"       {{ old('type') === 'traite'       ? 'selected' : '' }}>Traite</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Direction <span class="text-red-500">*</span></label>
                    <select name="direction" x-model="direction" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="a_recevoir">À recevoir (client)</option>
                        <option value="a_payer">À payer (fournisseur)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" value="{{ old('amount') }}" required min="1"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date d'émission <span class="text-red-500">*</span></label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date d'échéance</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                    <input type="text" name="currency_code" value="{{ old('currency_code', 'XOF') }}" maxlength="3"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Référence (N° chèque, etc.)</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" maxlength="100"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Tiers --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Tiers</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div x-show="direction === 'a_recevoir'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                    <select name="client_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ old('client_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="direction === 'a_payer'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                    <select name="supplier_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">— Aucun —</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tireur (drawer)</label>
                    <input type="text" name="drawer" value="{{ old('drawer') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tiré (drawee)</label>
                    <input type="text" name="drawee" value="{{ old('drawee') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bénéficiaire</label>
                    <input type="text" name="payee" value="{{ old('payee') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        {{-- Bank --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Domiciliation bancaire</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banque</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte bancaire</label>
                    <input type="text" name="bank_account" value="{{ old('bank_account') }}" maxlength="100"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 resize-none">{{ old('notes') }}</textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('tresorerie.effets.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Créer l'effet</button>
        </div>
    </form>
</div>
@endsection
