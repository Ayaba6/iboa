@extends('layouts.erp')
@section('title', 'Livre journal')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Livre journal</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Livre journal</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $entries->total() }} écriture(s) validée(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('comptabilite.journaux.export-pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        {{-- Entry header --}}
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="font-mono font-bold text-violet-700 text-sm">{{ $entry->number }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                    {{ $entry->journalType?->code ?? '—' }}
                </span>
                <span class="text-sm font-medium text-gray-700">{{ $entry->entry_date?->format('d/m/Y') }}</span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                @if($entry->reference)
                <span class="text-gray-500 text-xs">Réf: {{ $entry->reference }}</span>
                @endif
                <a href="{{ route('comptabilite.journaux.show', $entry) }}"
                   class="text-violet-600 hover:text-violet-800 text-xs font-medium underline">Détail</a>
            </div>
        </div>

        <div class="px-5 py-2 text-sm text-gray-700 border-b border-gray-50 font-medium">
            {{ $entry->description }}
        </div>

        {{-- Lines --}}
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                @foreach($entry->lines as $line)
                <tr class="{{ $line->debit > 0 ? '' : 'bg-gray-50/40' }}">
                    <td class="px-3 py-1.5 w-full">
                        @if($line->debit > 0)
                            <span class="font-mono text-violet-600 font-semibold text-xs">{{ $line->account?->code }}</span>
                            <span class="text-gray-700 ml-2 text-xs">{{ $line->account?->name }}</span>
                        @else
                            <span class="pl-6 font-mono text-indigo-500 text-xs">{{ $line->account?->code }}</span>
                            <span class="text-gray-600 ml-2 text-xs italic">{{ $line->account?->name }}</span>
                        @endif
                        @if($line->label && $line->label !== $entry->description)
                            <span class="text-gray-400 text-xs ml-2">— {{ $line->label }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-xs font-semibold whitespace-nowrap {{ $line->debit > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                        {{ $line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '' }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-xs font-semibold whitespace-nowrap {{ $line->credit > 0 ? 'text-gray-900' : 'text-gray-300' }}">
                        {{ $line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-gray-200 bg-gray-50">
                <tr>
                    <td class="px-3 py-1.5 text-xs font-bold text-gray-400 uppercase w-full">Totaux</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-gray-800 text-xs whitespace-nowrap">
                        {{ number_format($entry->total_debit, 0, ',', ' ') }}
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-gray-800 text-xs whitespace-nowrap">
                        {{ number_format($entry->total_credit, 0, ',', ' ') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @empty
    <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
        <p class="text-gray-500">Aucune écriture validée pour cette période</p>
    </div>
    @endforelse

    {{ $entries->withQueryString()->links() }}

    {{-- Page totals --}}
    @if($entries->count() > 0)
    <div style="background:#065f46" class="text-white rounded-[4px] px-5 py-3 flex justify-between items-center font-bold">
        <span class="text-sm">TOTAUX PAGE ({{ $entries->count() }} écritures)</span>
        <div class="flex gap-8 text-sm">
            <span>Débit : {{ number_format($totalDebit, 0, ',', ' ') }} FCFA</span>
            <span>Crédit : {{ number_format($totalCredit, 0, ',', ' ') }} FCFA</span>
        </div>
    </div>
    @endif

</div>
@endsection
