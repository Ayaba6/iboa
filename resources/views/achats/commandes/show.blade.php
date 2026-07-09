@extends('layouts.erp')
@section('title', 'Commande '.$purchaseOrder->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.index') }}" class="hover:text-gray-700">Commandes fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium font-mono">{{ $purchaseOrder->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ── Workflow progress ──────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 px-5 py-4">
        <x-workflow.progress-steps
            :steps="[
                ['key' => 'brouillon',          'label' => 'Brouillon',   'icon' => '✏️'],
                ['key' => 'envoye',             'label' => 'Envoyé',      'icon' => '📬'],
                ['key' => 'confirme',           'label' => 'Confirmé',    'icon' => '✅'],
                ['key' => 'approuvee',          'label' => 'Approuvé',    'icon' => '👍'],
                ['key' => 'partiellement_recu', 'label' => 'Partiel',     'icon' => '📦'],
                ['key' => 'recu',               'label' => 'Reçu',        'icon' => '🏭'],
                ['key' => 'cloture',            'label' => 'Clôturé',     'icon' => '🔒'],
            ]"
            :current="$purchaseOrder->status"
            size="sm"
        />
    </div>

    {{-- ================================================================
         Header
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $purchaseOrder->number }}</h1>
                @switch($purchaseOrder->status)
                    @case('brouillon')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-gray-100 text-gray-700">Brouillon</span>
                        @break
                    @case('envoye')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-blue-100 text-blue-700">Envoyé</span>
                        @break
                    @case('confirme')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-emerald-100 text-emerald-800">Confirmé</span>
                        @break
                    @case('partiellement_recu')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-amber-100 text-amber-700">Partiellement reçu</span>
                        @break
                    @case('recu')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-green-100 text-green-700">Reçu</span>
                        @break
                    @case('facture')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-purple-100 text-purple-700">Facturé</span>
                        @break
                    @case('annule')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-red-100 text-red-700">Annulé</span>
                        @break
                @endswitch
                <span class="text-gray-500 text-sm">{{ $purchaseOrder->supplier?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                {{-- Créer réception (confirme / partiellement_recu / envoye) --}}
                @if(in_array($purchaseOrder->status, ['confirme', 'partiellement_recu', 'envoye']))
                <form action="{{ route('achats.commandes.reception', $purchaseOrder) }}" method="POST"
                      onsubmit="return confirm('Créer une réception pour cette commande ?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-[4px] text-sm font-medium hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Créer réception
                    </button>
                </form>
                @endif

                {{-- Créer facture fournisseur (confirme / partiellement_recu / recu) --}}
                @if(in_array($purchaseOrder->status, ['confirme', 'partiellement_recu', 'recu', 'envoye']))
                <form action="{{ route('achats.commandes.facture', $purchaseOrder) }}" method="POST"
                      onsubmit="return confirm('Créer une facture fournisseur pour cette commande ?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-[4px] text-sm font-medium hover:bg-amber-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Créer facture fournisseur
                    </button>
                </form>
                @endif

                {{-- PDF --}}
                <a href="{{ route('achats.commandes.pdf', $purchaseOrder) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 transition-colors"
                   data-loading data-loading-text="Génération du bon de commande…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    PDF
                </a>

                {{-- Confirmer la commande (brouillon seulement) --}}
                @if($purchaseOrder->status === 'brouillon')
                @can('purchase_orders.create')
                <form action="{{ route('achats.commandes.confirm', $purchaseOrder) }}" method="POST"
                      onsubmit="return confirm('Confirmer cette commande ? Elle ne pourra plus être modifiée.')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-[4px] text-sm font-medium hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Confirmer la commande
                    </button>
                </form>
                @endcan
                @endif

                {{-- Dupliquer en brouillon (toujours disponible) --}}
                @can('purchase_orders.create')
                <form action="{{ route('achats.commandes.duplicate', $purchaseOrder) }}" method="POST"
                      onsubmit="return confirm('Dupliquer ce bon de commande en brouillon ?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-slate-600 text-white rounded-[4px] text-sm font-medium hover:bg-slate-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Dupliquer
                    </button>
                </form>
                @endcan

                {{-- Modifier (brouillon seulement) --}}
                @if($purchaseOrder->status === 'brouillon')
                <a href="{{ route('achats.commandes.edit', $purchaseOrder) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                @endif

                {{-- Supprimer (brouillon seulement) --}}
                @if($purchaseOrder->status === 'brouillon')
                <form action="{{ route('achats.commandes.destroy', $purchaseOrder) }}" method="POST"
                      onsubmit="return confirm('Supprimer définitivement cette commande ?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer
                    </button>
                </form>
                @endif

                <a href="{{ route('achats.commandes.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================
         2-column: info + summary
    ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Info card --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Fournisseur</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $purchaseOrder->supplier?->name ?? '—' }}</dd>
                    @if($purchaseOrder->supplier?->city)
                    <dd class="text-gray-500 text-xs">{{ $purchaseOrder->supplier->city }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $purchaseOrder->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date de commande</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $purchaseOrder->ordered_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</dt>
                    <dd class="mt-0.5 {{ $purchaseOrder->expected_at?->isPast() && !in_array($purchaseOrder->status, ['recu','facture','annule']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                        {{ $purchaseOrder->expected_at?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                @if($purchaseOrder->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $purchaseOrder->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Right: Summary --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($purchaseOrder->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($purchaseOrder->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-amber-700 tabular-nums">{{ number_format($purchaseOrder->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
    </div>

    {{-- ================================================================
         Items table
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de commande</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix Unit.</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Remise%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">TVA%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($purchaseOrder->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">
                            {{ $item->description }}
                            @if($item->product)
                            <p class="text-xs text-gray-400">{{ $item->product->reference }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun résultat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================
         Linked Receptions
    ================================================================ --}}
    @if($purchaseOrder->receptions->count())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Réceptions liées</h2>
            <span class="text-sm text-gray-500">{{ $purchaseOrder->receptions->count() }} réception(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date de réception</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Type</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($purchaseOrder->receptions as $reception)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-mono font-semibold text-gray-800">{{ $reception->number }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $reception->received_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center text-gray-600 capitalize">{{ $reception->type }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @switch($reception->status)
                                @case('brouillon')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-700">Brouillon</span>
                                    @break
                                @case('valide')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Validé</span>
                                    @break
                                @case('annule')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">Annulé</span>
                                    @break
                            @endswitch
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ================================================================
         Linked Supplier Invoices
    ================================================================ --}}
    @if($purchaseOrder->supplierInvoices->count())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Factures fournisseurs liées</h2>
            <span class="text-sm text-gray-500">{{ $purchaseOrder->supplierInvoices->count() }} facture(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date réception</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Échéance</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant TTC</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($purchaseOrder->supplierInvoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5">
                            <a href="{{ route('achats.factures-fournisseurs.show', $invoice) }}"
                               class="font-mono font-semibold text-amber-600 hover:text-amber-800">
                                {{ $invoice->number }}
                            </a>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $invoice->received_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-gray-900">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-center">
                            @switch($invoice->status)
                                @case('recue')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-700">Reçue</span>
                                    @break
                                @case('validee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">Validée</span>
                                    @break
                                @case('payee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Payée</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">{{ $invoice->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-3 py-1.5">
                            <a href="{{ route('achats.factures-fournisseurs.show', $invoice) }}"
                               class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Voir">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Pièces jointes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <x-attachments.manager model="PurchaseOrder" :id="$purchaseOrder->id" />
    </div>


    {{-- [LIAISONS] RFQ source · Réceptions · Factures FF --}}
    @php
        $relatedLinks = [];
        // RFQ source (cherchée via FK sur rfqs)
        $sourceRfq = \App\Models\Rfq::where('purchase_order_id', $purchaseOrder->id)->first();
        if ($sourceRfq) {
            $relatedLinks[] = [
                'icon' => '📋', 'label' => 'RFQ source ' . $sourceRfq->number,
                'href' => route('achats.rfq.show', $sourceRfq),
                'subtitle' => $sourceRfq->title,
                'badge' => $sourceRfq->statusLabel(), 'badgeColor' => $sourceRfq->statusColor(),
            ];
        }
        $receptions = \App\Models\Reception::where('purchase_order_id', $purchaseOrder->id)->whereNull('deleted_at')->get();
        foreach ($receptions as $r) {
            $relatedLinks[] = [
                'icon' => '📦', 'label' => 'Réception ' . $r->number,
                'href' => route('achats.receptions.show', $r),
                'badge' => ucfirst((string) $r->status), 'badgeColor' => $r->status === 'validee' ? 'emerald' : 'amber',
            ];
        }
        $supplierInvoices = \App\Models\SupplierInvoice::where('purchase_order_id', $purchaseOrder->id)->whereNull('deleted_at')->get();
        foreach ($supplierInvoices as $si) {
            $relatedLinks[] = [
                'icon' => '🧾', 'label' => 'Facture FF ' . $si->number,
                'href' => route('achats.factures-fournisseurs.show', $si),
                'subtitle' => number_format($si->total_ttc, 0, ',', ' ') . ' FCFA · ' . ($si->supplier_invoice_number ?? '—'),
                'badge' => ucfirst((string) $si->status), 'badgeColor' => $si->status === 'payee' ? 'emerald' : 'orange',
            ];
        }
    @endphp
    <x-document.related :links="$relatedLinks" />

    <x-audit.timeline :model="\App\Models\PurchaseOrder::class" :id="$purchaseOrder->id" />

</div>
@endsection
