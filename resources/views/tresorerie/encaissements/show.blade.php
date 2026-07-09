@extends('layouts.erp')
@section('title', 'Encaissement ' . $payment->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.encaissements.index') }}" class="hover:text-gray-700">Encaissements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $payment->number }}</span>
@endsection

@section('content')
<div class="space-y-3">

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
                @if($payment->is_acompte)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Acompte</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Encaissement du {{ $payment->payment_date?->format('d/m/Y') }}
                — {{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Reçu PDF --}}
            <a href="{{ route('tresorerie.encaissements.recu', $payment) }}"
               target="_blank"
               class="inline-flex items-center gap-2 border border-green-300 text-green-700 hover:bg-green-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px] transition-colors"
               title="Télécharger le reçu PDF">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reçu PDF
            </a>
            <div class="text-right">
                <p class="text-xs text-gray-500">Montant reçu</p>
                <p class="text-[16px] font-bold text-green-700 tabular-nums">
                    {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Info card --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Détails du paiement</h2>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500">Client</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}
                    </dd>
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
                    <dd class="font-semibold text-green-700 tabular-nums">{{ number_format($payment->allocated_amount, 0, ',', ' ') }} FCFA</dd>
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
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Factures imputées</h2>

            @if($payment->allocations->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-200">
                            <th class="pb-2 text-left">Facture</th>
                            <th class="pb-2 text-left hidden md:table-cell">Date émission</th>
                            <th class="pb-2 text-right">Montant facture</th>
                            <th class="pb-2 text-right">Montant imputé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($payment->allocations as $alloc)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4">
                                @if($alloc->invoice)
                                    <a href="{{ route('ventes.factures.show', $alloc->invoice) }}"
                                       class="font-mono font-semibold text-emerald-700 hover:text-emerald-900">
                                        {{ $alloc->invoice->number }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Facture supprimée</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-gray-600 hidden md:table-cell">
                                {{ $alloc->invoice?->issued_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 text-right tabular-nums text-gray-700">
                                {{ number_format($alloc->invoice?->total_ttc ?? 0, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-3 text-right tabular-nums font-semibold text-green-700">
                                {{ number_format($alloc->amount, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="3" class="pt-3 text-sm font-semibold text-gray-700 text-right pr-4">Total imputé :</td>
                            <td class="pt-3 text-right tabular-nums font-bold text-green-700">
                                {{ number_format($payment->allocations->sum('amount'), 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Summary bar --}}
            @php
                $allocated   = $payment->allocated_amount;
                $total       = $payment->amount;
                $pct         = $total > 0 ? min(100, round($allocated / $total * 100)) : 0;
            @endphp
            <div class="mt-4 p-3 bg-gray-50 rounded-[4px]">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Imputation</span>
                    <span>{{ $pct }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
                <div class="flex justify-between text-xs mt-1">
                    <span class="text-green-700 font-medium">{{ number_format($allocated, 0, ',', ' ') }} FCFA imputés</span>
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
                    <p class="text-sm">Aucune imputation — ce paiement n'est pas encore lettrée sur une facture.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Imputation a posteriori (lettrage) ───────────────────────────────── --}}
    @if($payment->unallocated_amount > 0 && $payment->status !== 'annule')
    @php
        $unpaidInvoices = \App\Models\Invoice::where('client_id', $payment->client_id)
            ->whereIn('status', ['emise','envoyee','partiellement_payee','en_retard'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_at')
            ->get(['id','number','issued_at','due_at','total_ttc','remaining_amount','status']);
    @endphp
    @if($unpaidInvoices->count())
    <div class="bg-white rounded-[4px] border border-orange-200 overflow-hidden"
         x-data="{ open: true }">
        <div class="px-3 py-1.5 bg-orange-50 border-b border-orange-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <h2 class="text-sm font-bold text-orange-700">Imputer sur une facture</h2>
                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-medium">
                    {{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA disponibles
                </span>
            </div>
            <button @click="open = !open" class="text-orange-400 hover:text-orange-600">
                <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
        <div x-show="open" x-collapse>
            <form action="{{ route('tresorerie.encaissements.imputer', $payment) }}" method="POST"
                  class="p-4 space-y-4" x-data="imputerForm({{ $payment->unallocated_amount }})">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide border-b border-gray-200">
                                <th class="pb-2 text-left">Facture</th>
                                <th class="pb-2 text-center hidden md:table-cell">Échéance</th>
                                <th class="pb-2 text-right">Reste dû</th>
                                <th class="pb-2 text-center w-8">Choisir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($unpaidInvoices as $inv)
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                @click="selectInvoice({{ $inv->id }}, {{ (int)$inv->remaining_amount }})">
                                <td class="py-2.5 pr-4">
                                    <span class="font-mono font-semibold text-emerald-700">{{ $inv->number }}</span>
                                    @php
                                        $statusColors = ['emise'=>'gray','envoyee'=>'blue','partiellement_payee'=>'amber','en_retard'=>'red'];
                                        $statusLabels = ['emise'=>'Émise','envoyee'=>'Envoyée','partiellement_payee'=>'Partielle','en_retard'=>'En retard'];
                                        $sc = $statusColors[$inv->status] ?? 'gray';
                                    @endphp
                                    <span class="ml-1.5 text-xs bg-{{ $sc }}-100 text-{{ $sc }}-700 px-1.5 py-0.5 rounded-full">
                                        {{ $statusLabels[$inv->status] ?? $inv->status }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-center text-gray-500 text-xs hidden md:table-cell">
                                    {{ $inv->due_at?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-2.5 pr-4 text-right tabular-nums font-medium text-gray-800">
                                    {{ number_format($inv->remaining_amount, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="py-2.5 text-center">
                                    <input type="radio" name="_invoice_radio" :value="{{ $inv->id }}"
                                           :checked="selectedInvoiceId === {{ $inv->id }}"
                                           @click.stop="selectInvoice({{ $inv->id }}, {{ (int)$inv->remaining_amount }})"
                                           class="text-orange-500 focus:ring-emerald-400">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="invoice_id" :value="selectedInvoiceId">

                <div class="flex flex-col sm:flex-row gap-3 items-end pt-2 border-t border-gray-100">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Montant à imputer (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="allocated_amount"
                               x-model="allocAmount"
                               :max="maxAlloc"
                               :min="1"
                               placeholder="Montant…"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400 focus:border-emerald-500 tabular-nums">
                        <p class="text-[10px] text-gray-400 mt-1">
                            Max. disponible : <span class="font-medium tabular-nums" x-text="maxAlloc.toLocaleString('fr-FR')"></span> FCFA
                        </p>
                    </div>
                    <button type="submit"
                            :disabled="!selectedInvoiceId || allocAmount <= 0"
                            class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Valider l'imputation
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endif

    {{-- Back --}}
    <div>
        <a href="{{ route('tresorerie.encaissements.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour à la liste
        </a>
    </div>

</div>

@push('scripts')
<script>
function imputerForm(available) {
    return {
        selectedInvoiceId: null,
        invoiceRemaining: 0,
        allocAmount: '',
        get maxAlloc() {
            return Math.min(available, this.invoiceRemaining);
        },
        selectInvoice(id, remaining) {
            this.selectedInvoiceId = id;
            this.invoiceRemaining  = remaining;
            this.allocAmount       = Math.min(available, remaining);
        }
    };
}
</script>
@endpush
@endsection
