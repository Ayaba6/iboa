@extends('layouts.erp')
@section('title', 'Plan comptable')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Plan comptable SYSCOHADA</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Plan comptable SYSCOHADA</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $accounts->total() }} compte(s)</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start">
            {{-- Export --}}
            <a href="{{ route('comptabilite.plan-comptable.export-pdf', array_filter(['class_id' => $classId ?? null, 'search' => $search ?? null])) }}"
               class="border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                PDF
            </a>
            <a href="{{ route('comptabilite.plan-comptable.export', array_filter(['class_id' => $classId ?? null])) }}"
               class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-2.5 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Exporter
            </a>

            @can('accounting.manage')
            {{-- Import trigger — dispatche un event natif capté par la modal dans @push('modals') --}}
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-import-modal'))"
                    class="border border-violet-300 bg-violet-50 hover:bg-violet-100 text-violet-700 text-sm font-medium px-2.5 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 6l5-5 5 5M12 1v12"/>
                </svg>
                Importer
            </button>

            <a href="{{ route('comptabilite.plan-comptable.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau compte
            </a>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Code ou libellé..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500 focus:border-violet-500">

            <select name="class_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                <option value="">Toutes les classes</option>
                @foreach($classes as $class)
                <option value="{{ $class->id }}" {{ ($classId ?? '') == $class->id ? 'selected' : '' }}>
                    Classe {{ $class->number }} — {{ $class->name }}
                </option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if($search || $classId)
                <a href="{{ route('comptabilite.plan-comptable.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left w-24">Code</th>
                        <th class="text-left">Libellé</th>
                        <th class="text-left hidden md:table-cell">Classe</th>
                        <th class="text-left hidden lg:table-cell">Type</th>
                        <th class="text-right hidden lg:table-cell">Solde débiteur</th>
                        <th class="text-right hidden lg:table-cell">Solde créditeur</th>
                        <th class="text-center hidden md:table-cell">Saisissable</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr class="{{ !$account->is_active ? 'opacity-50' : '' }}">
                        <td>
                            <span class="font-mono font-semibold text-violet-700">{{ $account->code }}</span>
                        </td>
                        <td>
                            <span class="{{ $account->is_detail ? 'text-gray-900' : 'text-gray-500 font-medium' }}">
                                {{ $account->name }}
                            </span>
                            @if($account->parent)
                            <p class="text-xs text-gray-400">↳ {{ $account->parent->code }}</p>
                            @endif
                        </td>
                        <td class="text-gray-500 hidden md:table-cell text-xs">
                            Classe {{ $account->accountClass?->number }}
                        </td>
                        <td class="hidden lg:table-cell">
                            @php
                            $typeColors = ['actif'=>'blue','passif'=>'indigo','charge'=>'red','produit'=>'green','bilan'=>'gray','resultat'=>'amber'];
                            $tc = $typeColors[$account->type] ?? 'gray';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $tc }}-100 text-{{ $tc }}-700">
                                {{ ucfirst($account->type) }}
                            </span>
                        </td>
                        <td class="text-right tabular-nums text-gray-700 hidden lg:table-cell">
                            {{ $account->debit_balance > 0 ? number_format($account->debit_balance, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-right tabular-nums text-gray-700 hidden lg:table-cell">
                            {{ $account->credit_balance > 0 ? number_format($account->credit_balance, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-center hidden md:table-cell">
                            @if($account->is_detail)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Oui</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Regroupement</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('accounting.manage')
                            <a href="{{ route('comptabilite.plan-comptable.edit', $account) }}"
                               class="p-1.5 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded transition-colors inline-flex" title="Modifier">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucun compte trouvé.
                            @if(!$search && !$classId)
                            <br><span class="text-xs">Exécutez le seeder SYSCOHADA pour initialiser le plan comptable.</span>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $accounts->appends(compact('search','classId'))->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

@can('accounting.manage')
@push('modals')
{{-- Modal import — composant autonome directement sous <body>, aucun stacking context parent --}}
<div x-data="{ open: false, fileName: '' }"
     x-show="open"
     style="display:none"
     @open-import-modal.window="open = true"
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false; fileName = ''"></div>

    {{-- Panel --}}
    <div class="relative bg-white rounded-[4px] shadow-2xl w-full max-w-md p-6 z-10" @click.stop>
        <div class="flex items-start justify-between mb-5">
            <div>
                <h2 class="text-[16px] font-bold text-gray-900">Importer le plan comptable</h2>
                <p class="text-sm text-gray-500 mt-0.5">Fichier Excel (.xlsx, .xls) ou CSV</p>
            </div>
            <button type="button" @click="open = false; fileName = ''"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-[4px] hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Template download --}}
        <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-3 mb-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
            </svg>
            <div class="text-sm">
                <p class="text-blue-800 font-medium">Première fois ?</p>
                <a href="{{ route('comptabilite.plan-comptable.template') }}"
                   class="text-blue-600 hover:underline text-xs">
                    Télécharger le modèle Excel →
                </a>
            </div>
        </div>

        <form action="{{ route('comptabilite.plan-comptable.import') }}" method="POST" enctype="multipart/form-data" data-turbo="false">
            @csrf
            {{-- Zone de dépôt --}}
            <label class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-[4px] cursor-pointer transition-colors relative"
                   :class="fileName ? 'border-violet-400 bg-violet-50' : 'border-gray-300 hover:border-violet-400 hover:bg-violet-50'">
                <input type="file" name="file"
                       class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                       accept=".xlsx,.xls,.csv"
                       @change="fileName = $event.target.files[0]?.name ?? ''">

                <template x-if="!fileName">
                    <div class="text-center pointer-events-none">
                        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm text-gray-500">Glissez votre fichier ici</p>
                        <p class="text-xs text-gray-400 mt-1">ou cliquez pour parcourir</p>
                    </div>
                </template>
                <template x-if="fileName">
                    <div class="text-center pointer-events-none">
                        <svg class="w-7 h-7 text-violet-500 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-medium text-violet-700" x-text="fileName"></p>
                    </div>
                </template>
            </label>

            <p class="text-xs text-gray-400 mt-2">Les comptes existants seront mis à jour. Max 5 Mo.</p>

            <div class="flex gap-3 mt-5">
                <button type="button" @click="open = false; fileName = ''"
                        class="flex-1 border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px] hover:bg-gray-50 transition-colors">
                    Annuler
                </button>
                <button type="submit" :disabled="!fileName"
                        :class="fileName ? 'bg-emerald-700 hover:bg-emerald-800 cursor-pointer' : 'bg-violet-300 cursor-not-allowed'"
                        class="flex-1 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Importer
                </button>
            </div>
        </form>
    </div>
</div>
@endpush
@endcan
