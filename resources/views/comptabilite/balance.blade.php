@extends('layouts.erp')
@section('title', 'Balance générale')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Balance générale</span>
@endsection

@section('content')
@php
    $isBalanced = abs($totals['solde_debiteur'] - $totals['solde_crediteur']) < 1;
    $imbalance  = abs($totals['solde_debiteur'] - $totals['solde_crediteur']);
    $hasPeriod  = $dateFrom || $dateTo;
@endphp
<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Balance générale</h1>
            <p class="text-xs text-gray-400 mt-0.5">Référentiel SYSCOHADA — {{ $accounts->count() }} compte(s) avec mouvements</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <button type="submit" form="balance-filters"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-[4px] text-sm font-semibold transition-colors shadow-sm">
                Rechercher
            </button>
            <a href="{{ route('comptabilite.balance.pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Imprimer
            </a>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" @click.away="open = false"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                    Exporter ▾
                </button>
                <div x-show="open" x-cloak x-transition
                     class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-[4px] shadow-lg z-20 py-1">
                    <a href="{{ route('comptabilite.balance.pdf', request()->query()) }}"
                       class="block px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Balance PDF</a>
                    <a href="{{ route('comptabilite.balance.export', request()->query()) }}"
                       class="block px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Balance Excel</a>
                </div>
            </div>
            <a href="{{ route('comptabilite.grand-livre') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Grand livre
            </a>
            <a href="{{ route('comptabilite.dashboard') ?? url('/comptabilite') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                ✕ Fermer
            </a>
        </div>
    </div>

    {{-- ══ 1. Critères de sélection ══════════════════════════════════════════ --}}
    <form method="GET" id="balance-filters" class="bg-white rounded-[4px] border border-gray-300">
        <div class="px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Critères de sélection</p>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Société</label>
                <input type="text" value="{{ currentCompany()?->name }}" readonly
                       class="w-full border border-gray-200 bg-gray-50 rounded-[4px] px-3 py-2 text-sm text-gray-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Classe de comptes</label>
                <select name="class_id"
                        class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <option value="">Toutes les classes</option>
                    @foreach($classes as $class)
                    <option value="{{ $class->id }}" {{ ($classId ?? '') == $class->id ? 'selected' : '' }}>
                        Classe {{ $class->number }} — {{ $class->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Période du</label>
                <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Période au</label>
                <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-2 rounded-[4px] transition-colors">
                    Afficher
                </button>
                @if($classId || $hasPeriod)
                <a href="{{ route('comptabilite.balance') }}"
                   class="flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-[4px] transition-colors"
                   title="Réinitialiser les filtres">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Bandeau période --}}
    @if($hasPeriod)
    <div class="flex items-center gap-2 px-3 py-2 bg-[#eef5f0] border border-emerald-200 rounded-[4px] text-sm text-emerald-900">
        <svg class="w-4 h-4 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span>
            Période : <strong>
            @if($dateFrom && $dateTo)
                du {{ \Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM YYYY') }}
                au {{ \Carbon\Carbon::parse($dateTo)->isoFormat('D MMM YYYY') }}
            @elseif($dateFrom)
                à partir du {{ \Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM YYYY') }}
            @else
                jusqu'au {{ \Carbon\Carbon::parse($dateTo)->isoFormat('D MMM YYYY') }}
            @endif
            </strong>
            — la colonne <em>Ouverture</em> reflète les soldes antérieurs à la date de début.
        </span>
    </div>
    @else
    <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-[4px] text-sm text-amber-800">
        <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Aucune période sélectionnée — affichage des <strong>soldes cumulés à ce jour</strong>. Les colonnes Ouverture sont sans objet.</span>
    </div>
    @endif

    {{-- ══ 2. Balance ═══════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. Balance des comptes</p>
            <p class="text-[11px] text-emerald-600">{{ $accounts->count() }} compte(s) · XOF</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[#eef5f0] text-[11px] text-emerald-900 font-bold uppercase tracking-wide border-b border-gray-300">
                        <th class="px-3 py-2 text-left w-24" rowspan="2">Code</th>
                        <th class="px-3 py-2 text-left" rowspan="2">Libellé</th>
                        <th class="px-3 py-2 text-center border-l border-emerald-100 {{ !$hasPeriod ? 'opacity-40' : '' }}" colspan="2">Ouverture</th>
                        <th class="px-3 py-2 text-center border-l border-emerald-100" colspan="2">Mouvements</th>
                        <th class="px-3 py-2 text-center border-l border-emerald-100" colspan="2">Soldes finaux</th>
                    </tr>
                    <tr class="bg-[#eef5f0]/70 text-[10px] text-emerald-800 font-semibold uppercase border-b border-gray-300">
                        <th class="px-3 py-1 text-right border-l border-emerald-100 {{ !$hasPeriod ? 'opacity-40' : '' }}">Débit</th>
                        <th class="px-3 py-1 text-right {{ !$hasPeriod ? 'opacity-40' : '' }}">Crédit</th>
                        <th class="px-3 py-1 text-right border-l border-emerald-100">Débit</th>
                        <th class="px-3 py-1 text-right">Crédit</th>
                        <th class="px-3 py-1 text-right border-l border-emerald-100">Débiteur</th>
                        <th class="px-3 py-1 text-right">Créditeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $currentClass = null; @endphp
                    @forelse($accounts as $account)
                    @php $classNum = substr($account->code, 0, 1); @endphp

                    {{-- Séparateur de classe --}}
                    @if($classNum !== $currentClass)
                    @php $currentClass = $classNum; @endphp
                    <tr class="bg-gray-50/80">
                        <td colspan="8" class="px-3 py-1.5 text-[11px] font-bold text-emerald-800 uppercase tracking-wide">
                            Classe {{ $classNum }}
                            @if($account->accountClass?->name)
                                — {{ $account->accountClass->name }}
                            @endif
                        </td>
                    </tr>
                    @endif

                    <tr class="hover:bg-[#eef5f0]/40 transition-colors {{ $loop->even ? 'bg-gray-50/40' : '' }}">
                        <td class="px-3 py-1.5">
                            <a href="{{ route('comptabilite.grand-livre', array_merge(request()->query(), ['account_id' => $account->id])) }}"
                               class="font-mono font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                                {{ $account->code }}
                            </a>
                        </td>
                        <td class="px-3 py-1.5 text-gray-700 max-w-[240px] truncate" title="{{ $account->name }}">
                            {{ $account->name }}
                        </td>

                        {{-- Ouverture --}}
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums border-l border-gray-100
                            {{ !$hasPeriod ? 'text-gray-200' : ($account->open_debit > 0 ? 'text-gray-600' : 'text-gray-300') }}">
                            {{ $hasPeriod ? ($account->open_debit > 0 ? number_format($account->open_debit, 0, ',', ' ') : '—') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums
                            {{ !$hasPeriod ? 'text-gray-200' : ($account->open_credit > 0 ? 'text-gray-600' : 'text-gray-300') }}">
                            {{ $hasPeriod ? ($account->open_credit > 0 ? number_format($account->open_credit, 0, ',', ' ') : '—') : '—' }}
                        </td>

                        {{-- Mouvements --}}
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 border-l border-gray-100">
                            {{ $account->period_debit  > 0 ? number_format($account->period_debit,  0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700">
                            {{ $account->period_credit > 0 ? number_format($account->period_credit, 0, ',', ' ') : '—' }}
                        </td>

                        {{-- Soldes --}}
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold border-l border-gray-100
                            {{ $account->solde_debiteur > 0 ? 'text-blue-700' : 'text-gray-300' }}">
                            {{ $account->solde_debiteur  > 0 ? number_format($account->solde_debiteur,  0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold
                            {{ $account->solde_crediteur > 0 ? 'text-red-600' : 'text-gray-300' }}">
                            {{ $account->solde_crediteur > 0 ? number_format($account->solde_crediteur, 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-sm font-medium">Aucun compte avec des mouvements</p>
                                @if($classId || $hasPeriod)
                                <a href="{{ route('comptabilite.balance') }}"
                                   class="text-emerald-700 hover:text-emerald-900 text-sm hover:underline">Effacer les filtres</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if($accounts->isNotEmpty())
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr class="font-bold text-sm">
                        <td colspan="2" class="px-3 py-2.5 text-right text-[11px] uppercase text-gray-500 tracking-wider">
                            Totaux généraux
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums border-l border-gray-200
                            {{ !$hasPeriod ? 'text-gray-300' : 'text-gray-700' }}">
                            {{ $hasPeriod ? number_format($totals['open_debit'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums
                            {{ !$hasPeriod ? 'text-gray-300' : 'text-gray-700' }}">
                            {{ $hasPeriod ? number_format($totals['open_credit'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-blue-700 border-l border-gray-200">
                            {{ number_format($totals['period_debit'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-red-600">
                            {{ number_format($totals['period_credit'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-blue-700 border-l border-gray-200">
                            {{ number_format($totals['solde_debiteur'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-red-600">
                            {{ number_format($totals['solde_crediteur'], 0, ',', ' ') }}
                        </td>
                    </tr>
                    @if(!$isBalanced)
                    <tr>
                        <td colspan="8" class="px-3 py-2 text-center">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-full px-3 py-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                Écart de {{ number_format($imbalance, 0, ',', ' ') }} — vérifier les écritures
                            </span>
                        </td>
                    </tr>
                    @endif
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ══ Synthèse (pattern X3 : barre basse) ═══════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-200">
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Mouvements débit</p>
            <p class="text-[15px] font-bold font-mono text-blue-700 mt-0.5">{{ number_format($totals['period_debit'], 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">XOF</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Mouvements crédit</p>
            <p class="text-[15px] font-bold font-mono text-red-600 mt-0.5">{{ number_format($totals['period_credit'], 0, ',', ' ') }} <span class="text-[10px] font-normal text-gray-400">XOF</span></p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Équilibre des soldes</p>
            @if($isBalanced)
            <p class="text-[15px] font-bold text-emerald-700 mt-0.5">✓ Équilibré</p>
            @else
            <p class="text-[15px] font-bold text-red-600 mt-0.5">Écart {{ number_format($imbalance, 0, ',', ' ') }}</p>
            @endif
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Comptes affichés</p>
            <p class="text-[15px] font-bold text-gray-800 mt-0.5">{{ $accounts->count() }}</p>
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Référentiel : <strong class="text-white">SYSCOHADA</strong></span>
            <span>Période : <strong class="text-white">
                @if($hasPeriod)
                    {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '…' }} → {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '…' }}
                @else
                    Cumul à ce jour
                @endif
            </strong></span>
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
