@extends('layouts.erp')
@section('title', 'Journal comptable')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Journal comptable</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Journal comptable</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $entries->total() }} écriture(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('comptabilite.journaux.export-pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('comptabilite.journaux.export', request()->query()) }}"
               class="inline-flex items-center gap-1.5 border border-green-600 text-green-700 hover:bg-green-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                Exporter Excel
            </a>
            @can('accounting.write')
            <a href="{{ route('comptabilite.journaux.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle écriture
            </a>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, libellé..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">

            <select name="journal_type_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">Tous les journaux</option>
                @foreach($journalTypes as $jt)
                <option value="{{ $jt->id }}" {{ ($filters['journal_type_id'] ?? '') == $jt->id ? 'selected' : '' }}>
                    {{ $jt->code }} — {{ $jt->name }}
                </option>
                @endforeach
            </select>

            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
                <option value="cloture"   {{ ($filters['status'] ?? '') === 'cloture'   ? 'selected' : '' }}>Clôturé</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">

            <div class="flex gap-2">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                       class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm px-2.5 py-1.5 rounded-[4px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Numéro</th>
                        <th class="text-left hidden md:table-cell">Journal</th>
                        <th class="text-left hidden lg:table-cell">Date</th>
                        <th class="text-left">Libellé</th>
                        <th class="text-right hidden lg:table-cell">Débit</th>
                        <th class="text-right hidden lg:table-cell">Crédit</th>
                        <th class="text-center">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td>
                            <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                               class="font-mono font-semibold text-violet-600 hover:text-violet-800">
                                {{ $entry->number }}
                            </a>
                        </td>
                        <td class="hidden md:table-cell">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 font-mono">
                                {{ $entry->journalType?->code }}
                            </span>
                        </td>
                        <td class="text-gray-600 hidden lg:table-cell">{{ $entry->entry_date?->format('d/m/Y') }}</td>
                        <td class="text-gray-700">
                            {{ \Illuminate\Support\Str::limit($entry->description, 50) }}
                            @if($entry->reference)
                            <span class="text-xs text-gray-400 ml-1">{{ $entry->reference }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums text-gray-700 hidden lg:table-cell">
                            {{ number_format($entry->total_debit, 0, ',', ' ') }}
                        </td>
                        <td class="text-right tabular-nums hidden lg:table-cell">
                            <span class="{{ $entry->total_debit !== $entry->total_credit ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                {{ number_format($entry->total_credit, 0, ',', ' ') }}
                            </span>
                        </td>
                        <td class="text-center">
                            @php $color = $entry->statusColor(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ $entry->statusLabel() }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                                   class="p-1.5 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($entry->status === 'brouillon')
                                @can('accounting.validate')
                                <form action="{{ route('comptabilite.journaux.validate', $entry) }}" method="POST"
                                      data-confirm="Valider cette écriture ? Cette action est irréversible."
                                      data-confirm-title="Valider l'écriture"
                                      data-confirm-label="Valider"
                                      data-confirm-danger="false">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                <form action="{{ route('comptabilite.journaux.destroy', $entry) }}" method="POST"
                                      data-confirm="Supprimer cette écriture ?"
                                      data-confirm-title="Supprimer l'écriture">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune écriture comptable.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $entries->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
