@extends('layouts.erp')
@section('title', 'Nouveau compte de trésorerie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Comptes de trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp  = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo= 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk   = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'text-[12px] font-bold text-emerald-700 uppercase tracking-wide border-b border-gray-200 pb-1.5 mb-3';
    $caret= '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel= 'bg-white border border-gray-200 rounded-[4px] p-4';
    $chk  = 'w-[15px] h-[15px] rounded-[2px] border-gray-400 text-emerald-600 focus:ring-emerald-500';
@endphp

<div class="max-w-[1400px]" x-data="{ tab: 'general', saveAndNew: false, submitting: false }">

    <form method="POST" action="{{ route('tresorerie.caisses.store') }}"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf
        <input type="hidden" name="save_and_new" :value="saveAndNew ? 1 : 0">

        {{-- Header bar --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouveau compte de trésorerie</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
                <button type="button" @click="saveAndNew = true; $nextTick(() => $refs.form.submit())" :disabled="submitting"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    Enregistrer et créer
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Aperçu</button>
                <a href="{{ route('tresorerie.caisses.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Tabs (ancres) --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['general'=>'Général','banque'=>'Banque','parametres'=>'Paramètres','options'=>'Options'] as $tk => $tl)
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

        {{-- ═══ INFORMATIONS GÉNÉRALES ═══ --}}
        <section x-ref="sec_general" class="{{ $panel }}">
            <h2 class="{{ $secH }}">Informations générales</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Compte de trésorerie <span class="text-red-500">*</span></label>
                    <input type="text" name="code" required maxlength="30" value="{{ old('code') }}" placeholder="BQ-01" class="{{ $errors->has('code') ? $inp.' border-red-400 bg-red-50' : $inp }} font-mono uppercase">
                </div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Type de compte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="type" required class="{{ $lk }}">
                            <option value="banque"       {{ old('type', 'banque') === 'banque' ? 'selected' : '' }}>Compte bancaire</option>
                            <option value="caisse"       {{ old('type') === 'caisse' ? 'selected' : '' }}>Caisse</option>
                            <option value="mobile_money" {{ old('type') === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-3"><label class="{{ $lbl }}">Statut <span class="text-red-500">*</span></label><input type="text" value="Actif" class="{{ $inpRo }}" readonly></div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                    <input type="text" name="currency_code" required maxlength="3" value="{{ old('currency_code', 'XOF') }}" class="{{ $inp }} font-mono uppercase">
                </div>

                <div class="col-span-3"><label class="{{ $lbl }}">Intitulé <span class="text-red-500">*</span></label><input type="text" name="name" required maxlength="100" value="{{ old('name') }}" class="{{ $inp }}"></div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                    <input type="text" value="{{ optional(currentCompany())->name }}" class="{{ $inpRo }}" readonly>
                    <p class="text-[12px] text-emerald-600 mt-0.5 truncate">{{ optional(currentCompany())->name }}</p>
                </div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                    <input type="text" name="site" maxlength="40" value="{{ old('site', '01') }}" class="{{ $inp }}">
                    <p class="text-[12px] text-emerald-600 mt-0.5">Site principal</p>
                </div>
                <div class="col-span-3"><label class="{{ $lbl }}">Responsable</label><input type="text" name="manager_name" maxlength="100" value="{{ old('manager_name') }}" class="{{ $inp }}"></div>

                <div class="col-span-3">
                    <label class="{{ $lbl }}">Groupe de comptes</label>
                    <input type="text" name="account_group" maxlength="60" value="{{ old('account_group') }}" placeholder="BANG - Comptes bancaires" class="{{ $inp }}">
                </div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Catégorie</label>
                    <div class="relative">
                        <select name="category" class="{{ $lk }}">
                            @foreach(['Exploitation','Placement','Emprunt','Autre'] as $cat)
                            <option value="{{ $cat }}" {{ old('category', 'Exploitation') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-3"><label class="{{ $lbl }}">Compte général</label><input type="text" name="general_account" maxlength="20" value="{{ old('general_account') }}" placeholder="521000" class="{{ $inp }} font-mono"></div>
                <div class="col-span-3"><label class="{{ $lbl }}">Description</label><textarea name="description" rows="2" maxlength="500" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('description') }}</textarea></div>
            </div>
        </section>

        {{-- ═══ BANQUE ═══ --}}
        <section x-ref="sec_banque" class="{{ $panel }}">
            <h2 class="{{ $secH }}">Banque</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                <div class="col-span-3"><label class="{{ $lbl }}">Banque</label><input type="text" name="bank_name" maxlength="150" value="{{ old('bank_name') }}" class="{{ $inp }}"></div>
                <div class="col-span-3"><label class="{{ $lbl }}">Agence</label><input type="text" name="bank_branch" maxlength="150" value="{{ old('bank_branch') }}" class="{{ $inp }}"></div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Pays</label>
                    <div class="relative">
                        <select name="country_code" class="{{ $lk }}">
                            @foreach(['BF' => 'BF - Burkina Faso', 'CI' => 'CI - Côte d\'Ivoire', 'ML' => 'ML - Mali', 'SN' => 'SN - Sénégal', 'TG' => 'TG - Togo', 'BJ' => 'BJ - Bénin', 'NE' => 'NE - Niger'] as $cc => $cl)
                            <option value="{{ $cc }}" {{ old('country_code', 'BF') === $cc ? 'selected' : '' }}>{{ $cl }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-3"><label class="{{ $lbl }}">Code banque</label><input type="text" name="bank_code" maxlength="20" value="{{ old('bank_code') }}" class="{{ $inp }} font-mono"></div>

                <div class="col-span-3"><label class="{{ $lbl }}">Code guichet</label><input type="text" name="branch_code" maxlength="20" value="{{ old('branch_code') }}" class="{{ $inp }} font-mono"></div>
                <div class="col-span-3"><label class="{{ $lbl }}">N° de compte</label><input type="text" name="account_number" maxlength="50" value="{{ old('account_number') }}" class="{{ $inp }} font-mono"></div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Clé RIB / IBAN</label>
                    <div class="flex gap-1.5">
                        <input type="text" name="rib_key" maxlength="4" value="{{ old('rib_key') }}" placeholder="Clé" class="w-16 h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-center font-mono">
                        <input type="text" name="iban" maxlength="34" value="{{ old('iban') }}" placeholder="IBAN" class="{{ $inp }} font-mono">
                    </div>
                </div>
                <div class="col-span-3"><label class="{{ $lbl }}">BIC / SWIFT</label><input type="text" name="swift_bic" maxlength="11" value="{{ old('swift_bic') }}" class="{{ $inp }} font-mono uppercase"></div>
            </div>
        </section>

        {{-- ═══ PARAMÈTRES FINANCIERS ═══ --}}
        <section x-ref="sec_parametres" class="{{ $panel }}">
            <h2 class="{{ $secH }}">Paramètres financiers</h2>
            <div class="grid grid-cols-12 gap-x-3 gap-y-3 items-start">
                <div class="col-span-2"><label class="{{ $lbl }}">Découvert autorisé</label><input type="number" name="overdraft_limit" min="0" value="{{ old('overdraft_limit', 0) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="col-span-2"><label class="{{ $lbl }}">Devise de découvert</label><input type="text" name="overdraft_currency" maxlength="3" value="{{ old('overdraft_currency', 'XOF') }}" class="{{ $inp }} font-mono uppercase"></div>
                <div class="col-span-2"><label class="{{ $lbl }}">Plafond par transaction</label><input type="number" name="transaction_ceiling" min="0" value="{{ old('transaction_ceiling', 0) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="col-span-2"><label class="{{ $lbl }}">Plafond par opération</label><input type="number" name="operation_ceiling" min="0" value="{{ old('operation_ceiling', 0) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="col-span-2">
                    <label class="{{ $lbl }}">Génération des écritures</label>
                    <div class="relative">
                        <select name="entry_generation" class="{{ $lk }}">
                            <option value="automatique" {{ old('entry_generation', 'automatique') === 'automatique' ? 'selected' : '' }}>Automatique</option>
                            <option value="manuelle"    {{ old('entry_generation') === 'manuelle' ? 'selected' : '' }}>Manuelle</option>
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-2 space-y-1.5 pt-5">
                    <label class="flex items-center gap-1.5 text-[12px] text-gray-800 cursor-pointer">
                        <input type="checkbox" name="include_in_forecast" value="1" {{ old('include_in_forecast', '1') ? 'checked' : '' }} class="{{ $chk }}">
                        <span class="whitespace-normal leading-tight">Inclure dans les prévisions de trésorerie</span>
                    </label>
                    <label class="flex items-center gap-1.5 text-[12px] text-gray-800 cursor-pointer">
                        <input type="checkbox" name="is_regularization" value="1" {{ old('is_regularization') ? 'checked' : '' }} class="{{ $chk }}">
                        <span class="whitespace-normal leading-tight">Compte de contrepartie de régularisation</span>
                    </label>
                </div>

                <div class="col-span-2"><label class="{{ $lbl }}">Solde initial <span class="text-red-500">*</span></label><input type="number" name="opening_balance" required value="{{ old('opening_balance', 0) }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="col-span-2"><label class="{{ $lbl }}">Seuil d'alerte (min)</label><input type="number" name="min_balance" min="0" value="{{ old('min_balance') }}" class="{{ $inp }} text-right tabular-nums"></div>
                <div class="col-span-3">
                    <label class="{{ $lbl }}">Mode de paiement lié</label>
                    <div class="relative">
                        <select name="payment_method_id" class="{{ $lk }}">
                            <option value="">—</option>
                            @foreach($paymentMethods as $pm)
                            <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                </div>
                <div class="col-span-3 pt-5">
                    <label class="flex items-center gap-1.5 text-[12px] text-gray-800 cursor-pointer">
                        <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="{{ $chk }}">
                        Compte par défaut
                    </label>
                </div>
            </div>
        </section>

        {{-- ═══ OPTIONS | PRÉVISIONS ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">
            <section x-ref="sec_options" class="{{ $panel }} xl:col-span-8">
                <h2 class="{{ $secH }}">Options</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-2"><label class="{{ $lbl }}">Date d'ouverture</label><input type="date" name="opened_at" value="{{ old('opened_at') }}" class="{{ $inp }}"></div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Date de clôture prévue</label><input type="date" name="closes_at" value="{{ old('closes_at') }}" class="{{ $inp }}"></div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Format de relevé</label>
                        <div class="relative">
                            <select name="statement_format" class="{{ $lk }}">
                                @foreach(['MT940','CAMT.053','CSV','PDF'] as $fmt)
                                <option value="{{ $fmt }}" {{ old('statement_format', 'MT940') === $fmt ? 'selected' : '' }}>{{ $fmt }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-3">
                        <label class="{{ $lbl }}">Fréquence de relevé</label>
                        <div class="relative">
                            <select name="statement_frequency" class="{{ $lk }}">
                                @foreach(['quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire', 'mensuelle' => 'Mensuelle'] as $fv => $fl)
                                <option value="{{ $fv }}" {{ old('statement_frequency', 'quotidienne') === $fv ? 'selected' : '' }}>{{ $fl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Dernier relevé reçu le</label><input type="date" name="last_statement_at" value="{{ old('last_statement_at') }}" class="{{ $inp }}"></div>

                    <div class="col-span-12"><label class="{{ $lbl }}">Commentaire</label><textarea name="notes" rows="2" maxlength="500" class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes') }}</textarea></div>
                </div>
            </section>

            <section class="{{ $panel }} xl:col-span-4">
                <h2 class="{{ $secH }}">Prévisions de trésorerie</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Inclure dans prévisions</label>
                        <input type="text" value="{{ old('include_in_forecast', '1') ? 'Oui' : 'Non' }}" class="{{ $inpRo }}" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Réglé aux Paramètres</p>
                    </div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Horizon (jours)</label><input type="number" name="forecast_horizon_days" min="1" max="730" value="{{ old('forecast_horizon_days', 90) }}" class="{{ $inp }} text-right tabular-nums"></div>
                    <div class="col-span-4"><label class="{{ $lbl }}">Devise de prévision</label><input type="text" name="forecast_currency" maxlength="3" value="{{ old('forecast_currency', 'XOF') }}" class="{{ $inp }} font-mono uppercase"></div>
                </div>
            </section>
        </div>

    </form>
</div>
@endsection
