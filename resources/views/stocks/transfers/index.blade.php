@extends('layouts.erp')
@section('title', 'Transferts inter-dépôts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Transferts</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Transferts inter-dépôts</h1>
            <p class="text-[11.5px] text-gray-400">{{ $transfers->total() }} transfert(s) — workflow 2 étapes : expédition source → réception destination</p>
        </div>
        @can('stocks.adjust')
        <a href="{{ route('stocks.transfers.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors self-start">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau transfert
        </a>
        @endcan
    </div>

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total transferts</p>
            <p class="mt-0.5 text-[17px] font-bold text-gray-900 tabular-nums leading-none">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Brouillons</p>
            <p class="mt-0.5 text-[17px] font-bold text-gray-500 tabular-nums leading-none">{{ $summary['brouillon'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-amber-300 px-3 py-2">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wide">En transit</p>
            <p class="mt-0.5 text-[17px] font-bold text-amber-600 tabular-nums leading-none">{{ $summary['en_transit'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-emerald-300 px-3 py-2">
            <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide">Reçus</p>
            <p class="mt-0.5 text-[17px] font-bold text-emerald-600 tabular-nums leading-none">{{ $summary['recu'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-1.5">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="N° transfert…"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

            <select name="status" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                @foreach(['brouillon' => 'Brouillon', 'en_transit' => 'En transit', 'recu' => 'Reçu', 'annule' => 'Annulé'] as $k => $v)
                <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>

            <select name="from_warehouse_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Dépôt source</option>
                @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ request('from_warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                @endforeach
            </select>

            <select name="to_warehouse_id" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Dépôt destination</option>
                @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ request('to_warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                @endforeach
            </select>

            <div class="flex gap-1.5">
                <button type="submit"
                        class="flex-1 h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'from_warehouse_id', 'to_warehouse_id']))
                <a href="{{ route('stocks.transfers.index') }}"
                   class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Date</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">De → Vers</th>
                        <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Articles</th>
                        <th class="px-3 py-1.5 text-center text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">Créé par</th>
                        <th class="px-3 py-1.5 w-px"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transfers as $t)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <a href="{{ route('stocks.transfers.show', $t) }}"
                               class="font-mono font-semibold text-blue-600 hover:text-blue-800">
                                {{ $t->number }}
                            </a>
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">
                            {{ $t->transfer_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-1">
                            <span class="text-gray-900 font-medium">{{ $t->fromWarehouse?->name ?? '—' }}</span>
                            <span class="text-gray-400 mx-1">→</span>
                            <span class="text-gray-900 font-medium">{{ $t->toWarehouse?->name ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-600 hidden lg:table-cell">
                            {{ $t->items_count }}
                        </td>
                        <td class="px-3 py-1 text-center">
                            @php
                                $statusStyles = [
                                    'brouillon'  => 'bg-gray-100 text-gray-600 border-gray-200',
                                    'en_transit' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'recu'       => 'bg-green-50 text-green-700 border-green-100',
                                    'annule'     => 'bg-red-50 text-red-700 border-red-100',
                                ];
                                $statusDots = [
                                    'brouillon'  => 'bg-gray-400',
                                    'en_transit' => 'bg-amber-500',
                                    'recu'       => 'bg-green-500',
                                    'annule'     => 'bg-red-500',
                                ];
                            @endphp
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium border {{ $statusStyles[$t->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusDots[$t->status] ?? 'bg-gray-400' }}"></span>
                                {{ $t->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-1 text-[11px] text-gray-500 hidden xl:table-cell">
                            {{ $t->createdBy?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('stocks.transfers.show', $t) }}"
                                   class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Voir">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @can('stocks.adjust')
                                @if($t->canEdit())
                                <a href="{{ route('stocks.transfers.edit', $t) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif
                                @if($t->canShip())
                                <form method="POST" action="{{ route('stocks.transfers.ship', $t) }}"
                                      onsubmit="return confirm('Expédier le transfert {{ addslashes($t->number) }} ? Le stock du dépôt source sera décrémenté.')">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Expédier">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                @if($t->canReceive())
                                <a href="{{ route('stocks.transfers.show', $t) }}"
                                   class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Réceptionner">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </a>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <div>
                                    <p class="text-[12.5px] font-medium text-gray-600">Aucun transfert trouvé</p>
                                    @if(request()->hasAny(['search', 'status', 'from_warehouse_id', 'to_warehouse_id']))
                                        <a href="{{ route('stocks.transfers.index') }}" class="text-[12px] text-blue-600 hover:text-blue-700 mt-1 inline-block">Effacer les filtres</a>
                                    @else
                                        @can('stocks.adjust')
                                        <a href="{{ route('stocks.transfers.create') }}" class="text-[12px] text-blue-600 hover:text-blue-700 mt-1 inline-block">Créer le premier transfert</a>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
