@extends('layouts.erp')
@section('title', 'Nomenclatures (BOM)')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nomenclatures</span>
@endsection

@section('content')
@php
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Nomenclatures de fabrication</h1>
            <p class="text-[12px] text-gray-500">Recettes tôle bac : consommation/m, taux de chute, temps machine</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.bom.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle nomenclature
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 items-end">
            <div class="col-span-2">
                <label class="{{ $lbl }}">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, type tôle, produit fini…" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="active" class="{{ $lk }}">
                    <option value="">Tous</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['q','active']))
                <a href="{{ route('production.bom.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
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
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Produit fini</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Type tôle</th>
                        <th class="{{ $th }} text-left hidden xl:table-cell">Version</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Conso/m</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Chute std.</th>
                        <th class="{{ $th }} text-right">Composants</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $b)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $b->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5">
                            <a href="{{ route('production.bom.show', $b) }}" class="font-medium text-gray-900 hover:text-emerald-800 hover:underline">{{ $b->name }}</a>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 max-w-[220px] truncate">{{ $b->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell">{{ $b->sheet_type ?? '—' }}</td>
                        <td class="px-3 py-1.5 font-mono text-[12px] text-gray-500 hidden xl:table-cell">{{ $b->version_majeure ?? '—' }}.{{ $b->version_mineure ?? '0' }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 hidden lg:table-cell">{{ number_format($b->consumption_per_meter,4,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700 hidden lg:table-cell">{{ number_format($b->standard_waste_rate,2,',',' ') }} %</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ $b->lines_count }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $b->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $b->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.bom.edit', $b) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.bom.destroy', $b) }}" class="inline ml-2" data-confirm="Supprimer cette nomenclature ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune nomenclature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $boms->total() }} nomenclature(s)</span>
            @if($boms->hasPages())<div>{{ $boms->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
