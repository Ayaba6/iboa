@extends('layouts.erp')
@section('title', 'Prévisions de trésorerie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Prévisions de trésorerie</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Prévisions de trésorerie</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('tresorerie.simulations.index') }}"
               class="inline-flex items-center gap-2 border border-emerald-200 text-emerald-800 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Simulation what-if
            </a>
            @can('treasury.write')
            <a href="{{ route('tresorerie.previsions.create') }}"
               class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle prévision
            </a>
            @endcan
        </div>
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <select name="period_type" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Toutes périodes</option>
                <option value="mensuel"     {{ ($filters['period_type'] ?? '') === 'mensuel'     ? 'selected' : '' }}>Mensuel</option>
                <option value="trimestriel" {{ ($filters['period_type'] ?? '') === 'trimestriel' ? 'selected' : '' }}>Trimestriel</option>
                <option value="annuel"      {{ ($filters['period_type'] ?? '') === 'annuel'      ? 'selected' : '' }}>Annuel</option>
            </select>
            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
            </select>
            <select name="year" class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Toutes années</option>
                @foreach(range(date('Y'), date('Y') - 3) as $y)
                <option value="{{ $y }}" {{ ($filters['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Filtrer</button>
            @if(array_filter($filters))
            <a href="{{ route('tresorerie.previsions.index') }}" class="border border-gray-300 text-gray-600 text-sm px-2.5 py-1.5 rounded-[4px]">✕</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N°</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Période</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde ouverture</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Encaissements</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Décaissements</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Flux net</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Solde clôture prévu</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($forecasts as $f)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 font-mono font-semibold text-emerald-700">{{ $f->number }}</td>
                        <td class="px-3 py-1.5">
                            <p class="font-medium text-gray-800">{{ $f->label }}</p>
                            <p class="text-xs text-gray-400 capitalize">{{ $f->period_type }}</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($f->opening_balance, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-green-600 font-medium">+{{ number_format($f->total_inflows, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-red-600 font-medium">-{{ number_format($f->total_outflows, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $f->net_flow >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $f->net_flow >= 0 ? '+' : '' }}{{ number_format($f->net_flow, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold text-emerald-800">{{ number_format($f->closing_balance_forecast, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $f->status === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $f->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('tresorerie.previsions.show', $f) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-16 text-center text-gray-400">Aucune prévision.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($forecasts->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $forecasts->links() }}</div>
        @endif
    </div>
</div>
@endsection
