@extends('layouts.erp')
@section('title', 'RFQ ' . $rfq->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.rfq.index') }}" class="hover:text-gray-700">RFQ</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $rfq->number }}</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
    $color = $rfq->statusColor();
    $supplierStatusColor = [
        'en_attente'=>'gray', 'envoyee'=>'blue', 'recue'=>'emerald', 'declinee'=>'red',
    ];
    $supplierStatusLabel = [
        'en_attente'=>'En attente','envoyee'=>'Envoyée','recue'=>'Réponse reçue','declinee'=>'Déclinée',
    ];
@endphp

<div class="max-w-6xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-[15px] font-bold text-gray-900 font-mono">{{ $rfq->number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $color }}-100 text-{{ $color }}-700">{{ $rfq->statusLabel() }}</span>
                </div>
                <p class="text-[15px] text-gray-700 mt-1">{{ $rfq->title }}</p>
                <p class="text-[12px] text-gray-500 mt-0.5">
                    @if($rfq->deadline) Échéance : <strong>{{ $rfq->deadline->format('d/m/Y') }}</strong> · @endif
                    Créée par {{ $rfq->createdBy?->name ?? '—' }} le {{ $rfq->created_at->format('d/m/Y') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('purchase_orders.create')
                    @if($rfq->isDraft())
                    <form action="{{ route('achats.rfq.send', $rfq) }}" method="POST" onsubmit="return confirm('Marquer la RFQ comme envoyée à tous les fournisseurs ?')">
                        @csrf
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px]">📤 Marquer envoyée</button>
                    </form>
                    @endif
                    @if(!$rfq->isClosed() && !$rfq->isCancelled())
                    <a href="{{ route('achats.rfq.compare', $rfq) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px]">📊 Comparatif</a>
                    @endif
                    @if($rfq->isDraft())
                    <form action="{{ route('achats.rfq.destroy', $rfq) }}" method="POST" onsubmit="return confirm('Supprimer cette RFQ ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="border border-gray-300 text-gray-600 hover:bg-red-50 hover:text-red-600 text-[13px] font-medium px-3 py-1.5 rounded-[4px]">Supprimer</button>
                    </form>
                    @endif
                @endcan
                <a href="{{ route('achats.rfq.index') }}" class="border border-gray-300 text-gray-700 text-[13px] font-medium px-3 py-1.5 rounded-[4px]">← Retour</a>
            </div>
        </div>

        @if($rfq->awardedQuote)
        <div class="mt-4 bg-emerald-50 border border-emerald-200 rounded-[4px] p-3 text-[13px] text-emerald-800">
            ✓ <strong>Attribuée</strong> à {{ $rfq->awardedQuote->rfqSupplier?->supplier?->name }} ·
            Total {{ $fmt($rfq->awardedQuote->total_ttc) }} FCFA ·
            @if($rfq->purchase_order_id)
            <a href="{{ route('achats.commandes.show', $rfq->purchase_order_id) }}" class="underline font-medium">Voir le PO →</a>
            @endif
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Items --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-[13px] font-semibold text-gray-700">Lignes demandées ({{ $rfq->items->count() }})</h2></div>
            <table class="w-full text-[13px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Description</th><th class="px-3 py-1.5 text-right">Quantité</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($rfq->items as $item)
                    <tr>
                        <td class="px-3 py-1.5 text-[13px]">
                            @if($item->product)<span class="font-mono text-[12px] text-blue-700">{{ $item->product->reference }}</span><br>@endif
                            {{ $item->description }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Suppliers + statut --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-[13px] font-semibold text-gray-700">Fournisseurs consultés ({{ $rfq->rfqSuppliers->count() }})</h2></div>
            <table class="w-full text-[13px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Fournisseur</th>
                        <th class="px-3 py-1.5 text-center">Statut</th>
                        <th class="px-3 py-1.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($rfq->rfqSuppliers as $rs)
                    @php $c = $supplierStatusColor[$rs->status] ?? 'gray'; @endphp
                    <tr>
                        <td class="px-3 py-1.5 text-[13px]">{{ $rs->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $c }}-100 text-{{ $c }}-700">{{ $supplierStatusLabel[$rs->status] ?? $rs->status }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            @can('purchase_orders.create')
                            @if(!$rfq->isClosed() && !$rfq->isCancelled() && !$rfq->isDraft())
                                @if($rs->quote)
                                    <span class="text-[12px] text-gray-400">Cotation enregistrée</span>
                                @else
                                    <button type="button" onclick="document.getElementById('quote-form-{{ $rs->id }}').classList.toggle('hidden')" class="text-[12px] text-blue-600 hover:underline font-medium">Saisir cotation</button>
                                @endif
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @if(!$rfq->isClosed() && !$rfq->isCancelled() && !$rfq->isDraft() && !$rs->quote)
                    <tr id="quote-form-{{ $rs->id }}" class="hidden bg-blue-50/40">
                        <td colspan="3" class="px-3 py-1.5">
                            <form action="{{ route('achats.rfq.record-quote', $rfq) }}" method="POST" class="space-y-3">
                                @csrf
                                <input type="hidden" name="rfq_supplier_id" value="{{ $rs->id }}">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[12px]">
                                    <div><label class="block font-medium mb-1">Réf. devis fournisseur</label><input type="text" name="supplier_reference" maxlength="100" class="w-full border border-gray-300 rounded px-2 py-1.5"></div>
                                    <div><label class="block font-medium mb-1">Valide jusqu'au</label><input type="date" name="valid_until" class="w-full border border-gray-300 rounded px-2 py-1.5"></div>
                                    <div><label class="block font-medium mb-1">Délai (jours)</label><input type="number" name="delivery_days" min="0" class="w-full border border-gray-300 rounded px-2 py-1.5"></div>
                                </div>
                                <table class="w-full text-[12px]">
                                    <thead><tr class="border-b"><th class="text-left py-1">Ligne</th><th class="text-right py-1">Prix unitaire</th><th class="text-right py-1">Remise %</th><th class="text-right py-1">TVA %</th></tr></thead>
                                    <tbody>
                                        @foreach($rfq->items as $item)
                                        <tr>
                                            <td class="py-1">
                                                <input type="hidden" name="items[{{ $loop->index }}][rfq_item_id]" value="{{ $item->id }}">
                                                {{ $item->description }} <span class="text-gray-400">× {{ number_format($item->quantity, 2, ',', ' ') }}</span>
                                            </td>
                                            <td class="py-1 text-right"><input type="number" name="items[{{ $loop->index }}][unit_price]" required min="0" step="0.01" class="w-24 border border-gray-300 rounded px-2 py-1 text-right"></td>
                                            <td class="py-1 text-right"><input type="number" name="items[{{ $loop->index }}][discount_percent]" min="0" max="100" step="1" inputmode="numeric" value="0" class="w-20 border border-gray-300 rounded px-2 py-1 text-right"></td>
                                            <td class="py-1 text-right"><input type="number" name="items[{{ $loop->index }}][tax_rate]" min="0" max="100" step="1" inputmode="numeric" value="18" class="w-20 border border-gray-300 rounded px-2 py-1 text-right"></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="document.getElementById('quote-form-{{ $rs->id }}').classList.add('hidden')" class="border border-gray-300 text-gray-700 text-[12px] px-3 py-1.5 rounded">Annuler</button>
                                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 py-1.5 rounded">Enregistrer la cotation</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Synthèse cotations reçues --}}
    @if($rfq->quotes->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-[13px] font-semibold text-gray-700">Cotations reçues ({{ $rfq->quotes->count() }})</h2>
            <a href="{{ route('achats.rfq.compare', $rfq) }}" class="text-[12px] text-violet-600 hover:underline font-medium">Voir le comparatif détaillé →</a>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Fournisseur</th>
                    <th class="px-3 py-1.5 text-right">Total HT</th>
                    <th class="px-3 py-1.5 text-right">Total TTC</th>
                    <th class="px-3 py-1.5 text-right">Délai (j)</th>
                    <th class="px-3 py-1.5 text-right">Valide jusqu'au</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($rfq->quotes->sortBy('total_ttc') as $q)
                <tr class="{{ $q->is_winner ? 'bg-emerald-50/50' : '' }}">
                    <td class="px-3 py-1.5 text-[13px] font-medium text-gray-900">
                        {{ $q->rfqSupplier?->supplier?->name }}
                        @if($q->supplier_reference) <span class="text-[12px] text-gray-400 ml-1">({{ $q->supplier_reference }})</span>@endif
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($q->subtotal_ht) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold">{{ $fmt($q->total_ttc) }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $q->delivery_days ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right text-[12px] text-gray-600">{{ $q->valid_until?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-center">
                        @if($q->is_winner)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">🏆 Gagnante</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <x-audit.timeline :model="\App\Models\Rfq::class" :id="$rfq->id" />
</div>
@endsection
