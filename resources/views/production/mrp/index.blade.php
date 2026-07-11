@extends('layouts.erp')
@section('title', 'MRP — Réapprovisionnement bobines')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Réappro (MRP)</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">MRP — Réapprovisionnement bobines</h1>
        <p class="text-[12px] text-gray-500">Déficits de matière première (poids disponible &lt; seuil minimum produit)</p>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach([
            ['label' => 'Produits en déficit', 'value' => number_format($stats['count'], 0, ',', ' '),             'color' => $stats['count'] > 0 ? 'text-red-600' : 'text-gray-900', 'bg' => 'bg-red-50',    'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
            ['label' => 'Déficit total',       'value' => number_format($stats['deficit'], 0, ',', ' ') . ' kg',   'color' => 'text-gray-900', 'bg' => 'bg-amber-50',  'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
            ['label' => 'Coût estimé',         'value' => number_format($stats['estimated'], 0, ',', ' ') . ' F',  'color' => 'text-gray-900', 'bg' => 'bg-[#eef5f0]', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
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

    {{-- Table déficits --}}
    <form method="POST" action="{{ route('production.mrp.generate') }}" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Déficits matière</h2>
            @can('production.update')
            @if($shortfalls->isNotEmpty())
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Générer demande d'achat
            </button>
            @endif
            @endcan
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} w-8"></th>
                        <th class="{{ $th }} text-left">Matière</th>
                        <th class="{{ $th }} text-right">Disponible</th>
                        <th class="{{ $th }} text-right">Seuil min</th>
                        <th class="{{ $th }} text-right">Déficit</th>
                        <th class="{{ $th }} text-right hidden md:table-cell">Coût/kg moy.</th>
                        <th class="{{ $th }} text-right">Coût estimé</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shortfalls as $s)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5"><input type="checkbox" name="product_ids[]" value="{{ $s['product_id'] }}" checked class="rounded border-[#c3d3c9] text-emerald-600 focus:ring-emerald-400"></td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $s['product'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($s['available'], 0, ',', ' ') }} kg</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($s['min'], 0, ',', ' ') }} kg</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-red-600">{{ number_format($s['deficit'], 0, ',', ' ') }} kg</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden md:table-cell">{{ number_format($s['avg_cost_per_kg'], 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($s['estimated'], 0, ',', ' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun déficit — stock matière au-dessus des seuils.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            {{ $stats['count'] }} déficit(s) — {{ number_format($stats['deficit'], 0, ',', ' ') }} kg — {{ number_format($stats['estimated'], 0, ',', ' ') }} F estimés — Le seuil minimum provient du champ « stock min » de chaque produit-matière (en kg).
        </div>
    </form>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Réappro (MRP)</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
