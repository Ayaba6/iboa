@extends('layouts.erp')
@section('title', 'Bons de livraison')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bons de livraison</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Total BL (filtré)</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Brouillons</p>
            <p class="text-[16px] font-bold text-gray-500 tabular-nums">{{ $summary['count_draft'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Validés</p>
            <p class="text-[16px] font-bold text-emerald-600 tabular-nums">{{ $summary['count_validated'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Facturés</p>
            <p class="text-[16px] font-bold text-emerald-700 tabular-nums">{{ $summary['count_invoiced'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Bons de livraison</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $deliveryNotes->total() }} bon(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-[4px] border border-gray-300 p-4">
        @php $lblX = 'block text-[11px] font-bold text-gray-700 mb-1'; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
            <label class="{{ $lblX }}">Rechercher</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Numéro, client…"
                   class="w-full h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div>
            <label class="{{ $lblX }}">Statut</label>
            <select name="status" class="h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="valide"                {{ ($filters['status'] ?? '') === 'valide'                ? 'selected' : '' }}>Validé</option>
                <option value="livre"     {{ ($filters['status'] ?? '') === 'livre'     ? 'selected' : '' }}>Livré</option>
                <option value="annule"    {{ ($filters['status'] ?? '') === 'annule'    ? 'selected' : '' }}>Annulé</option>
            </select>
            </div>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'client_id', 'order_id']))
                <a href="{{ route('ventes.bons-livraison.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#3b4248]">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap">Client</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Commande</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap hidden lg:table-cell">Entrepôt</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deliveryNotes as $dn)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-1.5">
                            <a href="{{ route('ventes.bons-livraison.show', $dn) }}"
                               class="font-mono font-semibold text-emerald-700 hover:text-emerald-900">
                                {{ $dn->number }}
                            </a>
                        </td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">
                            {{ $dn->client?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5 hidden md:table-cell">
                            @if($dn->order)
                                <a href="{{ route('ventes.commandes.show', $dn->order) }}"
                                   class="font-mono text-blue-600 hover:text-blue-800 text-xs">
                                    {{ $dn->order->number }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell">
                            {{ $dn->issued_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 hidden lg:table-cell">
                            {{ $dn->warehouse?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            <x-workflow.status-badge :status="$dn->status" :label="$dn->status_label" size="sm" />
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('ventes.bons-livraison.show', $dn) }}"
                                   class="p-1.5 text-gray-400 hover:text-emerald-700 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('ventes.bons-livraison.pdf', $dn) }}" target="_blank"
                                   class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                @if($dn->status !== 'valide')
                                <form action="{{ route('ventes.bons-livraison.validate', $dn) }}" method="POST"
                                      onsubmit="return confirm('Valider le BL {{ addslashes($dn->number) }} ? Le stock sera décrémenté.')">
                                    @csrf
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucun bon de livraison trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($deliveryNotes->hasPages())
        <div class="px-3 py-1.5 border-t border-gray-100">
            {{ $deliveryNotes->appends($filters)->links() }}
        </div>
        @endif
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
