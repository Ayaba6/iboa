@extends('layouts.erp')
@section('title', 'Plans de maintenance préventive')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.maintenance.index') }}" class="hover:text-gray-700">Maintenance</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Plans préventifs</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Plans de maintenance préventive</h1>
            <p class="text-[12px] text-gray-500">§13.8 CDC — Périodicité par machine, génération automatique des interventions</p>
        </div>
        <div class="flex gap-2">
            @can('production.update')
            @if($due->isNotEmpty())
            <form method="POST" action="{{ route('production.maintenance-plans.generate') }}">
                @csrf
                <button class="bg-amber-500 hover:bg-amber-600 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] transition-colors">
                    Générer les {{ $due->count() }} intervention(s) due(s)
                </button>
            </form>
            @endif
            <a href="{{ route('production.maintenance-plans.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouveau plan
            </a>
            @endcan
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Plan</th>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-right">Fréquence</th>
                        <th class="{{ $th }} text-left">Prochaine échéance</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $plan->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $plan->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $plan->machine?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $plan->frequency_days }} j</td>
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $plan->next_due_at?->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if(!$plan->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                            @elseif($plan->isDue())
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700">Due</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">Planifié</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.maintenance-plans.edit', $plan) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            <form method="POST" action="{{ route('production.maintenance-plans.destroy', $plan) }}" data-confirm="Supprimer ce plan ?" class="inline ml-2">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun plan de maintenance préventive.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $plans->total() }} plan(s) — {{ $due->count() }} due(s)</span>
            @if($plans->hasPages())<div>{{ $plans->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
