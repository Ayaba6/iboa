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
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Balance générale SYSCOHADA</h1>
            @if($accounts->isNotEmpty())
                <p class="text-sm text-gray-500 mt-0.5">{{ $accounts->count() }} compte(s) avec mouvements</p>
            @endif
        </div>
        <div class="flex items-center gap-3 self-start">
            <a href="{{ route('comptabilite.balance.pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('comptabilite.balance.export', request()->query()) }}"
               class="inline-flex items-center gap-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('comptabilite.grand-livre') }}"
               class="inline-flex items-center gap-1.5 text-sm text-violet-600 hover:text-violet-700 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Grand livre
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <select name="class_id"
                    class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                <option value="">Toutes les classes</option>
                @foreach($classes as $class)
                <option value="{{ $class->id }}" {{ ($classId ?? '') == $class->id ? 'selected' : '' }}>
                    Classe {{ $class->number }} — {{ $class->name }}
                </option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Afficher
                </button>
                @if($classId || $dateFrom || $dateTo)
                <a href="{{ route('comptabilite.balance') }}"
                   class="flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors"
                   title="Réinitialiser">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Period / context banner --}}
    @if($dateFrom || $dateTo)
    <div class="flex items-center gap-2 px-3 py-2.5 bg-violet-50 border border-violet-200 rounded-[4px] text-sm text-violet-800">
        <svg class="w-4 h-4 flex-shrink-0 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            — La colonne <em>Ouverture</em> reflète les soldes antérieurs à la date de début.
        </span>
    </div>
    @else
    <div class="flex items-center gap-2 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-[4px] text-sm text-amber-800">
        <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Aucune période sélectionnée — affichage des <strong>soldes cumulés à ce jour</strong>. Les colonnes Ouverture sont sans objet.</span>
    </div>
    @endif

    {{-- Summary KPIs --}}
    @php
        $isBalanced = abs($totals['solde_debiteur'] - $totals['solde_crediteur']) < 1;
        $imbalance  = abs($totals['solde_debiteur'] - $totals['solde_crediteur']);
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">Mvts Débit</p>
            <p class="text-[16px] font-bold tabular-nums text-blue-700">
                {{ number_format($totals['period_debit'], 0, ',', ' ') }}
            </p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">Mvts Crédit</p>
            <p class="text-[16px] font-bold tabular-nums text-red-700">
                {{ number_format($totals['period_credit'], 0, ',', ' ') }}
            </p>
        </div>
        <div class="bg-white rounded-[4px] border {{ $isBalanced ? 'border-gray-200' : 'border-red-200 bg-red-50' }} p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">Équilibre soldes</p>
            @if($isBalanced)
            <span class="inline-flex items-center gap-1 text-sm font-bold text-green-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                Équilibré
            </span>
            @else
            <span class="inline-flex items-center gap-1 text-sm font-bold text-red-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Écart : {{ number_format($imbalance, 0, ',', ' ') }}
            </span>
            @endif
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">Comptes affichés</p>
            <p class="text-[16px] font-bold text-gray-700">{{ $accounts->count() }}</p>
        </div>
    </div>

    {{-- Balance table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="bg-violet-700 text-white">
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider w-24" rowspan="2">Code</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider" rowspan="2">Libellé</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider border-l border-violet-500 {{ !($dateFrom || $dateTo) ? 'opacity-40' : '' }}" colspan="2">
                            Ouverture
                        </th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider border-l border-violet-500" colspan="2">
                            Mouvements
                        </th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider border-l border-violet-500" colspan="2">
                            Soldes finaux
                        </th>
                    </tr>
                    <tr class="bg-violet-600 text-white text-xs">
                        <th class="px-3 py-1.5 text-right font-semibold border-l border-violet-500 {{ !($dateFrom || $dateTo) ? 'opacity-40' : '' }}">Débit</th>
                        <th class="px-3 py-1.5 text-right font-semibold {{ !($dateFrom || $dateTo) ? 'opacity-40' : '' }}">Crédit</th>
                        <th class="px-3 py-1.5 text-right font-semibold border-l border-violet-500">Débit</th>
                        <th class="px-3 py-1.5 text-right font-semibold">Crédit</th>
                        <th class="px-3 py-1.5 text-right font-semibold border-l border-violet-500 text-blue-200">Débiteur</th>
                        <th class="px-3 py-1.5 text-right font-semibold text-red-200">Créditeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $currentClass = null; @endphp
                    @forelse($accounts as $account)
                    @php $classNum = substr($account->code, 0, 1); @endphp

                    {{-- Class separator --}}
                    @if($classNum !== $currentClass)
                    @php $currentClass = $classNum; @endphp
                    <tr class="bg-violet-50">
                        <td colspan="8" class="px-3 py-1.5 text-xs font-bold text-violet-700 uppercase tracking-wide">
                            Classe {{ $classNum }}
                            @if($account->accountClass?->name)
                                — {{ $account->accountClass->name }}
                            @endif
                        </td>
                    </tr>
                    @endif

                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-2">
                            <a href="{{ route('comptabilite.grand-livre', array_merge(request()->query(), ['account_id' => $account->id])) }}"
                               class="font-mono font-semibold text-violet-600 hover:text-violet-800 hover:underline">
                                {{ $account->code }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-gray-700 max-w-[220px] truncate" title="{{ $account->name }}">
                            {{ $account->name }}
                        </td>

                        {{-- Ouverture --}}
                        <td class="px-3 py-2 text-right tabular-nums border-l border-gray-100
                            {{ !($dateFrom || $dateTo) ? 'text-gray-200' : ($account->open_debit > 0 ? 'text-gray-600' : 'text-gray-300') }}">
                            {{ ($dateFrom || $dateTo) ? ($account->open_debit > 0 ? number_format($account->open_debit, 0, ',', ' ') : '—') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums
                            {{ !($dateFrom || $dateTo) ? 'text-gray-200' : ($account->open_credit > 0 ? 'text-gray-600' : 'text-gray-300') }}">
                            {{ ($dateFrom || $dateTo) ? ($account->open_credit > 0 ? number_format($account->open_credit, 0, ',', ' ') : '—') : '—' }}
                        </td>

                        {{-- Mouvements --}}
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700 border-l border-gray-100">
                            {{ $account->period_debit  > 0 ? number_format($account->period_debit,  0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                            {{ $account->period_credit > 0 ? number_format($account->period_credit, 0, ',', ' ') : '—' }}
                        </td>

                        {{-- Soldes --}}
                        <td class="px-3 py-2 text-right tabular-nums font-semibold border-l border-gray-100
                            {{ $account->solde_debiteur > 0 ? 'text-blue-700' : 'text-gray-300' }}">
                            {{ $account->solde_debiteur  > 0 ? number_format($account->solde_debiteur,  0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold
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
                                @if($classId || $dateFrom || $dateTo)
                                <a href="{{ route('comptabilite.balance') }}"
                                   class="text-violet-600 hover:text-violet-700 text-sm">Effacer les filtres</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if($accounts->isNotEmpty())
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr class="font-bold text-sm">
                        <td colspan="2" class="px-3 py-3 text-right text-xs uppercase text-gray-500 tracking-wider">
                            Totaux généraux
                        </td>

                        {{-- Ouverture totals --}}
                        <td class="px-3 py-3 text-right tabular-nums border-l border-gray-200
                            {{ !($dateFrom || $dateTo) ? 'text-gray-300' : 'text-gray-700' }}">
                            {{ ($dateFrom || $dateTo) ? number_format($totals['open_debit'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-3 text-right tabular-nums
                            {{ !($dateFrom || $dateTo) ? 'text-gray-300' : 'text-gray-700' }}">
                            {{ ($dateFrom || $dateTo) ? number_format($totals['open_credit'], 0, ',', ' ') : '—' }}
                        </td>

                        {{-- Mouvements totals --}}
                        <td class="px-3 py-3 text-right tabular-nums text-blue-700 border-l border-gray-200">
                            {{ number_format($totals['period_debit'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right tabular-nums text-red-600">
                            {{ number_format($totals['period_credit'], 0, ',', ' ') }}
                        </td>

                        {{-- Soldes totals --}}
                        <td class="px-3 py-3 text-right tabular-nums text-blue-700 border-l border-gray-200">
                            {{ number_format($totals['solde_debiteur'], 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right tabular-nums text-red-600">
                            {{ number_format($totals['solde_crediteur'], 0, ',', ' ') }}
                        </td>
                    </tr>

                    {{-- Balance check row --}}
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

</div>
@endsection
