@extends('layouts.erp')
@section('title', 'Virements internes')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Virements internes</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Virements internes</h1>
            <p class="text-sm text-gray-500 mt-0.5">Transferts entre comptes de trésorerie (caisse ↔ banque)</p>
        </div>
        @can('treasury.write')
        <a href="{{ route('tresorerie.virements.create') }}"
           class="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
            + Nouveau virement
        </a>
        @endcan
    </div>

    {{-- KPIs --}}
    @php
        $kpis = [
            ['label' => 'Total viré (filtré)', 'value' => number_format($stats['total'], 0, ',', ' ') . ' F', 'text' => 'text-emerald-700', 'bd' => 'border-emerald-200'],
            ['label' => 'Virements validés',   'value' => $stats['count'],   'text' => 'text-gray-900', 'bd' => 'border-gray-300'],
            ['label' => 'Annulés',             'value' => $stats['annules'], 'text' => $stats['annules'] > 0 ? 'text-red-600' : 'text-gray-400', 'bd' => $stats['annules'] > 0 ? 'border-red-200' : 'border-gray-300'],
        ];
    @endphp
    <div class="grid grid-cols-3 gap-1.5">
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-[4px] border {{ $kpi['bd'] }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
            <p class="mt-0.5 text-[17px] font-bold {{ $kpi['text'] }} tabular-nums leading-none">{{ $kpi['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2 flex flex-wrap items-center gap-2">
        <div class="relative">
            <select name="cash_account_id" class="appearance-none h-8 py-0 pl-2 pr-7 border border-gray-300 rounded-[4px] text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                <option value="">Tous les comptes</option>
                @foreach($cashAccounts as $ca)
                    <option value="{{ $ca->id }}" @selected(($filters['cash_account_id'] ?? '') == $ca->id)>{{ $ca->name }}</option>
                @endforeach
            </select>
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>
        </div>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px]">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px]">
        <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Filtrer</button>
        @if(request()->hasAny(['from','to','cash_account_id']))
        <a href="{{ route('tresorerie.virements.index') }}" class="h-8 inline-flex items-center px-2.5 border border-gray-300 text-gray-500 rounded-[4px] text-[12px] hover:bg-gray-50">✕</a>
        @endif
        <span class="ml-auto text-[12px] text-gray-400">{{ $transfers->total() }} résultat(s)</span>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full text-[14px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">N°</th>
                    <th class="px-3 py-1.5 text-left">Date</th>
                    <th class="px-3 py-1.5 text-left">Source</th>
                    <th class="px-3 py-1.5 text-left">Destination</th>
                    <th class="px-3 py-1.5 text-right">Montant (FCFA)</th>
                    <th class="px-3 py-1.5 text-left">Statut</th>
                    <th class="px-3 py-1.5 text-left">Créé par</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($transfers as $t)
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $t->status === 'annule' ? 'opacity-50' : '' }}">
                    <td class="px-3 py-1">
                        <a href="{{ route('tresorerie.virements.show', $t) }}" class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[13px]">{{ $t->number }}</a>
                    </td>
                    <td class="px-3 py-1 tabular-nums text-gray-600">{{ $t->transfer_date?->format('d/m/Y') }}</td>
                    <td class="px-3 py-1">
                        <span class="text-gray-900 font-medium">{{ $t->fromAccount?->name ?? '—' }}</span>
                        <span class="text-[11px] text-gray-400 block">{{ ucfirst(str_replace('_', ' ', $t->fromAccount?->type ?? '')) }}</span>
                    </td>
                    <td class="px-3 py-1">
                        <span class="text-gray-900 font-medium">{{ $t->toAccount?->name ?? '—' }}</span>
                        <span class="text-[11px] text-gray-400 block">{{ ucfirst(str_replace('_', ' ', $t->toAccount?->type ?? '')) }}</span>
                    </td>
                    <td class="px-3 py-1 text-right font-mono font-bold tabular-nums text-gray-900">{{ number_format($t->amount, 0, ',', ' ') }}</td>
                    <td class="px-3 py-1">
                        @if($t->status === 'annule')
                            <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-red-100 text-red-700">Annulé</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-emerald-100 text-emerald-700">Validé</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-gray-500 text-[12px]">{{ $t->createdBy?->name ?? '—' }}</td>
                    <td class="px-3 py-1 text-right">
                        <a href="{{ route('tresorerie.virements.show', $t) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Détail →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-[13px]">Aucun virement enregistré.</td></tr>
                @endforelse
            </tbody>
            @if($transfers->isNotEmpty())
            <tfoot>
                <tr class="text-white font-bold" style="background:#065f46">
                    <td colspan="4" class="px-3 py-1.5 text-right text-[11px] uppercase">Total viré (validés, filtre)</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ number_format($stats['total'], 0, ',', ' ') }} F</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
        @if($transfers->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $transfers->links() }}</div>
        @endif
    </div>

</div>
@endsection
