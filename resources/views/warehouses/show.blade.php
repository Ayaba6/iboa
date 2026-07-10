@extends('layouts.erp')
@section('title', $warehouse->name . ' — Stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.index') }}" class="hover:text-gray-700">Entrepôts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $warehouse->name }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('stocks.warehouses.index') }}"
               class="w-8 h-8 rounded-[4px] border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="font-mono text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">{{ $warehouse->code }}</span>
                    @if($warehouse->is_default)
                    <span class="text-xs font-medium text-emerald-800 bg-[#eef5f0] px-2 py-0.5 rounded">Entrepôt par défaut</span>
                    @endif
                    <span class="text-xs font-medium {{ $warehouse->is_active ? 'text-emerald-700 bg-emerald-50' : 'text-gray-500 bg-gray-100' }} px-2 py-0.5 rounded">
                        {{ $warehouse->is_active ? 'Actif' : 'Inactif' }}
                    </span>
                </div>
                <h1 class="text-[16px] font-bold text-gray-900">{{ $warehouse->name }}</h1>
                @if($warehouse->city || $warehouse->address)
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ collect([$warehouse->city, $warehouse->address])->filter()->implode(' — ') }}
                </p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2 self-start">
            @can('stocks.view')
            <a href="{{ route('stocks.warehouses.locations.index', $warehouse) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Emplacements
            </a>
            @endcan
            @can('stocks.adjust')
            <a href="{{ route('stocks.warehouses.edit', $warehouse) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Modifier
            </a>
            @endcan
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Articles</p>
            <p class="text-[16px] font-bold text-gray-900">{{ number_format($warehouse->product_stocks_count) }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Mouvements</p>
            <p class="text-[16px] font-bold text-gray-900">{{ number_format($warehouse->stock_movements_count) }}</p>
        </div>
        @if($warehouse->manager_name)
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 col-span-2">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Responsable</p>
            <p class="text-base font-semibold text-gray-900">{{ $warehouse->manager_name }}</p>
            @if($warehouse->phone)
            <p class="text-xs text-gray-500 mt-0.5">{{ $warehouse->phone }}</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Stock table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Stock dans cet entrepôt</h2>
            <span class="text-sm text-gray-500">{{ $stocks->total() }} article(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Stock physique</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réservé</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Disponible</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Coût moyen</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stocks as $stock)
                    @php
                        $available  = (float)$stock->quantity - (float)$stock->reserved_quantity;
                        $threshold  = (float)($stock->product?->stock_min ?? 0);
                        if ($available <= 0) {
                            $badge = ['bg-red-100 text-red-700', 'Rupture'];
                            $qtyClass = 'text-red-600 font-semibold';
                        } elseif ($threshold > 0 && $available <= $threshold) {
                            $badge = ['bg-orange-100 text-orange-700', 'Stock bas'];
                            $qtyClass = 'text-orange-600 font-semibold';
                        } else {
                            $badge = ['bg-emerald-100 text-emerald-700', 'OK'];
                            $qtyClass = 'text-gray-900';
                        }
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5">
                            <span class="font-mono text-xs text-gray-500">{{ $stock->product?->reference ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1.5">
                            <a href="{{ route('stocks.show', $stock->product_id) }}"
                               class="font-medium text-gray-900 hover:text-emerald-700 hover:underline">
                                {{ $stock->product?->name ?? '—' }}
                            </a>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">
                            {{ number_format((float)$stock->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">
                            {{ number_format((float)$stock->reserved_quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $qtyClass }}">
                            {{ number_format($available, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 hidden lg:table-cell">
                            {{ $stock->avg_cost ? number_format((float)$stock->avg_cost, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $badge[0] }}">
                                {{ $badge[1] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucun article en stock dans cet entrepôt.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stocks->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">{{ $stocks->links() }}</div>
        @endif
    </div>

    {{-- Recent movements --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Derniers mouvements</h2>
            <a href="{{ route('stocks.movements', ['warehouse_id' => $warehouse->id]) }}"
               class="text-sm text-emerald-600 hover:text-emerald-800 hover:underline">
                Voir tout →
            </a>
        </div>
        @if($recentMovements->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-400">Aucun mouvement enregistré.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentMovements as $mv)
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
                        $isIn = in_array($mv->type, ['entree', 'retour_client', 'retour_fournisseur']);
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($mv->occurred_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="font-medium text-gray-900">{{ $mv->product?->name ?? '—' }}</span>
                            @if($mv->product?->reference)
                            <br><span class="font-mono text-xs text-gray-400">{{ $mv->product->reference }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $mvColor }}">
                                {{ $mvLabel }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $isIn ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $isIn ? '+' : '-' }}{{ number_format((float)$mv->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden md:table-cell">
                            {{ $mv->creator?->name ?? '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection
