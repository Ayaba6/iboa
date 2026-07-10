@extends('layouts.erp')
@section('title', 'Facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $invoice->number }}</span>
@endsection

@section('content')
<div class="space-y-3"
     x-data="{
        showCancelModal: false,
        cancelReason: '',
        get canSubmitCancel() { return this.cancelReason.trim().length >= 5; }
     }">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => in_array($invoice->status, ['payee']) ? 'paiement' : 'facture',
        'quote'        => $invoice->order?->quote ?? null,
        'order'        => $invoice->order ?? null,
        'deliveryNote' => $invoice->deliveryNote ?? null,
        'invoice'      => $invoice,
    ])

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $invoice->number }}</h1>
                @php
                    $statusBadges = [
                        'brouillon'           => 'badge-gray',
                        'emise'               => 'badge-blue',
                        'envoyee'             => 'badge-indigo',
                        'partiellement_payee' => 'badge-orange',
                        'payee'               => 'badge-green',
                        'en_retard'           => 'badge-red',
                        'annulee'             => 'badge-red',
                    ];
                    $statusLabels = [
                        'brouillon'             => 'Brouillon',
                        'en_attente_validation' => 'En attente de validation',
                        'emise'               => 'Émise',
                        'envoyee'             => 'Envoyée',
                        'partiellement_payee' => 'Partiellement payée',
                        'payee'               => 'Payée',
                        'en_retard'           => 'En retard',
                        'annulee'             => 'Annulée',
                    ];
                    $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                        && !in_array($invoice->status, ['payee', 'annulee']);
                @endphp
                <span class="badge {{ $statusBadges[$invoice->status] ?? 'badge-gray' }}">
                    {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                </span>
                {{-- [UI-1] Badge PROFORMA visible sur les factures de type proforma --}}
                @if($invoice->type === 'proforma')
                <span class="badge bg-orange-100 text-orange-800 border border-orange-300 font-bold tracking-wider uppercase" title="Document non comptable — doit être converti en facture standard">
                    PROFORMA
                </span>
                @endif
                @if($isOverdue)
                    <span class="badge bg-red-600 text-white font-bold">EN RETARD</span>
                @endif
                {{-- [INVOICE-LOCKED-GUARD] Badge "Verrouillée" pour les factures soldées --}}
                @if($invoice->status === 'payee' || (int) $invoice->remaining_amount === 0)
                    <span class="badge bg-gray-800 text-white font-bold inline-flex items-center gap-1" title="Cette facture est entièrement réglée et ne peut plus recevoir de nouveau paiement">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        VERROUILLÉE
                    </span>
                @endif
                <span class="text-gray-500 text-sm">{{ $invoice->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- PDF --}}
                <a href="{{ route('ventes.factures.pdf', $invoice) }}" class="btn btn-secondary" title="Télécharger le PDF"
                   data-loading data-loading-text="Génération de la facture…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Télécharger PDF
                </a>
                <a href="{{ route('ventes.factures.pdf', $invoice) }}?preview=1" target="_blank" class="btn btn-secondary" title="Aperçu PDF">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>

                {{-- ── BROUILLON : Soumettre à validation interne ──────────────────────── --}}
                @if($invoice->status === 'brouillon')
                    @can('sales.submit')
                    <form action="{{ route('ventes.factures.submit', $invoice) }}" method="POST"
                          onsubmit="return confirm('Soumettre cette facture à la validation interne ?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800 transition-colors shadow-sm">
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
                @if($invoice->status === 'en_attente_validation')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    @can('sales.validate')
                    <form action="{{ route('ventes.factures.validate-internal', $invoice) }}" method="POST"
                          onsubmit="return confirm('Valider cette facture ? Elle sera émise et ne pourra plus être modifiée.')">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider la facture
                        </button>
                    </form>
                    <form action="{{ route('ventes.factures.reject-internal', $invoice) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-[4px] text-sm font-medium hover:bg-orange-50 transition-colors">
                            Refuser
                        </button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire (5 caractères min.)…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="btn btn-secondary">Annuler</button>
                                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 bg-orange-600 text-white rounded-[4px] text-sm font-semibold hover:bg-orange-700 transition-colors">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                    @can('sales.cancel')
                    <form action="{{ route('ventes.factures.cancel-internal', $invoice) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50 transition-colors">
                            Annuler
                        </button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="btn btn-secondary">Fermer</button>
                                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-semibold hover:bg-red-700 transition-colors">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- Envoyer par email --}}
                @if($invoice->client?->email && $invoice->status !== 'brouillon')
                <form action="{{ route('ventes.factures.send-email', $invoice) }}" method="POST"
                      onsubmit="return confirm('Envoyer la facture à {{ addslashes($invoice->client->email) }} ?')">
                    @csrf
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Email
                    </button>
                </form>
                @endif

                {{-- Encaisser : visible uniquement après validation de la facture.
                     Avant validation (brouillon / en_attente_validation) le bouton est masqué.
                     Facture verrouillée (payée / annulée) : bouton grisé + tooltip. --}}
                @php
                    $encaissableStatuses = ['emise', 'validee', 'envoyee', 'partiellement_payee', 'en_retard'];
                    $preValidation = in_array($invoice->status, ['brouillon', 'en_attente_validation']);
                    $canEncaisser = in_array($invoice->status, $encaissableStatuses);
                    $disabledReason = match (true) {
                        $invoice->status === 'payee'   => 'Facture entièrement payée — verrouillée, aucun nouvel encaissement possible',
                        $invoice->status === 'annulee' => 'Facture annulée — aucun encaissement possible',
                        default => null,
                    };
                @endphp
                @if($canEncaisser)
                <a href="{{ route('tresorerie.encaissements.create', ['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id]) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Encaisser
                </a>
                @elseif(!$preValidation)
                {{-- [INVOICE-LOCKED-GUARD] Facture verrouillée : bouton grisé + tooltip.
                     (Avant validation le bouton est totalement masqué.) --}}
                <button type="button" disabled
                        title="{{ $disabledReason }}"
                        aria-disabled="true"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-300 text-gray-500 rounded-[4px] text-sm font-medium cursor-not-allowed opacity-70 select-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                    </svg>
                    Encaisser
                </button>
                @endif

                {{-- Créer un avoir --}}
                @if(!in_array($invoice->status, ['brouillon', 'annulee']))
                <a href="{{ route('ventes.avoirs.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-purple">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Avoir
                </a>
                @endif

                {{-- Modifier --}}
                @if($invoice->status === 'brouillon')
                <a href="{{ route('ventes.factures.edit', $invoice) }}" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                @endif

                {{-- [UX-2] Convertir une proforma en facture standard --}}
                @if($invoice->type === 'proforma' && in_array($invoice->status, ['emise', 'envoyee']))
                <form action="{{ route('ventes.factures.convert-proforma', $invoice) }}" method="POST"
                      onsubmit="return confirm('Convertir cette proforma en facture standard ?\n\nUne nouvelle facture (compta + stock) sera générée. La proforma sera marquée annulée.')">
                    @csrf
                    <button type="submit" class="btn btn-teal" title="Convertir en facture standard avec impact compta + stock">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Convertir en facture
                    </button>
                </form>
                @endif

                {{-- [UX-1] Annuler avec motif (contre-passation) --}}
                @if(in_array($invoice->status, ['emise', 'envoyee', 'en_retard']) && $invoice->paid_amount == 0)
                <button type="button" @click="showCancelModal = true; cancelReason = ''"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-red-300 text-red-600 hover:bg-red-50 rounded-[4px] text-sm font-medium transition-colors"
                        title="Annuler la facture avec contre-passation comptable">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Annuler
                </button>
                @endif

                <a href="{{ route('ventes.factures.index') }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>

        {{-- [INVOICE-LOCKED-GUARD] Bandeau explicite quand la facture est verrouillée --}}
        @if($invoice->status === 'payee' || (int) $invoice->remaining_amount === 0)
        <div class="mt-4 bg-gray-100 border-l-4 border-gray-800 rounded-[4px] p-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-gray-800 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            <div class="text-sm text-gray-800">
                <p class="font-semibold">Facture verrouillée — entièrement réglée</p>
                <p class="text-xs text-gray-600 mt-0.5">
                    Cette facture ne peut plus recevoir de nouveau paiement (total payé : {{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA, reste à payer : 0).
                    En cas de paiement excédentaire reçu, créez un <a href="{{ route('ventes.avoirs.create', ['invoice_id' => $invoice->id]) }}" class="underline font-medium">avoir client</a>
                    ou laissez le paiement en crédit non alloué côté trésorerie.
                </p>
            </div>
        </div>
        @endif
    </div>

    {{-- En-tête document avec logo --}}
    @php
        $statusLabelsLh = [
            'brouillon'           => ['label' => 'Brouillon',            'class' => 'bg-gray-100 text-gray-700'],
            'emise'               => ['label' => 'Émise',                'class' => 'bg-blue-100 text-blue-700'],
            'envoyee'             => ['label' => 'Envoyée',              'class' => 'bg-emerald-100 text-emerald-800'],
            'partiellement_payee' => ['label' => 'Part. payée',          'class' => 'bg-orange-100 text-orange-700'],
            'payee'               => ['label' => 'Payée',                'class' => 'bg-green-100 text-green-700'],
            'en_retard'           => ['label' => 'En retard',            'class' => 'bg-red-100 text-red-700'],
            'annulee'             => ['label' => 'Annulée',              'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'FACTURE',
        'docNumber' => $invoice->number,
        'docDate'   => $invoice->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusLabelsLh[$invoice->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $invoice->due_at ? ['label' => 'Échéance', 'value' => $invoice->due_at->format('d/m/Y')] : null,
            $invoice->client ? ['label' => 'Client',   'value' => $invoice->client->name]              : null,
        ])),
    ])

    {{-- 2 colonnes: info + totaux --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $invoice->client?->name ?? '—' }}</dd>
                    @if($invoice->client?->phone)<dd class="text-gray-500 text-xs">{{ $invoice->client->phone }}</dd>@endif
                    @if($invoice->client?->email)<dd class="text-gray-500 text-xs">{{ $invoice->client->email }}</dd>@endif
                    @php
                        $addr = $invoice->client?->addresses
                            ?->firstWhere('type', 'facturation')
                            ?? $invoice->client?->addresses?->firstWhere('is_default', true)
                            ?? $invoice->client?->addresses?->first();
                    @endphp
                    @if($addr)
                    <dd class="text-gray-500 text-xs mt-1 leading-snug">
                        @if($addr->address)<span>{{ $addr->address }}</span><br>@endif
                        @if($addr->city || $addr->country)
                            <span>{{ implode(', ', array_filter([$addr->city, $addr->country])) }}</span>
                        @endif
                    </dd>
                    @elseif($invoice->client?->address || $invoice->client?->city)
                    <dd class="text-gray-500 text-xs mt-1 leading-snug">
                        @if($invoice->client->address)<span>{{ $invoice->client->address }}</span><br>@endif
                        @if($invoice->client->city || $invoice->client->country)
                            <span>{{ implode(', ', array_filter([$invoice->client->city, $invoice->client->country])) }}</span>
                        @endif
                    </dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $invoice->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'émission</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'échéance</dt>
                    <dd class="mt-0.5 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                        {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                @if($invoice->payment_terms)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Conditions paiement</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $invoice->payment_terms }}</dd>
                </div>
                @endif
                @if($invoice->order)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Commande d'origine</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.commandes.show', $invoice->order) }}" class="text-blue-600 hover:underline font-mono">{{ $invoice->order->number }}</a>
                    </dd>
                </div>
                @endif
                @if($invoice->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap text-xs">{{ $invoice->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Totaux --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Montant HT</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($invoice->global_discount_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— {{ number_format($invoice->global_discount_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-sm font-bold text-gray-900">Montant TTC</span>
                <span class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>

            {{-- Retenues à la source --}}
            @if(!empty($invoice->withholding_details))
                @foreach($invoice->withholding_details as $w)
                <div class="flex justify-between text-sm text-amber-700">
                    <span>Retenue {{ $w['short_name'] ?? $w['name'] }} {{ number_format($w['rate'], 2, ',', '') }}%</span>
                    <span class="font-medium tabular-nums">— {{ number_format($w['amount'], 0, ',', ' ') }} FCFA</span>
                </div>
                @endforeach
            @endif

            {{-- Net à payer --}}
            <div class="border-t-2 border-emerald-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">NET À PAYER</span>
                <span class="text-base font-bold text-emerald-800 tabular-nums">{{ number_format($invoice->net_to_pay ?: $invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>

            @if($invoice->paid_amount > 0)
            <div class="flex justify-between text-sm text-gray-600 border-t border-gray-100 pt-2">
                <span>Déjà payé</span>
                <span class="font-medium tabular-nums text-green-600">{{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            @if($invoice->remaining_amount > 0)
            <div class="flex justify-between text-sm border-t border-gray-100 pt-2">
                <span class="font-bold text-red-700">Reste à payer</span>
                <span class="font-bold tabular-nums text-red-700">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Lignes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de facture</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix Unit.</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Remise%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">TVA%</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoice->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $item->description }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums hidden md:table-cell">{{ ($item->discount_percent ?? 0) > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Paiements reçus --}}
    @if($invoice->payments->isNotEmpty())
    @php
        $totalPaye = $invoice->payments->sum(fn($p) => $p->pivot->amount ?? 0);
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                Paiements reçus
                <span class="text-xs bg-green-100 text-green-700 font-medium px-2 py-0.5 rounded-full">
                    {{ $invoice->payments->count() }} encaissement(s)
                </span>
            </h2>
            <span class="text-sm font-semibold text-green-700 tabular-nums">
                {{ number_format($totalPaye, 0, ',', ' ') }} FCFA encaissés
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">N° encaissement</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Mode</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant alloué</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Référence</th>
                        <th class="px-3 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($invoice->payments as $pmt)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5">
                            <span class="font-mono font-semibold text-green-700 text-xs">{{ $pmt->number }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $pmt->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell">{{ $pmt->paymentMethod?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-green-600">
                            {{ number_format($pmt->pivot->amount ?? $pmt->amount, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-3 py-1.5 text-gray-400 text-xs hidden lg:table-cell">
                            {{ $pmt->reference ?: '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <a href="{{ route('tresorerie.encaissements.show', $pmt) }}"
                               class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                Voir →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-3 py-2.5 text-xs font-semibold text-gray-600 text-right hidden md:table-cell">Total encaissé :</td>
                        <td colspan="3" class="px-3 py-2.5 text-xs font-semibold text-gray-600 text-right md:hidden">Total :</td>
                        <td class="px-3 py-2.5 text-right font-bold tabular-nums text-green-700">
                            {{ number_format($totalPaye, 0, ',', ' ') }} FCFA
                        </td>
                        <td colspan="2" class="px-3 py-2.5">
                            @if($invoice->remaining_amount > 0)
                                <span class="text-xs font-medium text-red-600">
                                    Reste : {{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA
                                </span>
                            @else
                                <span class="text-xs font-medium text-green-600 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Soldée
                                </span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Avoirs liés --}}
    @if($invoice->creditNotes->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Avoirs liés</h2>
            <a href="{{ route('ventes.avoirs.create', ['invoice_id' => $invoice->id]) }}"
               class="text-xs text-purple-600 hover:text-purple-800 font-medium border border-purple-200 hover:bg-purple-50 px-3 py-1.5 rounded-[4px] transition-colors">
                + Nouvel avoir
            </a>
        </div>
        <table class="w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Numéro</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Motif</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant TTC</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($invoice->creditNotes as $cn)
                @php
                    $cnBadges = ['brouillon' => 'bg-gray-100 text-gray-600', 'valide' => 'bg-purple-100 text-purple-700', 'applique' => 'bg-green-100 text-green-700', 'annule' => 'bg-red-100 text-red-600'];
                    $cnLabels = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'applique' => 'Appliqué', 'annule' => 'Annulé'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 font-mono font-semibold text-purple-700">
                        <a href="{{ route('ventes.avoirs.show', $cn) }}" class="hover:text-purple-900">{{ $cn->number }}</a>
                    </td>
                    <td class="px-3 py-1.5 text-gray-600">{{ $cn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-gray-500 text-xs hidden md:table-cell">{{ $cn->reason ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-purple-700 font-semibold">{{ number_format($cn->total_ttc, 0, ',', ' ') }} FCFA</td>
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $cnBadges[$cn->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $cnLabels[$cn->status] ?? $cn->status }}
                        </span>
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <a href="{{ route('ventes.avoirs.show', $cn) }}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded" title="Voir">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- [UX-4] Historique d'audit --}}
    @if(isset($audits) && $audits->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Historique
            </h2>
            <span class="text-xs text-gray-400">{{ $audits->count() }} opération(s)</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($audits as $audit)
            @php
                $actionColors = [
                    'created'        => 'bg-blue-100 text-blue-700',
                    'updated'        => 'bg-gray-100 text-gray-700',
                    'validated'      => 'bg-emerald-100 text-emerald-700',
                    'sent'           => 'bg-emerald-100 text-emerald-800',
                    'cancelled'      => 'bg-red-100 text-red-700',
                    'paid'           => 'bg-green-100 text-green-700',
                    'partially_paid' => 'bg-amber-100 text-amber-700',
                    'overdue'        => 'bg-orange-100 text-orange-700',
                    'deleted'        => 'bg-red-100 text-red-700',
                    'restored'       => 'bg-purple-100 text-purple-700',
                ];
                $actionLabels = [
                    'created'        => 'Création',
                    'updated'        => 'Modification',
                    'validated'      => 'Validation',
                    'sent'           => 'Envoi par email',
                    'cancelled'      => 'Annulation',
                    'paid'           => 'Payée',
                    'partially_paid' => 'Paiement partiel',
                    'overdue'        => 'Passage en retard',
                    'deleted'        => 'Suppression',
                    'restored'       => 'Restauration',
                ];
                $cls = $actionColors[$audit->action] ?? 'bg-gray-100 text-gray-700';
                $label = $actionLabels[$audit->action] ?? $audit->action;
            @endphp
            <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50/50">
                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-semibold {{ $cls }} flex-shrink-0">
                    {{ $label }}
                </span>
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-600">
                        <span class="font-medium text-gray-800">{{ $audit->user_name ?? 'Système' }}</span>
                        <span class="text-gray-400">·</span>
                        <span>{{ $audit->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    @if($audit->action === 'updated' && $audit->new_values)
                    @php
                        $diffFields = array_diff_key($audit->new_values, ['number' => 1]);
                    @endphp
                    @if(!empty($diffFields))
                    <div class="mt-1 text-xs text-gray-500">
                        @foreach($diffFields as $field => $newVal)
                            @php
                                $oldVal = $audit->old_values[$field] ?? null;
                                if (is_array($newVal) || is_array($oldVal)) continue;
                            @endphp
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono font-medium text-gray-700">{{ $field }}</span>
                                <span class="text-gray-400 line-through">{{ $oldVal !== null ? \Illuminate\Support\Str::limit((string) $oldVal, 50) : '∅' }}</span>
                                <span class="text-gray-400">→</span>
                                <span class="text-gray-800">{{ \Illuminate\Support\Str::limit((string) $newVal, 50) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- [UX-1] Modale d'annulation avec motif obligatoire --}}
    <div x-show="showCancelModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showCancelModal = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md" @click.outside="showCancelModal = false">
            <div class="px-3 py-1.5 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Annuler la facture {{ $invoice->number }}
                </h3>
            </div>
            <form action="{{ route('ventes.factures.cancel', $invoice) }}" method="POST" data-turbo="false">
                @csrf
                <div class="px-6 py-5 space-y-3">
                    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-3 text-xs text-amber-800">
                        <strong>⚠ Conséquences :</strong>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <li>Contre-passation comptable automatique</li>
                            <li>Statut passe à « Annulée »</li>
                            <li>La commande parent (si elle existe) revient à son état précédent</li>
                            <li>Action irréversible — pour une correction partielle, créez plutôt un avoir</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motif de l'annulation <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" x-model="cancelReason" rows="3" required minlength="5" maxlength="500"
                                  placeholder="Ex : Erreur de saisie client / Annulation commerciale demandée / …"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">Conservé dans l'historique d'audit comptable</p>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="showCancelModal = false"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px] hover:bg-white">
                        Fermer
                    </button>
                    <button type="submit" :disabled="!canSubmitCancel"
                            class="bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </div>
    </div>


    {{-- ── Échéancier client ──────────────────────────────────────────────────── --}}
    @if(!in_array($invoice->status, ['brouillon','annulee','payee']))
    @php $schedules = $invoice->paymentSchedules; @endphp
    <div class="bg-white rounded-[4px] border border-emerald-200 overflow-hidden"
         x-data="{ tab: '{{ $schedules->count() ? 'view' : 'create' }}', mode: 'percent', rows: [{ percent: 100, days_after: 0, label: '' }], customRows: [{ due_date: '', amount: '', label: '' }] }">

        {{-- Header --}}
        <div class="px-3 py-1.5 bg-[#eef5f0] border-b border-emerald-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h2 class="text-sm font-bold text-emerald-800">Échéancier de paiement</h2>
                @if($schedules->count())
                <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-medium">
                    {{ $schedules->count() }} échéance(s)
                </span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if($schedules->count())
                <button @click="tab = (tab === 'view' ? 'create' : 'view')"
                        class="text-xs text-emerald-700 hover:text-emerald-900 font-medium underline">
                    <span x-text="tab === 'view' ? 'Modifier' : 'Voir les échéances'"></span>
                </button>
                @endif
            </div>
        </div>

        {{-- View existing schedule --}}
        @if($schedules->count())
        <div x-show="tab === 'view'">
            <table class="w-full text-sm">
                <thead class="bg-emerald-800 text-white">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold">Libellé</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Échéance</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Montant</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Payé</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Reste</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-indigo-50">
                    @foreach($schedules as $sch)
                    @php
                        $schColors = ['en_attente'=>'gray','partiel'=>'amber','paye'=>'green','annule'=>'red'];
                        $schLabels = ['en_attente'=>'En attente','partiel'=>'Partiel','paye'=>'Payé','annule'=>'Annulé'];
                        $sc2 = $schColors[$sch->status] ?? 'gray';
                        $isLate = $sch->isOverdue();
                    @endphp
                    <tr class="hover:bg-emerald-50 {{ $isLate ? 'bg-rose-50' : '' }}">
                        <td class="px-3 py-2.5 text-gray-700">
                            {{ $sch->label ?: ('Échéance '.$sch->installment_number) }}
                        </td>
                        <td class="px-3 py-2.5 text-center {{ $isLate ? 'text-rose-700 font-semibold' : 'text-gray-700' }}">
                            {{ $sch->due_date->format('d/m/Y') }}
                            @if($isLate)
                            <span class="ml-1 text-xs text-rose-600 font-bold">
                                ({{ now()->diffInDays($sch->due_date) }}j)
                            </span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ number_format($sch->amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-green-700">{{ number_format($sch->paid_amount, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $sch->remainingAmount() > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                            {{ number_format($sch->remainingAmount(), 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-{{ $sc2 }}-100 text-{{ $sc2 }}-700">
                                {{ $schLabels[$sch->status] ?? $sch->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-[#eef5f0]">
                    <tr>
                        <td colspan="2" class="px-3 py-2.5 text-sm font-semibold text-emerald-900 text-right">Totaux</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-bold text-emerald-900">{{ number_format($schedules->sum('amount'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-bold text-green-700">{{ number_format($schedules->sum('paid_amount'), 0, ',', ' ') }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-bold text-orange-600">{{ number_format($schedules->sum(fn($s) => $s->remainingAmount()), 0, ',', ' ') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            {{-- Delete all --}}
            <div class="px-3 py-1.5 border-t border-emerald-100 flex justify-end">
                <form action="{{ route('ventes.factures.schedules.destroy-all', $invoice) }}" method="POST"
                      onsubmit="return confirm('Supprimer tout l\'échéancier ?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="text-xs text-red-500 hover:text-red-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer l'échéancier
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Create/Edit schedule --}}
        <div x-show="tab === 'create'" class="p-4 space-y-4">

            {{-- Mode toggle --}}
            <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-[4px] w-fit">
                <button type="button" @click="mode = 'percent'"
                        :class="mode === 'percent' ? 'bg-white shadow text-emerald-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 rounded-md text-sm transition-all">
                    Par tranches (%)
                </button>
                <button type="button" @click="mode = 'custom'"
                        :class="mode === 'custom' ? 'bg-white shadow text-emerald-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 rounded-md text-sm transition-all">
                    Dates & montants
                </button>
            </div>

            {{-- Percent mode --}}
            <div x-show="mode === 'percent'">
                <form action="{{ route('ventes.factures.schedules.store', $invoice) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="space-y-2" id="pct-rows">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="number" :name="'installments['+i+'][percent]'"
                                       x-model="row.percent" min="1" max="100" step="0.01"
                                       placeholder="%" required
                                       class="w-20 border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm text-center focus:ring-1 focus:ring-emerald-400">
                                <span class="text-gray-400 text-sm">%</span>
                                <input type="number" :name="'installments['+i+'][days_after]'"
                                       x-model="row.days_after" min="0"
                                       placeholder="jours après"
                                       class="w-28 border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-emerald-400">
                                <span class="text-gray-400 text-xs whitespace-nowrap">j. après émission</span>
                                <input type="text" :name="'installments['+i+'][label]'"
                                       x-model="row.label" placeholder="Libellé (optionnel)"
                                       class="flex-1 border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-emerald-400">
                                <button type="button" @click="rows.splice(i,1)" x-show="rows.length > 1"
                                        class="text-red-400 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="rows.push({ percent: 0, days_after: 30, label: '' })"
                                    class="text-sm text-emerald-700 hover:text-emerald-900 font-medium flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Ajouter une tranche
                            </button>
                            <span class="text-xs"
                                  :class="Math.abs(rows.reduce((s,r)=>s+parseFloat(r.percent||0),0)-100)<0.01 ? 'text-green-600 font-medium' : 'text-orange-500'">
                                Total : <strong x-text="rows.reduce((s,r)=>s+parseFloat(r.percent||0),0).toFixed(1)"></strong> %
                                <span x-show="Math.abs(rows.reduce((s,r)=>s+parseFloat(r.percent||0),0)-100)>=0.01">(doit être 100)</span>
                            </span>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Créer l'échéancier
                        </button>
                    </div>
                </form>
            </div>

            {{-- Custom mode --}}
            <div x-show="mode === 'custom'">
                <form action="{{ route('ventes.factures.schedules.store-custom', $invoice) }}" method="POST" class="space-y-3">
                    @csrf
                    @php $schedBasis = (int) ($invoice->net_to_pay ?: $invoice->total_ttc); @endphp
                    <p class="text-xs text-gray-500">
                        Net à payer : <strong class="tabular-nums">{{ number_format($schedBasis, 0, ',', ' ') }} FCFA</strong>
                        — la somme des montants doit être exactement égale.
                    </p>
                    <div class="space-y-2">
                        <template x-for="(row, i) in customRows" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="date" :name="'rows['+i+'][due_date]'"
                                       x-model="row.due_date" required
                                       class="border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-emerald-400">
                                <input type="number" :name="'rows['+i+'][amount]'"
                                       x-model="row.amount" min="1" required
                                       placeholder="Montant FCFA"
                                       class="w-40 border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm tabular-nums focus:ring-1 focus:ring-emerald-400">
                                <input type="text" :name="'rows['+i+'][label]'"
                                       x-model="row.label" placeholder="Libellé"
                                       class="flex-1 border border-gray-300 rounded-[4px] px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-emerald-400">
                                <button type="button" @click="customRows.splice(i,1)" x-show="customRows.length > 1"
                                        class="text-red-400 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="customRows.push({ due_date: '', amount: '', label: '' })"
                                    class="text-sm text-emerald-700 hover:text-emerald-900 font-medium flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Ajouter une ligne
                            </button>
                            <span class="text-xs"
                                  :class="customRows.reduce((s,r)=>s+parseInt(r.amount||0),0)==={{ $schedBasis }} ? 'text-green-600 font-medium' : 'text-orange-500'">
                                Saisi : <strong class="tabular-nums" x-text="customRows.reduce((s,r)=>s+parseInt(r.amount||0),0).toLocaleString('fr-FR')"></strong> FCFA
                            </span>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Créer l'échéancier
                        </button>
                    </div>
                </form>
            </div>

        </div>{{-- /create --}}
    </div>
    @endif

    {{-- [LIAISONS] Documents liés au cycle de vente --}}
    @php
        $relatedLinks = [];
        if ($invoice->order) {
            $relatedLinks[] = [
                'icon' => '📋', 'label' => 'Commande ' . $invoice->order->number,
                'href' => route('ventes.commandes.show', $invoice->order),
                'subtitle' => 'Du ' . $invoice->order->issued_at?->format('d/m/Y'),
                'badge' => ucfirst((string) $invoice->order->status), 'badgeColor' => 'blue',
            ];
        }
        if ($invoice->deliveryNote) {
            $relatedLinks[] = [
                'icon' => '🚚', 'label' => 'Bon de livraison ' . $invoice->deliveryNote->number,
                'href' => route('ventes.bons-livraison.show', $invoice->deliveryNote),
                'badge' => ucfirst((string) $invoice->deliveryNote->status), 'badgeColor' => 'teal',
            ];
        }
        foreach ($invoice->creditNotes ?? [] as $cn) {
            $relatedLinks[] = [
                'icon' => '↩️', 'label' => 'Avoir ' . $cn->number,
                'href' => route('ventes.avoirs.show', $cn),
                'subtitle' => number_format($cn->total_ttc, 0, ',', ' ') . ' FCFA',
                'badge' => ucfirst((string) $cn->status), 'badgeColor' => 'orange',
            ];
        }
    @endphp
    <x-document.related :links="$relatedLinks" />

    {{-- ── Workflow validation interne ─────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <x-workflow.status-badge :status="$invoice->status" :label="$invoice->status_label" />
        </div>
        @if($invoice->rejection_reason)
            <div class="mb-4 rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> {{ $invoice->rejection_reason }}
            </div>
        @endif
        {{-- Les actions de workflow sont dans la barre du header — ici : historique seul. --}}
        <x-workflow.history :document="$invoice" />
    </div>

    <x-audit.timeline :model="\App\Models\Invoice::class" :id="$invoice->id" />

</div>
@endsection
