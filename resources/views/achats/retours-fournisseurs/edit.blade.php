@extends('layouts.erp')
@section('title', 'Modifier ' . $return->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.retours-fournisseurs.index') }}" class="hover:text-gray-700">Retours fournisseurs</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.retours-fournisseurs.show', $return) }}" class="hover:text-gray-700">{{ $return->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3"
     x-data="supplierReturnEditForm()"
     x-init="init()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Modifier le retour</h1>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ $return->number }}</p>
        </div>
        <a href="{{ route('achats.retours-fournisseurs.show', $return) }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('achats.retours-fournisseurs.update', $return) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="space-y-3">

            {{-- General info --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Informations générales</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fournisseur <span class="text-red-500">*</span>
                        </label>
                        <select name="supplier_id"
                                class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 @error('supplier_id') border-red-500 @enderror"
                                required>
                            <option value="">Sélectionner un fournisseur</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    {{ old('supplier_id', $return->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date de retour <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="returned_at"
                               value="{{ old('returned_at', $return->returned_at?->format('Y-m-d')) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 @error('returned_at') border-red-500 @enderror"
                               required>
                        @error('returned_at')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motif du retour</label>
                        <input type="text" name="reason"
                               value="{{ old('reason', $return->reason) }}"
                               placeholder="Produit défectueux, erreur de commande..."
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                  placeholder="Informations complémentaires...">{{ old('notes', $return->notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Lines --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-800">Articles retournés</h2>
                    <button type="button" @click="addLine()"
                            class="text-sm text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter une ligne
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-5/12">Article / Description</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-1/12">Qté</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-2/12">Prix unit. HT</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-1/12">Rem %</th>
                                <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-2/12">Total HT</th>
                                <th class="pb-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="line.id">
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 pr-2">
                                        <select :name="`items[${index}][product_id]`"
                                                data-product-select
                                                @change="onProductChange($event, index)"
                                                class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 mb-1">
                                            <option value="">— Sélectionner un produit —</option>
                                            @foreach($products as $product)
                                            <option value="{{ $product->id }}" data-price="{{ $product->purchase_price ?? 0 }}">
                                                {{ $product->name }} @if($product->reference)({{ $product->reference }})@endif
                                            </option>
                                            @endforeach
                                        </select>
                                        <input type="text" :name="`items[${index}][description]`"
                                               x-model="line.description"
                                               placeholder="Description..."
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`items[${index}][quantity]`"
                                               x-model="line.quantity"
                                               @input="calcLine(index)"
                                               min="1" step="1" inputmode="numeric"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`items[${index}][unit_price]`"
                                               x-model="line.unit_price"
                                               @input="calcLine(index)"
                                               min="0" step="1"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`items[${index}][discount_percent]`"
                                               x-model="line.discount"
                                               @input="calcLine(index)"
                                               min="0" max="100" step="1" inputmode="numeric"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                        <input type="hidden" :name="`items[${index}][tax_rate_value]`" value="0">
                                    </td>
                                    <td class="py-2 pr-2 text-right font-medium tabular-nums" x-text="formatAmount(line.total_ht)"></td>
                                    <td class="py-2">
                                        <button type="button" @click="removeLine(index)"
                                                class="p-1 text-gray-400 hover:text-red-500 rounded transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Totals --}}
                <div class="mt-4 flex justify-end">
                    <div class="w-72 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Sous-total HT</span>
                            <span class="font-medium tabular-nums" x-text="formatAmount(subtotalHt)"></span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900">
                            <span>Total TTC</span>
                            <span class="tabular-nums" x-text="formatAmount(totalTtc)"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('achats.retours-fournisseurs.show', $return) }}"
                   class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function supplierReturnEditForm() {
    return {
        lines: [],
        nextId: 0,

        init() {
            @if(old('items'))
            @foreach(old('items', []) as $i => $item)
            this.lines.push({
                id:          this.nextId++,
                product_id:  '{{ $item['product_id'] ?? '' }}',
                description: @json($item['description'] ?? ''),
                quantity:    {{ $item['quantity'] ?? 1 }},
                unit_price:  {{ $item['unit_price'] ?? 0 }},
                discount:    {{ $item['discount_percent'] ?? 0 }},
                total_ht:    0,
            });
            @endforeach
            @else
            {{-- Load existing items --}}
            @foreach($return->items as $item)
            this.lines.push({
                id:          this.nextId++,
                product_id:  '{{ $item->product_id ?? '' }}',
                description: @json($item->description ?? ''),
                quantity:    {{ $item->quantity }},
                unit_price:  {{ (int) $item->unit_price }},
                discount:    {{ $item->discount_percent }},
                total_ht:    {{ (int) $item->line_total_ht }},
            });
            @endforeach
            @endif

            // Restore selected product
            this.$nextTick(() => {
                this.lines.forEach((line, i) => {
                    if (!line.product_id) return;
                    const sel = document.querySelectorAll('[data-product-select]')[i];
                    if (sel) sel.value = line.product_id;
                });
                // Recalculate all lines
                this.lines.forEach((_, i) => this.calcLine(i));
            });
        },

        addLine() {
            this.lines.push({
                id: this.nextId++,
                description: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
                total_ht: 0,
            });
        },

        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
            }
        },

        onProductChange(event, index) {
            const option = event.target.options[event.target.selectedIndex];
            const price  = parseFloat(option.dataset.price || 0);
            this.lines[index].unit_price = Math.round(price);
            this.calcLine(index);
        },

        calcLine(index) {
            const line    = this.lines[index];
            const qty     = parseFloat(line.quantity) || 0;
            const price   = parseFloat(line.unit_price) || 0;
            const disc    = parseFloat(line.discount) || 0;
            line.total_ht = Math.round(qty * price * (1 - disc / 100));
        },

        get subtotalHt() {
            return this.lines.reduce((s, l) => s + (parseInt(l.total_ht) || 0), 0);
        },

        get totalTtc() {
            return this.subtotalHt;
        },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR').format(val || 0) + ' FCFA';
        },
    };
}
</script>
@endpush
@endsection
