@extends('layouts.erp')
@section('title', 'Virements internes')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Virements internes</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Virements internes</h1>
            <p class="text-sm text-gray-500 mt-0.5">Transferts entre comptes de trésorerie (caisse ↔ banque)</p>
        </div>
        @can('treasury.write')
        <a href="{{ route('tresorerie.virements.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau virement
        </a>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total viré (filtré)</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums mt-1">{{ number_format($stats['total'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Virements validés</p>
            <p class="text-[16px] font-bold text-gray-900 mt-1">{{ $stats['count'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Annulés</p>
            <p class="text-[16px] font-bold {{ $stats['annules'] > 0 ? 'text-red-600' : 'text-gray-400' }} mt-1">{{ $stats['annules'] }}</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-3">
        <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les comptes</option>
            @foreach($cashAccounts as $ca)
                <option value="{{ $ca->id }}" @selected(($filters['cash_account_id'] ?? '') == $ca->id)>{{ $ca->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['from','to','cash_account_id']))
        <a href="{{ route('tresorerie.virements.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Date</th>
                        <th class="text-left">Source</th>
                        <th class="text-left">Destination</th>
                        <th class="text-right">Montant</th>
                        <th class="text-left">Statut</th>
                        <th class="text-left">Créé par</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $t)
                    <tr class="{{ $t->status === 'annule' ? 'opacity-50' : '' }}">
                        <td class="font-mono font-semibold text-emerald-700">{{ $t->number }}</td>
                        <td class="tabular-nums text-gray-600">{{ $t->transfer_date?->format('d/m/Y') }}</td>
                        <td>
                            <span class="text-gray-800">{{ $t->fromAccount?->name ?? '—' }}</span>
                            <span class="text-xs text-gray-400 block">{{ ucfirst($t->fromAccount?->type ?? '') }}</span>
                        </td>
                        <td>
                            <span class="text-gray-800">{{ $t->toAccount?->name ?? '—' }}</span>
                            <span class="text-xs text-gray-400 block">{{ ucfirst($t->toAccount?->type ?? '') }}</span>
                        </td>
                        <td class="text-right font-mono font-semibold tabular-nums text-gray-900">{{ number_format($t->amount, 0, ',', ' ') }} F</td>
                        <td>
                            @if($t->status === 'annule')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">Annulé</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">Validé</span>
                            @endif
                        </td>
                        <td class="text-gray-500 text-xs">{{ $t->createdBy?->name ?? '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('tresorerie.virements.show', $t) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun virement enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $transfers->links() }}</div>
        @endif
    </div>

</div>
@endsection
