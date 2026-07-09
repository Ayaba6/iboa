@extends('layouts.erp')
@section('title', 'BL '.$deliveryNote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.index') }}" class="hover:text-gray-700">Bons de livraison</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $deliveryNote->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => 'livraison',
        'quote'        => $deliveryNote->order?->quote ?? null,
        'order'        => $deliveryNote->order ?? null,
        'deliveryNote' => $deliveryNote,
        'invoice'      => $deliveryNote->invoices->first() ?? ($deliveryNote->order?->invoices->first() ?? null),
    ])

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $deliveryNote->number }}</h1>
                <x-workflow.status-badge :status="$deliveryNote->status" :label="$deliveryNote->status_label" />
                <span class="text-gray-500 text-sm">{{ $deliveryNote->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                {{-- PDF (toujours disponible) --}}
                <a href="{{ route('ventes.bons-livraison.pdf', $deliveryNote) }}?preview=1" target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>
                <a href="{{ route('ventes.bons-livraison.pdf', $deliveryNote) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors"
                   data-loading data-loading-text="Génération du bon de livraison…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>

                @php
                    $blBtnO  = 'inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors';
                    $blBtnP  = 'inline-flex items-center gap-2 px-3 py-1.5 text-white rounded-[4px] text-sm font-semibold shadow-sm transition-colors';
                    $blBtnWO = 'inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-[4px] text-sm font-medium hover:bg-orange-50 transition-colors';
                    $blBtnDO = 'inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50 transition-colors';
                @endphp

                {{-- ── BROUILLON : Modifier + Soumettre ───────────────────────────────── --}}
                @if($deliveryNote->status === 'brouillon')
                    <a href="{{ route('ventes.bons-livraison.edit', $deliveryNote) }}" class="{{ $blBtnO }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Modifier
                    </a>
                    @can('sales.submit')
                    <form action="{{ route('ventes.bons-livraison.submit', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Soumettre ce bon de livraison à la validation interne ?')">
                        @csrf
                        <button type="submit" class="{{ $blBtnP }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    @endcan
                @endif

                {{-- ── EN ATTENTE DE VALIDATION ────────────────────────────────────────── --}}
                @if($deliveryNote->status === 'en_attente_validation')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    @can('sales.validate')
                    <form action="{{ route('ventes.bons-livraison.validate-internal', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Valider ce bon de livraison ? Le stock sera décrémenté.')">
                        @csrf
                        <button type="submit" class="{{ $blBtnP }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider la livraison
                        </button>
                    </form>
                    <form action="{{ route('ventes.bons-livraison.reject-internal', $deliveryNote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $blBtnWO }}">Refuser</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $blBtnO }}">Annuler</button>
                                    <button type="submit" class="{{ $blBtnP }} bg-emerald-700 hover:bg-emerald-800">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                    @can('sales.cancel')
                    <form action="{{ route('ventes.bons-livraison.cancel-internal', $deliveryNote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $blBtnDO }}">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $blBtnO }}">Fermer</button>
                                    <button type="submit" class="{{ $blBtnP }} bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- Validé: Créer Facture --}}
                @if($deliveryNote->status === 'valide')
                    <form action="{{ route('ventes.bons-livraison.invoice', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis ce bon de livraison ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                @endif

                <a href="{{ route('ventes.bons-livraison.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- Letterhead : logo + infos société + badge document --}}
    @include('partials._doc-letterhead', [
        'docType'   => 'BON DE LIVRAISON',
        'docNumber' => $deliveryNote->number,
        'docDate'   => $deliveryNote->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'valide'               => ['label' => 'Validé',                  'class' => 'bg-green-100 text-green-700'],
            'livre'                => ['label' => 'Livré',                   'class' => 'bg-purple-100 text-purple-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-red-100 text-red-700'],
        ][$deliveryNote->status] ?? ['label' => ucfirst($deliveryNote->status), 'class' => 'bg-gray-100 text-gray-700'],
        'docExtra'  => array_values(array_filter([
            $deliveryNote->client    ? ['label' => 'Client',      'value' => $deliveryNote->client->name]          : null,
            $deliveryNote->warehouse ? ['label' => 'Entrepôt',    'value' => $deliveryNote->warehouse->name]       : null,
            $deliveryNote->order     ? ['label' => 'Commande',    'value' => $deliveryNote->order->number]         : null,
        ])),
    ])

    {{-- Info card --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $deliveryNote->client?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $deliveryNote->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Entrepôt</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->warehouse?->name ?? '—' }}</dd>
                </div>
                @if($deliveryNote->delivery_address)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->delivery_address }}</dd>
                </div>
                @endif
                @if($deliveryNote->carrier)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Transporteur</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->carrier }}</dd>
                </div>
                @endif
                @if($deliveryNote->tracking_number)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">N° suivi</dt>
                    <dd class="mt-0.5 text-gray-700 font-mono">{{ $deliveryNote->tracking_number }}</dd>
                </div>
                @endif
                @if($deliveryNote->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $deliveryNote->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5 h-fit space-y-3">
            <h2 class="text-base font-semibold text-gray-900">Quantités</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total articles</span>
                <span class="font-semibold text-gray-900">{{ $deliveryNote->items->count() }} ligne(s)</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total quantité</span>
                <span class="font-semibold text-gray-900">{{ number_format($deliveryNote->total_quantity, 2, ',', ' ') }}</span>
            </div>
        </div>
    </div>

    {{-- Articles livrés --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Articles livrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Réf. produit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Quantité</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">N° Lot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deliveryNote->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">
                            {{ $item->description }}
                            @if($item->expiry_date)
                                <p class="text-xs text-orange-500">Exp. : {{ $item->expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs font-mono hidden md:table-cell">{{ $item->product?->reference ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-gray-900">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-gray-500 text-xs hidden lg:table-cell">{{ $item->lot_number ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Workflow validation interne ─────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <x-workflow.status-badge :status="$deliveryNote->status" :label="$deliveryNote->status_label" />
        </div>
        @if($deliveryNote->rejection_reason)
            <div class="mb-4 rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> {{ $deliveryNote->rejection_reason }}
            </div>
        @endif
        {{-- Les actions de workflow sont dans la barre du header — ici : historique seul. --}}
        <x-workflow.history :document="$deliveryNote" />
    </div>

    {{-- Documents liés --}}
    @php
        $relatedLinks = [];
        if ($deliveryNote->order) {
            $relatedLinks[] = [
                'icon'       => '📦',
                'label'      => 'Commande ' . $deliveryNote->order->number,
                'href'       => route('ventes.commandes.show', $deliveryNote->order),
                'badge'      => $deliveryNote->order->status_label ?? ucfirst($deliveryNote->order->status),
                'badgeColor' => 'blue',
            ];
            if ($deliveryNote->order->quote) {
                $relatedLinks[] = [
                    'icon'       => '📋',
                    'label'      => 'Devis ' . $deliveryNote->order->quote->number,
                    'href'       => route('ventes.devis.show', $deliveryNote->order->quote),
                    'badge'      => $deliveryNote->order->quote->status_label ?? ucfirst($deliveryNote->order->quote->status),
                    'badgeColor' => 'gray',
                ];
            }
        }
        foreach ($deliveryNote->invoices ?? [] as $inv) {
            $relatedLinks[] = [
                'icon'       => '🧾',
                'label'      => 'Facture ' . $inv->number,
                'href'       => route('ventes.factures.show', $inv),
                'badge'      => $inv->status_label ?? ucfirst($inv->status),
                'badgeColor' => 'green',
            ];
        }
    @endphp
    @if(count($relatedLinks))
        <x-document.related :links="$relatedLinks" title="Documents liés à ce bon de livraison" />
    @endif

    {{-- Audit timeline --}}
    <x-audit.timeline :model="\App\Models\DeliveryNote::class" :id="$deliveryNote->id"
                      title="Historique du bon de livraison" />

</div>
@endsection
