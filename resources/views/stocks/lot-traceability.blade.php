@extends('layouts.erp')
@section('title', 'Traçabilité lot ' . $lot->lot_number)

@section('breadcrumb')
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.lots') }}" class="hover:text-gray-700">Lots</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Traçabilité {{ $lot->lot_number }}</span>
@endsection

@section('content')
{{-- Entête lot --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Traçabilité inverse — Lot {{ $lot->lot_number }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">§8 CDC — Retrouver les clients livrés avec ce lot de matière</p>
    </div>
    <a href="{{ route('stocks.lots') }}" class="btn-secondary text-sm">← Retour aux lots</a>
</div>

{{-- Info lot --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Article</p>
        <p class="font-semibold text-gray-900">{{ $lot->product?->name ?? '—' }}</p>
        <p class="text-xs text-gray-500 font-mono">{{ $lot->product?->reference }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Dépôt</p>
        <p class="font-semibold text-gray-900">{{ $lot->warehouse?->name ?? '—' }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Statut</p>
        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium
            {{ $lot->status === 'disponible' ? 'bg-emerald-100 text-emerald-700' :
               ($lot->status === 'consomme' ? 'bg-gray-100 text-gray-600' : 'bg-rose-100 text-rose-700') }}">
            {{ $lot->statusLabel() }}
        </span>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Quantité</p>
        <p class="font-bold text-gray-900 tabular-nums">{{ number_format($lot->quantity, 2, ',', ' ') }}</p>
    </div>
</div>

{{-- [CDC §9.2] Fournisseur + réception --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Fournisseur</p>
        <p class="font-semibold text-gray-900">{{ $supplier?->name ?? '—' }}</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Date réception</p>
        <p class="font-semibold text-gray-900">{{ $lot->received_at?->format('d/m/Y') ?? '—' }}</p>
    </div>
    @if($coilsFromLot->isNotEmpty())
        @php($coil = $coilsFromLot->first())
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Poids initial / restant</p>
            <p class="font-semibold text-gray-900 tabular-nums">
                {{ number_format($coil->initial_weight, 2, ',', ' ') }} / {{ number_format($coil->remaining_weight, 2, ',', ' ') }} kg
            </p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-400 uppercase font-semibold mb-1">Largeur / Épaisseur / Couleur</p>
            <p class="font-semibold text-gray-900 text-sm">
                {{ $coil->width ?? '—' }} mm / {{ $coil->thickness ?? '—' }} mm / {{ $coil->color ?? '—' }}
            </p>
        </div>
    @endif
</div>

{{-- [CDC §9.2/§9.3] Certificats qualité associés au lot --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Certificats qualité</h3>
    </div>
    @if($certificates->isNotEmpty())
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Résultat</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Contrôleur</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($certificates as $cert)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5">
                    <a href="{{ route('qualite.certificats.show', $cert) }}" class="font-mono font-semibold text-emerald-700 hover:underline">
                        {{ $cert->number }}
                    </a>
                </td>
                <td class="px-3 py-1.5 text-gray-700">{{ $cert->typeLabel() }}</td>
                <td class="px-3 py-1.5 text-gray-500">{{ $cert->date_certificat?->format('d/m/Y') ?? '—' }}</td>
                <td class="px-3 py-1.5">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-{{ $cert->resultatColor() }}-100 text-{{ $cert->resultatColor() }}-700">
                        {{ $cert->resultatLabel() }}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $cert->controleur?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="px-5 py-6 text-sm text-gray-400">Aucun certificat qualité enregistré pour ce lot.</p>
    @endif
</div>

{{-- Clients impactés --}}
@if($impactedClients->isNotEmpty())
<div class="bg-rose-50 border border-rose-200 rounded-[4px] p-4 mb-6">
    <h3 class="text-sm font-bold text-rose-700 mb-3 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        {{ $impactedClients->count() }} client(s) impacté(s) par ce lot
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        @foreach($impactedClients as $client)
        <div class="bg-white rounded-[4px] px-3 py-2 border border-rose-100">
            <p class="font-semibold text-gray-900 text-sm">{{ $client->name }}</p>
            <p class="text-xs text-gray-500 font-mono">{{ $client->code }}</p>
        </div>
        @endforeach
    </div>
</div>
@else
<div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-4 mb-6">
    <p class="text-sm text-emerald-700 font-medium">Aucun client impacté trouvé pour ce lot.</p>
</div>
@endif

{{-- Ordres de fabrication --}}
@if($productionOrders->isNotEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Ordres de fabrication utilisant ce lot</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">OF</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Client</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Commande liée</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($productionOrders as $of)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5">
                    <a href="{{ route('production.orders.show', $of) }}" class="font-mono font-semibold text-emerald-700 hover:underline">
                        {{ $of->number }}
                    </a>
                </td>
                <td class="px-3 py-1.5 text-gray-900">{{ $of->client?->name ?? '—' }}</td>
                <td class="px-3 py-1.5 text-gray-700">{{ $of->product?->name ?? '—' }}</td>
                <td class="px-3 py-1.5">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        bg-{{ $of->statusColor() }}-100 text-{{ $of->statusColor() }}-700">
                        {{ $of->statusLabel() }}
                    </span>
                </td>
                <td class="px-3 py-1.5 font-mono text-gray-500">{{ $of->order?->number ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Factures liées --}}
@if($impactedInvoices->isNotEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Factures émises avec produits de ce lot</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Facture</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Client</th>
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant TTC</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($impactedInvoices as $invoice)
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-1.5">
                    <a href="{{ route('ventes.factures.show', $invoice) }}" class="font-mono font-semibold text-emerald-700 hover:underline">
                        {{ $invoice->number }}
                    </a>
                </td>
                <td class="px-3 py-1.5 text-gray-900">{{ $invoice->client?->name ?? '—' }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">
                    {{ number_format($invoice->total_ttc, 0, ',', ' ') }} F
                </td>
                <td class="px-3 py-1.5">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                        {{ $invoice->status }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Mouvements de stock --}}
<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Mouvements de stock du lot</h3>
    </div>
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300">
            <tr>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Dépôt</th>
                <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Par</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($movements as $mv)
            <tr>
                <td class="px-3 py-2.5 text-gray-500 tabular-nums">{{ $mv->occurred_at?->format('d/m/Y') }}</td>
                <td class="px-3 py-2.5">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        {{ $mv->type === 'entree' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                        {{ ucfirst($mv->type) }}
                    </span>
                </td>
                <td class="px-3 py-2.5 text-right tabular-nums font-semibold
                    {{ $mv->type === 'entree' ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $mv->type === 'sortie' ? '-' : '+' }}{{ number_format(abs($mv->quantity), 2, ',', ' ') }}
                </td>
                <td class="px-3 py-2.5 text-gray-600">{{ $mv->warehouse?->name ?? '—' }}</td>
                <td class="px-3 py-2.5 text-gray-500 text-xs">{{ $mv->createdBy?->name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun mouvement trouvé.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
