@extends('layouts.erp')
@section('title', 'Remises en banque')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Remises en banque</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Remises en banque</h1>
        @can('treasury.write')
        <a href="{{ route('tresorerie.remises.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle remise
        </a>
        @endcan
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Brouillons</p>
            <p class="text-[16px] font-bold text-amber-600 mt-1">{{ $stats['brouillons'] }}</p>
            <p class="text-xs text-gray-400">à valider</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Remises validées</p>
            <p class="text-[16px] font-bold text-emerald-600 mt-1">{{ $stats['valide_count'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total remis (validé)</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums mt-1">{{ number_format($stats['valide_total'], 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">F</span></p>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <select name="cash_account_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les comptes</option>
                @foreach($bankAccounts as $ba)
                <option value="{{ $ba->id }}" {{ ($filters['cash_account_id'] ?? '') == $ba->id ? 'selected' : '' }}>{{ $ba->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Filtrer</button>
            @if(array_filter($filters))
            <a href="{{ route('tresorerie.remises.index') }}" class="border border-gray-300 text-gray-600 text-sm px-2.5 py-1.5 rounded-[4px]">✕</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N°</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Banque</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Source</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deposits as $d)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 font-mono font-semibold text-emerald-700">{{ $d->number }}</td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $d->deposit_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $d->cashAccount?->name }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $d->sourceCashAccount?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-800">{{ number_format($d->total_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $d->status === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $d->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('tresorerie.remises.show', $d) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">Aucune remise en banque.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($deposits->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $deposits->links() }}</div>
        @endif
    </div>
</div>
@endsection
