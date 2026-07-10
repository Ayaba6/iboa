@extends('layouts.erp')
@section('title', 'Nouvel avoir')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.avoirs.index') }}" class="hover:text-gray-700">Avoirs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="space-y-1 mb-5">
    <h1 class="text-[16px] font-bold text-gray-900">Nouvel avoir</h1>
    @if($invoice)
    <p class="text-sm text-gray-500">Avoir sur la facture <span class="font-mono font-semibold text-emerald-700">{{ $invoice->number }}</span> — {{ $invoice->client?->name }}</p>
    @endif
</div>

<x-validation-errors />

@if(!$invoice)
<div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4 mb-4 text-sm text-amber-800">
    Aucune facture sélectionnée. Accédez à cette page depuis la fiche d'une facture.
    <a href="{{ route('ventes.factures.index') }}" class="underline font-medium ml-1">Voir les factures</a>
</div>
@else

@php
    $creditNoteInvoice  = $invoice->load('items.product');
    $creditNoteProducts = $invoice->items->map(fn($i) => [
        'id'             => $i->product_id,
        'name'           => $i->description,
        'unit_price'     => $i->unit_price,
        'tax_rate_value' => $i->tax_rate_value,
    ]);
@endphp
<script>
window._creditNoteFormData = {
    invoice:  {!! json_encode($creditNoteInvoice,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
    products: {!! json_encode($creditNoteProducts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
};
</script>

<form method="POST" action="{{ route('ventes.avoirs.store') }}">
    @csrf
    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

    <div x-data="creditNoteForm()" class="space-y-3">

        {{-- En-tête --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Date d'émission <span class="text-red-500">*</span></label>
                    <input type="date" name="issued_at" value="{{ old('issued_at', now()->format('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500 focus:border-purple-500">
                    @error('issued_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Motif de l'avoir</label>
                    <input type="text" name="reason" value="{{ old('reason') }}"
                           placeholder="Ex: Retour marchandise, erreur de facturation..."
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500 focus:border-purple-500">
                    @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notes internes</label>
                    <textarea name="notes" rows="2"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-purple-500 focus:border-purple-500">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Lignes --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Lignes de l'avoir</h2>
                <button type="button" @click="prefillFromInvoice()"
                        class="text-xs text-purple-600 hover:text-purple-800 font-medium border border-purple-200 hover:bg-purple-50 px-3 py-1.5 rounded-[4px] transition-colors">
                    Reprendre toutes les lignes de la facture
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-8">#</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Description</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-20">Qté</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Prix Unit.</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-20">TVA%</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Total HT</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-28">Total TTC</th>
                            <th class="px-3 py-2.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="'items[' + index + '][description]'"
                                           x-model="item.description" placeholder="Description..."
                                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-purple-500 min-w-[200px]">
                                    <input type="hidden" :name="'items[' + index + '][product_id]'"  :value="item.product_id">
                                    <input type="hidden" :name="'items[' + index + '][unit_id]'"      :value="item.unit_id">
                                    <input type="hidden" :name="'items[' + index + '][tax_rate_id]'"  :value="item.tax_rate_id">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" :name="'items[' + index + '][quantity]'"
                                           x-model.number="item.quantity" min="1" step="1" inputmode="numeric"
                                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-purple-500">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" :name="'items[' + index + '][unit_price]'"
                                           x-model.number="item.unit_price" min="0" step="1"
                                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-purple-500">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" :name="'items[' + index + '][tax_rate_value]'"
                                           x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric"
                                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-purple-500">
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700 font-medium text-xs whitespace-nowrap"
                                    x-text="fmt(lineHt(item))"></td>
                                <td class="px-3 py-2 text-right tabular-nums text-purple-700 font-semibold text-xs whitespace-nowrap"
                                    x-text="fmt(lineTtc(item))"></td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                            class="text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="addItem()"
                        class="flex items-center gap-2 text-sm text-purple-600 hover:text-purple-800 font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Ajouter une ligne
                </button>
            </div>
        </div>

        {{-- Totaux --}}
        <div class="flex justify-end">
            <div class="w-full sm:w-72 bg-white rounded-[4px] border border-gray-300 p-5 space-y-3">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Sous-total HT</span>
                    <span class="tabular-nums font-medium" x-text="fmt(subtotalHt)"></span>
                </div>
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Total TVA</span>
                    <span class="tabular-nums font-medium" x-text="fmt(totalTax)"></span>
                </div>
                <div class="border-t border-gray-200 pt-3 flex justify-between">
                    <span class="text-base font-bold text-gray-900">Total avoir TTC</span>
                    <span class="text-base font-bold text-purple-700 tabular-nums" x-text="fmt(totalTtc)"></span>
                </div>
            </div>
        </div>

        {{-- Boutons --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ $invoice ? route('ventes.factures.show', $invoice) : route('ventes.avoirs.index') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                Créer l'avoir
            </button>
        </div>

    </div>
</form>

@push('scripts')
<script>
function creditNoteForm() {
    const { invoice } = window._creditNoteFormData;
    return {
        items: [{ product_id: null, unit_id: null, tax_rate_id: null, description: '', quantity: 1, unit_price: 0, tax_rate_value: 18 }],

        get subtotalHt() { return this.items.reduce((s, i) => s + this.lineHt(i), 0); },
        get totalTax()   { return this.items.reduce((s, i) => s + Math.round(this.lineHt(i) * i.tax_rate_value / 100), 0); },
        get totalTtc()   { return this.subtotalHt + this.totalTax; },

        lineHt(item)   { return Math.round(item.quantity * item.unit_price); },
        lineTtc(item)  { return Math.round(this.lineHt(item) * (1 + item.tax_rate_value / 100)); },

        addItem()       { this.items.push({ product_id: null, unit_id: null, tax_rate_id: null, description: '', quantity: 1, unit_price: 0, tax_rate_value: 18 }); },
        removeItem(i)   { this.items.splice(i, 1); },

        prefillFromInvoice() {
            if (!invoice || !invoice.items) return;
            this.items = invoice.items.map(i => ({
                product_id:     i.product_id,
                unit_id:        i.unit_id        ?? null,
                tax_rate_id:    i.tax_rate_id    ?? null,
                description:    i.description,
                quantity:       parseInt(i.quantity, 10) || 1,
                unit_price:     parseFloat(i.unit_price)     || 0,
                tax_rate_value: parseFloat(i.tax_rate_value) || 18,
            }));
        },

        fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA'; },
    };
}
</script>
@endpush

@endif
@endsection
