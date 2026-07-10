@extends('layouts.erp')
@section('title', 'Alertes réapprovisionnement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Alertes réappro</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">⚠ Alertes de réapprovisionnement</h1>
            <p class="text-[11.5px] text-gray-400">{{ $alerts->total() }} article(s) sous le point de réappro.</p>
        </div>
        @if($alerts->total() > 0)
        @can('purchase_orders.create')
        <a href="{{ route('achats.dashboard.restock-po') }}"
           class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Générer PO d'achat
        </a>
        @endcan
        @endif
    </div>

    @if($alerts->isEmpty())
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] px-4 py-6 text-center text-emerald-700 text-[12.5px]">
            ✓ Aucune alerte de réappro — tous les articles sont au-dessus de leur point de réapprovisionnement.
        </div>
    @else
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <table class="w-full text-[12.5px] border-collapse">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[10px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-right">Disponible</th>
                    <th class="px-3 py-1.5 text-right">Réappro</th>
                    <th class="px-3 py-1.5 text-right">Stock max</th>
                    <th class="px-3 py-1.5 text-right">À commander</th>
                    <th class="px-3 py-1.5 text-right">Coût estimé</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($alerts as $row)
                @php
                    $unitCost = $row->last_purchase_price ?: $row->purchase_price ?: 0;
                    $estimated = (int) ($unitCost * $row->suggested_qty);
                @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                    <td class="px-3 py-1">
                        <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-[11px] text-blue-700 hover:underline">{{ $row->reference }}</a>
                        <span class="text-gray-900"> · {{ $row->name }}</span>
                        @if($row->warehouse_name)
                        <span class="text-[10.5px] text-gray-400">· 📦 {{ $row->warehouse_name }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-[11px]">
                        @if($row->supplier_name)
                            <span class="text-gray-700">{{ $row->supplier_name }}</span>
                        @else
                            <span class="text-gray-400 italic">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums {{ $row->available_qty <= 0 ? 'text-red-600 font-semibold' : 'text-orange-700' }}">
                        {{ number_format($row->available_qty, 0, ',', ' ') }}
                        @if($row->reserved_quantity > 0)
                            <span class="text-[10.5px] text-gray-400">({{ number_format($row->quantity, 0, ',', ' ') }} − {{ number_format($row->reserved_quantity, 0, ',', ' ') }} rés.)</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ $row->reorder_point }}</td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ $row->stock_max ?: '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-semibold text-emerald-700">
                        {{ number_format($row->suggested_qty, 0, ',', ' ') }}
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-700">
                        {{ $fmt($estimated) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($alerts->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">{{ $alerts->links() }}</div>
        @endif
    </div>
    @endif

    {{-- Légende --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-2 text-[12px] text-blue-800">
        <p class="font-semibold mb-1 text-[11.5px]">💡 Comment ça marche</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-[11px]">
            <li><strong>Disponible</strong> = stock physique − réservé (devis / commandes).</li>
            <li><strong>Point de réappro</strong> = seuil défini par article. Quand on descend dessous, on déclenche l'achat.</li>
            <li><strong>À commander</strong> = qté suggérée pour remonter au stock max (ou réappro + 1 si max non défini).</li>
            <li><strong>Coût estimé</strong> = qté suggérée × dernier prix d'achat (ou prix d'achat catalogue).</li>
        </ul>
    </div>
</div>
@endsection
