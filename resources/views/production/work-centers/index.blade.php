@extends('layouts.erp')
@section('title', 'Centres de travail')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Centres de travail</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Centres de travail</h1>
            <p class="text-[12px] text-gray-500">Unités de capacité &amp; coût horaire — socle des gammes et de la planification</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.work-centers.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau centre
        </a>
        @endcan
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Code</th>
                        <th class="{{ $th }} text-left">Nom</th>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-right">Capacité/j</th>
                        <th class="{{ $th }} text-right">Coût/h</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Rendement</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centers as $c)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors {{ $c->is_active ? '' : 'opacity-50' }}">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $c->code }}</td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $c->name }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $c->machine?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($c->capacity_hours_per_day, 1, ',', ' ') }} h</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-900">{{ number_format($c->cost_per_hour, 0, ',', ' ') }} F</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">{{ number_format($c->efficiency_rate, 0, ',', ' ') }} %</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $c->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $c->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.work-centers.edit', $c) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.work-centers.destroy', $c) }}" class="inline ml-2" data-confirm="Supprimer ce centre ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun centre de travail. Créez-en un.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $centers->total() }} centre(s)</span>
            @if($centers->hasPages())<div>{{ $centers->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Centres de travail</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
