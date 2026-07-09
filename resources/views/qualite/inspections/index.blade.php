@extends('layouts.erp')
@section('title', 'Contrôles qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contrôles qualité</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Contrôles qualité</h1>
            <p class="text-[12px] text-gray-500">Réception · en-cours · produit fini</p>
        </div>
        @can('production.update')
        <a href="{{ route('qualite.inspections.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau contrôle
        </a>
        @endcan
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach([
            ['label' => 'Contrôles',     'value' => number_format($stats['total'], 0, ',', ' '),        'color' => 'text-gray-900',  'bg' => 'bg-[#eef5f0]', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['label' => 'Non conformes', 'value' => number_format($stats['non_conforme'], 0, ',', ' '), 'color' => $stats['non_conforme'] > 0 ? 'text-red-600' : 'text-gray-900', 'bg' => 'bg-red-50', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'Qté rejetée',   'value' => number_format($stats['rejected'], 0, ',', ' '),     'color' => 'text-gray-900',  'bg' => 'bg-amber-50',  'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ str_contains($kpi['color'], 'red') ? 'text-red-500' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
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
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Type contrôle</label>
                <div class="relative"><select name="type" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['reception'=>'Réception','en_cours'=>'En cours','produit_fini'=>'Produit fini'] as $k=>$v)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Résultat</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['conforme'=>'Conforme','non_conforme'=>'Non conforme','partiel'=>'Partiel'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['type','status']))
                <a href="{{ route('qualite.inspections.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
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
                        <th class="{{ $th }} text-left">Réf.</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-left">Source</th>
                        <th class="{{ $th }} text-right">Contrôlé</th>
                        <th class="{{ $th }} text-right">Rejeté</th>
                        <th class="{{ $th }} text-center">Résultat</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Date</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inspections as $i)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $i->reference }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $i->typeLabel() }}</td>
                        <td class="px-3 py-1.5 text-gray-600 text-[12px] max-w-[220px] truncate">{{ $i->reception?->number ?? $i->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($i->quantity_checked,0,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $i->quantity_rejected>0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ number_format($i->quantity_rejected,0,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $sc = match($i->status){ 'conforme'=>'bg-emerald-100 text-emerald-700','partiel'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $i->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden md:table-cell whitespace-nowrap">{{ optional($i->inspected_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('qualite.inspections.edit', $i) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @if($i->status !== 'conforme')<a href="{{ route('qualite.non-conformities.create', ['quality_inspection_id' => $i->id]) }}" class="text-red-600 hover:underline text-[12px] font-semibold ml-2">+ NC</a>@endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun contrôle.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $inspections->total() }} contrôle(s) — {{ $stats['non_conforme'] }} non conforme(s) — {{ number_format($stats['rejected'],0,',',' ') }} rejeté(s)</span>
            @if($inspections->hasPages())<div>{{ $inspections->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
