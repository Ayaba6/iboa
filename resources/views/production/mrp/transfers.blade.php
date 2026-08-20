@extends('layouts.erp')
@section('title', 'MRP — propositions de transfert')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Propositions de transfert</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">MRP — propositions de transfert</h2>
                <p class="text-[11.5px] text-gray-400">
                    Dépôts ayant réservé plus qu’ils ne détiennent, couverts par l’excédent réel d’un autre dépôt.
                    Quarantaine, rebuts et chutes ne sont jamais source.
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.mrp') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Calcul des besoins</a>
                <a href="{{ route('production.mrp.of') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Propositions d’OF</a>
            </div>
        </div>
        <div class="px-4 py-2 border-t border-gray-200 flex flex-wrap gap-x-8 gap-y-1 text-[12px] text-gray-600">
            <span>Propositions : <span class="font-semibold text-gray-900 tabular-nums">{{ $stats['count'] }}</span></span>
            <span>Quantité à déplacer : <span class="font-semibold text-gray-900 tabular-nums">{{ number_format($stats['quantite'], 0, ',', ' ') }}</span></span>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Depuis</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Vers</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide" title="Réservé au-delà du physique dans le dépôt destinataire">Manque</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Dispo. source</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Quantité proposée</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($proposals as $r)
                <tr class="hover:bg-[#eef5f0]/40">
                    <td class="px-3 py-1.5">
                        <span class="font-medium text-gray-900">{{ $r['product']->name }}</span>
                        <span class="text-[11px] text-gray-400 font-mono ml-1">{{ $r['product']->reference }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $r['from']->name }}
                        <span class="text-[11px] text-gray-400 font-mono">{{ $r['from']->code }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $r['to']->name }}
                        <span class="text-[11px] text-gray-400 font-mono">{{ $r['to']->code }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-red-600 font-semibold">{{ number_format($r['deficit'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ number_format($r['disponible_source'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold text-amber-700">{{ number_format($r['quantite'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right">
                        {{-- `stocks.adjust` et non `stocks.transfer` : c'est la permission qui
                             garde réellement la route de création (StockTransferController).
                             Afficher le bouton sous une autre mènerait à un 403. --}}
                        @can('stocks.adjust')
                        <a href="{{ route('stocks.transfers.create') }}"
                           class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer le transfert</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">
                        Aucune proposition : aucun dépôt n’a réservé plus qu’il ne détient.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — MRP / transferts</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
