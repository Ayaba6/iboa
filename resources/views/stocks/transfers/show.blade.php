@extends('layouts.erp')
@section('title', 'Transfert ' . $transfer->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.transfers.index') }}" class="hover:text-gray-700">Transferts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $transfer->number }}</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3">

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $transfer->number }}</h1>
                    @php
                        $statusBadge = [
                            'brouillon'  => 'bg-gray-100 text-gray-700',
                            'en_transit' => 'bg-amber-100 text-amber-700',
                            'recu'       => 'bg-emerald-100 text-emerald-700',
                            'annule'     => 'bg-red-100 text-red-700',
                        ][$transfer->status] ?? 'bg-gray-100 text-gray-700';
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusBadge }}">
                        {{ $transfer->statusLabel() }}
                    </span>
                    @if($transfer->hasDiscrepancy())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-100 text-amber-700">⚠ Écart à la réception</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="font-medium">{{ $transfer->fromWarehouse?->name }}</span>
                    <span class="text-gray-400 mx-2">→</span>
                    <span class="font-medium">{{ $transfer->toWarehouse?->name }}</span>
                    · prévu le {{ $transfer->transfer_date?->format('d/m/Y') }}
                </p>
                @if($transfer->reason)
                <p class="text-sm text-gray-600 mt-1 italic">« {{ $transfer->reason }} »</p>
                @endif
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                @can('stocks.adjust')
                    @if($transfer->canEdit())
                    <a href="{{ route('stocks.transfers.edit', $transfer) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Modifier
                    </a>
                    @endif

                    @if($transfer->canShip())
                    <form action="{{ route('stocks.transfers.ship', $transfer) }}" method="POST"
                          onsubmit="return confirm('Expédier ce transfert ? Le stock du dépôt source sera décrémenté.')">
                        @csrf
                        <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Expédier
                        </button>
                    </form>
                    @endif

                    @if($transfer->canReceive())
                    {{-- Bouton qui ouvre un modal pour saisir éventuels écarts de réception --}}
                    <button type="button"
                            onclick="document.getElementById('receive-form').classList.toggle('hidden')"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Recevoir
                    </button>
                    @endif

                    @if($transfer->canCancel())
                    <form action="{{ route('stocks.transfers.cancel', $transfer) }}" method="POST"
                          x-data="{ open: false, reason: '' }" class="inline">
                        @csrf
                        <button type="button" @click="open = true" class="border border-red-300 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
                            <div class="relative bg-white rounded-[4px] shadow-xl w-full max-w-md p-6 z-10">
                                <h3 class="text-base font-semibold text-gray-900">Annuler le transfert {{ $transfer->number }}</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    @if($transfer->isInTransit())Le stock sera ré-incrémenté au dépôt source.@endif
                                </p>
                                <label class="block mt-4 text-xs font-medium text-gray-700">Motif (≥ 5 caractères)</label>
                                <textarea name="reason" x-model="reason" rows="3" required minlength="5" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm mt-1"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open=false" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Retour</button>
                                    <button type="submit" :disabled="reason.length < 5" :class="reason.length>=5?'bg-red-600 hover:bg-red-700 text-white':'bg-gray-200 text-gray-400 cursor-not-allowed'" class="text-sm font-medium px-3 py-1.5 rounded-[4px]">Confirmer</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif

                    @if($transfer->isDraft())
                    <form action="{{ route('stocks.transfers.destroy', $transfer) }}" method="POST" onsubmit="return confirm('Supprimer ce brouillon ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="border border-gray-300 text-gray-600 hover:bg-red-50 hover:text-red-600 text-sm font-medium px-3 py-1.5 rounded-[4px]">Supprimer</button>
                    </form>
                    @endif
                @endcan

                <a href="{{ route('stocks.transfers.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px]">← Retour</a>
            </div>
        </div>
    </div>

    {{-- Réception form (caché par défaut, dépliable) --}}
    @if($transfer->canReceive())
    @can('stocks.adjust')
    <form id="receive-form" action="{{ route('stocks.transfers.receive', $transfer) }}" method="POST" class="hidden bg-emerald-50 border border-emerald-200 rounded-[4px] p-5">
        @csrf
        <p class="text-sm text-emerald-800 font-medium mb-3">Saisir les quantités effectivement reçues. Laisser vide = quantité expédiée par défaut.</p>
        <table class="w-full text-sm">
            <thead><tr><th class="px-2 py-1 text-left">Article</th><th class="px-2 py-1 text-right">Expédiée</th><th class="px-2 py-1 text-right">Reçue</th></tr></thead>
            <tbody>
                @foreach($transfer->items as $item)
                <tr>
                    <td class="px-2 py-1 text-xs">{{ $item->product?->reference }} — {{ $item->product?->name }}</td>
                    <td class="px-2 py-1 text-right tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                    <td class="px-2 py-1 text-right">
                        <input type="number" name="received_quantities[{{ $item->id }}]"
                               min="0" max="{{ $item->quantity }}" step="0.0001"
                               value="{{ $item->quantity }}"
                               class="border border-gray-300 rounded px-2 py-1 text-sm text-right w-32 tabular-nums">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="document.getElementById('receive-form').classList.add('hidden')" class="border border-gray-300 text-gray-700 text-sm px-3 py-1.5 rounded-[4px]">Annuler</button>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Valider la réception</button>
        </div>
    </form>
    @endcan
    @endif

    {{-- Lignes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Articles ({{ $transfer->items->count() }})</h2></div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr>
                    <th class="px-3 py-1.5 text-left">Article</th>
                    <th class="px-3 py-1.5 text-left">Lot / Série</th>
                    <th class="px-3 py-1.5 text-left">DLC</th>
                    <th class="px-3 py-1.5 text-right">Qté expédiée</th>
                    <th class="px-3 py-1.5 text-right">Qté reçue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($transfer->items as $item)
                @php $discrepancy = $item->hasDiscrepancy(); @endphp
                <tr class="{{ $discrepancy ? 'bg-amber-50/40' : '' }}">
                    <td class="px-3 py-1.5">
                        <span class="font-mono text-xs text-blue-700">{{ $item->product?->reference }}</span>
                        <p class="text-sm">{{ $item->product?->name }}</p>
                    </td>
                    <td class="px-3 py-1.5 font-mono text-xs">{{ $item->lot_number ?? $item->serial_number ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-xs text-gray-600">{{ $item->expiry_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $discrepancy ? 'text-amber-700 font-medium' : 'text-gray-700' }}">
                        @if($item->received_quantity !== null)
                            {{ number_format($item->received_quantity, 2, ',', ' ') }}
                            @if($discrepancy)
                                <span class="text-xs text-amber-600 ml-1">(écart {{ number_format($item->received_quantity - $item->quantity, 2, ',', ' ') }})</span>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Audit trail --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Créé par</p>
                <p class="mt-0.5 text-gray-900">{{ $transfer->createdBy?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ $transfer->created_at?->format('d/m/Y H:i') }}</p>
            </div>
            @if($transfer->shipped_at)
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Expédié par</p>
                <p class="mt-0.5 text-gray-900">{{ $transfer->shippedBy?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ $transfer->shipped_at?->format('d/m/Y H:i') }}</p>
            </div>
            @endif
            @if($transfer->received_at)
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Reçu par</p>
                <p class="mt-0.5 text-gray-900">{{ $transfer->receivedBy?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ $transfer->received_at?->format('d/m/Y H:i') }}</p>
            </div>
            @endif
            @if($transfer->isCancelled())
            <div>
                <p class="text-xs font-medium text-red-500 uppercase">Annulé par</p>
                <p class="mt-0.5 text-red-700">{{ $transfer->cancelledBy?->name ?? '—' }}</p>
                <p class="text-xs text-red-500 italic">« {{ $transfer->reason }} »</p>
            </div>
            @endif
        </div>
    </div>

    <x-audit.timeline :model="\App\Models\StockTransfer::class" :id="$transfer->id" />
</div>
@endsection
