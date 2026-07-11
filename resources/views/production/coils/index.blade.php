@extends('layouts.erp')
@section('title', 'Bobines (matière première)')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bobines</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Bobines — matière première</h1>
            <p class="text-[12px] text-gray-500">Réception, lot, poids restant &amp; coût au kg</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.coils.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Réceptionner une bobine
        </a>
        @endcan
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Bobines',       'value' => number_format($stats['total'], 0, ',', ' '),                 'color' => 'text-gray-900',    'bg' => 'bg-[#eef5f0]',  'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['label' => 'Disponibles',   'value' => number_format($stats['disponible'], 0, ',', ' '),            'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Poids restant', 'value' => number_format($stats['poids_dispo'], 0, ',', ' ') . ' kg',   'color' => 'text-gray-900',    'bg' => 'bg-blue-50',    'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
            ['label' => 'Valeur stock',  'value' => number_format($stats['valeur'], 0, ',', ' ') . ' F',         'color' => 'text-gray-900',    'bg' => 'bg-amber-50',   'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
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
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 items-end">
            <div>
                <label class="{{ $lbl }}">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Référence, lot, couleur…" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    <option value="disponible"    @selected(request('status')==='disponible')>Disponible</option>
                    <option value="en_production" @selected(request('status')==='en_production')>En production</option>
                    <option value="epuisee"       @selected(request('status')==='epuisee')>Épuisée</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['q','status']))
                <a href="{{ route('production.coils.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">Référence</th>
                        <th class="{{ $th }} text-left">Lot</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Couleur</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Ép. × Larg.</th>
                        <th class="{{ $th }} text-right">Restant / Initial</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Coût/kg</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coils as $c)
                    @php $rate = $c->initial_weight > 0 ? ($c->remaining_weight / $c->initial_weight) * 100 : 0; @endphp
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <a href="{{ route('production.coils.show', $c) }}" class="font-mono text-emerald-800 hover:underline">{{ $c->reference }}</a>
                        </td>
                        <td class="px-3 py-1.5 font-mono text-[12px] text-gray-600">{{ $c->lot_number ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell">{{ $c->color ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums hidden lg:table-cell whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $c->thickness,2,',',''),'0'),',') }} × {{ number_format((float) $c->width,0,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">
                            <span class="font-semibold text-gray-900">{{ number_format($c->remaining_weight,0,',',' ') }}</span>
                            <span class="text-gray-400"> / {{ number_format($c->initial_weight,0,',',' ') }} kg</span>
                            <div class="mt-1 h-1 bg-gray-100 rounded-full overflow-hidden w-24 ml-auto">
                                <div class="h-full {{ $rate < 20 ? 'bg-red-400' : 'bg-emerald-500' }}" style="width: {{ min(100,$rate) }}%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 hidden lg:table-cell">{{ number_format($c->cost_per_kg,2,',',' ') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php [$sl, $sc] = match($c->status){
                                'disponible'    => ['Disponible',    'bg-emerald-100 text-emerald-700'],
                                'en_production' => ['En production', 'bg-blue-100 text-blue-700'],
                                'epuisee'       => ['Épuisée',       'bg-gray-100 text-gray-500'],
                                default         => [str_replace('_',' ',ucfirst($c->status)), 'bg-gray-100 text-gray-500'],
                            }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.coils.edit', $c) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.coils.destroy', $c) }}" class="inline ml-2" data-confirm="Supprimer cette bobine ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-[12px]">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune bobine. Réceptionnez-en une.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $coils->total() }} bobine(s) — poids disponible : <span class="font-semibold text-gray-700 tabular-nums">{{ number_format($stats['poids_dispo'], 0, ',', ' ') }} kg</span> — valeur : <span class="font-semibold text-gray-700 tabular-nums">{{ number_format($stats['valeur'], 0, ',', ' ') }} F</span></span>
            @if($coils->hasPages())<div>{{ $coils->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Bobines / lots matière</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
