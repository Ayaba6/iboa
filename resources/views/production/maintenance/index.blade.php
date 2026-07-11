@extends('layouts.erp')
@section('title', 'Maintenance machines')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Maintenance</span>
@endsection

@section('content')
@php
    $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
    $panelH = 'px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Maintenance machines</h1>
            <p class="text-[12px] text-gray-500">Préventive · corrective · disponibilité (MTBF / MTTR)</p>
        </div>
        @can('production.update')
        <div class="flex gap-2">
            <a href="{{ route('production.maintenance-plans.index') }}" class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center transition-colors">
                Plans préventifs
            </a>
            <a href="{{ route('production.maintenance.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle intervention
            </a>
        </div>
        @endcan
    </div>

    @if(count($due))
    <div class="flex items-start gap-3 bg-[#fff8ec] border border-amber-200 rounded-[4px] px-3 py-2.5">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <p class="text-[13px] font-bold text-amber-700">Maintenance préventive due ({{ count($due) }})</p>
            <p class="text-[12px] text-gray-600">{{ collect($due)->pluck('name')->implode(' · ') }}</p>
        </div>
    </div>
    @endif

    {{-- Disponibilité par machine --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $panelH }}"><h2 class="text-[13px] font-bold text-gray-900">Disponibilité machines (30 j)</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-right">Disponibilité</th>
                        <th class="{{ $th }} text-right">Arrêts</th>
                        <th class="{{ $th }} text-right">Pannes</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">MTBF</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">MTTR</th>
                        <th class="{{ $th }} text-right">Coût</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($machineKpis as $k)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $k['machine']->name }}</td>
                        <td class="px-3 py-1.5 text-right">
                            @php $av = $k['availability']; $ac = $av >= 90 ? 'text-emerald-600' : ($av >= 75 ? 'text-amber-600' : 'text-red-600'); @endphp
                            <span class="font-bold tabular-nums {{ $ac }}">{{ number_format($av,1,',',' ') }} %</span>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ number_format($k['downtime_h'],1,',',' ') }} h</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $k['failures']>0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ $k['failures'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">{{ $k['mtbf_h'] !== null ? number_format($k['mtbf_h'],1,',',' ').' h' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell">{{ $k['mttr_h'] !== null ? number_format($k['mttr_h'],1,',',' ').' h' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($k['cost'],0,',',' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune machine active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Interventions --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $panelH }}"><h2 class="text-[13px] font-bold text-gray-900">Interventions</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Machine</th>
                        <th class="{{ $th }} text-left">Type</th>
                        <th class="{{ $th }} text-left">Intitulé</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Planifiée</th>
                        <th class="{{ $th }} text-right hidden md:table-cell">Arrêt</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $m)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 text-gray-900">{{ $m->machine?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $m->type==='corrective' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">{{ $m->typeLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-700 max-w-[260px] truncate">{{ $m->title }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $sc = match($m->status){ 'planifie'=>'bg-gray-100 text-gray-600','en_cours'=>'bg-amber-100 text-amber-700',default=>'bg-emerald-100 text-emerald-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $m->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-[12px] hidden md:table-cell whitespace-nowrap">{{ optional($m->planned_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden md:table-cell">{{ $m->downtime_minutes > 0 ? number_format($m->downtime_minutes/60,1,',',' ').' h' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            @if($m->status === 'planifie')
                            <form method="POST" action="{{ route('production.maintenance.start', $m) }}" class="inline">@csrf<button class="text-amber-600 hover:underline text-[12px] font-semibold">Démarrer</button></form>
                            @elseif($m->status === 'en_cours')
                            <form method="POST" action="{{ route('production.maintenance.finish', $m) }}" class="inline">@csrf<button class="text-emerald-600 hover:underline text-[12px] font-semibold">Terminer</button></form>
                            @endif
                            <a href="{{ route('production.maintenance.edit', $m) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold ml-2">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune intervention.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $maintenances->total() }} intervention(s)</span>
            @if($maintenances->hasPages())<div>{{ $maintenances->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Maintenance</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
