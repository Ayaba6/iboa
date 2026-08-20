@extends('layouts.erp')
@section('title', 'Commandes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Commandes</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-3">

    <x-sales.module-nav />

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total TTC filtré</p>
            <p class="text-[15px] font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-[12px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En cours</p>
            <p class="text-[15px] font-bold text-blue-600 tabular-nums">{{ $summary['count_confirmed'] }} <span class="text-[12px] font-normal text-gray-400">commande(s)</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Livrées</p>
            <p class="text-[15px] font-bold text-emerald-600 tabular-nums">{{ $summary['count_delivered'] }} <span class="text-[12px] font-normal text-gray-400">commande(s)</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Facturées</p>
            <p class="text-[15px] font-bold text-emerald-700 tabular-nums">{{ $summary['count_invoiced'] }} <span class="text-[12px] font-normal text-gray-400">commande(s)</span></p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[20px] font-bold text-gray-900 leading-tight">Commandes</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $orders->total() }} commande(s)</p>
        </div>
        <a href="{{ route('ventes.commandes.create') }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle commande
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        @php $lblX = 'block text-[11px] font-bold text-gray-700 mb-1'; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
            <label class="{{ $lblX }}">Rechercher</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, client…"
                   class="w-full h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div>
            <label class="{{ $lblX }}">Statut</label>
            <select name="status" class="h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="confirme"              {{ ($filters['status'] ?? '') === 'confirme'              ? 'selected' : '' }}>Confirmée</option>
                <option value="en_preparation"     {{ ($filters['status'] ?? '') === 'en_preparation'     ? 'selected' : '' }}>En préparation</option>
                <option value="partiellement_livre" {{ ($filters['status'] ?? '') === 'partiellement_livre' ? 'selected' : '' }}>Part. livrée</option>
                <option value="livre"              {{ ($filters['status'] ?? '') === 'livre'              ? 'selected' : '' }}>Livrée</option>
                <option value="facture"            {{ ($filters['status'] ?? '') === 'facture'            ? 'selected' : '' }}>Facturée</option>
                <option value="annule"             {{ ($filters['status'] ?? '') === 'annule'             ? 'selected' : '' }}>Annulée</option>
            </select>
            </div>

            <input type="text" name="client_id" value="{{ $filters['client_id'] ?? '' }}" placeholder="ID Client"
                   class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 hidden">

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search','status','client_id']))
                <a href="{{ route('ventes.commandes.index') }}"
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
                    <tr class="bg-[#3b4248] text-white text-[11px]">
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-32">N° commande</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap">Client</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden md:table-cell w-24">Date</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden lg:table-cell w-28">Livraison prévue</th>
                        <th class="text-right font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden lg:table-cell w-32">Montant HT</th>
                        <th class="text-right font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-32">Montant TTC</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-32">Statut</th>
                        <th class="px-3 py-2 w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.commandes.show', $order) }}" class="hover:underline font-semibold">{{ $order->number }}</a>
                            @if($order->reference)<p class="text-[11px] text-gray-400 font-sans">{{ $order->reference }}</p>@endif
                        </td>
                        <td class="px-3 py-1">
                            <span class="font-medium text-gray-900">{{ $order->client?->name ?? '—' }}</span>
                            @if($order->client?->trade_name)<p class="text-[11px] text-gray-400">{{ $order->client->trade_name }}</p>@endif
                        </td>
                        <td class="px-3 py-1 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1 text-gray-600 hidden lg:table-cell whitespace-nowrap">{{ $order->delivery_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700 hidden lg:table-cell whitespace-nowrap">{{ number_format($order->subtotal_ht, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ number_format($order->total_ttc, 0, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-center">
                            <x-workflow.status-badge :status="$order->status" :label="$order->status_label" size="sm" />
                        </td>
                        <td class="px-3 py-1">
                            <div class="flex items-center justify-end gap-0.5">
                                {{-- Voir --}}
                                <a href="{{ route('ventes.commandes.show', $order) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Modifier (draft ou confirmed) --}}
                                @if(in_array($order->status, ['brouillon', 'confirme']))
                                <a href="{{ route('ventes.commandes.edit', $order) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif
                                {{-- Supprimer (draft seulement) --}}
                                @if($order->status === 'brouillon')
                                <form action="{{ route('ventes.commandes.destroy', $order) }}" method="POST"
                                      data-confirm="Supprimer la commande {{ addslashes($order->number) }} ?">
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
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-[13px]">
                            Aucune commande trouvée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            <span>{{ $orders->total() }} commande(s) · Total TTC filtré : <b class="text-emerald-700 tabular-nums">{{ $fmt($summary['total_ttc']) }} FCFA</b></span>
            @if($orders->hasPages())<div>{{ $orders->appends($filters)->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">{{ array_filter($filters ?? []) ? implode(', ', array_keys(array_filter($filters))) : 'Aucun' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
