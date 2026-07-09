@extends('layouts.erp')
@section('title', 'Entrepôts & Dépôts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Entrepôts</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Entrepôts &amp; Dépôts</h1>
            <p class="text-[11.5px] text-gray-400">{{ $warehouses->total() }} entrepôt(s) configuré(s)</p>
        </div>
        @can('stocks.adjust')
        <a href="{{ route('stocks.warehouses.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors self-start">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvel entrepôt
        </a>
        @endcan
    </div>

    {{-- Search --}}
    <form method="GET" class="flex gap-1.5">
        <div class="relative flex-1 max-w-xs">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Nom, code, ville…"
                   class="w-full h-8 pl-8 pr-2 border border-gray-300 rounded-[4px] text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Chercher</button>
        @if($search)
        <a href="{{ route('stocks.warehouses.index') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">✕</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] font-bold text-emerald-900 uppercase tracking-wide">
                    <tr>
                        <th class="px-3 py-1.5 text-left w-28">Code</th>
                        <th class="px-3 py-1.5 text-left">Intitulé</th>
                        <th class="px-3 py-1.5 text-left hidden lg:table-cell">Ville</th>
                        <th class="px-3 py-1.5 text-left hidden xl:table-cell">Responsable</th>
                        <th class="px-3 py-1.5 text-right hidden md:table-cell">Articles</th>
                        <th class="px-3 py-1.5 text-right hidden md:table-cell">Mouvements</th>
                        <th class="px-3 py-1.5 text-center">Statut</th>
                        <th class="px-3 py-1.5 w-px"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($warehouses as $wh)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <span class="font-mono text-[11px] font-semibold text-emerald-700">{{ $wh->code }}</span>
                        </td>
                        <td class="px-3 py-1">
                            <a href="{{ route('stocks.warehouses.show', $wh) }}" class="font-medium text-gray-900 hover:text-emerald-700">{{ $wh->name }}</a>
                            @if($wh->address)
                            <span class="text-[10.5px] text-gray-400 hidden lg:inline">· {{ $wh->address }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden lg:table-cell">{{ $wh->city ?? '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden xl:table-cell">
                            {{ $wh->manager_name ?? '—' }}
                            @if($wh->phone)<span class="text-[10.5px] text-gray-400">· {{ $wh->phone }}</span>@endif
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 hidden md:table-cell">{{ number_format($wh->product_stocks_count) }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-500 hidden md:table-cell">{{ number_format($wh->stock_movements_count) }}</td>
                        <td class="px-3 py-1 text-center whitespace-nowrap">
                            @if($wh->is_default)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-emerald-100 text-emerald-700">Défaut</span>
                            @endif
                            @if(!$wh->is_active)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                            @elseif(!$wh->is_default)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-green-50 text-green-700">Actif</span>
                            @endif
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                <a href="{{ route('stocks.warehouses.show', $wh) }}" class="text-[11px] font-semibold text-emerald-700 hover:underline">Stock</a>
                                @can('stocks.adjust')
                                <a href="{{ route('stocks.warehouses.edit', $wh) }}" class="text-[11px] font-semibold text-gray-500 hover:text-gray-800">Modifier</a>
                                @if(!$wh->is_default)
                                <form action="{{ route('stocks.warehouses.destroy', $wh) }}" method="POST" onsubmit="return confirm('Supprimer cet entrepôt ?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[11px] font-semibold text-red-500 hover:text-red-700">Supprimer</button>
                                </form>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="text-[12.5px]">Aucun entrepôt trouvé</p>
                            @can('stocks.adjust')
                            <a href="{{ route('stocks.warehouses.create') }}" class="mt-1 inline-block text-[12px] text-emerald-600 hover:underline">Créer le premier entrepôt →</a>
                            @endcan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($warehouses->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">{{ $warehouses->appends(['search' => $search])->links() }}</div>
        @endif
    </div>

</div>
@endsection
