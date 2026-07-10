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

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Brouillard comptable</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $entries->total() }} écriture(s) en brouillon en attente de validation</p>
        </div>
        @can('accounting.write')
        <a href="{{ route('comptabilite.journaux.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors self-start">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle écriture
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, libellé, référence..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            <select name="journal_type_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">Tous les journaux</option>
                @foreach($journalTypes as $jt)
                <option value="{{ $jt->id }}" {{ ($filters['journal_type_id'] ?? '') == $jt->id ? 'selected' : '' }}>
                    {{ $jt->code }} — {{ $jt->name }}
                </option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            <div class="flex gap-2">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                       class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-2.5 py-1.5 rounded-[4px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
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
                    {{ $entry->isBalanced() ? '✓ équilibré' : '⚠ déséquilibré' }}
                </span>
                <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                   class="text-violet-600 hover:text-violet-800 font-medium text-xs underline ml-2">Voir</a>
                @can('accounting.validate')
                @if($entry->isBalanced())
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
                        <span class="font-mono text-violet-600 font-semibold">{{ $line->account?->code ?? '?' }}</span>
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

</div>
@endsection
