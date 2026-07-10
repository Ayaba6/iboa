@extends('layouts.erp')
@section('title', 'Ordres de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ordres de fabrication</span>
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
            <h1 class="text-[17px] font-bold text-gray-900">Ordres de fabrication</h1>
            <p class="text-[12px] text-gray-500">Lancement, suivi &amp; clôture de la production tôle bac</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.orders.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvel OF
        </a>
        @endcan
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Brouillons',      'value' => number_format($stats['brouillon'], 0, ',', ' '),         'color' => 'text-gray-900',    'bg' => 'bg-gray-100',   'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            ['label' => 'En production',   'value' => number_format($stats['en_cours'], 0, ',', ' '),          'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
            ['label' => 'Terminés',        'value' => number_format($stats['termine'], 0, ',', ' '),           'color' => 'text-gray-900',    'bg' => 'bg-blue-50',    'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Mètres produits', 'value' => number_format($stats['metres'], 0, ',', ' ') . ' m',     'color' => 'text-gray-900',    'bg' => 'bg-amber-50',   'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ $kpi['color'] === 'text-emerald-700' ? 'text-emerald-600' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold {{ $kpi['color'] }} tabular-nums leading-tight">{{ $kpi['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">N° OF</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Ex. : OF-2026-0109" class="{{ $inp }} font-mono">
            </div>
            <div>
                <label class="{{ $lbl }}">Client</label>
                <div class="relative"><select name="client_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->trade_name ?? $c->name }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['brouillon'=>'Brouillon','lance'=>'Lancé','en_cours'=>'En cours','termine'=>'Terminé','annule'=>'Annulé'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['q','client_id','status']))
                <a href="{{ route('production.orders.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
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
                        <th class="{{ $th }} text-left">N° OF</th>
                        <th class="{{ $th }} text-left">Client</th>
                        <th class="{{ $th }} text-left">Produit</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Type / Ép.</th>
                        <th class="{{ $th }} text-right">Qté</th>
                        <th class="{{ $th }} text-left hidden xl:table-cell">Ligne</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $o->status === 'annule' ? 'opacity-50' : '' }}">
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <a href="{{ route('production.orders.show', $o) }}" class="font-mono text-emerald-800 hover:underline">{{ $o->number }}</a>
                        </td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $o->client?->trade_name ?? $o->client?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 max-w-[220px] truncate">{{ $o->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden lg:table-cell whitespace-nowrap">{{ $o->sheet_type ?? '—' }}{{ $o->thickness ? ' · '.rtrim(rtrim(number_format($o->thickness,2,',',''),'0'),',').' mm' : '' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($o->quantity_requested, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-[12px] hidden xl:table-cell">{{ $o->productionLine?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php [$sl, $sc] = match($o->status){
                                'brouillon' => [$o->statusLabel(), 'bg-gray-100 text-gray-600'],
                                'lance'     => [$o->statusLabel(), 'bg-blue-100 text-blue-700'],
                                'en_cours'  => [$o->statusLabel(), 'bg-emerald-100 text-emerald-700'],
                                'termine'   => [$o->statusLabel(), 'bg-gray-200 text-gray-700'],
                                'annule'    => [$o->statusLabel(), 'bg-red-100 text-red-700'],
                                default     => [$o->statusLabel(), 'bg-amber-100 text-amber-700'],
                            }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            <a href="{{ route('production.orders.show', $o) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Ouvrir</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun ordre de fabrication.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $orders->total() }} ordre(s) de fabrication — {{ $stats['en_cours'] }} en production — {{ number_format($stats['metres'], 0, ',', ' ') }} m produits</span>
            @if($orders->hasPages())<div>{{ $orders->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
