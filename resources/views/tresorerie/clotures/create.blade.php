@extends('layouts.erp')
@section('title', 'Nouvelle clôture de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.clotures.index') }}" class="hover:text-gray-700">Clôtures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php
    $accountsJson = $cashAccounts->mapWithKeys(fn($a) => [$a->id => (int) $a->current_balance]);
@endphp
<div class="max-w-xl mx-auto"
     x-data="{
        account: '{{ old('cash_account_id', '') }}',
        counted: {{ (int) old('counted_balance', 0) }},
        balances: {{ Js::from($accountsJson) }},
        get theoretical() { return this.account ? (this.balances[this.account] ?? 0) : null; },
        get diff() { return this.theoretical === null ? null : this.counted - this.theoretical; },
        fmt(n) { return n === null ? '—' : new Intl.NumberFormat('fr-FR').format(n) + ' F'; }
     }">

    <div class="mb-5">
        <h1 class="text-[17px] font-bold text-gray-900">Nouvelle clôture de caisse</h1>
        <p class="text-sm text-gray-500 mt-0.5">Comptez physiquement la caisse, le système calcule l'écart</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.clotures.store') }}"
          class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 space-y-3">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Caisse <span class="text-red-500">*</span></label>
                <select name="cash_account_id" x-model="account" required
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    <option value="">— Sélectionner —</option>
                    @foreach($cashAccounts as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" name="closure_date" value="{{ old('closure_date', date('Y-m-d')) }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Montant compté (FCFA) <span class="text-red-500">*</span></label>
            <input type="number" name="counted_balance" x-model.number="counted" min="0" step="1" required
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-3 text-lg text-right font-mono font-semibold focus:ring-1 focus:ring-emerald-300">
        </div>

        {{-- Comparatif --}}
        <div class="bg-gray-50 border border-gray-300 rounded-[4px] p-4 grid grid-cols-3 gap-3 text-center" x-show="theoretical !== null" x-cloak>
            <div>
                <p class="text-xs text-gray-500">Théorique</p>
                <p class="font-bold font-mono text-gray-700 mt-1" x-text="fmt(theoretical)"></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Compté</p>
                <p class="font-bold font-mono text-gray-900 mt-1" x-text="fmt(counted)"></p>
            </div>
            <div class="border-l border-gray-200">
                <p class="text-xs text-gray-500">Écart</p>
                <p class="font-bold font-mono mt-1" :class="diff === 0 ? 'text-gray-400' : (diff > 0 ? 'text-emerald-600' : 'text-red-600')"
                   x-text="(diff > 0 ? '+' : '') + fmt(diff)"></p>
            </div>
        </div>

        {{-- Motif écart (requis à la validation si écart) --}}
        <div x-show="diff !== null && diff !== 0" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Motif de l'écart <span class="text-amber-600">(requis pour valider)</span></label>
            <textarea name="difference_reason" rows="2" maxlength="1000" placeholder="Ex. : rendu de monnaie, vol, erreur de saisie…"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-amber-300">{{ old('difference_reason') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="1000"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.clotures.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
            <button type="submit" :disabled="!account || counted < 0"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 disabled:opacity-40 disabled:cursor-not-allowed">
                Enregistrer la clôture
            </button>
        </div>
    </form>
</div>
@endsection
