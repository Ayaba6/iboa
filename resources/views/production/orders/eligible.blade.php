@extends('layouts.erp')
@section('title', 'Commandes éligibles à la production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Commandes éligibles</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Commandes éligibles à la production</h2>
                <p class="text-[11.5px] text-gray-400">Commandes réglées (paiement caisse) ou approuvées par le gérant, sans ordre de fabrication — MTO tôle bac.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.orders.mts') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Planification MTS</a>
                <a href="{{ route('production.orders.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Ordres de fabrication</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Commande</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Client</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Articles</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Éligibilité</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($orders as $order)
                <tr class="hover:bg-[#eef5f0]/40">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('ventes.commandes.show', $order->id) }}" class="font-semibold text-emerald-800 hover:underline">{{ $order->number }}</a>
                        <div class="text-[11px] text-gray-400">{{ optional($order->issued_at)->format('d/m/Y') }}</div>
                    </td>
                    <td class="px-3 py-1.5 text-gray-900">{{ $order->client?->trade_name ?? $order->client?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-600 text-[12px]">
                        {{ $order->items->take(2)->map(fn ($i) => $i->product?->name ?? $i->description)->filter()->implode(', ') }}@if($order->items->count() > 2) +{{ $order->items->count() - 2 }}@endif
                    </td>
                    <td class="px-3 py-1.5">
                        @if($order->production_approved)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="{{ $order->production_approval_reason }}">Approuvée gérant</span>
                            @if($order->productionApprovedBy)<div class="text-[10.5px] text-gray-400 mt-0.5">{{ $order->productionApprovedBy->name }}@if($order->production_approval_expires_at) · valide → {{ $order->production_approval_expires_at->format('d/m/Y') }}@endif</div>@endif
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Réglée</span>
                            <div class="text-[10.5px] text-gray-400 mt-0.5 tabular-nums">{{ number_format($order->confirmedReceipts(), 0, ',', ' ') }} / {{ number_format((int) ($order->requiredBeforeProduction() ?? 0), 0, ',', ' ') }} F</div>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ number_format((int) $order->total_ttc, 0, ',', ' ') }} FCFA</td>
                    <td class="px-3 py-1.5 text-right">
                        @can('production.create')
                        <a href="{{ route('production.orders.create', ['order_id' => $order->id]) }}"
                           class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer OF</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucune commande éligible en attente d'OF.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — éligibilité MTO</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
