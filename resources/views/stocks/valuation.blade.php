@extends('layouts.erp')
@section('title', 'Valorisation du stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Valorisation</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Valorisation du stock</h1>
        <p class="text-[11px] text-gray-400">Calculé au {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
        <div class="bg-white rounded-[4px] border border-emerald-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur totale du stock</p>
            <p class="mt-0.5 text-[19px] font-bold text-emerald-700 tabular-nums leading-none">{{ number_format($totalValue, 0, ',', ' ') }} <span class="text-[11px] text-emerald-500">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Références valorisées</p>
            <p class="mt-0.5 text-[19px] font-bold text-gray-900 tabular-nums leading-none">{{ $stocks->count() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Entrepôts</p>
            <p class="mt-0.5 text-[19px] font-bold text-gray-900 tabular-nums leading-none">{{ $byWarehouse->count() }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="flex flex-wrap gap-1.5 items-end">
            <select name="warehouse_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous entrepôts</option>
                @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <select name="family_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Toutes familles</option>
                @foreach($families as $fam)
                <option value="{{ $fam->id }}" {{ $familyId == $fam->id ? 'selected' : '' }}>{{ $fam->name }}</option>
                @endforeach
            </select>
            <select name="method" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Toutes méthodes</option>
                <option value="cmp"  {{ $method === 'cmp'  ? 'selected' : '' }}>CMP (Coût moyen pondéré)</option>
                <option value="fifo" {{ $method === 'fifo' ? 'selected' : '' }}>FIFO</option>
                <option value="lifo" {{ $method === 'lifo' ? 'selected' : '' }}>LIFO</option>
            </select>
            <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px]">Filtrer</button>
            @if($warehouseId || $familyId || $method)
            <a href="{{ route('stocks.valuation') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">✕</a>
            @endif
        </div>
    </form>

    {{-- By warehouse --}}
    @foreach($byWarehouse as $warehouseIdKey => $warehouseStocks)
    @php
        $wh = $warehouseStocks->first()->warehouse;
        $whTotal = $warehouseStocks->sum(fn($s) => (float)$s->quantity * (float)$s->avg_cost);
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-300 bg-[#eef5f0]">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">{{ $wh?->name ?? 'Entrepôt #' . $warehouseIdKey }}</h2>
                <span class="text-[10.5px] text-gray-400">{{ $warehouseStocks->count() }} article(s)</span>
            </div>
            <span class="text-[13px] font-bold text-emerald-700 tabular-nums">{{ number_format($whTotal, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] font-bold text-emerald-900 uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Référence</th>
                        <th class="px-3 py-1.5 text-left">Produit</th>
                        <th class="px-3 py-1.5 text-left">Famille</th>
                        <th class="px-3 py-1.5 text-left">Méthode</th>
                        <th class="px-3 py-1.5 text-right">Quantité</th>
                        <th class="px-3 py-1.5 text-right">Coût unitaire</th>
                        <th class="px-3 py-1.5 text-right">Valeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($warehouseStocks as $stock)
                    @php
                        $value = (float)$stock->quantity * (float)$stock->avg_cost;
                        $pct   = $whTotal > 0 ? round($value / $whTotal * 100, 1) : 0;
                        $methodLabel = match($stock->product?->valuation_method) {
                            'fifo' => 'FIFO',
                            'lifo' => 'LIFO',
                            default => 'CMP',
                        };
                    @endphp
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <span class="font-mono text-[11px] text-gray-500">{{ $stock->product?->reference ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1 font-medium text-gray-900">
                            <a href="{{ route('stocks.show', $stock->product_id) }}" class="hover:text-emerald-700 hover:underline">
                                {{ $stock->product?->name }}
                            </a>
                        </td>
                        <td class="px-3 py-1 text-gray-500 text-[11px]">{{ $stock->product?->family?->name ?? '—' }}</td>
                        <td class="px-3 py-1">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-blue-50 text-blue-700">{{ $methodLabel }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700">
                            {{ number_format((float)$stock->quantity, 2, ',', ' ') }}
                            <span class="text-[10.5px] text-gray-400">{{ $stock->product?->unit?->abbreviation }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-600">
                            {{ $stock->avg_cost ? number_format((float)$stock->avg_cost, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-3 py-1 text-right">
                            <span class="tabular-nums font-semibold text-gray-900">{{ number_format($value, 0, ',', ' ') }}</span>
                            @if($pct > 0)
                            <span class="ml-1 text-[10.5px] text-gray-400">({{ $pct }}%)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-300 bg-[#f7faf8]">
                    <tr>
                        <td colspan="6" class="px-3 py-1.5 text-right text-[10px] font-bold text-gray-600 uppercase">Sous-total</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-bold text-emerald-700">{{ number_format($whTotal, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endforeach

    @if($stocks->isEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 px-4 py-12 text-center text-[12.5px] text-gray-400">
        Aucun stock valorisé pour les filtres sélectionnés.
    </div>
    @else
    {{-- Grand total --}}
    <div class="flex justify-end">
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] px-3 py-1.5 text-right">
            <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide">Valeur totale du stock</p>
            <p class="mt-0.5 text-[20px] font-bold text-emerald-800 tabular-nums leading-none">{{ number_format($totalValue, 0, ',', ' ') }} <span class="text-[12px] text-emerald-500">FCFA</span></p>
        </div>
    </div>
    @endif

</div>
@endsection
