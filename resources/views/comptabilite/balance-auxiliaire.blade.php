@extends('layouts.erp')
@section('title', 'Balance auxiliaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Balance auxiliaire</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Balance auxiliaire des tiers</h1>
            <p class="text-sm text-gray-500 mt-0.5">Comptes de tiers (classe 4) — {{ $accounts->count() }} compte(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <select name="type" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="all"          {{ $type === 'all'           ? 'selected' : '' }}>Tous les tiers</option>
                <option value="clients"      {{ $type === 'clients'       ? 'selected' : '' }}>Clients (41x)</option>
                <option value="fournisseurs" {{ $type === 'fournisseurs'  ? 'selected' : '' }}>Fournisseurs (40x)</option>
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" placeholder="Début période"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            <div class="flex gap-2">
                <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" placeholder="Fin période"
                       class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-2.5 py-1.5 rounded-[4px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
            </div>
            <a href="{{ route('comptabilite.balance-auxiliaire') }}"
               class="inline-flex items-center justify-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-[4px] px-3 py-2 transition-colors">
                Réinitialiser
            </a>
        </div>
    </form>

    @if($accounts->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
        <p class="text-gray-500">Aucun mouvement trouvé pour les critères sélectionnés.</p>
    </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
        <table class="tbl tbl-sticky w-full">
            <thead>
                <tr>
                    <th class="text-left w-full">Compte</th>
                    <th class="text-right whitespace-nowrap">Débit</th>
                    <th class="text-right whitespace-nowrap">Crédit</th>
                    <th class="text-right whitespace-nowrap text-blue-600">Solde D</th>
                    <th class="text-right whitespace-nowrap text-red-600">Solde C</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                <tr class="cursor-pointer"
                    onclick="window.location='{{ route('comptabilite.grand-livre', ['account_id' => $account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}'">
                    <td class="w-full">
                        <span class="font-mono font-semibold text-violet-700 text-xs">{{ $account->code }}</span>
                        <span class="text-gray-700 ml-2">{{ $account->name }}</span>
                    </td>
                    <td class="text-right tabular-nums text-gray-700 font-medium whitespace-nowrap">
                        {{ number_format($account->total_debit, 0, ',', ' ') }}
                    </td>
                    <td class="text-right tabular-nums text-gray-700 font-medium whitespace-nowrap">
                        {{ number_format($account->total_credit, 0, ',', ' ') }}
                    </td>
                    <td class="text-right tabular-nums font-semibold text-blue-700 whitespace-nowrap">
                        {{ $account->solde_debiteur > 0 ? number_format($account->solde_debiteur, 0, ',', ' ') : '—' }}
                    </td>
                    <td class="text-right tabular-nums font-semibold text-red-700 whitespace-nowrap">
                        {{ $account->solde_crediteur > 0 ? number_format($account->solde_crediteur, 0, ',', ' ') : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-300 bg-gray-100 font-bold">
                <tr>
                    <td class="text-xs text-gray-600 uppercase w-full">TOTAUX</td>
                    <td class="text-right tabular-nums text-gray-900 whitespace-nowrap">
                        {{ number_format($totals['total_debit'], 0, ',', ' ') }}
                    </td>
                    <td class="text-right tabular-nums text-gray-900 whitespace-nowrap">
                        {{ number_format($totals['total_credit'], 0, ',', ' ') }}
                    </td>
                    <td class="text-right tabular-nums text-blue-700 whitespace-nowrap">
                        {{ number_format($totals['solde_debiteur'], 0, ',', ' ') }}
                    </td>
                    <td class="text-right tabular-nums text-red-700 whitespace-nowrap">
                        {{ number_format($totals['solde_crediteur'], 0, ',', ' ') }}
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    @endif

</div>
@endsection
