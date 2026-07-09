@extends('layouts.erp')
@section('title', 'Nouveau virement')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.virements.index') }}" class="hover:text-gray-700">Virements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $accountsJson = $cashAccounts->mapWithKeys(fn($a) => [$a->id => ['name' => $a->name, 'type' => $a->type, 'balance' => (int) $a->current_balance]]);
@endphp
<div class="max-w-2xl mx-auto"
     x-data="{
        from: '{{ old('from_cash_account_id', '') }}',
        to: '{{ old('to_cash_account_id', '') }}',
        amount: {{ (int) old('amount', 0) }},
        accounts: {{ Js::from($accountsJson) }},
        get fromBalance() { return this.accounts[this.from]?.balance ?? null; },
        get toBalance() { return this.accounts[this.to]?.balance ?? null; },
        get sameAccount() { return this.from && this.to && this.from === this.to; },
        get insufficient() { return this.fromBalance !== null && this.amount > this.fromBalance; },
        get fromAfter() { return this.fromBalance !== null ? this.fromBalance - this.amount : null; },
        get toAfter() { return this.toBalance !== null ? this.toBalance + this.amount : null; },
        fmt(n) { return n === null ? '—' : new Intl.NumberFormat('fr-FR').format(n) + ' F'; },
        get blocked() { return !this.from || !this.to || this.sameAccount || this.amount <= 0 || this.insufficient; }
     }">

    <div class="mb-5">
        <h1 class="text-[17px] font-bold text-gray-900">Nouveau virement interne</h1>
        <p class="text-sm text-gray-500 mt-0.5">Déplacer des fonds entre deux comptes de trésorerie</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.virements.store') }}"
          class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-6 space-y-3">
        @csrf

        {{-- Source / Destination --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Compte source <span class="text-red-500">*</span></label>
                <select name="from_cash_account_id" x-model="from" required
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    <option value="">— Sélectionner —</option>
                    @foreach($cashAccounts as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->name }} ({{ ucfirst($ca->type) }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-show="fromBalance !== null">
                    Solde : <span class="font-semibold" x-text="fmt(fromBalance)"></span>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Compte destination <span class="text-red-500">*</span></label>
                <select name="to_cash_account_id" x-model="to" required
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
                    <option value="">— Sélectionner —</option>
                    @foreach($cashAccounts as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->name }} ({{ ucfirst($ca->type) }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-show="toBalance !== null">
                    Solde : <span class="font-semibold" x-text="fmt(toBalance)"></span>
                </p>
            </div>
        </div>

        <p class="text-xs text-red-600" x-show="sameAccount" x-cloak>Les comptes source et destination doivent être différents.</p>

        {{-- Montant + Date --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Montant (FCFA) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" x-model.number="amount" min="1" step="1" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm text-right font-mono focus:ring-1 focus:ring-emerald-300"
                       :class="insufficient ? 'border-red-400' : ''">
                <p class="text-xs text-red-600 mt-1" x-show="insufficient" x-cloak>Solde insuffisant sur le compte source.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
            </div>
        </div>

        {{-- Aperçu soldes après --}}
        <div class="bg-[#eef5f0] border border-emerald-100 rounded-[4px] p-4 grid grid-cols-2 gap-3 text-sm" x-show="from && to && !sameAccount && amount > 0" x-cloak>
            <div>
                <p class="text-xs text-emerald-600">Source après</p>
                <p class="font-bold font-mono" :class="fromAfter < 0 ? 'text-red-600' : 'text-emerald-900'" x-text="fmt(fromAfter)"></p>
            </div>
            <div>
                <p class="text-xs text-emerald-600">Destination après</p>
                <p class="font-bold font-mono text-emerald-700" x-text="fmt(toAfter)"></p>
            </div>
        </div>

        {{-- Référence + Notes --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Référence <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <input type="text" name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="N° bordereau, motif…"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="1000"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300">{{ old('notes') }}</textarea>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.virements.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
            <button type="submit" :disabled="blocked"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m4 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Effectuer le virement
            </button>
        </div>
    </form>
</div>
@endsection
