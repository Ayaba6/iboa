@extends('layouts.erp')
@section('title', 'Réceptions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.index') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Réceptions</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total réceptions</p>
            <p class="text-[15px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En attente</p>
            <p class="text-[15px] font-bold text-gray-500 tabular-nums">{{ $summary['pending'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Validées</p>
            <p class="text-[15px] font-bold text-emerald-600 tabular-nums">{{ $summary['validated'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Partielles</p>
            <p class="text-[15px] font-bold text-amber-600 tabular-nums">{{ $summary['partial'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">Réceptions fournisseurs</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $receptions->total() }} réception(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° réception…"
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">

            <select name="supplier_id"
                    class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status"
                    class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                @foreach(['brouillon' => 'Brouillon', 'valide' => 'Validé', 'annule' => 'Annulé'] as $val => $label)
                    <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'supplier_id']))
                    <a href="{{ route('achats.receptions.index') }}"
                       class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] px-2.5 py-1.5 rounded-[4px] transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide w-36">N° réception</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide">Fournisseur</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden md:table-cell w-32">BC lié</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell w-32">Date réception</th>
                        <th class="text-center font-bold px-3 py-2 uppercase tracking-wide w-28">Statut</th>
                        <th class="text-right font-bold px-3 py-2 uppercase tracking-wide w-24">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receptions as $reception)
                        @php
                            [$badgeClass, $badgeLabel] = match($reception->status) {
                                'valide'  => ['bg-emerald-100 text-emerald-700', 'Validé'],
                                'annule'  => ['bg-red-100 text-red-700', 'Annulé'],
                                default   => ['bg-amber-100 text-amber-700', 'Brouillon'],
                            };
                        @endphp
                        <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                            <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                                <a href="{{ route('achats.receptions.show', $reception) }}" class="hover:underline font-semibold">{{ $reception->number }}</a>
                            </td>
                            <td class="px-3 py-1.5 font-medium text-gray-900">{{ $reception->supplier?->name ?? '—' }}</td>
                            <td class="px-3 py-1.5 hidden md:table-cell whitespace-nowrap">
                                @if($reception->purchaseOrder)
                                <a href="{{ route('achats.commandes.show', $reception->purchaseOrder) }}" class="font-mono text-emerald-700 hover:underline text-[12px]">{{ $reception->purchaseOrder->number }}</a>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-gray-500 hidden lg:table-cell whitespace-nowrap">{{ $reception->received_at?->format('d/m/Y') }}</td>
                            <td class="px-3 py-1.5 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $badgeClass }}">{{ $badgeLabel }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                <a href="{{ route('achats.receptions.show', $reception) }}"
                                   class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Voir →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-gray-400">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                                    </svg>
                                    <p class="text-[13px] font-medium text-gray-500">Aucune réception trouvée</p>
                                    <p class="text-[12px] text-gray-400">Créez une réception depuis un bon de commande fournisseur.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            <span>{{ $receptions->total() }} réception(s)</span>
            @if($receptions->hasPages())<div>{{ $receptions->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
