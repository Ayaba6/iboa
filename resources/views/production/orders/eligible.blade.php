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

    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Commandes éligibles à la production</h1>
            <p class="text-[11.5px] text-gray-400">Commandes réglées (paiement caisse) ou approuvées par le gérant, sans ordre de fabrication.</p>
        </div>
        <a href="{{ route('production.orders.index') }}"
           class="text-[12.5px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-[4px]">Ordres de fabrication</a>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Approuvée gérant</span>
                            @if($order->productionApprovedBy)<div class="text-[10.5px] text-gray-400 mt-0.5">{{ $order->productionApprovedBy->name }}</div>@endif
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Réglée (caisse)</span>
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

    @if($orders->hasPages())<div>{{ $orders->withQueryString()->links() }}</div>@endif
</div>
@endsection
