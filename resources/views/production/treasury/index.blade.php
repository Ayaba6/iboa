@extends('layouts.erp')
@section('title', 'Prévision trésorerie production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Prévision trésorerie</span>
@endsection

@section('content')
@php $th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau --}}
    <div>
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Prévision trésorerie production</h1>
        <p class="text-[12px] text-gray-500">Besoin de financement, achats matières à venir et marge réalisée</p>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Coûts engagés (OF actifs)',   'value' => number_format($forecast['engaged_cost'], 0, ',', ' ') . ' F',   'sub' => $forecast['active_count'] . ' OF lancés / en cours', 'color' => 'text-gray-900', 'bg' => 'bg-[#eef5f0]', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'Achats matières à prévoir',   'value' => number_format($forecast['material_need'], 0, ',', ' ') . ' F',  'sub' => 'Déficit bobines (MRP)',                              'color' => 'text-gray-900', 'bg' => 'bg-amber-50',  'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
            ['label' => 'Besoin de financement',       'value' => number_format($forecast['financing_need'], 0, ',', ' ') . ' F', 'sub' => 'Engagé + achats',                                    'color' => 'text-red-600',  'bg' => 'bg-red-50',    'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Marge réalisée',              'value' => number_format($forecast['realized_margin'], 0, ',', ' ') . ' F','sub' => 'OF terminés',                                        'color' => $forecast['realized_margin'] >= 0 ? 'text-emerald-700' : 'text-red-600', 'bg' => $forecast['realized_margin'] >= 0 ? 'bg-emerald-50' : 'bg-red-50', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg style="width:18px;height:18px" class="{{ str_contains($kpi['color'], 'red') ? 'text-red-500' : (str_contains($kpi['color'], 'emerald') ? 'text-emerald-600' : 'text-gray-500') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold {{ $kpi['color'] }} tabular-nums leading-tight">{{ $kpi['value'] }}</p>
                <p class="text-[10.5px] text-gray-400">{{ $kpi['sub'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Détail OF actifs --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Engagements par OF actif</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#3b4248] text-white">
                    <tr>
                        <th class="{{ $th }} text-left">N° OF</th>
                        <th class="{{ $th }} text-left">Client</th>
                        <th class="{{ $th }} text-left">Statut</th>
                        <th class="{{ $th }} text-right">Coût engagé</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forecast['breakdown'] as $row)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ $row['number'] }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $row['client'] }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $row['status'] }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-900">{{ number_format($row['cost'], 0, ',', ' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun OF actif.</td></tr>
                    @endforelse
                </tbody>
                @if($forecast['breakdown']->isNotEmpty())
                <tfoot>
                    <tr class="font-bold text-white" style="background:#065f46;">
                        <td colspan="3" class="px-3 py-1.5 text-right text-[12px] uppercase tracking-wide">Total engagé</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ number_format($forecast['engaged_cost'], 0, ',', ' ') }} F</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            {{ $forecast['breakdown']->count() }} OF actif(s) — Les coûts engagés proviennent des coûts de revient calculés sur les OF lancés/en cours. Calculez le coût (onglet OF) pour affiner la prévision.
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Fonction : <span class="text-white font-semibold">Prévision trésorerie</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
