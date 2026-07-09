@extends('layouts.erp')
@section('title', 'Seuils stock — min / max / réappro')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Seuils min / max</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Seuils de stock — édition en masse</h1>
            <p class="text-[12px] text-gray-500">Stock minimum, maximum et point de réapprovisionnement pour chaque article</p>
        </div>
        <a href="{{ route('stocks.dashboard.restock') }}"
           class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Alertes réappro
        </a>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Recherche</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Référence, désignation…" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Famille</label>
                <div class="relative"><select name="family_id" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach($families as $fam)
                    <option value="{{ $fam->id }}" {{ $familyId == $fam->id ? 'selected' : '' }}>{{ $fam->name }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="flex items-center h-8">
                <label class="inline-flex items-center gap-2 text-[13px] text-gray-700 cursor-pointer">
                    <input type="checkbox" name="alert_only" value="1" {{ !empty($alertOnly) ? 'checked' : '' }}
                           class="rounded border-[#c3d3c9] text-emerald-600 focus:ring-emerald-400">
                    Alertes réappro uniquement
                </label>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['search', 'family_id', 'alert_only']))
                <a href="{{ route('stocks.seuils') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Bandeau info --}}
    <div class="flex items-start gap-3 bg-[#fff8ec] border border-amber-200 rounded-[4px] px-3 py-2.5 text-[12.5px] text-gray-700">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <strong>Stock min</strong> — déclenche l'alerte visuelle (badge « Stock bas »).
            <strong class="ml-2">Point réappro</strong> — déclenche l'alerte sur la page Alertes réappro (peut être supérieur au min).
            <strong class="ml-2">Stock max</strong> — quantité cible lors du réapprovisionnement.
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('stocks.seuils.update') }}" method="POST">
        @csrf

        @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-[4px] px-3 py-2.5 text-[13px] mb-4">
            ✓ {{ session('success') }}
        </div>
        @endif

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[13px] font-bold text-gray-900">Seuils par article ({{ $products->total() }})</h2>
                @if($products->count() > 0)
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Enregistrer les seuils
                </button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] border-collapse">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="{{ $th }} text-left">Article</th>
                            <th class="{{ $th }} text-center hidden md:table-cell">Stock actuel</th>
                            <th class="{{ $th }} text-center">Stock min</th>
                            <th class="{{ $th }} text-center">Point réappro</th>
                            <th class="{{ $th }} text-center">Stock max</th>
                            <th class="{{ $th }} text-center hidden lg:table-cell">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        @php
                            $totalQty       = $product->productStocks->sum('quantity');
                            $totalReserved  = $product->productStocks->sum('reserved_quantity');
                            $available      = $totalQty - $totalReserved;
                            $min            = $product->stock_min;
                            $max            = $product->stock_max;
                            $reorder        = $product->reorder_point;

                            if ($available <= 0) {
                                $statusClass = 'bg-red-100 text-red-700';
                                $statusLabel = 'Rupture';
                            } elseif ($reorder && $available <= $reorder) {
                                $statusClass = 'bg-amber-100 text-amber-700';
                                $statusLabel = 'Réappro';
                            } elseif ($min && $available <= $min) {
                                $statusClass = 'bg-orange-100 text-orange-700';
                                $statusLabel = 'Stock bas';
                            } else {
                                $statusClass = 'bg-emerald-100 text-emerald-700';
                                $statusLabel = 'OK';
                            }
                        @endphp
                        <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                            <td class="px-3 py-1.5">
                                <a href="{{ route('stocks.show', $product) }}" class="font-mono text-[12px] text-emerald-800 hover:underline">{{ $product->reference }}</a>
                                <p class="text-gray-900 font-medium">{{ $product->name }}</p>
                                <p class="text-[11px] text-gray-400">
                                    {{ $product->family?->name ?? '' }}
                                    @if($product->unit) · {{ $product->unit->abbreviation ?? $product->unit->name }} @endif
                                </p>
                            </td>
                            <td class="px-3 py-1.5 text-center tabular-nums hidden md:table-cell">
                                <span class="font-semibold {{ $available <= 0 ? 'text-red-600' : ($min && $available <= $min ? 'text-amber-600' : 'text-gray-800') }}">
                                    {{ number_format($available, 0, ',', ' ') }}
                                </span>
                                @if($totalReserved > 0)
                                <p class="text-[11px] text-gray-400">{{ number_format($totalReserved, 0, ',', ' ') }} rés.</p>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][stock_min]"
                                       value="{{ $min !== null ? (int) $min : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 h-8 text-center border border-[#c3d3c9] rounded-[3px] text-[13px] font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][reorder_point]"
                                       value="{{ $reorder !== null ? (int) $reorder : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 h-8 text-center border border-[#c3d3c9] rounded-[3px] text-[13px] font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][stock_max]"
                                       value="{{ $max !== null ? (int) $max : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 h-8 text-center border border-[#c3d3c9] rounded-[3px] text-[13px] font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
                            </td>
                            <td class="px-3 py-1.5 text-center hidden lg:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun article stockable trouvé.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
                <span>{{ $products->total() }} article(s) — les champs vides seront remis à zéro (aucune alerte)</span>
                @if($products->hasPages())<div>{{ $products->appends(request()->query())->links() }}</div>@endif
            </div>
        </div>
    </form>

</div>
@endsection
