@extends('layouts.erp')
@section('title', 'Brouillard comptable')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Brouillard comptable</span>
@endsection

@section('content')
<div class="space-y-3">

    @php
        $lblX = 'block text-[11px] font-bold text-gray-700 mb-1';
        $inpX = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
        $lkX  = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    @endphp

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Brouillard comptable</h1>
            <p class="text-[12.5px] text-gray-500">{{ $entries->total() }} écriture(s) en brouillon en attente de validation</p>
        </div>
        <div class="flex items-center gap-1.5">
            @can('accounting.write')
            <a href="{{ route('comptabilite.journaux.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-semibold px-3 rounded-[4px] transition-colors">
                + Nouvelle écriture
            </a>
            @endcan
            <button type="button" onclick="window.print()"
                    class="h-8 inline-flex items-center border border-emerald-300 text-emerald-700 bg-white hover:bg-emerald-50 text-[12px] font-semibold px-3 rounded-[4px] transition-colors">Imprimer</button>
            <a href="{{ route('comptabilite.dashboard') }}"
               class="h-8 inline-flex items-center border border-gray-300 text-gray-500 hover:text-gray-700 hover:bg-gray-50 text-[12px] font-semibold px-3 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- Filtres [X3] --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3 items-end">
            <div class="sm:col-span-4">
                <label class="{{ $lblX }}">Rechercher</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, libellé, référence…" class="{{ $inpX }}">
            </div>
            <div class="sm:col-span-3">
                <label class="{{ $lblX }}">Journal</label>
                <div class="relative"><select name="journal_type_id" class="{{ $lkX }}">
                    <option value="">Tous les journaux</option>
                    @foreach($journalTypes as $jt)
                    <option value="{{ $jt->id }}" {{ ($filters['journal_type_id'] ?? '') == $jt->id ? 'selected' : '' }}>
                        {{ $jt->code }} — {{ $jt->name }}
                    </option>
                    @endforeach
                </select><span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span></div>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lblX }}">Période du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inpX }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lblX }}">au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inpX }}">
            </div>
            <div class="sm:col-span-1">
                <button type="submit" class="w-full h-8 bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-semibold rounded-[4px] transition-colors">OK</button>
            </div>
        </div>
    </form>

    @forelse($entries as $entry)
    <div class="bg-white rounded-[4px] border border-amber-200 overflow-hidden">
        {{-- Entry header --}}
        <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                    Brouillon
                </span>
                <span class="font-mono font-semibold text-gray-900 text-sm">{{ $entry->number }}</span>
                <span class="text-xs text-gray-500">{{ $entry->journalType?->code ?? '—' }}</span>
                <span class="text-xs text-gray-500">{{ $entry->entry_date?->format('d/m/Y') }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-700">
                <span class="font-semibold">{{ number_format($entry->total_debit, 0, ',', ' ') }} FCFA</span>
                <span class="text-xs">
                    @if($entry->lines->isEmpty())
                        <span class="text-red-600 font-semibold">⚠ aucune ligne</span>
                    @else
                        {{ $entry->isBalanced() ? '✓ équilibré' : '⚠ déséquilibré' }}
                    @endif
                </span>
                <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                   class="text-emerald-700 hover:text-emerald-900 font-medium text-xs underline ml-2">Voir</a>
                @can('accounting.validate')
                {{-- Valider seulement si équilibrée ET non vide (le service refuse
                     désormais les écritures sans ligne / à montant nul). --}}
                @if($entry->isBalanced() && $entry->lines->isNotEmpty() && ($entry->total_debit > 0 || $entry->total_credit > 0))
                <form method="POST" action="{{ route('comptabilite.journaux.validate', $entry) }}" class="inline"
                      data-confirm="Valider l'écriture {{ $entry->number }} ? Cette action est irréversible."
                      data-confirm-title="Valider l'écriture"
                      data-confirm-label="Valider"
                      data-confirm-danger="false">
                    @csrf
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white text-xs font-medium px-3 py-1 rounded-[4px] transition-colors">
                        Valider
                    </button>
                </form>
                @endif
                @endcan
            </div>
        </div>

        {{-- Description --}}
        <div class="px-5 py-2 text-sm text-gray-600 border-b border-gray-50">
            {{ $entry->description }}
            @if($entry->reference) <span class="text-gray-400 ml-2">· Réf: {{ $entry->reference }}</span> @endif
        </div>

        {{-- Lines --}}
        <table class="w-full text-xs">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-gray-500 font-medium w-full">Compte / Libellé</th>
                    <th class="px-3 py-1.5 text-right text-gray-500 font-medium whitespace-nowrap">Débit</th>
                    <th class="px-3 py-1.5 text-right text-gray-500 font-medium whitespace-nowrap">Crédit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($entry->lines as $line)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 w-full">
                        <span class="font-mono text-blue-600 font-semibold">{{ $line->account?->code ?? '?' }}</span>
                        <span class="text-gray-500 ml-1">{{ $line->account?->name ?? '—' }}</span>
                        @if($line->label && $line->label !== $entry->description)
                            <span class="text-gray-400 ml-2 italic">{{ $line->label }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $line->debit > 0 ? 'text-gray-900 font-medium' : 'text-gray-300' }} whitespace-nowrap">
                        {{ $line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '—' }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $line->credit > 0 ? 'text-gray-900 font-medium' : 'text-gray-300' }} whitespace-nowrap">
                        {{ $line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                <tr>
                    <td class="px-3 py-1.5 text-xs font-bold text-gray-500 uppercase w-full">Totaux</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-gray-900 whitespace-nowrap">
                        {{ number_format($entry->total_debit, 0, ',', ' ') }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-gray-900 whitespace-nowrap">
                        {{ number_format($entry->total_credit, 0, ',', ' ') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @empty
    <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-gray-500 font-medium">Aucun brouillon en attente</p>
        <p class="text-gray-400 text-sm mt-1">Toutes les écritures ont été validées</p>
    </div>
    @endforelse

    {{ $entries->withQueryString()->links() }}

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Brouillons : <span class="text-white font-semibold">{{ $entries->total() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
