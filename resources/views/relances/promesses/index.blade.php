@extends('layouts.erp')
@section('title', 'Promesses de paiement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('relances.index') }}" class="hover:text-gray-700">Relances</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Promesses de paiement</span>
@endsection

@section('content')
<div class="space-y-3" x-data="{ createOpen: false }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Promesses de paiement</h1>
            <p class="text-sm text-gray-500 mt-0.5">Engagements de règlement client (suivi du recouvrement)</p>
        </div>
        @can('clients.create')
        <button type="button" @click="createOpen = true"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle promesse
        </button>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">En attente</p>
            <p class="text-[16px] font-bold text-amber-800 mt-1">{{ $stats['en_attente'] }}</p>
        </div>
        <div class="bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Montant promis</p>
            <p class="text-[16px] font-bold text-emerald-900 tabular-nums mt-1">{{ number_format($stats['montant'], 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Tenues</p>
            <p class="text-[16px] font-bold text-green-800 mt-1">{{ $stats['tenues'] }}</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Non tenues</p>
            <p class="text-[16px] font-bold text-red-800 mt-1">{{ $stats['non_tenues'] }}</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-3">
        <select name="client_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm min-w-48">
            <option value="">Tous les clients</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(($filters['client_id'] ?? '') == $c->id)>{{ $c->trade_name ?? $c->name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            <option value="en_attente" @selected(($filters['status'] ?? '') === 'en_attente')>En attente</option>
            <option value="tenue" @selected(($filters['status'] ?? '') === 'tenue')>Tenue</option>
            <option value="non_tenue" @selected(($filters['status'] ?? '') === 'non_tenue')>Non tenue</option>
            <option value="annulee" @selected(($filters['status'] ?? '') === 'annulee')>Annulée</option>
        </select>
        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['client_id','status']))
        <a href="{{ route('promesses.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Client</th>
                        <th class="text-left">Facture</th>
                        <th class="text-right">Montant</th>
                        <th class="text-left">Date promise</th>
                        <th class="text-left">Statut</th>
                        <th class="text-left">Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promises as $p)
                    <tr class="{{ $p->status === 'annulee' ? 'opacity-50' : '' }}">
                        <td class="text-gray-800">{{ $p->client?->trade_name ?? $p->client?->name ?? '—' }}</td>
                        <td class="font-mono text-xs text-emerald-700">{{ $p->invoice?->number ?? '—' }}</td>
                        <td class="text-right font-mono font-semibold tabular-nums text-gray-900">{{ number_format($p->amount, 0, ',', ' ') }} F</td>
                        <td class="tabular-nums {{ $p->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                            {{ $p->promised_date?->format('d/m/Y') }}
                            @if($p->isOverdue())<span class="text-xs block text-red-500">échue</span>@endif
                        </td>
                        <td>
                            @php
                                $sc = match($p->status) {
                                    'en_attente' => 'bg-amber-100 text-amber-700',
                                    'tenue'      => 'bg-green-100 text-green-700',
                                    'non_tenue'  => 'bg-red-100 text-red-700',
                                    default      => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $sc }}">{{ $p->statusLabel() }}</span>
                        </td>
                        <td class="text-gray-500 text-xs max-w-xs truncate">{{ $p->notes ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('clients.create')
                            @if(in_array($p->status, ['en_attente', 'non_tenue']))
                            <form method="POST" action="{{ route('promesses.status', $p) }}" class="inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="tenue">
                                <button class="text-green-600 hover:underline text-xs font-medium">Tenue</button>
                            </form>
                            @endif
                            @if($p->status === 'en_attente')
                            <form method="POST" action="{{ route('promesses.status', $p) }}" class="inline ml-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="non_tenue">
                                <button class="text-red-600 hover:underline text-xs font-medium">Non tenue</button>
                            </form>
                            @endif
                            @endcan
                            @can('clients.delete')
                            <form method="POST" action="{{ route('promesses.destroy', $p) }}" class="inline ml-2"
                                  data-confirm="Supprimer cette promesse ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucune promesse enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($promises->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $promises->links() }}</div>
        @endif
    </div>

    {{-- Modal création --}}
    @can('clients.create')
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="createOpen = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Nouvelle promesse de paiement</h3>
            <form method="POST" action="{{ route('promesses.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                    <select name="client_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" @selected(($filters['client_id'] ?? '') == $c->id)>{{ $c->trade_name ?? $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" min="1" step="1" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono focus:ring-1 focus:ring-emerald-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date promise <span class="text-red-500">*</span></label>
                        <input type="date" name="promised_date" value="{{ date('Y-m-d') }}" required
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-300">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-emerald-300"
                              placeholder="Contexte, n° facture, engagement pris…"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="createOpen = false" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Annuler</button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</div>
@endsection
