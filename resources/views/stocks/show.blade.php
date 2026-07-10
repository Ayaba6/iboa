@extends('layouts.erp')
@section('title', $product->name . ' — Stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $product->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('stocks.index') }}"
               class="w-8 h-8 rounded-[4px] border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="font-mono text-xs font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded">{{ $product->reference }}</span>
                    @if($product->family)
                    <span class="text-xs text-gray-500">{{ $product->family->name }}</span>
                    @endif
                </div>
                <h1 class="text-[16px] font-bold text-gray-900">{{ $product->name }}</h1>
                @if($product->brand)
                <p class="text-sm text-gray-500 mt-0.5">{{ $product->brand->name }}</p>
                @endif
            </div>
        </div>
        <div class="flex gap-2 self-start flex-wrap">
            <a href="{{ route('products.show', $product) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Fiche produit
            </a>
            @can('stocks.adjust')
            <a href="{{ route('stocks.seuils', ['search' => $product->reference]) }}"
               class="border border-orange-300 text-orange-700 hover:bg-orange-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                Seuils
            </a>
            <a href="{{ route('stocks.movement.create', ['product_id' => $product->id, 'type' => 'entree']) }}"
               class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Entrée
            </a>
            <a href="{{ route('stocks.movement.create', ['product_id' => $product->id, 'type' => 'sortie']) }}"
               class="border border-red-400 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                </svg>
                Sortie
            </a>
            <a href="{{ route('stocks.movement.create', ['product_id' => $product->id, 'type' => 'ajustement']) }}"
               class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Ajustement
            </a>
            @endcan
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Stock physique total</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ number_format($totalQty, 2, ',', ' ') }}</p>
            @if($product->unit)
            <p class="text-xs text-gray-400 mt-0.5">{{ $product->unit->abbreviation ?? $product->unit->name }}</p>
            @endif
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Disponible</p>
            <p class="text-[16px] font-bold tabular-nums {{ $totalAvailable <= 0 ? 'text-red-600' : ($totalAvailable <= ($product->stock_min ?? 0) && ($product->stock_min ?? 0) > 0 ? 'text-orange-600' : 'text-emerald-600') }}">
                {{ number_format($totalAvailable, 2, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">Réservé : {{ number_format($totalReserved, 2, ',', ' ') }}</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Seuils de stock</p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-base font-bold tabular-nums {{ $totalAvailable > 0 && $product->stock_min && $totalAvailable <= $product->stock_min ? 'text-orange-600' : 'text-gray-900' }}">
                    Min : {{ $product->stock_min ? number_format($product->stock_min, 0, ',', ' ') : '—' }}
                </span>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-base font-bold tabular-nums text-gray-700">
                    Max : {{ $product->stock_max ? number_format($product->stock_max, 0, ',', ' ') : '—' }}
                </span>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Réappro : {{ $product->reorder_point ? number_format($product->reorder_point, 0, ',', ' ') : '—' }}
                · Méthode : {{ strtoupper($product->valuation_method ?? 'cmp') }}
            </p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Coût moyen pondéré</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">
                {{ $avgCost ? number_format($avgCost, 0, ',', ' ') . ' FCFA' : '—' }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                Valeur totale : {{ $avgCost && $totalQty ? number_format($avgCost * $totalQty, 0, ',', ' ') . ' FCFA' : '—' }}
            </p>
        </div>
    </div>

    {{-- Stock by warehouse --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Répartition par entrepôt</h2>
        </div>

        @if($stocks->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-400">Aucun stock enregistré pour ce produit.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Entrepôt</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Physique</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réservé</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Disponible</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Coût moyen</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Valeur stock</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">Dernier mvt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stocks as $stock)
                    @php
                        $avail = (float)$stock->quantity - (float)$stock->reserved_quantity;
                        $threshold = (float)($product->stock_min ?? 0);
                        if ($avail <= 0) {
                            $qtyClass = 'text-red-600 font-semibold';
                        } elseif ($threshold > 0 && $avail <= $threshold) {
                            $qtyClass = 'text-orange-600 font-semibold';
                        } else {
                            $qtyClass = 'text-gray-900';
                        }
                        $valeur = (float)$stock->avg_cost * (float)$stock->quantity;
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-md bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $stock->warehouse?->name ?? '—' }}</p>
                                    <p class="text-xs font-mono text-gray-400">{{ $stock->warehouse?->code ?? '' }}</p>
                                </div>
                                @if($stock->warehouse?->is_default)
                                <span class="text-xs text-emerald-700 bg-[#eef5f0] px-1.5 py-0.5 rounded">Défaut</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">
                            {{ number_format((float)$stock->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">
                            {{ number_format((float)$stock->reserved_quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $qtyClass }}">
                            {{ number_format($avail, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">
                            {{ $stock->avg_cost ? number_format((float)$stock->avg_cost, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">
                            {{ $valeur > 0 ? number_format($valeur, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs hidden xl:table-cell">
                            {{ $stock->last_movement_at ? \Carbon\Carbon::parse($stock->last_movement_at)->diffForHumans() : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                @if($stocks->count() > 1)
                <tfoot class="bg-gray-50">
                    <tr>
                        <td class="px-3 py-1.5 font-semibold text-gray-700">Total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($totalQty, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-500">{{ number_format($totalReserved, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $totalAvailable <= 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($totalAvailable, 2, ',', ' ') }}</td>
                        <td class="hidden lg:table-cell px-3 py-1.5"></td>
                        <td class="hidden lg:table-cell px-3 py-1.5 text-right tabular-nums font-semibold text-gray-700">
                            {{ $avgCost && $totalQty ? number_format($avgCost * $totalQty, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="hidden xl:table-cell"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @endif
    </div>

    {{-- Movement history --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Historique des mouvements</h2>
            <span class="text-sm text-gray-500">{{ $movements->total() }} mouvement(s)</span>
        </div>
        @if($movements->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-400">Aucun mouvement enregistré pour ce produit.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Entrepôt</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Coût unit.</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Coût après</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">Réf. doc.</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">Par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($movements as $mv)
                    @php
                        $typeColors = [
                            'entree'             => 'bg-emerald-100 text-emerald-700',
                            'sortie'             => 'bg-red-100 text-red-700',
                            'transfert'          => 'bg-blue-100 text-blue-700',
                            'ajustement'         => 'bg-orange-100 text-orange-700',
                            'retour_client'      => 'bg-emerald-100 text-emerald-800',
                            'retour_fournisseur' => 'bg-purple-100 text-purple-700',
                        ];
                        $typeLabels = [
                            'entree'             => 'Entrée',
                            'sortie'             => 'Sortie',
                            'transfert'          => 'Transfert',
                            'ajustement'         => 'Ajustement',
                            'retour_client'      => 'Retour client',
                            'retour_fournisseur' => 'Retour fourn.',
                        ];
                        $mvColor = $typeColors[$mv->type] ?? 'bg-gray-100 text-gray-600';
                        $mvLabel = $typeLabels[$mv->type] ?? $mv->type;
                        // [FIX affichage] ajustement/inventaire portent une quantité SIGNÉE :
                        // le sens vient du signe, pas du type (un ajustement -3 est une sortie).
                        $isIn = in_array($mv->type, ['ajustement', 'inventaire'])
                            ? (float) $mv->quantity >= 0
                            : in_array($mv->type, ['entree', 'retour_client', 'retour_fournisseur']);
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap text-xs">
                            {{ \Carbon\Carbon::parse($mv->occurred_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $mvColor }}">
                                {{ $mvLabel }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell">{{ $mv->warehouse?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $isIn ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $isIn ? '+' : '-' }}{{ number_format(abs((float)$mv->quantity), 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">
                            {{ $mv->unit_cost ? number_format((float)$mv->unit_cost, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 hidden lg:table-cell">
                            {{ $mv->avg_cost_after ? number_format((float)$mv->avg_cost_after, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs hidden xl:table-cell">
                            @if($mv->reference_type && $mv->reference_id)
                            <span class="font-mono">{{ class_basename($mv->reference_type) }} #{{ $mv->reference_id }}</span>
                            @else
                            —
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs hidden xl:table-cell">{{ $mv->createdBy?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $movements->links() }}</div>
        @endif
        @endif
    </div>

</div>
@endsection
