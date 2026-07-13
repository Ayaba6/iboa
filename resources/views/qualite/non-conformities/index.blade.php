@extends('layouts.erp')
@section('title', 'Non-conformités')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Non-conformités</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Non-conformités (CAPA)</h1>
            <p class="text-[12px] text-gray-500">Registre des non-conformités &amp; actions correctives</p>
        </div>
        @can('production.update')
        <a href="{{ route('qualite.non-conformities.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle NC
        </a>
        @endcan
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach([
            ['label' => 'Ouvertes',           'value' => number_format($stats['ouvertes'], 0, ',', ' '),  'color' => $stats['ouvertes'] > 0 ? 'text-amber-600' : 'text-gray-900', 'bg' => 'bg-amber-50', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Critiques ouvertes', 'value' => number_format($stats['critiques'], 0, ',', ' '), 'color' => $stats['critiques'] > 0 ? 'text-red-600' : 'text-gray-900',  'bg' => 'bg-red-50',   'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'Clôturées',          'value' => number_format($stats['cloturees'], 0, ',', ' '), 'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ str_contains($kpi['color'], 'red') ? 'text-red-500' : (str_contains($kpi['color'], 'amber') ? 'text-amber-500' : (str_contains($kpi['color'], 'emerald') ? 'text-emerald-600' : 'text-gray-500')) }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
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
                <label class="{{ $lbl }}">Statut</label>
                <div class="relative"><select name="status" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['ouverte'=>'Ouverte','en_cours'=>'En cours','cloturee'=>'Clôturée'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Gravité</label>
                <div class="relative"><select name="severity" class="{{ $lk }}">
                    <option value="">Toutes</option>
                    @foreach(['mineure'=>'Mineure','majeure'=>'Majeure','critique'=>'Critique'] as $k=>$v)<option value="{{ $k }}" @selected(request('severity')===$k)>{{ $v }}</option>@endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                @if(request()->hasAny(['status','severity']))
                <a href="{{ route('qualite.non-conformities.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">Réinitialiser</a>
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
                        <th class="{{ $th }} text-left">Réf.</th>
                        <th class="{{ $th }} text-left">Titre</th>
                        <th class="{{ $th }} text-center">Gravité</th>
                        <th class="{{ $th }} text-center">Statut</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Responsable</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Échéance</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $nc)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $nc->reference }}</td>
                        <td class="px-3 py-1.5 text-gray-900 max-w-[300px] truncate">{{ $nc->title }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php $vc = match($nc->severity){ 'mineure'=>'bg-gray-100 text-gray-600','majeure'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $vc }}">{{ $nc->severityLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @php $sc = match($nc->status){ 'ouverte'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-blue-100 text-blue-700',default=>'bg-emerald-100 text-emerald-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc }}">{{ $nc->statusLabel() }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 text-[12px] hidden md:table-cell whitespace-nowrap">{{ $nc->responsible?->full_name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-[12px] hidden md:table-cell whitespace-nowrap">{{ optional($nc->due_date)->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right whitespace-nowrap">
                            <a href="{{ route('qualite.corrective-actions.nc', $nc) }}" class="text-blue-700 hover:text-blue-900 hover:underline text-[12px] font-semibold mr-3">CAPA</a>
                            @can('production.update')
                            <a href="{{ route('qualite.non-conformities.edit', $nc) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline text-[12px] font-semibold">Traiter</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune non-conformité.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $items->total() }} non-conformité(s) — {{ $stats['ouvertes'] }} ouverte(s) — {{ $stats['critiques'] }} critique(s)</span>
            @if($items->hasPages())<div>{{ $items->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Non-conformités</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
