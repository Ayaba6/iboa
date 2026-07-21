@extends('layouts.erp')
@section('title', 'Commande '.$order->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $order->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => 'commande',
        'quote'        => $order->quote ?? null,
        'order'        => $order,
        'deliveryNote' => $order->deliveryNotes->first() ?? null,
        'invoice'      => $order->invoices->first() ?? null,
    ])

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2.5 bg-gradient-to-b from-gray-50 to-white">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[15px] font-bold text-gray-900 font-mono">{{ $order->number }}</h1>
                <x-workflow.status-badge :status="$order->status" :label="$order->status_label" />
                <span class="text-gray-500 text-sm">{{ $order->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                @php
                    $btnO  = 'inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-semibold hover:bg-gray-50 transition-colors';
                    $btnP  = 'inline-flex items-center gap-2 px-3 py-1.5 text-white rounded-[4px] text-sm font-semibold transition-colors';
                    $btnWO = 'inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-[4px] text-sm font-semibold hover:bg-orange-50 transition-colors';
                    $btnDO = 'inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-[4px] text-sm font-semibold hover:bg-red-50 transition-colors';
                @endphp

                {{-- ── BROUILLON : Modifier + Soumettre + Supprimer ───────────────────── --}}
                @if($order->status === 'brouillon')
                    <a href="{{ route('ventes.commandes.edit', $order) }}" class="{{ $btnO }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifier
                    </a>
                    @can('sales.submit')
                    <form action="{{ route('ventes.commandes.submit', $order) }}" method="POST"
                          onsubmit="return confirm('Soumettre cette commande à la validation interne ?')">
                        @csrf
                        <button type="submit" class="{{ $btnP }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    @endcan
                    @can('delete', $order)
                    <form action="{{ route('ventes.commandes.destroy', $order) }}" method="POST"
                          onsubmit="return confirm('Supprimer définitivement cette commande brouillon ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $btnDO }}">Supprimer</button>
                    </form>
                    @endcan
                @endif

                {{-- ── EN ATTENTE DE VALIDATION ────────────────────────────────────────── --}}
                @if($order->status === 'en_attente_validation')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    @can('sales.validate')
                    <form action="{{ route('ventes.commandes.validate-internal', $order) }}" method="POST"
                          onsubmit="return confirm('Valider cette commande ?')">
                        @csrf
                        <button type="submit" class="{{ $btnP }} bg-emerald-600 hover:bg-emerald-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider
                        </button>
                    </form>
                    <form action="{{ route('ventes.commandes.reject-internal', $order) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $btnWO }}">Refuser</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $btnO }}">Annuler</button>
                                    <button type="submit" class="{{ $btnP }} bg-emerald-700 hover:bg-emerald-800">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                    @can('sales.cancel')
                    <form action="{{ route('ventes.commandes.cancel-internal', $order) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $btnDO }}">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $btnO }}">Fermer</button>
                                    <button type="submit" class="{{ $btnP }} bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- Confirmé: [paiement cash si applicable] + Créer BL + Annuler.
                     Facturation interdite avant livraison — le bouton « Créer facture »
                     n'apparaît qu'à partir de « en préparation » (un BL existe). --}}
                @if($order->status === 'confirme')
                    {{-- [CDC §5] Client cash : caissier enregistre paiement → déclenche BP --}}
                    @if($order->client?->isCash() && !$order->hasBonPreparation())
                    @can('payments.create')
                    <div x-data="{ open: false }">
                        <button type="button" @click="open = true"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Enregistrer paiement
                        </button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-4">Enregistrer le paiement comptant</h3>
                                <form action="{{ route('ventes.commandes.register-payment', $order) }}" method="POST">
                                    @csrf
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Montant reçu (XOF) <span class="text-red-500">*</span></label>
                                            <input type="number" name="payment_amount" min="1" required
                                                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-transparent"
                                                   placeholder="Ex: 150000">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Référence / Reçu</label>
                                            <input type="text" name="payment_reference" maxlength="100"
                                                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-transparent"
                                                   placeholder="Numéro de reçu caisse…">
                                        </div>
                                        <p class="text-xs text-gray-500">Un bon de préparation sera automatiquement créé et le magasinier notifié.</p>
                                    </div>
                                    <div class="flex justify-end gap-2 mt-5">
                                        <button type="button" @click="open = false" class="{{ $btnO }}">Annuler</button>
                                        <button type="submit" class="{{ $btnP }} bg-green-600 hover:bg-green-700">Confirmer le paiement</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                    @endif

                    {{-- [Flux tôle bac §3] Gérant : approuver une commande NON réglée pour production --}}
                    @if($order->production_approved)
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-[4px] text-sm font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Approuvée pour production
                        </span>
                    @elseif(!$order->hasBonPreparation())
                    @can('production.approve_financial')
                    <div x-data="{ open: false }">
                        <button type="button" @click="open = true" class="inline-flex items-center gap-2 px-3 py-2 border border-blue-300 text-blue-700 bg-white rounded-[4px] text-sm font-semibold hover:bg-blue-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Approuver pour production
                        </button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-1">Approbation exceptionnelle pour production</h3>
                                <p class="text-xs text-gray-500 mb-4">Commande non réglée — l'approbation autorise la fabrication sans encaissement préalable. Elle est tracée (motif, montant non réglé, validité).</p>
                                <form action="{{ route('ventes.commandes.approve-production', $order) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Motif <span class="text-red-500">*</span></label>
                                        <textarea name="motif" rows="3" required minlength="5" maxlength="500"
                                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500"
                                                  placeholder="Ex. : client historique, engagement de règlement sous 8 jours…"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Validité (jours) <span class="text-gray-400 font-normal">— vide = sans limite</span></label>
                                        <input type="number" name="valide_jours" min="1" max="90"
                                               class="w-32 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="Ex. 15">
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <button type="button" @click="open = false" class="{{ $btnO }}">Annuler</button>
                                        <button type="submit" class="{{ $btnP }} bg-blue-600 hover:bg-blue-700">Approuver</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                    @endif

                    @php $activeBp = $order->activeBonPreparation(); @endphp
                    @if($order->isReadyForDelivery())
                    <form action="{{ route('ventes.commandes.delivery-note', $order) }}" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison depuis cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Créer BL
                        </button>
                    </form>
                    @elseif($activeBp)
                    {{-- [CDC §13.7] BL verrouillé tant que le chargement n'est pas terminé --}}
                    <a href="{{ route('ventes.bons-preparation.show', $activeBp) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-[4px] text-sm font-semibold hover:bg-amber-100 transition-colors"
                       title="Le bon de livraison sera disponible une fois le chargement terminé (§13.7)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        BL verrouillé — {{ $activeBp->number }} : {{ $activeBp->status_label }}
                    </a>
                    @endif
                    <form action="{{ route('ventes.commandes.cancel', $order) }}" method="POST"
                          onsubmit="return confirm('Annuler cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-red-300 text-red-600 rounded-[4px] text-sm font-semibold hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Annuler
                        </button>
                    </form>
                @endif

                {{-- En préparation / Partiellement livré: Créer BL supplémentaire + Créer Facture --}}
                @if(in_array($order->status, ['en_preparation', 'partiellement_livre']))
                    <form action="{{ route('ventes.commandes.delivery-note', $order) }}" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison complémentaire ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Nouveau BL
                        </button>
                    </form>
                    <form action="{{ route('ventes.commandes.invoice', $order) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                @endif

                {{-- Livré: Créer Facture --}}
                @if($order->status === 'livre')
                    <form action="{{ route('ventes.commandes.invoice', $order) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                @endif

                {{-- [CDC §2] Annulée : réouverture par responsable hiérarchique uniquement --}}
                @if($order->status === 'annule')
                    @can('reopen', $order)
                    <form action="{{ route('ventes.commandes.reopen', $order) }}" method="POST"
                          onsubmit="return confirm('Réouvrir cette commande annulée ? Elle repassera en brouillon.')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Réouvrir la commande
                        </button>
                    </form>
                    @endcan
                @endif

                {{-- Lancer en production — seulement après validation financière (§13.1/§13.2) :
                     un OF ne se crée pas sur une commande brouillon ou en attente. --}}
                @can('production.create')
                @if(in_array($order->status, ['confirme', 'en_preparation', 'partiellement_livre', 'livre', 'facture']))
                    <a href="{{ route('production.orders.create', ['order_id' => $order->id]) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Lancer en production
                    </a>
                @endif
                @endcan

                <a href="{{ route('ventes.commandes.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-semibold hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- Letterhead : logo + infos société + badge document --}}
    @php
        $statusMapCmd = [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'confirme'             => ['label' => 'Confirmé',                'class' => 'bg-blue-100 text-blue-700'],
            'en_preparation'       => ['label' => 'En préparation',          'class' => 'bg-yellow-100 text-yellow-700'],
            'partiellement_livre'  => ['label' => 'Part. livré',             'class' => 'bg-orange-100 text-orange-700'],
            'livre'                => ['label' => 'Livré',                   'class' => 'bg-green-100 text-green-700'],
            'facture'              => ['label' => 'Facturé',                 'class' => 'bg-purple-100 text-purple-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'COMMANDE',
        'docNumber' => $order->number,
        'docDate'   => $order->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusMapCmd[$order->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $order->client        ? ['label' => 'Client',            'value' => $order->client->name]                  : null,
            $order->delivery_date ? ['label' => 'Livraison prévue',  'value' => $order->delivery_date->format('d/m/Y')] : null,
        ])),
    ])

    {{-- ── KPI de progression ────────────────────────────────────────────── --}}
    @php
        $qtyTotal     = (float) $order->items->sum('quantity');
        $qtyDelivered = (float) $order->items->sum('delivered_quantity');
        $pctDelivered = $qtyTotal > 0 ? min(100, round($qtyDelivered / $qtyTotal * 100)) : 0;
        $pctInvoiced  = $order->total_ttc > 0 ? min(100, round($order->invoiced_amount / $order->total_ttc * 100)) : 0;
        $docsCount    = $order->deliveryNotes->count() + $order->invoices->count();
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Total TTC</p>
            <p class="text-[16px] font-bold text-blue-700 tabular-nums">{{ number_format($order->total_ttc, 0, ',', ' ') }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500">Livraison</p>
                <p class="text-xs font-semibold {{ $pctDelivered >= 100 ? 'text-green-600' : 'text-gray-700' }} tabular-nums">{{ $pctDelivered }}%</p>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full {{ $pctDelivered >= 100 ? 'bg-green-500' : 'bg-emerald-600' }} transition-all" style="width: {{ $pctDelivered }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-gray-400 tabular-nums">{{ number_format($qtyDelivered, 0, ',', ' ') }} / {{ number_format($qtyTotal, 0, ',', ' ') }} unités</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500">Facturation</p>
                <p class="text-xs font-semibold {{ $pctInvoiced >= 100 ? 'text-green-600' : 'text-gray-700' }} tabular-nums">{{ $pctInvoiced }}%</p>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full {{ $pctInvoiced >= 100 ? 'bg-green-500' : 'bg-emerald-600' }} transition-all" style="width: {{ $pctInvoiced }}%"></div>
            </div>
            <p class="mt-1.5 text-xs text-gray-400 tabular-nums">{{ number_format($order->invoiced_amount, 0, ',', ' ') }} FCFA facturés</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-xs text-gray-500">Documents liés</p>
            <p class="text-[16px] font-bold text-gray-900 tabular-nums">{{ $docsCount }}</p>
            <p class="text-xs text-gray-400">{{ $order->deliveryNotes->count() }} BL · {{ $order->invoices->count() }} facture(s)</p>
        </div>
    </div>

    {{-- 2 colonnes: info + résumé --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Informations
            </h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $order->client?->name ?? '—' }}</dd>
                    @if($order->client)
                    <dd class="mt-1">
                        @if($order->client->is_tax_exempt)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-100 text-amber-700" title="{{ $order->client->tax_exemption_reason }}">Exonéré TVA</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Assujetti TVA</span>
                        @endif
                        @if($order->client->tax_regime)<span class="ml-1 text-xs text-gray-400">{{ $order->client->tax_regime }}</span>@endif
                    </dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $order->number }}</dd>
                    @if($order->reference)
                    <dd class="text-gray-500 text-xs">Réf : {{ $order->reference }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date commande</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->delivery_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                @if($order->delivery_address)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->delivery_address }}</dd>
                </div>
                @endif
                @if($order->quote)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Devis d'origine</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.devis.show', $order->quote) }}" class="text-blue-600 hover:underline font-mono">{{ $order->quote->number }}</a>
                    </dd>
                </div>
                @endif
                @if($order->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $order->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Récapitulatif
            </h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($order->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($order->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->global_discount_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— {{ number_format($order->global_discount_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-blue-700 tabular-nums">{{ number_format($order->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->invoiced_amount > 0)
            <div class="flex justify-between text-sm text-gray-500 border-t border-gray-100 pt-2">
                <span>Déjà facturé</span>
                <span class="tabular-nums">{{ number_format($order->invoiced_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Suivi production (cockpit Vente → Production) ══ --}}
    @if(isset($productionSummary))
    @php $ps = $productionSummary; $agg = $ps['aggregate']; @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-900">Suivi production</h2>
                @php $ac = ['gray'=>'bg-gray-100 text-gray-600','green'=>'bg-green-100 text-green-700','sky'=>'bg-sky-100 text-sky-700','amber'=>'bg-amber-100 text-amber-700','teal'=>'bg-emerald-100 text-emerald-800','red'=>'bg-red-100 text-red-700'][$agg['color']] ?? 'bg-gray-100 text-gray-600'; @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $ac }}">{{ $agg['label'] }}</span>
            </div>
            @can('production.create')
            @if(!in_array($order->status, ['brouillon','annule']))
            <a href="{{ route('production.orders.create', ['order_id' => $order->id]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ $ps['count'] ? 'Nouvel OF' : 'Lancer en production' }}
            </a>
            @endif
            @endcan
        </div>

        @if($ps['count'])
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="px-5 py-2">N° OF</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2 text-right">Demandé</th>
                        <th class="px-3 py-2 text-right">Produit</th>
                        <th class="px-3 py-2">Qualité</th>
                        <th class="px-3 py-2">PF stock</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($ps['orders'] as $of)
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs text-emerald-700">{{ $of['number'] }}</td>
                        <td class="px-3 py-2.5">
                            @php $sc = match($of['status']){ 'brouillon'=>'bg-gray-100 text-gray-600','lance'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-sky-100 text-sky-700','termine'=>'bg-green-100 text-green-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $sc }}">{{ $of['status_label'] }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($of['qty_requested'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-900">{{ number_format($of['qty_produced'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5">
                            @if($of['qc_status'])
                                @php $qc = match($of['qc_status']){ 'conforme'=>'bg-green-100 text-green-700','a_reprendre'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $qc }}">{{ $of['qc_label'] }}</span>
                            @else <span class="text-gray-400 text-xs">—</span> @endif
                        </td>
                        <td class="px-3 py-2.5">{!! $of['has_output'] ? '<span class="text-green-600 text-xs">✓ Entré</span>' : '<span class="text-gray-400 text-xs">—</span>' !!}</td>
                        <td class="px-3 py-2.5 text-right">
                            @can('production.view')
                            <a href="{{ route('production.orders.show', $of['id']) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir OF →</a>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="px-5 py-8 text-center text-gray-400 text-sm">Aucun ordre de fabrication. Lancez la production pour cette commande.</p>
        @endif
    </div>
    @endif

    {{-- ══ Disponibilité produit fini (V2) ══ --}}
    @if(isset($stockAnalysis) && $stockAnalysis['lines']->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">Disponibilité produit fini</h2>
            @can('production.update')
            @if($stockAnalysis['reservable'] > 0 && !in_array($order->status, ['brouillon','annule']))
            <form method="POST" action="{{ route('production.sales.reserve-stock', $order) }}">@csrf
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Réserver le stock disponible ({{ number_format($stockAnalysis['reservable'],0,',',' ') }})
                </button>
            </form>
            @endif
            @endcan
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="px-5 py-2">Produit</th>
                        <th class="px-3 py-2 text-right">Commandé</th>
                        <th class="px-3 py-2 text-right">Dispo stock</th>
                        <th class="px-3 py-2 text-right">Déjà réservé</th>
                        <th class="px-3 py-2 text-right">À réserver</th>
                        <th class="px-3 py-2 text-right">À produire</th>
                        <th class="px-3 py-2">Décision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stockAnalysis['lines'] as $l)
                    <tr>
                        <td class="px-5 py-2.5 text-gray-800">{{ $l['product'] }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($l['ordered'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($l['available'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-500">{{ number_format($l['reserved'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-emerald-800 font-medium">{{ number_format($l['reservable'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-orange-700 font-medium">{{ number_format($l['to_produce'],0,',',' ') }}</td>
                        <td class="px-3 py-2.5">
                            @php [$dc,$dl] = match($l['decision']){ 'livre'=>['bg-gray-100 text-gray-600','Livré'],'stock'=>['bg-green-100 text-green-700','Stock suffisant'],'produce'=>['bg-orange-100 text-orange-700','À produire'],default=>['bg-amber-100 text-amber-700','Mixte (stock + prod)'] }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $dc }}">{{ $dl }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($stockAnalysis['to_produce'] > 0)
        <p class="px-5 py-3 text-xs text-orange-600 border-t border-gray-100">⚠ {{ number_format($stockAnalysis['to_produce'],0,',',' ') }} unité(s) à produire — lancez un ordre de fabrication.</p>
        @endif
    </div>
    @endif

    {{-- Lignes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Lignes de commande
            </h2>
            <span class="text-xs text-gray-400">{{ $order->items->count() }} ligne(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Livré</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix Unit.</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Remise%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">TVA%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($order->items as $item)
                    @php
                        $linePct = (float) $item->quantity > 0
                            ? min(100, round((float) $item->delivered_quantity / (float) $item->quantity * 100))
                            : 0;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $item->description }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden min-w-[40px]">
                                    <div class="h-full rounded-full {{ $linePct >= 100 ? 'bg-green-500' : ($linePct > 0 ? 'bg-emerald-600' : 'bg-gray-200') }}" style="width: {{ $linePct }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums {{ $linePct >= 100 ? 'text-green-600 font-medium' : 'text-gray-500' }}">{{ $linePct }}%</span>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bons de livraison liés --}}
    @if($order->deliveryNotes->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/>
                </svg>
                Bons de livraison
            </h2>
            <span class="text-xs text-gray-400">{{ $order->deliveryNotes->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->deliveryNotes as $dn)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-mono font-semibold text-emerald-700">{{ $dn->number }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $dn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center">
                            @if($dn->status === 'valide')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Validé</span>
                            @elseif($dn->status === 'livre')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-purple-100 text-purple-700">Livré</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">Brouillon</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('ventes.bons-livraison.show', $dn) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Factures liées --}}
    @if($order->invoices->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Factures
            </h2>
            <span class="text-xs text-gray-400">{{ $order->invoices->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant TTC</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-mono font-semibold text-emerald-700">{{ $invoice->number }}</td>
                        <td class="px-3 py-1.5 text-gray-600">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-center">
                            @switch($invoice->status)
                                @case('brouillon') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">Brouillon</span> @break
                                @case('emise') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">Émise</span> @break
                                @case('envoyee') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Envoyée</span> @break
                                @case('partiellement_payee') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-100 text-orange-700">Part. payée</span> @break
                                @case('payee') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Payée</span> @break
                                @case('en_retard') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">En retard</span> @break
                                @case('annulee') <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-200 text-gray-600">Annulée</span> @break
                                @default <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">{{ $invoice->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('ventes.factures.show', $invoice) }}" class="text-emerald-700 hover:text-emerald-900 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif



    {{-- ── Workflow validation interne ─────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <x-workflow.status-badge :status="$order->status" :label="$order->status_label" />
        </div>
        @if($order->rejection_reason)
            <div class="mb-4 rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> {{ $order->rejection_reason }}
            </div>
        @endif
        {{-- Les actions de workflow sont dans la barre du header — ici : historique seul. --}}
        <x-workflow.history :document="$order" />
    </div>

    {{-- Documents liés --}}
    @php
        $relatedLinks = [];
        if ($order->quote) {
            $relatedLinks[] = [
                'icon'       => '📋',
                'label'      => 'Devis ' . $order->quote->number,
                'href'       => route('ventes.devis.show', $order->quote),
                'badge'      => $order->quote->status_label ?? ucfirst($order->quote->status),
                'badgeColor' => 'gray',
            ];
        }
        foreach ($order->deliveryNotes ?? [] as $dn) {
            $relatedLinks[] = [
                'icon'       => '🚚',
                'label'      => 'Bon de livraison ' . $dn->number,
                'href'       => route('ventes.bons-livraison.show', $dn),
                'badge'      => $dn->status_label ?? ucfirst($dn->status),
                'badgeColor' => 'purple',
            ];
        }
        foreach ($order->invoices ?? [] as $inv) {
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
        <x-document.related :links="$relatedLinks" title="Documents liés à cette commande" />
    @endif

    <x-audit.timeline :model="\App\Models\Order::class" :id="$order->id" />

</div>
@endsection
