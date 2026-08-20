@extends('layouts.erp')
@section('title', 'Commandes fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Commandes fournisseurs</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total TTC filtré</p>
            <p class="text-[15px] font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-[12px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total HT filtré</p>
            <p class="text-[15px] font-bold text-gray-700 tabular-nums">{{ $fmt($summary['total_ht']) }} <span class="text-[12px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En cours</p>
            <p class="text-[15px] font-bold text-amber-600 tabular-nums">{{ $summary['count_confirmed'] }} <span class="text-[12px] font-normal text-gray-400">commande(s)</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Reçues</p>
            <p class="text-[15px] font-bold text-emerald-600 tabular-nums">{{ $summary['count_received'] }} <span class="text-[12px] font-normal text-gray-400">commande(s)</span></p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">Commandes fournisseurs</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $purchaseOrders->total() }} commande(s)</p>
        </div>
        <a href="{{ route('achats.commandes.create') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle commande
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, fournisseur..."
                   class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">

            <select name="supplier_id" class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"            {{ ($filters['status'] ?? '') === 'brouillon'            ? 'selected' : '' }}>Brouillon</option>
                <option value="envoye"               {{ ($filters['status'] ?? '') === 'envoye'               ? 'selected' : '' }}>Envoyé</option>
                <option value="confirme"             {{ ($filters['status'] ?? '') === 'confirme'             ? 'selected' : '' }}>Confirmé</option>
                <option value="partiellement_recu"   {{ ($filters['status'] ?? '') === 'partiellement_recu'   ? 'selected' : '' }}>Partiellement reçu</option>
                <option value="recu"                 {{ ($filters['status'] ?? '') === 'recu'                 ? 'selected' : '' }}>Reçu</option>
                <option value="facture"              {{ ($filters['status'] ?? '') === 'facture'              ? 'selected' : '' }}>Facturé</option>
                <option value="annule"               {{ ($filters['status'] ?? '') === 'annule'               ? 'selected' : '' }}>Annulé</option>
            </select>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'supplier_id', 'status']))
                <a href="{{ route('achats.commandes.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, montants HT/TTC --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="tbl-scroll">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-[#eef5f0] text-emerald-900 border-b border-gray-300">
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide w-32">N° commande</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide">Fournisseur</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden md:table-cell w-24">Date</th>
                        <th class="text-left font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell w-28">Livraison prévue</th>
                        <th class="text-right font-bold px-3 py-2 uppercase tracking-wide hidden lg:table-cell w-32">Montant HT</th>
                        <th class="text-right font-bold px-3 py-2 uppercase tracking-wide w-32">Montant TTC</th>
                        <th class="text-center font-bold px-3 py-2 uppercase tracking-wide w-28">Statut</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $po)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('achats.commandes.show', $po) }}" class="hover:underline font-semibold">{{ $po->number }}</a>
                        </td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $po->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $po->ordered_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 hidden lg:table-cell whitespace-nowrap">
                            @if($po->expected_at)
                                <span class="{{ $po->expected_at->isPast() && !in_array($po->status, ['recu','facture','annule']) ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ $po->expected_at->format('d/m/Y') }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700 hidden lg:table-cell whitespace-nowrap">{{ number_format($po->subtotal_ht, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($po->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @php
                                [$poBadge, $poLabel] = match($po->status) {
                                    'brouillon'          => ['bg-gray-100 text-gray-700', 'Brouillon'],
                                    'envoye'             => ['bg-blue-100 text-blue-700', 'Envoyé'],
                                    'confirme'           => ['bg-emerald-100 text-emerald-800', 'Confirmé'],
                                    'partiellement_recu' => ['bg-amber-100 text-amber-700', 'Part. reçu'],
                                    'recu'               => ['bg-green-100 text-green-700', 'Reçu'],
                                    'facture'            => ['bg-purple-100 text-purple-700', 'Facturé'],
                                    'annule'             => ['bg-red-100 text-red-700', 'Annulé'],
                                    default              => ['bg-gray-100 text-gray-600', $po->status],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $poBadge }}">{{ $poLabel }}</span>
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center justify-end gap-0.5">
                                {{-- Voir --}}
                                <a href="{{ route('achats.commandes.show', $po) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Modifier (brouillon seulement) --}}
                                @if($po->status === 'brouillon')
                                <a href="{{ route('achats.commandes.edit', $po) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('achats.commandes.destroy', $po) }}" method="POST"
                                      onsubmit="return confirm('Supprimer la commande {{ addslashes($po->number) }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-[13px]">Aucun résultat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            <span>{{ $purchaseOrders->total() }} commande(s) · Total TTC filtré : <b class="text-emerald-700 tabular-nums">{{ $fmt($summary['total_ttc']) }} FCFA</b> · HT : <b class="text-gray-700 tabular-nums">{{ $fmt($summary['total_ht']) }} FCFA</b></span>
            @if($purchaseOrders->hasPages())<div>{{ $purchaseOrders->appends($filters)->links() }}</div>@endif
        </div>
    </div>

</div>
@endsection
