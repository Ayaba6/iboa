@extends('layouts.erp')
@section('title', 'Plans de contrôle')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Plans de contrôle</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Plans de contrôle</h1>
            <p class="text-[12px] text-gray-500">Caractéristiques, méthodes, fréquences, échantillonnage et tolérances par article.</p>
        </div>
        @can('production.update')
        <a href="{{ route('qualite.control-plans.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px]">+ Nouveau plan</a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 items-end">
            <div class="col-span-2">
                <label class="{{ $lbl }}">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, référence…" class="w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px]">
            </div>
            <div>
                <label class="{{ $lbl }}">Étape</label>
                <div class="relative"><select name="stage" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach(\App\Modules\Quality\Models\ControlPlan::STAGES as $k=>$v)<option value="{{ $k }}" @selected(request('stage')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px]">Rechercher</button>
                @if(request()->hasAny(['q','stage','active']))<a href="{{ route('qualite.control-plans.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center">Réinitialiser</a>@endif
            </div>
        </div>
    </form>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Réf.</th>
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Article / Famille</th>
                        <th class="{{ $th }} text-left">Étape</th>
                        <th class="{{ $th }} text-center">Caract.</th>
                        <th class="{{ $th }} text-center">Actif</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $p)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $p->reference ?? '#'.$p->id }}</td>
                        <td class="px-3 py-1.5"><a href="{{ route('qualite.control-plans.show', $p) }}" class="text-blue-700 hover:underline">{{ $p->name }}</a></td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $p->product?->name ?? $p->family?->name ?? '— tous —' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $p->stageLabel() }}</td>
                        <td class="px-3 py-1.5 text-center tabular-nums">{{ $p->characteristics_count }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if($p->is_active)<span class="text-emerald-700 text-[11px] font-semibold">● Actif</span>@else<span class="text-gray-400 text-[11px]">○ Inactif</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('qualite.control-plans.edit', $p) }}" class="text-emerald-700 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun plan de contrôle.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $items->total() }} plan(s)</span>
            @if($items->hasPages())<div>{{ $items->links() }}</div>@endif
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Plans de contrôle</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
