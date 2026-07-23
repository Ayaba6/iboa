@extends('layouts.erp')
@section('title', 'Devis '.$quote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.devis.index') }}" class="hover:text-gray-700">Devis</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $quote->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ── Workflow bar ──────────────────────────────────────────────────────── --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => 'devis',
        'quote'        => $quote,
        'order'        => $quote->convertedOrder ?? null,
        {{-- [FIX chaîne devis] BL/Facture/Paiement restaient vides sur un devis
             converti alors que la commande est livrée/facturée/payée. --}}
        'deliveryNote' => $quote->convertedOrder?->deliveryNotes->first(),
        'invoice'      => $quote->convertedOrder?->invoices->first(),
    ])

    {{-- ================================================================
         Header
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2.5 bg-gradient-to-b from-gray-50 to-white">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-[17px] font-bold text-gray-900 font-mono">{{ $quote->number }}</h1>
                    <x-workflow.status-badge :status="$quote->status" :label="$quote->status_label" />
                </div>
                <p class="text-[11px] font-bold text-gray-500 mt-1.5">Client</p>
                <p class="text-[14px] text-gray-800">{{ $quote->client?->name ?? '—' }}</p>
            </div>

            {{-- ════════════════════════════════════════════════════════════════
                 Action bar — driven by quote status (state machine).
                 Convention : un seul bouton primaire coloré (l'action attendue),
                 le reste en outline gris/coloré pour réduire le bruit visuel.
                 ════════════════════════════════════════════════════════════════ --}}
            @php
                $btnOutline = 'inline-flex items-center gap-1.5 px-3.5 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-[13px] font-semibold hover:bg-gray-50 transition-colors';
                $btnPrimary = 'inline-flex items-center gap-1.5 px-4 py-1.5 text-white rounded-[4px] text-[13px] font-semibold transition-colors';
                $btnDangerOutline = 'inline-flex items-center gap-1.5 px-3.5 py-1.5 border border-red-300 text-red-600 rounded-[4px] text-[13px] font-semibold hover:bg-red-50 transition-colors';
                $btnWarnOutline = 'inline-flex items-center gap-1.5 px-3.5 py-1.5 border border-amber-300 text-amber-600 rounded-[4px] text-[13px] font-semibold hover:bg-orange-50 transition-colors';
            @endphp

            <div class="flex flex-wrap items-center gap-2">

                {{-- ── Actions transverses (toujours visibles) ───────────────── --}}
                <a href="{{ route('ventes.devis.pdf', $quote) }}?preview=1" target="_blank"
                   class="{{ $btnOutline }}" title="Aperçu du PDF">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>
                <a href="{{ route('ventes.devis.pdf', $quote) }}"
                   class="{{ $btnOutline }}" title="Télécharger le PDF"
                   data-loading data-loading-text="Génération du devis…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>

                {{-- [VENTES-PRO] Bouton Dupliquer (clone du devis en nouveau brouillon) --}}
                @can('quotes.create')
                <form action="{{ route('ventes.devis.duplicate', $quote) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="{{ $btnOutline }}" title="Créer un nouveau devis identique en brouillon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Dupliquer
                    </button>
                </form>
                @if(! $quote->converted_to_order_id && ! in_array($quote->status, ['brouillon', 'annule']) && ! $quote->hasActiveRevision())
                <form action="{{ route('ventes.devis.revise', $quote) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="{{ $btnOutline }}" title="Créer une révision : nouvelle version liée, ce devis reste consultable mais ne sera plus convertible">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Réviser
                    </button>
                </form>
                @endif
                @endcan

                {{-- ───────────────────── BROUILLON ───────────────────── --}}
                @if($quote->status === 'brouillon')
                    {{-- Modifier --}}
                    <a href="{{ route('ventes.devis.edit', $quote) }}" class="{{ $btnOutline }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifier
                    </a>

                    {{-- PRIMAIRE : Soumettre à validation interne --}}
                    @can('sales.submit')
                    <form action="{{ route('ventes.devis.submit', $quote) }}" method="POST"
                          onsubmit="return confirm('Soumettre ce devis à la validation interne ?')">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    @endcan

                    {{-- Supprimer --}}
                    <form action="{{ route('ventes.devis.destroy', $quote) }}" method="POST"
                          onsubmit="return confirm('Supprimer définitivement ce devis brouillon ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $btnDangerOutline }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Supprimer
                        </button>
                    </form>
                @endif

                {{-- ─────── EN ATTENTE DE VALIDATION ─────────────────────────────────────────── --}}
                @if($quote->status === 'en_attente_validation')
                    {{-- Badge info --}}
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    {{-- VALIDER --}}
                    @can('sales.validate')
                    <form action="{{ route('ventes.devis.validate-internal', $quote) }}" method="POST"
                          onsubmit="return confirm('Valider ce devis ?')">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }} bg-emerald-600 hover:bg-emerald-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider
                        </button>
                    </form>
                    {{-- REFUSER --}}
                    <form action="{{ route('ventes.devis.reject-internal', $quote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5) { alert('Le motif est obligatoire (5 caractères min.)'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $btnWarnOutline }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Refuser
                        </button>
                        {{-- Modal motif refus --}}
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4" @click.outside="open = false">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Expliquez le motif du refus (obligatoire)…" autofocus></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $btnOutline }}">Annuler</button>
                                    <button type="submit" class="{{ $btnPrimary }} bg-emerald-700 hover:bg-emerald-800">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                    {{-- ANNULER --}}
                    @can('sales.cancel')
                    <form action="{{ route('ventes.devis.cancel-internal', $quote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5) { alert('Le motif est obligatoire (5 caractères min.)'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $btnDangerOutline }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Annuler
                        </button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4" @click.outside="open = false">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Expliquez le motif de l'annulation (obligatoire)…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $btnOutline }}">Fermer</button>
                                    <button type="submit" class="{{ $btnPrimary }} bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- ─────── VALIDÉ → Transformer en commande ──────────────────────────────────── --}}
                @if($quote->status === 'valide')
                    @if(!$quote->converted_to_order_id)
                        @can('sales.transform')
                        <form action="{{ route('ventes.devis.convert', $quote) }}" method="POST"
                              onsubmit="return confirm('Transformer ce devis en commande ?')">
                            @csrf
                            <button type="submit" class="{{ $btnPrimary }} bg-emerald-600 hover:bg-emerald-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                Transformer en commande
                            </button>
                        </form>
                        @endcan
                    @else
                        <a href="{{ route('ventes.commandes.show', $quote->converted_to_order_id) }}"
                           class="{{ $btnPrimary }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Voir la commande
                        </a>
                    @endif
                    {{-- Annuler même un devis validé (admin) --}}
                    @can('sales.cancel')
                    <form action="{{ route('ventes.devis.cancel-internal', $quote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5) { alert('Motif obligatoire (5 caractères min.)'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $btnDangerOutline }}">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $btnOutline }}">Fermer</button>
                                    <button type="submit" class="{{ $btnPrimary }} bg-red-600 hover:bg-red-700">Confirmer</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- ─────── CONVERTI → Voir la commande ─────────────────────────────────────────────── --}}
                @if($quote->status === 'converti')
                    @if($quote->converted_to_order_id)
                        <a href="{{ route('ventes.commandes.show', $quote->converted_to_order_id) }}"
                           class="{{ $btnPrimary }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            Voir la commande
                        </a>
                    @endif
                @endif

                {{-- ─────────── REFUSÉ / ANNULÉ / EXPIRÉ / anciens statuts (lecture seule) ─────────── --}}
                @if(in_array($quote->status, ['refuse', 'annule', 'expire', 'envoye', 'accepte']))
                    @if(!$quote->converted_to_order_id)
                    <form action="{{ route('ventes.devis.destroy', $quote) }}" method="POST"
                          onsubmit="return confirm('Supprimer définitivement ce devis ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $btnDangerOutline }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Supprimer
                        </button>
                    </form>
                    @endif
                @endif

                {{-- Retour — toujours présent, à droite --}}
                <a href="{{ route('ventes.devis.index') }}" class="{{ $btnOutline }} ml-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- [CDC §7] Traçabilité de révision --}}
    @if($quote->revision_of_id && $quote->revisionOf)
    <div class="mx-6 mt-4 rounded border border-blue-200 bg-blue-50 px-4 py-2.5 text-[13px] text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
        Révision n°{{ $quote->revision_number }} — remplace le devis
        <a href="{{ route('ventes.devis.show', $quote->revisionOf) }}" class="font-semibold underline">{{ $quote->revisionOf->number }}</a>.
    </div>
    @endif
    @php $activeRev = $quote->revisions()->whereNotIn('status', ['annule', 'refuse'])->latest('id')->first(); @endphp
    @if($activeRev)
    <div class="mx-6 mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-2.5 text-[13px] text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
        Ce devis a été remplacé par la révision
        <a href="{{ route('ventes.devis.show', $activeRev) }}" class="font-semibold underline">{{ $activeRev->number }}</a>
        — il n'est plus convertible en commande.
    </div>
    @endif

    {{-- Letterhead : logo + infos société + badge document --}}
    @php
        $statusMapLh = [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'valide'               => ['label' => 'Validé',                  'class' => 'bg-emerald-100 text-emerald-700'],
            'envoye'               => ['label' => 'Envoyé',                  'class' => 'bg-blue-100 text-blue-700'],
            'accepte'              => ['label' => 'Accepté',                 'class' => 'bg-emerald-100 text-emerald-700'],
            'refuse'               => ['label' => 'Refusé',                  'class' => 'bg-red-100 text-red-700'],
            'expire'               => ['label' => 'Expiré',                  'class' => 'bg-orange-100 text-orange-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-purple-100 text-purple-700'],
            'converti'             => ['label' => 'Converti',                'class' => 'bg-emerald-100 text-emerald-800'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'DEVIS',
        'docNumber' => $quote->number,
        'docDate'   => $quote->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusMapLh[$quote->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $quote->expires_at ? ['label' => 'Validité', 'value' => $quote->expires_at->format('d/m/Y')] : null,
            $quote->client     ? ['label' => 'Client',   'value' => $quote->client->name]                : null,
        ])),
    ])

    {{-- ================================================================
         Carte à onglets SAGE : Lignes / Informations / Documents / Suivi
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300" x-data="{ tab: 'lignes' }">
        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach(['lignes'=>'Lignes','informations'=>'Informations','documents'=>'Documents','suivi'=>'Suivi'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

    {{-- ═══════════ INFORMATIONS ═══════════ --}}
    <div x-show="tab === 'informations'" x-cloak class="p-4">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Info card --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-[13px] font-bold text-emerald-900 uppercase tracking-wide">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $quote->client?->name ?? '—' }}</dd>
                    @if($quote->client?->trade_name)
                    <dd class="text-gray-500 text-xs">{{ $quote->client->trade_name }}</dd>
                    @endif
                    @if($quote->client)
                    <dd class="mt-1">
                        @if($quote->client->is_tax_exempt)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-amber-100 text-amber-700" title="{{ $quote->client->tax_exemption_reason }}">Exonéré TVA</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-700">Assujetti TVA</span>
                        @endif
                        @if($quote->client->tax_regime)<span class="ml-1 text-xs text-gray-400">{{ $quote->client->tax_regime }}</span>@endif
                    </dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $quote->number }}</dd>
                    @if($quote->reference)
                    <dd class="text-gray-500 text-xs">Réf : {{ $quote->reference }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'émission</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $quote->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date de validité</dt>
                    <dd class="mt-0.5 {{ $quote->expires_at?->isPast() && !in_array($quote->status, ['accepte','annule']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                        {{ $quote->expires_at?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                @if($quote->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $quote->notes }}</dd>
                </div>
                @endif
                @if($quote->convertedOrder)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Commande associée</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.commandes.show', $quote->convertedOrder) }}"
                           class="text-blue-600 hover:text-blue-800 font-mono font-semibold">
                            {{ $quote->convertedOrder->number }}
                        </a>
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Right: Summary --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-[13px] font-bold text-emerald-900 uppercase tracking-wide">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($quote->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($quote->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($quote->global_discount_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— {{ number_format($quote->global_discount_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-emerald-800 tabular-nums">{{ number_format($quote->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
    </div>

    </div>{{-- /tab informations --}}

    {{-- ═══════════ LIGNES ═══════════ --}}
    <div x-show="tab === 'lignes'" class="p-4">
    <div class="border border-gray-200 rounded-[4px] overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-[13px] font-bold text-emerald-900 uppercase tracking-wide">Lignes du devis</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-4 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-4 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix Unit.</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Remise%</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">TVA%</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-4 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($quote->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $item->description }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
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



    </div>{{-- /tab lignes --}}

    {{-- ═══════════ SUIVI ═══════════ --}}
    <div x-show="tab === 'suivi'" x-cloak class="p-4 space-y-4">
    {{-- ── Workflow : boutons d'action + historique de validation ─────────── --}}
    <div class="border border-gray-200 rounded-[4px] p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
                Validation interne
            </h2>
            <x-workflow.status-badge :status="$quote->status" :label="$quote->status_label" />
        </div>

        {{-- Alerte si refusé --}}
        @if($quote->rejection_reason)
            <div class="mb-4 rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> {{ $quote->rejection_reason }}
                @if($quote->rejected_at)
                    <span class="text-orange-500 ml-1">({{ $quote->rejected_at->format('d/m/Y H:i') }})</span>
                @endif
            </div>
        @endif

        {{-- Les actions de workflow (soumettre/valider/refuser/annuler) sont dans
             la barre d'actions du header — ce bloc ne montre que l'historique. --}}
        <x-workflow.history :document="$quote" />
    </div>

    {{-- [TRACE] Historique d'activité --}}
    <x-audit.timeline :model="\App\Models\Quote::class" :id="$quote->id" />
    </div>{{-- /tab suivi --}}

    {{-- ═══════════ DOCUMENTS ═══════════ --}}
    <div x-show="tab === 'documents'" x-cloak class="p-4">
    {{-- Documents liés --}}
    @php
        $relatedLinks = [];
        if ($quote->convertedOrder) {
            $relatedLinks[] = [
                'icon'       => '📦',
                'label'      => 'Commande ' . $quote->convertedOrder->number,
                'href'       => route('ventes.commandes.show', $quote->convertedOrder),
                'badge'      => $quote->convertedOrder->status_label ?? ucfirst($quote->convertedOrder->status),
                'badgeColor' => 'blue',
            ];
            foreach ($quote->convertedOrder->deliveryNotes ?? [] as $dn) {
                $relatedLinks[] = [
                    'icon'       => '🚚',
                    'label'      => 'Bon de livraison ' . $dn->number,
                    'href'       => route('ventes.bons-livraison.show', $dn),
                    'badge'      => $dn->status_label ?? ucfirst($dn->status),
                    'badgeColor' => 'purple',
                ];
            }
            foreach ($quote->convertedOrder->invoices ?? [] as $inv) {
                $relatedLinks[] = [
                    'icon'       => '🧾',
                    'label'      => 'Facture ' . $inv->number,
                    'href'       => route('ventes.factures.show', $inv),
                    'badge'      => $inv->status_label ?? ucfirst($inv->status),
                    'badgeColor' => 'green',
                ];
            }
        }
    @endphp
    @if(count($relatedLinks))
        <x-document.related :links="$relatedLinks" title="Documents liés à ce devis" />
    @else
        <p class="px-2 py-8 text-center text-gray-400 text-[13px]">Aucun document lié à ce devis.</p>
    @endif
    </div>{{-- /tab documents --}}

    </div>{{-- /carte onglets --}}

</div>
@endsection
