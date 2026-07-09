@extends('layouts.erp')
@section('title', 'Nouveau bulletin de paie')
@section('breadcrumb')
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span><span>Nouveau bulletin</span>
@endsection

@section('content')
@php
    $months = [
        1=>'Janvier', 2=>'Février',  3=>'Mars',     4=>'Avril',
        5=>'Mai',     6=>'Juin',     7=>'Juillet',  8=>'Août',
        9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre',
    ];
@endphp

<div class="w-full"
     x-data="{
         month: {{ old('period_month', $suggestMonth) }},
         year:  {{ old('period_year', $suggestYear) }},
         months: {
             1:'Janvier', 2:'Février',  3:'Mars',     4:'Avril',
             5:'Mai',     6:'Juin',     7:'Juillet',  8:'Août',
             9:'Septembre',10:'Octobre',11:'Novembre',12:'Décembre'
         },
         get label() { return this.months[this.month] + ' ' + this.year; }
     }">

    {{-- En-tête --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-1">
            <div class="flex items-center justify-center w-10 h-10 rounded-[4px] bg-emerald-100">
                <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-[17px] font-bold text-gray-900">Nouveau bulletin de paie</h1>
                <p class="text-sm text-gray-500">Sélectionnez la période pour lancer le traitement</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('rh.paie.store') }}">
    @csrf
    <x-form-guard />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

    {{-- Colonne gauche : période + notes --}}
    <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">

        {{-- Section période --}}
        <div class="px-6 pt-6 pb-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Période</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Mois <span class="text-red-500">*</span>
                    </label>
                    <select name="period_month" x-model.number="month"
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 transition-colors">
                        @foreach($months as $m => $l)
                            <option value="{{ $m }}" @selected($m == old('period_month', $suggestMonth))>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Année <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="period_year" x-model.number="year"
                           value="{{ old('period_year', $suggestYear) }}" min="2020" max="2100" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm font-mono text-center focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 transition-colors">
                </div>
            </div>
        </div>

        {{-- Preview dynamique --}}
        <div class="mx-6 mb-4 rounded-[4px] bg-[#eef5f0] border border-emerald-100 px-3 py-1.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-[4px] bg-emerald-700 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-emerald-600 font-medium">Bulletin pour</p>
                    <p class="text-sm font-bold text-emerald-900" x-text="label"></p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 bg-white border border-emerald-200 rounded-[4px] px-3 py-1.5">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-xs font-semibold text-emerald-800">{{ $activeCount }} employé(s)</span>
            </div>
        </div>

        {{-- Notes --}}
        <div class="px-6 pb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Notes internes <span class="text-gray-400 font-normal">(optionnel)</span>
            </label>
            <textarea name="notes" rows="3"
                      placeholder="Ex : Intégration des primes de fin d'année…"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2.5 text-sm resize-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500 transition-colors placeholder-gray-400">{{ old('notes') }}</textarea>
        </div>
    </div>{{-- /colonne gauche --}}

    {{-- Colonne droite : étapes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Ce qui va se passer</p>
        <ol class="space-y-2">
                <li class="flex items-start gap-2.5">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center mt-0.5">1</span>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Création du bulletin en brouillon</p>
                        <p class="text-xs text-gray-400">Le bulletin est créé avec statut <em>Brouillon</em>, aucun calcul automatique</p>
                    </div>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center mt-0.5">2</span>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Saisie des variables mensuelles</p>
                        <p class="text-xs text-gray-400">Primes, heures sup., absences et retenues saisies par employé</p>
                    </div>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center mt-0.5">3</span>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Calcul, validation et paiement</p>
                        <p class="text-xs text-gray-400">Calcul des rubriques, validation RH, puis marquage comme payé</p>
                    </div>
                </li>
            </ol>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between mt-5">
        <a href="{{ route('rh.paie.index') }}"
           class="flex items-center gap-1.5 px-3 py-2.5 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Annuler
        </a>
        <button type="submit"
                class="flex items-center gap-2 px-6 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 active:scale-95 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Créer le bulletin
            <span class="opacity-70 font-normal text-xs" x-text="'— ' + label"></span>
        </button>
    </div>

    </form>
</div>
@endsection
