@extends('layouts.erp')
@section('title', 'Planification MTS — production pour stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Planification MTS</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Planification MTS — production pour stock</h2>
                <p class="text-[11.5px] text-gray-400">Articles fabriqués pour le stock (fer à béton…). Besoin net = cible + sécurité − disponible − production planifiée.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.orders.eligible') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Éligibles MTO</a>
                <a href="{{ route('production.orders.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Ordres de fabrication</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Physique</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Réservé</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Disponible</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Min</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Cible</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">OF planifiés</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Besoin net</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">État</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $r)
                <tr class="hover:bg-[#eef5f0]/40">
                    <td class="px-3 py-1.5">
                        <span class="font-medium text-gray-900">{{ $r['p']->name }}</span>
                        <span class="text-[11px] text-gray-400 font-mono ml-1">{{ $r['p']->reference }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($r['physique'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($r['reserve'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $r['dispo'] <= 0 ? 'text-red-600' : '' }}">{{ number_format($r['dispo'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $r['p']->stock_min ? number_format($r['p']->stock_min, 0, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $r['cible'] ? number_format($r['cible'], 0, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-blue-700">{{ number_format($r['plan'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $r['besoin'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">{{ number_format($r['besoin'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($r['etat'] === 'rupture')<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Rupture</span>
                        @elseif($r['etat'] === 'sous_min')<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Sous le minimum</span>
                        @else<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">OK</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        @can('production.create')
                        @if($r['besoin'] > 0)
                        <a href="{{ route('production.orders.create', ['product_id' => $r['p']->id, 'qty' => $r['besoin']]) }}"
                           class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer OF MTS</a>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucun article MTS actif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — planification MTS</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
