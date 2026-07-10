@extends('layouts.erp')
@section('title', 'Comparatif RFQ ' . $rfq->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.rfq.index') }}" class="hover:text-gray-700">RFQ</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.rfq.show', $rfq) }}" class="hover:text-gray-700">{{ $rfq->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comparatif</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((float) $n, 0, ',', ' '); @endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">📊 Comparatif des cotations</h1>
            <p class="text-sm text-gray-500">{{ $rfq->title }} · {{ $compare['quotes']->count() }} fournisseur(s) coté(s) sur {{ $compare['items']->count() }} ligne(s)</p>
        </div>
        <a href="{{ route('achats.rfq.show', $rfq) }}" class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">← Retour à la RFQ</a>
    </div>

    @if(empty($compare['quotes']) || $compare['quotes']->isEmpty())
        <div class="bg-gray-50 border border-gray-300 rounded-[4px] p-8 text-center text-gray-500">
            Aucune cotation reçue pour l'instant.
        </div>
    @else

    {{-- Synthèse par cotation (cartes) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($compare['summary'] as $i => $row)
        @php $isMin = $i === 0; @endphp
        <div class="bg-white rounded-[4px] border-2 {{ $isMin ? 'border-emerald-300' : 'border-gray-200' }} p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-gray-900">{{ $row['supplier_name'] }}</p>
                    @if($isMin)
                    <span class="inline-block mt-1 px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-[3px] text-[11px] font-medium">💰 Moins-disant</span>
                    @endif
                    @if($row['is_winner'])
                    <span class="inline-block mt-1 ml-1 px-2 py-0.5 bg-violet-100 text-violet-800 rounded-[3px] text-[11px] font-medium">🏆 Attribuée</span>
                    @endif
                </div>
            </div>
            <p class="mt-3 text-[19px] font-bold tabular-nums {{ $isMin ? 'text-emerald-700' : 'text-gray-900' }}">{{ $fmt($row['total_ttc']) }}</p>
            <p class="text-xs text-gray-500">FCFA TTC · HT {{ $fmt($row['subtotal_ht']) }}</p>
            <div class="mt-3 text-xs text-gray-600 space-y-0.5">
                <p>Délai : <strong>{{ $row['delivery_days'] ?? '—' }} j</strong></p>
                <p>Validité : {{ $row['valid_until'] ? \Carbon\Carbon::parse($row['valid_until'])->format('d/m/Y') : '—' }}</p>
            </div>
            @can('purchase_orders.create')
                @if(!$rfq->isClosed() && !$rfq->isCancelled() && !$row['is_winner'])
                <form action="{{ route('achats.rfq.award', [$rfq, $row['quote']->id]) }}" method="POST"
                      onsubmit="return confirm('Attribuer la RFQ à {{ addslashes($row['supplier_name']) }} ? Un PO en brouillon sera créé.')">
                    @csrf
                    <button type="submit" class="mt-3 w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-2.5 py-1.5 rounded-[4px]">
                        🏆 Attribuer & générer PO
                    </button>
                </form>
                @endif
            @endcan
        </div>
        @endforeach
    </div>

    {{-- Matrice détaillée par ligne --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Détail par ligne — meilleur prix en vert</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Ligne</th>
                        <th class="px-3 py-1.5 text-right">Qté</th>
                        @foreach($compare['quotes'] as $q)
                        <th class="px-3 py-1.5 text-right">{{ $q->rfqSupplier?->supplier?->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($compare['items'] as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5">
                            @if($item->product)
                                <span class="font-mono text-xs text-blue-700">{{ $item->product->reference }}</span><br>
                            @endif
                            <span class="text-sm">{{ $item->description }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        @foreach($compare['quotes'] as $q)
                            @php
                                $qi = $compare['matrix'][$item->id][$q->id] ?? null;
                                $isMin = $qi && (float) $qi->unit_price === (float) $compare['min_by_item'][$item->id];
                            @endphp
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $isMin ? 'bg-emerald-50 text-emerald-800 font-semibold' : '' }}">
                                @if($qi)
                                    {{ $fmt($qi->unit_price) }}
                                    <p class="text-xs {{ $isMin ? 'text-emerald-600' : 'text-gray-500' }}">HT {{ $fmt($qi->line_total_ht) }}</p>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-bold">
                    <tr>
                        <td class="px-3 py-1.5 text-right text-gray-500 text-xs uppercase" colspan="2">Total TTC</td>
                        @foreach($compare['quotes'] as $q)
                        @php $isMinTotal = $compare['summary'][0]['quote']->id === $q->id; @endphp
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $isMinTotal ? 'text-emerald-700' : 'text-gray-900' }}">{{ $fmt($q->total_ttc) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
