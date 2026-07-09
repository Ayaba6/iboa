@extends('layouts.erp')
@section('title', 'Rapprochement bancaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapprochement bancaire</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Rapprochements bancaires</h1>
        @can('accounting.write')
        <a href="{{ route('comptabilite.rapprochement.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau rapprochement
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">Tous les comptes</option>
                @foreach($cashAccounts as $ca)
                <option value="{{ $ca->id }}" {{ ($filters['cash_account_id'] ?? '') == $ca->id ? 'selected' : '' }}>
                    {{ $ca->name }} ({{ $ca->code }})
                </option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Date début"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Date fin"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Filtrer</button>
                @if(array_filter($filters))
                <a href="{{ route('comptabilite.rapprochement.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">✕</a>
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
                        <th class="text-left">N°</th>
                        <th class="text-left">Compte bancaire</th>
                        <th class="text-left">Période</th>
                        <th class="text-left">Date relevé</th>
                        <th class="text-right">Solde relevé</th>
                        <th class="text-right">Solde compta</th>
                        <th class="text-right">Écart</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliations as $rec)
                    <tr>
                        <td class="font-mono font-semibold text-violet-600">{{ $rec->number }}</td>
                        <td class="text-gray-700">{{ $rec->cashAccount?->name }}</td>
                        <td class="text-gray-500 text-xs">
                            {{ $rec->period_start?->format('d/m/Y') }} → {{ $rec->period_end?->format('d/m/Y') }}
                        </td>
                        <td class="text-gray-700">{{ $rec->statement_date?->format('d/m/Y') }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($rec->closing_balance, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($rec->book_balance, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums font-semibold {{ $rec->difference == 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $rec->difference == 0 ? '✓ 0' : number_format($rec->difference, 0, ',', ' ') }}
                        </td>
                        <td>
                            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'valide' => 'bg-green-100 text-green-700']; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $colors[$rec->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $rec->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('comptabilite.rapprochement.show', $rec) }}" class="text-violet-600 hover:text-violet-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                            Aucun rapprochement trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reconciliations->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $reconciliations->links() }}</div>
        @endif
    </div>

</div>
@endsection
