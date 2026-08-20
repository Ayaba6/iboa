@extends('layouts.erp')
@section('title', 'Modifier ' . $pr->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.demandes-achat.index') }}" class="hover:text-gray-700">Demandes d'achat</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.demandes-achat.show', $pr) }}" class="hover:text-gray-700">{{ $pr->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-3"
     x-data="purchaseRequestEditForm()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[15px] font-bold text-gray-900">Modifier la demande</h1>
            <p class="text-[13px] text-gray-500 mt-0.5 font-mono">{{ $pr->number }}</p>
        </div>
        <a href="{{ route('achats.demandes-achat.show', $pr) }}"
           class="text-[13px] text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-[13px]">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('achats.demandes-achat.update', $pr) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="space-y-3">

            {{-- General info --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-[13px] font-semibold text-gray-800 mb-4">Informations générales</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Département / Service</label>
                        <input type="text" name="department" value="{{ old('department', $pr->department) }}"
                               placeholder="Ex: Informatique, Comptabilité..."
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Date souhaitée</label>
                        <input type="date" name="needed_at" value="{{ old('needed_at', $pr->needed_at?->format('Y-m-d')) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    {{-- [Maquette Demande d'achat] --}}
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Priorité</label>
                        @php $prio = old('priority', $pr->priority ?? 'normale'); @endphp
                        <select name="priority" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            <option value="normale" @selected($prio==='normale')>Normale</option>
                            <option value="haute" @selected($prio==='haute')>Haute</option>
                            <option value="basse" @selected($prio==='basse')>Basse</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Dépôt de destination</label>
                        <select name="warehouse_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            <option value="">—</option>
                            @foreach($warehouses ?? [] as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id', $pr->warehouse_id)==$w->id)>{{ $w->code }} – {{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Projet</label>
                        <input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $pr->project_reference) }}"
                               placeholder="PROJ-2026-0008"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] font-mono focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Justification / Objet</label>
                        <input type="text" name="justification" value="{{ old('justification', $pr->justification) }}"
                               placeholder="Raison de la demande..."
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[13px] font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-[13px] focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                  placeholder="Informations complémentaires...">{{ old('notes', $pr->notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[13px] font-semibold text-gray-800">Articles demandés</h2>
                    <button type="button" @click="addLine()"
                            class="text-[13px] text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter un article
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left text-[12px] font-semibold text-gray-500 uppercase w-5/12">Article / Description</th>
                                <th class="pb-2 text-right text-[12px] font-semibold text-gray-500 uppercase w-1/12">Qté</th>
                                <th class="pb-2 text-right text-[12px] font-semibold text-gray-500 uppercase w-2/12">Prix estimé</th>
                                <th class="pb-2 text-right text-[12px] font-semibold text-gray-500 uppercase w-2/12">Total estimé</th>
                                <th class="pb-2 text-left text-[12px] font-semibold text-gray-500 uppercase w-2/12">Notes</th>
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
                                                class="w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] focus:ring-1 focus:ring-amber-500 mb-1">
                                            <option value="">— Sélectionner —</option>
                                            @foreach($products as $product)
                                            <option value="{{ $product->id }}"
                                                    data-price="{{ $product->purchase_price ?? 0 }}"
                                                    data-name="{{ $product->name }}">
                                                {{ $product->name }} @if($product->reference)({{ $product->reference }})@endif
                                            </option>
                                            @endforeach
                                        </select>
                                        <input type="text" :name="`items[${index}][description]`"
                                               x-model="line.description"
                                               placeholder="Description..."
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`items[${index}][quantity]`"
                                               x-model="line.quantity"
                                               @input="calcLine(index)"
                                               min="1" step="1" inputmode="numeric"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] text-right focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" :name="`items[${index}][estimated_price]`"
                                               x-model="line.estimated_price"
                                               @input="calcLine(index)"
                                               min="0" step="1"
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] text-right focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 pr-2 text-right font-medium tabular-nums" x-text="formatAmount(line.total)"></td>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`items[${index}][notes]`"
                                               x-model="line.notes"
                                               placeholder="Note..."
                                               class="w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2">
                                        <button type="button" @click="removeLine(index)"
                                                class="p-1 text-gray-400 hover:text-red-500 rounded">
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

                <div class="mt-4 flex justify-end">
                    <div class="w-64 text-[13px]">
                        <div class="flex justify-between font-semibold text-gray-900 border-t border-gray-200 pt-2">
                            <span>Total estimé</span>
                            <span class="tabular-nums" x-text="formatAmount(totalEstimated)"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('achats.demandes-achat.show', $pr) }}"
                   class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-[13px] font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="bg-amber-600 hover:bg-amber-700 text-white text-[13px] font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function purchaseRequestEditForm() {
    return {
        lines: [],
        nextId: 0,

        init() {
            @if(old('items'))
            {{-- Restore lines after validation failure --}}
            @foreach(old('items', []) as $i => $item)
            this.lines.push({
                id:              this.nextId++,
                product_id:      '{{ $item['product_id'] ?? '' }}',
                description:     @json($item['description'] ?? ''),
                quantity:        {{ $item['quantity'] ?? 1 }},
                estimated_price: {{ $item['estimated_price'] ?? 0 }},
                total:           {{ round(($item['quantity'] ?? 1) * ($item['estimated_price'] ?? 0)) }},
                notes:           @json($item['notes'] ?? ''),
            });
            @endforeach
            @else
            {{-- Load existing items --}}
            @foreach($pr->items as $item)
            this.lines.push({
                id:              this.nextId++,
                product_id:      '{{ $item->product_id ?? '' }}',
                description:     @json($item->description ?? ''),
                quantity:        {{ $item->quantity }},
                estimated_price: {{ (int) $item->estimated_price }},
                total:           {{ (int) $item->line_total }},
                notes:           @json($item->notes ?? ''),
            });
            @endforeach
            @endif

            // Restore selected product on each select after DOM ready
            this.$nextTick(() => {
                this.lines.forEach((line, i) => {
                    if (!line.product_id) return;
                    const sel = document.querySelectorAll('[data-product-select]')[i];
                    if (sel) sel.value = line.product_id;
                });
            });
        },

        addLine() {
            this.lines.push({ id: this.nextId++, product_id: '', description: '', quantity: 1, estimated_price: 0, total: 0, notes: '' });
        },

        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },

        onProductChange(event, index) {
            const option = event.target.options[event.target.selectedIndex];
            this.lines[index].product_id      = option.value;
            this.lines[index].estimated_price = Math.round(parseFloat(option.dataset.price || 0));
            if (!this.lines[index].description) {
                this.lines[index].description = option.dataset.name || '';
            }
            this.calcLine(index);
        },

        calcLine(index) {
            const l = this.lines[index];
            l.total = Math.round((parseFloat(l.quantity) || 0) * (parseFloat(l.estimated_price) || 0));
        },

        get totalEstimated() {
            return this.lines.reduce((s, l) => s + (parseInt(l.total) || 0), 0);
        },

        formatAmount(val) {
            return new Intl.NumberFormat('fr-FR').format(val || 0) + ' FCFA';
        },
    };
}
</script>
@endpush
@endsection
