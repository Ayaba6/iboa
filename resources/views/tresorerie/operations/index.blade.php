@extends('layouts.erp')
@section('title', 'Opérations de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Opérations de caisse</span>
@endsection

@section('content')
<div class="space-y-3"
     x-data="{
        cancelOpen: false, cancelId: null, cancelNumber: '',
        openCancel(id, num) { this.cancelId = id; this.cancelNumber = num; this.cancelOpen = true; }
     }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Opérations diverses de caisse</h1>
            <p class="text-sm text-gray-500 mt-0.5">Entrées et sorties manuelles (apport, recette, dépense, petty cash)</p>
        </div>
        @can('treasury.write')
        <div class="flex gap-2">
            <a href="{{ route('tresorerie.operations.create', ['direction' => 'entree']) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                Entrée
            </a>
            <a href="{{ route('tresorerie.operations.create', ['direction' => 'sortie']) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                Sortie
            </a>
        </div>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Entrées (page)</p>
            <p class="text-[16px] font-bold text-emerald-800 tabular-nums mt-1">+{{ number_format($totalEntrees, 0, ',', ' ') }} F</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Sorties (page)</p>
            <p class="text-[16px] font-bold text-red-800 tabular-nums mt-1">-{{ number_format($totalSorties, 0, ',', ' ') }} F</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4 flex flex-wrap gap-3">
        <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Toutes les caisses</option>
            @foreach($cashAccounts as $ca)
                <option value="{{ $ca->id }}" @selected(($filters['cash_account_id'] ?? '') == $ca->id)>{{ $ca->name }}</option>
            @endforeach
        </select>
        <select name="direction" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
            <option value="">Tous les sens</option>
            <option value="entree" @selected(($filters['direction'] ?? '') === 'entree')>Entrées</option>
            <option value="sortie" @selected(($filters['direction'] ?? '') === 'sortie')>Sorties</option>
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded-[4px] text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['from','to','cash_account_id','direction']))
        <a href="{{ route('tresorerie.operations.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-[4px] text-sm hover:bg-gray-50">✕</a>
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
                        <th class="text-left">Caisse</th>
                        <th class="text-left">Sens</th>
                        <th class="text-left">Catégorie / Libellé</th>
                        <th class="text-right">Montant</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($operations as $op)
                    <tr class="{{ $op->status === 'annule' ? 'opacity-50' : '' }}">
                        <td class="font-mono font-semibold text-emerald-700">{{ $op->number }}</td>
                        <td class="tabular-nums text-gray-600">{{ $op->operation_date?->format('d/m/Y') }}</td>
                        <td class="text-gray-800">{{ $op->cashAccount?->name ?? '—' }}</td>
                        <td>
                            @if($op->direction === 'entree')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">Entrée</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">Sortie</span>
                            @endif
                        </td>
                        <td class="text-gray-700">
                            {{ $op->category ?? '—' }}
                            @if($op->label)<span class="text-xs text-gray-400 block">{{ $op->label }}</span>@endif
                        </td>
                        <td class="text-right font-mono font-semibold tabular-nums {{ $op->direction === 'entree' ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $op->direction === 'entree' ? '+' : '-' }}{{ number_format($op->amount, 0, ',', ' ') }} F
                        </td>
                        <td>
                            @if($op->status === 'annule')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Annulée</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Validée</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('treasury.write')
                            @if($op->status === 'valide')
                                <button type="button" @click="openCancel({{ $op->id }}, '{{ $op->number }}')"
                                        class="text-red-600 hover:underline text-xs font-medium">Annuler</button>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune opération enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($operations->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $operations->links() }}</div>
        @endif
    </div>

    {{-- Modal annulation --}}
    @can('treasury.write')
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="cancelOpen = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Annuler l'opération <span x-text="cancelNumber" class="font-mono text-emerald-700"></span></h3>
            <p class="text-sm text-gray-500">Le mouvement de caisse sera inversé et l'écriture comptable contre-passée.</p>
            <form method="POST" :action="'{{ url('tresorerie/operations') }}/' + cancelId + '/cancel'" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif <span class="text-red-500">*</span></label>
                    <textarea name="motif" rows="3" minlength="5" maxlength="500" required
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-400"
                              placeholder="Raison de l'annulation…"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cancelOpen = false" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Fermer</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Confirmer l'annulation</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</div>
@endsection
