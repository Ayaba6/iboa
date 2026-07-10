@extends('layouts.erp')
@section('title', 'Nouvel encaissement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.encaissements.index') }}" class="hover:text-gray-700">Encaissements clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $formConfig = [
        'clientId'        => $selectedClient,
        'amount'          => old('amount'),
        'bankFees'        => old('bank_fees', 0),
        'paymentMethodId' => old('payment_method_id', ''),
        'paymentMethods'  => $paymentMethods,
    ];
    $lbl  = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp  = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo= 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'text-[14px] font-bold text-emerald-700 mb-3';
    $err  = 'w-full h-8 px-2 border border-red-400 bg-red-50 rounded-[3px] text-[14px] focus:outline-none';
    $caret= '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel= 'bg-white border border-gray-200 rounded-[4px] p-4';
    $sfx  = '<span class="inline-flex items-center justify-center h-8 px-2 border border-l-0 border-gray-200 rounded-r-[3px] bg-gray-50 text-[12px] text-gray-500">XOF</span>';
@endphp

<div class="max-w-[1400px]"
     x-data="Object.assign(paymentForm({{ \Illuminate\Support\Js::from($formConfig) }}), { tab: 'general', heure: '' })"
     x-init="setInterval(() => heure = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'}), 1000)">

    <form method="POST" action="{{ route('tresorerie.encaissements.store') }}" enctype="multipart/form-data"
          x-ref="form" @submit.prevent="submitForm()" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvel encaissement client</h1>
            </div>
            <div class="flex items-center gap-1.5" x-data="{ menu: false }">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" @click="submitForm(true)" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-gray-600 hover:text-gray-800 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('tresorerie.encaissements.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
                <div class="relative">
                    <button type="button" @click="menu = !menu" class="w-9 h-9 flex items-center justify-center text-gray-500 hover:text-gray-800 rounded-[4px] hover:bg-gray-100">⋮</button>
                    <div x-show="menu" @click.outside="menu = false" x-cloak class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-[4px] shadow-lg py-1 z-10 text-[13px]">
                        <a href="{{ route('tresorerie.encaissements.index') }}" class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">Liste des encaissements</a>
                        <button type="button" onclick="window.print()" class="block w-full text-left px-3 py-1.5 text-gray-600 hover:bg-gray-50">Imprimer</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs (ancres de défilement) --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general'=>'Général','reglement'=>'Règlement','imputation'=>'Imputation','pieces'=>'Pièces jointes','complement'=>'Complément'] as $tk => $tl)
            <button type="button" @click="tab='{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({behavior:'smooth', block:'start'})"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-[4px] text-[14px]">
            <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- ═══ Rangée 1 : 1. Informations générales | 2. Détails du règlement ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section x-ref="sec_general" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Informations générales</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-2"><label class="{{ $lbl }}">Numéro encaissement <span class="text-red-500">*</span></label><input type="text" value="ENC-Auto" class="{{ $inpRo }} font-mono" readonly></div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5 truncate">{{ optional(currentCompany())->name }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                        <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
                    </div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Date encaissement <span class="text-red-500">*</span></label><input type="date" name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}" class="{{ $errors->has('payment_date') ? $err : $inp }}"></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Heure <span class="text-red-500">*</span></label><input type="text" :value="heure || '—'" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Statut</label><input type="text" value="Brouillon" class="{{ $inpRo }}" readonly></div>

                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Client <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="client_id" required x-model="clientId" @change="onClientChange()" class="{{ $errors->has('client_id') ? $err.' pr-8 appearance-none' : $lk }}">
                                <option value="">—</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ (old('client_id', $selectedClient) == $client->id) ? 'selected' : '' }}>{{ $client->trade_name ?? $client->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('client_id')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Référence paiement</label><input type="text" name="reference" maxlength="100" value="{{ old('reference') }}" class="{{ $inp }}"></div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                        <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA BCEAO</p>
                    </div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Journal de trésorerie <span class="text-red-500">*</span></label>
                        <input type="text" name="treasury_journal" maxlength="20" value="{{ old('treasury_journal', 'BAN1') }}" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Banque principale XOF</p>
                    </div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Mode de règlement <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="payment_method_id" x-model="paymentMethodId" class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="{{ $lbl }}">Condition de paiement</label>
                        <input type="text" name="payment_condition" maxlength="60" value="{{ old('payment_condition') }}" placeholder="30 jours fin de mois" class="{{ $inp }}">
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Banque / Caisse <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="cash_account_id" required class="{{ $lk }}">
                                <option value="">—</option>
                                @foreach($cashAccounts as $ca)
                                    <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>{{ $ca->name }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-8"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" class="w-full px-2.5 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes') }}</textarea></div>

                    <div class="col-span-4" x-show="isMobileMoney" x-transition x-cloak>
                        <label class="{{ $lbl }}">Tél. Mobile Money <span class="text-red-500" x-show="isMobileMoney">*</span></label>
                        <input type="tel" name="phone_number" maxlength="20" value="{{ old('phone_number') }}" placeholder="+226 70…" class="{{ $inp }}">
                    </div>
                </div>
            </section>

            <section x-ref="sec_reglement" class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Détails du règlement</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-3">
                    <div>
                        <label class="{{ $lbl }}">Montant reçu <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <input type="number" name="amount" min="1" step="1" required x-model.number="amount" class="{{ $errors->has('amount') ? $err.' rounded-r-none text-right tabular-nums' : $inp.' rounded-r-none text-right tabular-nums' }}">{!! $sfx !!}
                        </div>
                        @error('amount')<p class="text-red-500 text-[12px] mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant à affecter</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(amount||0)" class="{{ $inpRo }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>

                    <div>
                        <label class="{{ $lbl }}">Frais bancaires</label>
                        <div class="flex"><input type="number" name="bank_fees" min="0" step="1" x-model.number="bankFees" placeholder="0" class="{{ $inp }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Montant net</label>
                        <div class="flex"><input type="text" readonly :value="new Intl.NumberFormat('fr-FR').format(netAmount)" class="{{ $inpRo }} rounded-r-none text-right tabular-nums">{!! $sfx !!}</div>
                    </div>

                    <div><label class="{{ $lbl }}">Numéro de pièce</label><input type="text" name="piece_number" maxlength="60" value="{{ old('piece_number') }}" class="{{ $inp }} font-mono"></div>
                    <div><label class="{{ $lbl }}">Référence bancaire</label><input type="text" name="bank_reference" maxlength="100" value="{{ old('bank_reference') }}" class="{{ $inp }} font-mono"></div>

                    <div><label class="{{ $lbl }}">Date valeur</label><input type="date" name="value_date" value="{{ old('value_date') }}" class="{{ $inp }}"></div>
                    <div class="relative">
                        <label class="{{ $lbl }}">Compte bancaire <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ old('bank_account', '') }}" placeholder="512100" class="{{ $inpRo }} font-mono" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Lié à la banque/caisse sélectionnée</p>
                    </div>

                    <div class="col-span-2 flex flex-wrap items-center gap-x-5 gap-y-2 pt-2 border-t border-gray-100 mt-1">
                        <label class="inline-flex items-center gap-1.5 text-[12px] cursor-pointer">
                            <input type="checkbox" name="is_acompte" value="1" {{ old('is_acompte') ? 'checked' : '' }} class="w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600 focus:ring-emerald-500">
                            <span :class="remainingToAllocate > 0 && totalAllocated > 0 ? 'text-gray-700' : 'text-gray-700'">Paiement partiel</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-[12px] cursor-pointer text-gray-400">
                            <input type="checkbox" disabled class="w-[15px] h-[15px] rounded-[2px] border-gray-300">
                            <span>Encaissement validé</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-[12px] cursor-pointer">
                            <input type="checkbox" checked disabled class="w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600">
                            <span class="text-gray-700">Générer écriture comptable</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-[12px] cursor-pointer text-amber-600 ml-auto">
                            <input type="checkbox" name="force_duplicate" value="1" {{ old('force_duplicate') ? 'checked' : '' }} class="w-[14px] h-[14px] rounded-[2px] border-amber-400 text-amber-600">
                            <span>Forcer (doublon)</span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ 3. Factures / documents à affecter ═══ --}}
        <section x-ref="sec_imputation" class="bg-white border border-gray-200 rounded-[4px]">
            <div class="px-4 pt-4 pb-1">
                <h2 class="{{ $secH }} mb-2"><span class="text-gray-400 font-normal">3.</span> Factures / documents à affecter</h2>
                {{-- Barre d'outils (SAGE X3) --}}
                <div class="flex items-center gap-2 pb-2 text-gray-500">
                    <button type="button" @click="autoAllocate()" :disabled="invoices.length === 0 || amount <= 0"
                            class="w-6 h-6 flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-[3px] disabled:opacity-40 disabled:cursor-not-allowed" title="Répartir automatiquement">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button type="button" @click="invoices.forEach(i => i.allocated = 0)" :disabled="invoices.length === 0"
                            class="w-6 h-6 flex items-center justify-center hover:text-red-600 hover:bg-red-50 rounded-[3px] disabled:opacity-40" title="Remettre les imputations à zéro">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    <span class="w-px h-4 bg-gray-200"></span>
                    <span class="text-[12px]" x-text="invoices.length + ' Résultat' + (invoices.length > 1 ? 's' : '')"></span>
                    <span class="text-[12px] text-gray-400 ml-2">Afficher les soldes en</span>
                    <span class="inline-flex items-center h-6 px-2 border border-gray-300 rounded-[3px] text-[12px] text-gray-700 bg-white">Devise <span class="ml-1 text-[9px]">▾</span></span>
                    <span class="ml-auto flex items-center gap-1 text-gray-400">
                        <button type="button" @click="autoAllocate()" :disabled="invoices.length === 0 || amount <= 0"
                                class="inline-flex items-center gap-1.5 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-200 text-[12px] font-semibold px-2.5 py-1 rounded-[4px] transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Répartir auto.
                        </button>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </span>
                </div>
            </div>

            <div x-show="!clientId" class="px-4 py-6 text-gray-400 text-[13px]">Sélectionnez un client pour charger ses factures.</div>
            <div x-show="clientId && loading" class="px-4 py-6 text-gray-400 text-[13px]">Chargement des factures…</div>
            <div x-show="clientId && !loading && invoices.length === 0" class="px-4 py-6 text-gray-400 text-[13px]">Aucune facture impayée — le paiement sera enregistré comme avance.</div>

            <div x-show="clientId && !loading && invoices.length > 0" class="overflow-x-auto">
                <table class="w-full text-[13px] border-collapse">
                    <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                        <tr>
                            <th class="px-3 py-2 text-left w-10"><input type="checkbox" class="w-3.5 h-3.5 rounded border-gray-300" disabled></th>
                            <th class="px-3 py-2 text-left w-14">Ligne</th>
                            <th class="px-3 py-2 text-left">Type document</th>
                            <th class="px-3 py-2 text-left">N° facture</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Échéance</th>
                            <th class="px-3 py-2 text-right">Montant TTC</th>
                            <th class="px-3 py-2 text-right">Déjà réglé</th>
                            <th class="px-3 py-2 text-right">Reste à payer</th>
                            <th class="px-3 py-2 text-right">Montant affecté</th>
                            <th class="px-3 py-2 text-left">Devise</th>
                            <th class="px-3 py-2 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(inv, index) in invoices" :key="inv.id">
                            <tr class="hover:bg-emerald-50/40">
                                <td class="px-3 py-1.5"><input type="checkbox" class="w-3.5 h-3.5 rounded border-gray-300"></td>
                                <td class="px-3 py-1.5 tabular-nums text-gray-500" x-text="index + 1"></td>
                                <td class="px-3 py-1.5 text-gray-700">Facture</td>
                                <td class="px-3 py-1.5">
                                    <span class="font-mono text-emerald-700 underline" x-text="inv.number"></span>
                                    <input type="hidden" :name="`allocations[${index}][invoice_id]`" :value="inv.id">
                                    <input type="hidden" :name="`allocations[${index}][allocated_amount]`" :value="inv.allocated || 0">
                                </td>
                                <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap" x-text="inv.issued_at || '—'"></td>
                                <td class="px-3 py-1.5 whitespace-nowrap"><span :class="inv.status === 'en_retard' ? 'text-red-600' : 'text-gray-600'" x-text="inv.due_at || '—'"></span></td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-700 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format(inv.total_ttc)"></td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format((inv.total_ttc||0) - (inv.remaining_amount||0))"></td>
                                <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-800 whitespace-nowrap" x-text="new Intl.NumberFormat('fr-FR').format(inv.remaining_amount)"></td>
                                <td class="px-3 py-1.5 text-right">
                                    <input type="number" min="0" step="1" :max="inv.remaining_amount" x-model.number="inv.allocated"
                                           :class="inv.allocated > inv.remaining_amount ? 'border-red-400 bg-red-50' : 'border-gray-400'"
                                           class="w-28 h-7 border rounded-[3px] px-2 text-[13px] text-right focus:outline-none focus:ring-1 focus:ring-emerald-400 tabular-nums" placeholder="0">
                                </td>
                                <td class="px-3 py-1.5 text-gray-500 font-mono">XOF</td>
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 text-[12px]"
                                          :class="(inv.allocated || 0) >= inv.remaining_amount ? 'text-emerald-700' : ((inv.allocated || 0) > 0 ? 'text-orange-600' : 'text-blue-600')">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="(inv.allocated || 0) >= inv.remaining_amount ? 'bg-emerald-500' : ((inv.allocated || 0) > 0 ? 'bg-orange-500' : 'bg-blue-500')"></span>
                                        <span x-text="(inv.allocated || 0) >= inv.remaining_amount ? 'Réglé' : ((inv.allocated || 0) > 0 ? 'Partiellement réglé' : 'Disponible')"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="flex flex-wrap justify-end gap-x-10 gap-y-1 px-4 py-3 text-[12px]">
                    <div class="text-right"><p class="text-gray-500">Total à affecter</p><p class="font-bold tabular-nums text-gray-800"><span x-text="new Intl.NumberFormat('fr-FR').format(amount||0)"></span> XOF</p></div>
                    <div class="text-right"><p class="text-gray-500">Solde restant</p><p class="font-bold tabular-nums" :class="remainingToAllocate < 0 ? 'text-red-600' : 'text-gray-800'"><span x-text="new Intl.NumberFormat('fr-FR').format(Math.abs(remainingToAllocate))"></span> XOF</p></div>
                    <div class="text-right"><p class="text-gray-500">Total affecté</p><p class="font-bold tabular-nums text-emerald-700"><span x-text="new Intl.NumberFormat('fr-FR').format(totalAllocated)"></span> XOF</p></div>
                </div>
                <div class="px-4 pb-3">
                    <p x-show="remainingToAllocate < 0" class="text-[12px] text-red-600">⚠ Le montant imputé dépasse le montant reçu. Corrigez avant d'enregistrer.</p>
                </div>
            </div>
        </section>

        {{-- ═══ Rangée 3 : 4. Informations complémentaires | 5. Traçabilité ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_complement" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Informations complémentaires</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-3"><label class="{{ $lbl }}">Commercial</label><input type="text" name="salesperson" maxlength="100" value="{{ old('salesperson') }}" class="{{ $inp }}"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Centre de coût</label><input type="text" name="cost_center" maxlength="30" value="{{ old('cost_center') }}" placeholder="CC100" class="{{ $inp }} font-mono"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Section analytique</label><input type="text" name="analytic_section" maxlength="30" value="{{ old('analytic_section') }}" placeholder="SA100" class="{{ $inp }} font-mono"></div>
                    <div class="col-span-3"><label class="{{ $lbl }}">Projet</label><input type="text" name="project" maxlength="60" value="{{ old('project') }}" class="{{ $inp }}"></div>

                    <div class="col-span-3" x-ref="sec_pieces">
                        <label class="{{ $lbl }}">Pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-gray-400 rounded-[3px] px-2 py-1.5 cursor-pointer file:mr-2 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                    </div>
                    <div class="col-span-9"><label class="{{ $lbl }}">Observations</label><textarea name="observations" rows="2" maxlength="1000" class="w-full px-2.5 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('observations') }}</textarea></div>
                </div>
            </section>

            <section class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">5.</span> Traçabilité</h2>
                <div class="grid grid-cols-2 gap-x-3 gap-y-3">
                    <div><label class="{{ $lbl }}">Créé le</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inpRo }} tabular-nums" readonly></div>
                    <div><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inpRo }}" readonly></div>
                    <div><label class="{{ $lbl }}">Modifié le</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                    <div><label class="{{ $lbl }}">Modifié par</label><input type="text" value="—" class="{{ $inpRo }}" readonly></div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function paymentForm(config) {
    return {
        clientId:        config.clientId ? String(config.clientId) : '',
        amount:          (config.amount !== null && config.amount !== '') ? Number(config.amount) : '',
        bankFees:        Number(config.bankFees || 0),
        paymentMethodId: String(config.paymentMethodId || ''),
        paymentMethods:  config.paymentMethods || [],
        invoices:        [],
        loading:         false,
        submitting:      false,
        saveAndNew:      false,

        get totalAllocated() {
            return this.invoices.reduce((sum, inv) => sum + (Number(inv.allocated) || 0), 0);
        },
        get remainingToAllocate() {
            return this.amount - this.totalAllocated;
        },
        get netAmount() {
            return Math.max(0, (Number(this.amount) || 0) - (Number(this.bankFees) || 0));
        },
        get selectedMethod() {
            return this.paymentMethods.find(m => String(m.id) === String(this.paymentMethodId)) || null;
        },
        get isMobileMoney() {
            return this.selectedMethod?.is_mobile_money === true || this.selectedMethod?.is_mobile_money === 1;
        },

        init() {
            if (this.clientId) {
                this.loadInvoices();
            }
        },
        onClientChange() {
            this.loadInvoices();
        },
        loadInvoices() {
            if (!this.clientId) {
                this.invoices = [];
                return;
            }
            this.loading  = true;
            this.invoices = [];
            fetch(`{{ route('tresorerie.encaissements.invoices') }}?client_id=${this.clientId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.invoices = data.map(inv => ({ ...inv, allocated: 0 }));
                this.loading  = false;
            })
            .catch(() => { this.loading = false; });
        },
        autoAllocate() {
            let remaining = Number(this.amount);
            for (let inv of this.invoices) {
                if (remaining <= 0) {
                    inv.allocated = 0;
                    continue;
                }
                const canAllocate = Math.min(remaining, inv.remaining_amount);
                inv.allocated  = Math.floor(canAllocate);
                remaining     -= inv.allocated;
            }
        },
        submitForm(andNew = false) {
            if (this.remainingToAllocate < 0 || this.amount <= 0) return;
            this.saveAndNew = andNew === true;
            this.submitting = true;
            this.$nextTick(() => this.$refs.form.submit());
        },
        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';
        },
    };
}
</script>
@endpush
