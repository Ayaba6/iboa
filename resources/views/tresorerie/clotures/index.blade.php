@extends('layouts.erp')
@section('title', 'Clôtures de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Clôtures de caisse</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Clôtures de caisse</h1>
            <p class="text-sm text-gray-500 mt-0.5">Contrôle journalier : solde théorique vs compté</p>
        </div>
        @can('treasury.write')
        <a href="{{ route('tresorerie.clotures.create') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle clôture
        </a>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Brouillons</p>
            <p class="text-[16px] font-bold text-amber-600 mt-1">{{ $stats['brouillons'] }}</p>
            <p class="text-xs text-gray-400">à valider</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Validées</p>
            <p class="text-[16px] font-bold text-emerald-600 mt-1">{{ $stats['validees'] }}</p>
            <p class="text-xs text-gray-400">comptabilisées</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Manquants cumulés</p>
            <p class="text-[16px] font-bold text-red-600 tabular-nums mt-1">{{ number_format($stats['ecart_manque'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400">écarts négatifs (6588)</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Excédents cumulés</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums mt-1">+{{ number_format($stats['ecart_excedent'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400">écarts positifs (7588)</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-3">
        <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm min-w-48">
            <option value="">Toutes les caisses</option>
            @foreach($cashAccounts as $ca)
                <option value="{{ $ca->id }}" @selected(($filters['cash_account_id'] ?? '') == $ca->id)>{{ $ca->name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            <option value="brouillon" @selected(($filters['status'] ?? '') === 'brouillon')>Brouillon</option>
            <option value="valide" @selected(($filters['status'] ?? '') === 'valide')>Validée</option>
        </select>
        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['cash_account_id','status']))
        <a href="{{ route('tresorerie.clotures.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
        @endif
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Date</th>
                        <th class="text-left">Caisse</th>
                        <th class="text-right">Théorique</th>
                        <th class="text-right">Compté</th>
                        <th class="text-right">Écart</th>
                        <th class="text-center">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($closures as $c)
                    <tr>
                        <td class="font-mono font-semibold text-emerald-700">{{ $c->number }}</td>
                        <td class="tabular-nums text-gray-600">{{ $c->closure_date?->format('d/m/Y') }}</td>
                        <td class="text-gray-800">{{ $c->cashAccount?->name ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-600">{{ number_format($c->theoretical_balance, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900 font-medium">{{ number_format($c->counted_balance, 0, ',', ' ') }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums {{ $c->difference == 0 ? 'text-gray-400' : ($c->difference > 0 ? 'text-emerald-600' : 'text-red-600') }}">
                            {{ $c->difference == 0 ? '✓ 0' : ($c->difference > 0 ? '+' : '') . number_format($c->difference, 0, ',', ' ') }}
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $c->status === 'valide' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $c->status === 'valide' ? 'Validée' : 'Brouillon' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('tresorerie.clotures.show', $c) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune clôture enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($closures->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $closures->links() }}</div>
        @endif
    </div>

</div>
@endsection
