@extends('layouts.erp')
@section('title', 'Avoir '.$creditNote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.avoirs.index') }}" class="hover:text-gray-700">Avoirs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $creditNote->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $creditNote->number }}</h1>
                @php
                    $badges = [
                        'brouillon' => 'bg-gray-100 text-gray-700',
                        'valide'    => 'bg-purple-100 text-purple-700',
                        'applique'  => 'bg-green-100 text-green-700',
                        'annule'    => 'bg-red-100 text-red-600',
                    ];
                    $labels = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'applique' => 'Appliqué', 'annule' => 'Annulé'];
                @endphp
                <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold {{ $badges[$creditNote->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $labels[$creditNote->status] ?? $creditNote->status }}
                </span>
                <span class="text-gray-500 text-sm">{{ $creditNote->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- PDF --}}
                <a href="{{ route('ventes.avoirs.pdf', $creditNote) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors"
                   title="Télécharger le PDF"
                   data-loading data-loading-text="Génération de l'avoir…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Télécharger PDF
                </a>
                <a href="{{ route('ventes.avoirs.pdf', $creditNote) }}?preview=1" target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors"
                   title="Aperçu PDF">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>

                @php
                    $avBtnO  = 'inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors';
                    $avBtnP  = 'inline-flex items-center gap-2 px-3 py-1.5 text-white rounded-[4px] text-sm font-semibold shadow-sm transition-colors';
                    $avBtnWO = 'inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-[4px] text-sm font-medium hover:bg-orange-50 transition-colors';
                    $avBtnDO = 'inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50 transition-colors';
                @endphp

                {{-- ── BROUILLON : Soumettre à validation + Supprimer ───────────────────── --}}
                @if($creditNote->status === 'brouillon')
                    @can('sales.submit')
                    <form action="{{ route('ventes.avoirs.submit', $creditNote) }}" method="POST"
                          data-confirm="Soumettre cet avoir à la validation interne ?">
                        @csrf
                        <button type="submit" class="{{ $avBtnP }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    @endcan
                    <form action="{{ route('ventes.avoirs.destroy', $creditNote) }}" method="POST"
                          data-confirm="Supprimer définitivement cet avoir ?">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $avBtnDO }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Supprimer
                        </button>
                    </form>
                @endif

                {{-- ── EN ATTENTE DE VALIDATION ────────────────────────────────────────── --}}
                @if($creditNote->status === 'en_attente_validation')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    @can('sales.validate')
                    <form action="{{ route('ventes.avoirs.validate-internal', $creditNote) }}" method="POST"
                          data-confirm="Valider cet avoir ? Cette action génère une écriture comptable.">
                        @csrf
                        <button type="submit" class="{{ $avBtnP }} bg-emerald-700 hover:bg-emerald-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider l'avoir
                        </button>
                    </form>
                    <form action="{{ route('ventes.avoirs.reject-internal', $creditNote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $avBtnWO }}">Refuser</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $avBtnO }}">Annuler</button>
                                    <button type="submit" class="{{ $avBtnP }} bg-emerald-700 hover:bg-emerald-800">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                    @can('sales.cancel')
                    <form action="{{ route('ventes.avoirs.cancel-internal', $creditNote) }}" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        @csrf
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="{{ $avBtnDO }}">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-[4px] p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="{{ $avBtnO }}">Fermer</button>
                                    <button type="submit" class="{{ $avBtnP }} bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endcan
                @endif

                {{-- Appliquer à la facture --}}
                @if($creditNote->status === 'valide' && $creditNote->invoice_id && $creditNote->remaining_credit > 0)
                <form action="{{ route('ventes.avoirs.apply', $creditNote) }}" method="POST"
                      data-confirm="Appliquer cet avoir sur la facture liée ?">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-[4px] text-sm font-medium hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Appliquer sur facture
                    </button>
                </form>
                @endif

                {{-- [VEN Retour] Générer un BL de remplacement --}}
                @if(in_array($creditNote->status, ['valide', 'applique'], true) && !$creditNote->replacement_delivery_id)
                @can('update', $creditNote)
                <form action="{{ route('ventes.avoirs.replacement', $creditNote) }}" method="POST"
                      data-confirm="Générer un bon de livraison de remplacement (brouillon) pour ce client ?">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-amber-600 text-white rounded-[4px] text-sm font-medium hover:bg-amber-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Générer un remplacement
                    </button>
                </form>
                @endcan
                @endif

                <a href="{{ route('ventes.avoirs.index') }}"
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
    @php
        $statusMapAv = [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'valide'               => ['label' => 'Validé',                  'class' => 'bg-purple-100 text-purple-700'],
            'applique'             => ['label' => 'Appliqué',                'class' => 'bg-green-100 text-green-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-red-100 text-red-600'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'AVOIR',
        'docNumber' => $creditNote->number,
        'docDate'   => $creditNote->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusMapAv[$creditNote->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $creditNote->client  ? ['label' => 'Client',          'value' => $creditNote->client->name]           : null,
            $creditNote->invoice ? ['label' => 'Facture liée',    'value' => $creditNote->invoice->number]        : null,
        ])),
    ])

    {{-- Info + Totaux --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $creditNote->client?->name ?? '—' }}</dd>
                    @if($creditNote->client?->phone)<dd class="text-gray-500 text-xs">{{ $creditNote->client->phone }}</dd>@endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro avoir</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $creditNote->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'émission</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $creditNote->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                @if($creditNote->invoice)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Facture liée</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.factures.show', $creditNote->invoice) }}" class="font-mono font-semibold text-emerald-700 hover:text-emerald-900">
                            {{ $creditNote->invoice->number }}
                        </a>
                    </dd>
                </div>
                @endif
                @if($creditNote->reason)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Motif</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $creditNote->reason }}</dd>
                </div>
                @endif
                @if($creditNote->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap text-xs">{{ $creditNote->notes }}</dd>
                </div>
                @endif
                @if($creditNote->validatedBy)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Validé par</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $creditNote->validatedBy->name }} — {{ $creditNote->validated_at?->format('d/m/Y H:i') }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Totaux --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($creditNote->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($creditNote->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-purple-700 tabular-nums">{{ number_format($creditNote->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($creditNote->applied_amount > 0)
            <div class="flex justify-between text-sm text-gray-600 border-t border-gray-100 pt-2">
                <span>Montant appliqué</span>
                <span class="font-medium tabular-nums text-green-600">{{ number_format($creditNote->applied_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="flex justify-between text-sm border-t border-gray-100 pt-2">
                <span class="font-bold {{ $creditNote->remaining_credit > 0 ? 'text-orange-600' : 'text-gray-400' }}">Solde restant</span>
                <span class="font-bold tabular-nums {{ $creditNote->remaining_credit > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                    {{ number_format($creditNote->remaining_credit, 0, ',', ' ') }} FCFA
                </span>
            </div>
        </div>
    </div>

    {{-- Lignes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de l'avoir</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">#</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Qté</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Prix Unit.</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">TVA%</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Sort du retour</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($creditNote->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $item->description }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-3 py-1.5">
                            @if(($item->disposition ?? 'restock') === 'rebut')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10px] font-semibold bg-red-100 text-red-700">Rebut</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10px] font-semibold bg-emerald-100 text-emerald-700">Remis en stock</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-purple-700 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
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

    {{-- ── Workflow validation interne ─────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <x-workflow.status-badge :status="$creditNote->status" :label="$creditNote->status_label" />
        </div>
        @if($creditNote->rejection_reason)
            <div class="mb-4 rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> {{ $creditNote->rejection_reason }}
            </div>
        @endif
        {{-- Les actions de workflow sont dans la barre du header — ici : historique seul. --}}
        <x-workflow.history :document="$creditNote" />
    </div>

    {{-- Documents liés --}}
    @php
        $relatedLinks = [];
        if ($creditNote->invoice) {
            $relatedLinks[] = [
                'icon'       => '🧾',
                'label'      => 'Facture ' . $creditNote->invoice->number,
                'href'       => route('ventes.factures.show', $creditNote->invoice),
                'badge'      => $creditNote->invoice->status_label ?? ucfirst($creditNote->invoice->status),
                'badgeColor' => 'green',
            ];
            if ($creditNote->invoice->order) {
                $relatedLinks[] = [
                    'icon'       => '📦',
                    'label'      => 'Commande ' . $creditNote->invoice->order->number,
                    'href'       => route('ventes.commandes.show', $creditNote->invoice->order),
                    'badge'      => $creditNote->invoice->order->status_label ?? ucfirst($creditNote->invoice->order->status),
                    'badgeColor' => 'blue',
                ];
            }
        }
        if ($creditNote->replacementDelivery) {
            $relatedLinks[] = [
                'icon'       => '🔁',
                'label'      => 'BL remplacement ' . $creditNote->replacementDelivery->number,
                'href'       => route('ventes.bons-livraison.show', $creditNote->replacementDelivery),
                'badge'      => $creditNote->replacementDelivery->status_label ?? ucfirst($creditNote->replacementDelivery->status),
                'badgeColor' => 'amber',
            ];
        }
    @endphp
    @if(count($relatedLinks))
        <x-document.related :links="$relatedLinks" title="Documents liés à cet avoir" />
    @endif

    {{-- Audit timeline --}}
    <x-audit.timeline :model="\App\Models\CreditNote::class" :id="$creditNote->id"
                      title="Historique de l'avoir" />

</div>
@endsection
