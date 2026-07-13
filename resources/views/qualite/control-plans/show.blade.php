@extends('layouts.erp')
@section('title', 'Plan — '.$plan->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.control-plans.index') }}" class="hover:text-gray-700">Plans de contrôle</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $plan->name }}</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ' '), '0'), ',');
@endphp
<div class="space-y-4">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">{{ $plan->name }}</h1>
            <p class="text-[12px] text-gray-500">
                {{ $plan->reference ?? '#'.$plan->id }} · {{ $plan->stageLabel() }}
                · {{ $plan->product?->name ?? $plan->family?->name ?? 'Tous articles' }}
                · {{ $plan->is_active ? 'Actif' : 'Inactif' }}
            </p>
        </div>
        @can('production.update')
        <div class="flex items-center gap-2">
            <a href="{{ route('qualite.control-plans.edit', $plan) }}" class="border border-gray-300 text-gray-700 text-[13px] font-medium px-4 py-1.5 rounded-[4px] hover:bg-gray-50">Modifier</a>
            <form method="POST" action="{{ route('qualite.control-plans.destroy', $plan) }}" onsubmit="return confirm('Supprimer ce plan de contrôle ?');">@csrf @method('DELETE')
                <button class="border border-red-300 text-red-600 text-[13px] font-medium px-4 py-1.5 rounded-[4px] hover:bg-red-50">Supprimer</button>
            </form>
        </div>
        @endcan
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-[13px] rounded-[4px] px-4 py-2">{{ session('success') }}</div>@endif

    @if($plan->description)<div class="bg-white border border-gray-200 rounded-[4px] p-4 text-sm text-gray-700 whitespace-pre-line">{{ $plan->description }}</div>@endif

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="bg-[#eef5f0] text-emerald-900 px-4 py-2 text-[13px] font-semibold">Caractéristiques ({{ $plan->characteristics->count() }})</div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">#</th>
                        <th class="{{ $th }} text-left">Caractéristique</th>
                        <th class="{{ $th }} text-left">Méthode</th>
                        <th class="{{ $th }} text-left">Fréquence</th>
                        <th class="{{ $th }} text-left">Échantillon</th>
                        <th class="{{ $th }} text-right">Cible</th>
                        <th class="{{ $th }} text-right">Tolérance</th>
                        <th class="{{ $th }} text-center">Critique</th>
                        <th class="{{ $th }} text-left">Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plan->characteristics as $c)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40">
                        <td class="px-3 py-1.5 text-gray-400">{{ $c->sort_order }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $c->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $c->method ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $c->frequency ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $c->sampling ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $num($c->target_value) }}{{ $c->unit ? ' '.$c->unit : '' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">
                            @if($c->tolerance_min !== null || $c->tolerance_max !== null)[{{ $num($c->tolerance_min) ?: '−∞' }} ; {{ $num($c->tolerance_max) ?: '+∞' }}]@else—@endif
                        </td>
                        <td class="px-3 py-1.5 text-center">@if($c->is_critical)<span class="text-red-600 font-semibold text-[11px]">● Critique</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $c->responsible ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400 text-sm">Aucune caractéristique définie.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Plan : <span class="text-white font-semibold">{{ $plan->reference ?? '#'.$plan->id }}</span></span>
        <span class="border-l border-white/10 pl-6">Caract. : <span class="text-white font-semibold">{{ $plan->characteristics->count() }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
