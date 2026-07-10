@extends('layouts.erp')
@section('title', 'Facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.factures-fournisseurs.index') }}" class="hover:text-gray-700">Factures fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium font-mono">{{ $invoice->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ================================================================
         Header
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $invoice->number }}</h1>
                @switch($invoice->status)
                    @case('recue')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-gray-100 text-gray-700">Reçue</span>
                        @break
                    @case('validee')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-blue-100 text-blue-700">Validée</span>
                        @break
                    @case('en_litige')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-yellow-100 text-yellow-700">En litige</span>
                        @break
                    @case('partiellement_payee')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-amber-100 text-amber-700">Partiellement payée</span>
                        @break
                    @case('payee')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-green-100 text-green-700">Payée</span>
                        @break
                    @case('annulee')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-semibold bg-red-100 text-red-700">Annulée</span>
                        @break
                @endswitch
                @if($invoice->due_at && $invoice->due_at->isPast() && !in_array($invoice->status, ['payee', 'annulee']))
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">En retard</span>
                @endif
                {{-- [INVOICE-LOCKED-GUARD] --}}
                @if($invoice->status === 'payee' || (int) $invoice->remaining_amount === 0)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-bold bg-gray-800 text-white" title="Facture entièrement réglée — aucun nouveau décaissement n'est autorisé">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    VERROUILLÉE
                </span>
                @endif
                <span class="text-gray-500 text-sm">{{ $invoice->supplier?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2" x-data="{ payModal: false }">

                {{-- Enregistrer un paiement (grisé si facture verrouillée) --}}
                @php
                    $canPay = in_array($invoice->status, ['validee', 'partiellement_payee', 'recue']);
                    $payDisabledReason = match (true) {
                        $invoice->status === 'brouillon' => 'Validez d\'abord la facture',
                        $invoice->status === 'payee'     => 'Facture entièrement réglée — verrouillée, aucun nouveau paiement',
                        $invoice->status === 'annulee'   => 'Facture annulée — aucun paiement possible',
                        default => null,
                    };
                @endphp
                @if($canPay)
                <button type="button" @click="payModal = true"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-[4px] text-sm font-medium hover:bg-green-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Enregistrer un paiement
                </button>
                @else
                <button type="button" disabled aria-disabled="true"
                        title="{{ $payDisabledReason }}"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-300 text-gray-500 rounded-[4px] text-sm font-medium cursor-not-allowed opacity-70 select-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                    </svg>
                    Enregistrer un paiement
                </button>
                @endif

                @if($canPay)

                {{-- Payment modal --}}
                <div x-show="payModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div @click.outside="payModal = false"
                         class="bg-white rounded-[4px] shadow-xl w-full max-w-md p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">Enregistrer un paiement</h3>
                        <p class="text-sm text-gray-500">
                            Reste à payer :
                            <span class="font-semibold text-red-600">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
                        </p>
                        <form action="{{ route('achats.factures-fournisseurs.payment', $invoice) }}" method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Montant <span class="text-red-500">*</span></label>
                                <input type="number" name="amount" min="1" max="{{ $invoice->remaining_amount }}"
                                       value="{{ $invoice->remaining_amount }}" required
                                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date de paiement <span class="text-red-500">*</span></label>
                                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                                <select name="payment_method_id" class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($cashAccounts->isNotEmpty())
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Compte de trésorerie</label>
                                <select name="cash_account_id" class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($cashAccounts as $ca)
                                    <option value="{{ $ca->id }}">{{ $ca->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Référence</label>
                                <input type="text" name="reference" placeholder="N° chèque, virement..."
                                       class="w-full rounded-[4px] border border-gray-300 px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="payModal = false"
                                        class="px-3 py-1.5 rounded-[4px] border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                                    Annuler
                                </button>
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-[4px] bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                {{-- Valider (recue seulement) --}}
                @if(in_array($invoice->status, ['recue', 'brouillon']))
                <form action="{{ route('achats.factures-fournisseurs.validate', $invoice) }}" method="POST"
                      onsubmit="return confirm('Valider cette facture fournisseur ?')">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Valider
                    </button>
                </form>
                @endif

                {{-- Modifier (recue seulement) --}}
                @if(in_array($invoice->status, ['recue', 'brouillon']))
                <a href="{{ route('achats.factures-fournisseurs.edit', $invoice) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                @endif

                {{-- Supprimer (recue seulement) --}}
                @if(in_array($invoice->status, ['recue', 'brouillon']))
                <form action="{{ route('achats.factures-fournisseurs.destroy', $invoice) }}" method="POST"
                      onsubmit="return confirm('Supprimer définitivement cette facture fournisseur ?')">
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

                <a href="{{ route('achats.factures-fournisseurs.index') }}"
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
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $invoice->supplier?->name ?? '—' }}</dd>
                    @if($invoice->supplier?->city)
                    <dd class="text-gray-500 text-xs">{{ $invoice->supplier->city }}</dd>
                    @endif
                    @if($invoice->supplier?->phone)
                    <dd class="text-gray-500 text-xs">{{ $invoice->supplier->phone }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro interne</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $invoice->number }}</dd>
                    @if($invoice->supplier_invoice_number)
                    <dd class="text-gray-500 text-xs">Réf. fourn. : {{ $invoice->supplier_invoice_number }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date de réception</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $invoice->received_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'échéance</dt>
                    @php
                        $overdue = $invoice->due_at && $invoice->due_at->isPast()
                                   && !in_array($invoice->status, ['payee', 'annulee']);
                    @endphp
                    <dd class="mt-0.5 {{ $overdue ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                        {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                        @if($overdue)
                        <span class="ml-1 text-xs text-red-500">(en retard)</span>
                        @endif
                    </dd>
                </div>
                @if($invoice->purchaseOrder)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Commande achat liée</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('achats.commandes.show', $invoice->purchaseOrder) }}"
                           class="text-amber-600 hover:text-amber-800 font-mono font-semibold">
                            {{ $invoice->purchaseOrder->number }}
                        </a>
                    </dd>
                </div>
                @endif
                @if($invoice->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $invoice->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Right: Totals card --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-amber-700 tabular-nums">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($invoice->paid_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Montant payé</span>
                <span class="font-medium tabular-nums text-green-600">{{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            @if($invoice->remaining_amount > 0)
            <div class="flex justify-between text-sm">
                <span class="font-semibold text-gray-800">Reste à payer</span>
                <span class="font-bold tabular-nums text-red-600">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ================================================================
         Items table
    ================================================================ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de la facture</h2>
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
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total HT</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoice->items as $item)
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
                        <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-3 py-1.5 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun résultat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================
         Payments
    ================================================================ --}}
    @if($invoice->payments && $invoice->payments->count())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Paiements</h2>
            <span class="text-sm text-gray-500">{{ $invoice->payments->count() }} paiement(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Mode</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($invoice->payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-gray-600">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-700 capitalize">{{ $payment->paymentMethod?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-gray-600 font-mono text-xs">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-green-700">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Pièces jointes --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <x-attachments.manager model="SupplierInvoice" :id="$invoice->id" />
    </div>

    {{-- [LIAISONS] PO source · Réception · Cadencier --}}
    @php
        $relatedLinks = [];
        if ($invoice->purchaseOrder) {
            $relatedLinks[] = [
                'icon' => '📋', 'label' => 'Bon de commande ' . $invoice->purchaseOrder->number,
                'href' => route('achats.commandes.show', $invoice->purchaseOrder),
                'subtitle' => 'Du ' . $invoice->purchaseOrder->ordered_at?->format('d/m/Y'),
                'badge' => ucfirst((string) $invoice->purchaseOrder->status), 'badgeColor' => 'blue',
            ];
        }
        if ($invoice->reception) {
            $relatedLinks[] = [
                'icon' => '📦', 'label' => 'Réception ' . $invoice->reception->number,
                'href' => route('achats.receptions.show', $invoice->reception),
                'badge' => ucfirst((string) $invoice->reception->status), 'badgeColor' => 'teal',
            ];
        }
        $scheduleCount = $invoice->paymentSchedules?->count() ?? 0;
        if ($scheduleCount > 0) {
            $relatedLinks[] = [
                'icon' => '💰', 'label' => 'Cadencier de paiement (' . $scheduleCount . ' échéances)',
                'href' => route('achats.schedules.upcoming'),
                'subtitle' => 'Suivi des échéances multiples',
                'badge' => 'Détail', 'badgeColor' => 'violet',
            ];
        }
    @endphp
    <x-document.related :links="$relatedLinks" />

    <x-audit.timeline :model="\App\Models\SupplierInvoice::class" :id="$invoice->id" />

</div>
@endsection
