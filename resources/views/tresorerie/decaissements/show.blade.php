@extends('layouts.erp')
@section('title', 'Décaissement ' . $payment->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.decaissements.index') }}" class="hover:text-gray-700">Décaissements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $payment->number }}</span>
@endsection

@section('content')
<div class="space-y-3" x-data="{ showCancelModal: false, cancelReason: '', get canSubmit() { return this.cancelReason.trim().length >= 5; } }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $payment->number }}</h1>
                @switch($payment->status)
                    @case('confirme')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Confirmé</span>
                        @break
                    @case('en_attente')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-yellow-100 text-yellow-700">En attente</span>
                        @break
                    @case('rejete')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-red-100 text-red-700">Rejeté</span>
                        @break
                    @case('annule')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-600">Annulé</span>
                        @break
                @endswitch
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Décaissement du {{ $payment->payment_date?->format('d/m/Y') }}
                — {{ $payment->supplier?->name ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Reçu PDF --}}
            <a href="{{ route('tresorerie.decaissements.recu', $payment) }}"
               target="_blank"
               class="inline-flex items-center gap-2 border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] transition-colors"
               title="Télécharger le reçu PDF">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reçu PDF
            </a>
            {{-- [TRESO] Bouton Annuler : seulement sur décaissement validé (contre-passation) --}}
            @if($payment->status !== 'annule' && $payment->validation_status === 'valide')
            <button type="button" @click="showCancelModal = true; cancelReason = ''"
                    class="inline-flex items-center gap-2 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] transition-colors"
                    title="Annuler ce décaissement (contre-passation comptable + restauration facture)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Annuler
            </button>
            @endif
            <div class="text-right">
                <p class="text-xs text-gray-500">Montant payé</p>
                <p class="text-[16px] font-bold text-red-700 tabular-nums">
                    {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                </p>
            </div>
        </div>
    </div>

    {{-- [TRESO-WORKFLOW] Bandeau validation par seuil --}}
    @if($payment->validation_status === 'en_attente_validation')
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-800">En attente de validation</p>
                <p class="text-xs text-amber-700 mt-0.5">
                    Montant {{ number_format($payment->amount, 0, ',', ' ') }} FCFA — validation requise
                    @if($payment->required_role) (rôle « {{ $payment->required_role }} ») @endif.
                    Aucune écriture comptable ni sortie de caisse tant que non validé.
                </p>
                @can('treasury.validate')
                <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ rejectOpen: false }">
                    <form action="{{ route('tresorerie.decaissements.approve', $payment) }}" method="POST" class="inline"
                          data-confirm="Valider le décaissement {{ $payment->number }} ? L'écriture comptable et la sortie de caisse seront générées."
                          data-confirm-title="Valider le décaissement"
                          data-confirm-label="Valider"
                          data-confirm-danger="false">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-700">Valider</button>
                    </form>
                    <button type="button" @click="rejectOpen = !rejectOpen" class="px-3 py-1.5 border border-red-300 text-red-600 rounded-[4px] text-sm font-medium hover:bg-red-50">Rejeter</button>
                    <form x-show="rejectOpen" x-cloak action="{{ route('tresorerie.decaissements.reject', $payment) }}" method="POST" class="w-full mt-2 flex gap-2">
                        @csrf
                        <input type="text" name="motif" required minlength="5" maxlength="500" placeholder="Motif du rejet (obligatoire)…"
                               class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-300">
                        <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-[4px] text-sm font-medium hover:bg-red-700 whitespace-nowrap">Confirmer le rejet</button>
                    </form>
                </div>
                @else
                <p class="text-xs text-amber-600 mt-2 italic">Vous n'avez pas le droit de valider ce décaissement.</p>
                @endcan
            </div>
        </div>
    </div>
    @endif

    @if($payment->validation_status === 'rejete')
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4 text-sm">
        <p class="font-semibold text-red-800">Décaissement rejeté</p>
        @if($payment->rejection_reason)<p class="text-red-700 mt-1">Motif : {{ $payment->rejection_reason }}</p>@endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Info card --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Détails du paiement</h2>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500">Fournisseur</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Date</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Mode de paiement</dt>
                    <dd>
                        @if($payment->paymentMethod)
                            @php
                                $pmClass = match($payment->paymentMethod->type) {
                                    'especes'      => 'bg-gray-100 text-gray-700',
                                    'virement'     => 'bg-blue-100 text-blue-700',
                                    'cheque'       => 'bg-emerald-100 text-emerald-800',
                                    'mobile_money' => 'bg-purple-100 text-purple-700',
                                    default        => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $pmClass }}">
                                {{ $payment->paymentMethod->name }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </dd>
                </div>
                @if($payment->reference)
                <div>
                    <dt class="text-xs text-gray-500">Référence</dt>
                    <dd class="font-mono text-sm font-medium text-gray-900">{{ $payment->reference }}</dd>
                </div>
                @endif
                @if($payment->phone_number)
                <div>
                    <dt class="text-xs text-gray-500">N° téléphone</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->phone_number }}</dd>
                </div>
                @endif
                @if($payment->cashAccount)
                <div>
                    <dt class="text-xs text-gray-500">Compte de trésorerie</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->cashAccount->name }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500">Montant imputé</dt>
                    <dd class="font-semibold text-red-700 tabular-nums">{{ number_format($payment->allocated_amount, 0, ',', ' ') }} FCFA</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Montant non imputé</dt>
                    <dd class="font-semibold tabular-nums {{ $payment->unallocated_amount > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                        {{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                @if($payment->notes)
                <div>
                    <dt class="text-xs text-gray-500">Notes</dt>
                    <dd class="text-sm text-gray-700">{{ $payment->notes }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500">Enregistré par</dt>
                    <dd class="text-sm text-gray-700">{{ $payment->createdBy?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Allocations --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 p-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Factures fournisseur imputées</h2>

            {{-- [FIX rapport MTO #5] Imputations vers factures supprimées exclues
                 du tableau et du total — signalées à part pour l'audit. --}}
            @php
                $validAllocations  = $payment->allocations->filter(fn ($a) => $a->supplierInvoice);
                $orphanAllocations = $payment->allocations->reject(fn ($a) => $a->supplierInvoice);
            @endphp
            @if($payment->allocations->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-200">
                            <th class="pb-2 text-left">Facture</th>
                            <th class="pb-2 text-left hidden md:table-cell">N° fournisseur</th>
                            <th class="pb-2 text-left hidden md:table-cell">Date réception</th>
                            <th class="pb-2 text-right">Montant facture</th>
                            <th class="pb-2 text-right">Montant imputé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($validAllocations as $alloc)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4">
                                <a href="{{ route('achats.factures-fournisseurs.show', $alloc->supplierInvoice) }}"
                                   class="font-mono font-semibold text-red-600 hover:text-red-800">
                                    {{ $alloc->supplierInvoice->number }}
                                </a>
                            </td>
                            <td class="py-3 pr-4 text-gray-600 text-xs font-mono hidden md:table-cell">
                                {{ $alloc->supplierInvoice->supplier_invoice_number ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 text-gray-600 hidden md:table-cell">
                                {{ $alloc->supplierInvoice->received_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 text-right tabular-nums text-gray-700">
                                {{ number_format($alloc->supplierInvoice->total_ttc ?? 0, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-3 text-right tabular-nums font-semibold text-red-700">
                                {{ number_format($alloc->amount, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="4" class="pt-3 text-sm font-semibold text-gray-700 text-right pr-4">Total imputé :</td>
                            <td class="pt-3 text-right tabular-nums font-bold text-red-700">
                                {{ number_format($validAllocations->sum('amount'), 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if($orphanAllocations->isNotEmpty())
            <p class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                {{ $orphanAllocations->count() }} imputation(s) liée(s) à des factures supprimées
                ({{ number_format($orphanAllocations->sum('amount'), 0, ',', ' ') }} FCFA) —
                exclue(s) du total ci-dessus.
            </p>
            @endif

            @php
                $allocated = $payment->allocated_amount;
                $total     = $payment->amount;
                $pct       = $total > 0 ? min(100, round($allocated / $total * 100)) : 0;
            @endphp
            <div class="mt-4 p-3 bg-gray-50 rounded-[4px]">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Imputation</span>
                    <span>{{ $pct }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
                <div class="flex justify-between text-xs mt-1">
                    <span class="text-red-700 font-medium">{{ number_format($allocated, 0, ',', ' ') }} FCFA imputés</span>
                    <span class="{{ $payment->unallocated_amount > 0 ? 'text-orange-600' : 'text-gray-400' }} font-medium">
                        {{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA non imputés
                    </span>
                </div>
            </div>

            @else
                <div class="py-12 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">Aucune imputation — ce paiement n'est pas encore imputé sur une facture.</p>
                </div>
            @endif
        </div>
    </div>

    <div>
        <a href="{{ route('tresorerie.decaissements.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour à la liste
        </a>
    </div>

    {{-- [TRESO] Modale d'annulation avec motif obligatoire --}}
    @if($payment->status !== 'annule')
    <div x-show="showCancelModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showCancelModal = false">
        <div class="bg-white rounded-[4px] shadow-2xl w-full max-w-md" @click.outside="showCancelModal = false">
            <div class="px-3 py-1.5 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Annuler le décaissement {{ $payment->number }}
                </h3>
            </div>
            <form action="{{ route('tresorerie.decaissements.cancel', $payment) }}" method="POST" data-turbo="false">
                @csrf
                <div class="px-6 py-5 space-y-3">
                    <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-3 text-xs text-amber-800">
                        <strong>⚠ Conséquences :</strong>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <li>Contre-passation comptable automatique (nouvelle écriture inverse)</li>
                            <li>Restauration des factures fournisseur ({{ $payment->allocations->count() }} ligne(s))</li>
                            <li>Restitution du solde caisse si une transaction caisse existait</li>
                            <li>Statut passe à « Annulé » — action irréversible</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motif de l'annulation <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" x-model="cancelReason" rows="3" required minlength="5" maxlength="500"
                                  placeholder="Ex : Erreur de saisie, paiement non effectué, double comptabilisation…"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">Conservé dans l'historique d'audit comptable</p>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="showCancelModal = false"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px] hover:bg-white">
                        Fermer
                    </button>
                    <button type="submit" :disabled="!canSubmit"
                            class="bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
