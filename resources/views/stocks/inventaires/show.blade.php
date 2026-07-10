@extends('layouts.erp')
@section('title', 'Inventaire ' . ($session->number ?? '#'.$session->id))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.inventaires.index') }}" class="hover:text-gray-700">Inventaires</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $session->number ?? '#'.$session->id }}</span>
@endsection

@section('content')

@php
    $statusConfig = match($session->status) {
        'ouvert'   => ['label' => 'Ouvert',   'class' => 'bg-gray-100 text-gray-700'],
        'en_cours' => ['label' => 'En cours', 'class' => 'bg-blue-100 text-blue-700'],
        'valide'   => ['label' => 'Validé',   'class' => 'bg-emerald-100 text-emerald-700'],
        'annule'   => ['label' => 'Annulé',   'class' => 'bg-red-100 text-red-700'],
        default    => ['label' => $session->status, 'class' => 'bg-gray-100 text-gray-600'],
    };
    $isEditable = $session->isEditable();
@endphp

<div
    x-data="inventoryApp()"
    x-init="initItems({{ $session->items->map(fn($i) => [
        'id'                   => $i->id,
        'product_name'         => $i->product?->name ?? '—',
        'product_reference'    => $i->product?->reference ?? '',
        'theoretical_quantity' => (float) $i->theoretical_quantity,
        'counted_quantity'     => $i->counted_quantity !== null ? (float) $i->counted_quantity : null,
        'unit_cost'            => (float) $i->unit_cost,
        'notes'                => $i->notes ?? '',
    ])->values()->toJson() }})"
    class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900 font-mono">{{ $session->number ?? '#'.$session->id }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusConfig['class'] }}">
                    {{ $statusConfig['label'] }}
                </span>
                @if($session->type)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">
                    {{ $session->typeLabel() }}
                </span>
                @endif
            </div>
            <div class="flex flex-wrap gap-4 mt-1 text-sm text-gray-500">
                <span>
                    <span class="font-medium text-gray-700">Entrepôt :</span>
                    {{ $session->warehouse?->name ?? '—' }}
                </span>
                <span>
                    <span class="font-medium text-gray-700">Débuté le :</span>
                    {{ $session->started_at?->format('d/m/Y à H:i') ?? '—' }}
                </span>
                @if($session->validated_at)
                <span>
                    <span class="font-medium text-gray-700">Validé le :</span>
                    {{ $session->validated_at->format('d/m/Y à H:i') }}
                </span>
                @endif
                <span>
                    <span class="font-medium text-gray-700">Articles :</span>
                    {{ $session->items->count() }}
                </span>
            </div>
            @if($session->notes)
            <p class="mt-1 text-sm text-gray-500 italic">{{ $session->notes }}</p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">

            {{-- Export dropdown --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Exporter
                    <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-transition
                     class="absolute right-0 mt-1 w-44 bg-white border border-gray-300 rounded-[4px] shadow-lg z-20 py-1">

                    {{-- Excel --}}
                    <a href="{{ route('stocks.inventaires.export-excel', $session) }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                        </svg>
                        Excel (.xlsx)
                    </a>

                    {{-- PDF --}}
                    <a href="{{ route('stocks.inventaires.export-pdf', $session) }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 transition-colors">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        PDF (A4 paysage)
                    </a>
                </div>
            </div>

            @if($isEditable)
            <button type="button" @click="submitCount()"
                    class="border border-emerald-600 text-emerald-800 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Enregistrer
            </button>
            @endif

            @if($session->status === 'en_cours')
            <form action="{{ route('stocks.inventaires.validate', $session) }}" method="POST"
                  onsubmit="return confirm('Valider définitivement cet inventaire ? Le stock sera mis à jour avec les quantités comptées.')">
                @csrf
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Valider l'inventaire
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Summary bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-[16px] font-bold text-gray-900" x-text="totalItems"></p>
            <p class="text-xs text-gray-500 mt-0.5">Articles total</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-[16px] font-bold text-emerald-700" x-text="countedItems"></p>
            <p class="text-xs text-gray-500 mt-0.5">Articles comptés</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-[16px] font-bold text-emerald-600"
               x-text="'+' + positiveVariance.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
            <p class="text-xs text-gray-500 mt-0.5">Écart positif total</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-[16px] font-bold text-red-600"
               x-text="negativeVariance.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
            <p class="text-xs text-gray-500 mt-0.5">Écart négatif total</p>
        </div>
    </div>

    {{-- Hidden form for submission --}}
    <form id="countForm"
          action="{{ route('stocks.inventaires.count', $session) }}"
          method="POST"
          style="display:none;">
        @csrf
        <template x-for="(item, index) in items" :key="item.id">
            <div>
                <input type="hidden" :name="`items[${index}][id]`" :value="item.id">
                <input type="hidden" :name="`items[${index}][counted_quantity]`" :value="item.counted_quantity ?? ''">
                <input type="hidden" :name="`items[${index}][notes]`" :value="item.notes">
            </div>
        </template>
    </form>

    {{-- Progress bar --}}
    @if($isEditable)
    <div class="bg-white rounded-[4px] border border-gray-300 p-3">
        <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
            <span>Progression du comptage</span>
            <span x-text="`${countedItems} / ${totalItems} articles`"></span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2">
            <div class="bg-emerald-600 h-2 rounded-full transition-all duration-300"
                 :style="`width: ${totalItems > 0 ? Math.round((countedItems / totalItems) * 100) : 0}%`"></div>
        </div>
    </div>
    @endif

    {{-- Counting table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produit</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden md:table-cell">Référence</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Stock théorique</th>
                        <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-40">Qté comptée</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Écart</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Valeur écart</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(item, index) in items" :key="item.id">
                        <tr :class="rowClass(item)" class="transition-colors">
                            <td class="px-3 py-1.5">
                                <span class="font-medium text-gray-900" x-text="item.product_name"></span>
                            </td>
                            <td class="px-3 py-1.5 hidden md:table-cell">
                                <span class="font-mono text-xs text-gray-500" x-text="item.product_reference || '—'"></span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">
                                <span x-text="formatQty(item.theoretical_quantity)"></span>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                @if($isEditable)
                                <input
                                    type="number"
                                    x-model.number="item.counted_quantity"
                                    @change="recalc(item)"
                                    @input="recalc(item)"
                                    min="0"
                                    step="0.01"
                                    :placeholder="formatQty(item.theoretical_quantity)"
                                    class="w-32 border border-gray-300 rounded-[4px] px-2 py-1 text-sm text-center focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 tabular-nums">
                                @else
                                <span class="tabular-nums"
                                      :class="item.counted_quantity !== null ? 'text-gray-900' : 'text-gray-400'"
                                      x-text="item.counted_quantity !== null ? formatQty(item.counted_quantity) : '—'"></span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums">
                                <span :class="varianceClass(item)"
                                      x-text="varianceText(item)"></span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums hidden lg:table-cell">
                                <span :class="item.counted_quantity !== null && varianceVal(item) !== 0 ? (varianceVal(item) > 0 ? 'text-emerald-600' : 'text-red-600') : 'text-gray-400'"
                                      x-text="item.counted_quantity !== null ? (varianceVal(item) !== 0 ? Math.abs(varianceVal(item)).toLocaleString('fr-FR', {maximumFractionDigits: 0}) + ' FCFA' : '—') : '—'"></span>
                            </td>
                            <td class="px-3 py-1.5 hidden xl:table-cell">
                                @if($isEditable)
                                <input
                                    type="text"
                                    x-model="item.notes"
                                    maxlength="200"
                                    class="w-full border border-gray-300 rounded-[4px] px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                                    placeholder="Note...">
                                @else
                                <span class="text-xs text-gray-500 italic" x-text="item.notes || '—'"></span>
                                @endif
                            </td>
                        </tr>
                    </template>
                    <template x-if="items.length === 0">
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">
                                Aucun article dans cet inventaire.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Footer save button (repeated for convenience on long lists) --}}
        @if($isEditable)
        <div class="px-4 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                <span x-text="countedItems"></span> / <span x-text="totalItems"></span> articles comptés
            </p>
            <button type="button" @click="submitCount()"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Enregistrer le comptage
            </button>
        </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
<script>
function inventoryApp() {
    return {
        items: [],

        initItems(data) {
            this.items = data.map(item => ({
                ...item,
                variance: item.counted_quantity !== null
                    ? item.counted_quantity - item.theoretical_quantity
                    : 0,
                variance_value: item.counted_quantity !== null
                    ? (item.counted_quantity - item.theoretical_quantity) * item.unit_cost
                    : 0,
            }));
        },

        recalc(item) {
            if (item.counted_quantity !== null && item.counted_quantity !== '') {
                const counted = parseFloat(item.counted_quantity) || 0;
                item.counted_quantity = counted;
                item.variance = counted - item.theoretical_quantity;
                item.variance_value = item.variance * item.unit_cost;
            } else {
                item.variance = 0;
                item.variance_value = 0;
            }
        },

        get totalItems() {
            return this.items.length;
        },

        get countedItems() {
            return this.items.filter(i => i.counted_quantity !== null && i.counted_quantity !== '').length;
        },

        get positiveVariance() {
            return this.items.reduce((sum, i) => {
                const v = parseFloat(i.variance) || 0;
                return sum + (v > 0 ? v : 0);
            }, 0);
        },

        get negativeVariance() {
            return this.items.reduce((sum, i) => {
                const v = parseFloat(i.variance) || 0;
                return sum + (v < 0 ? v : 0);
            }, 0);
        },

        varianceVal(item) {
            return parseFloat(item.variance_value) || 0;
        },

        varianceText(item) {
            if (item.counted_quantity === null || item.counted_quantity === '') return '—';
            const v = parseFloat(item.variance) || 0;
            if (v === 0) return '=';
            const sign = v > 0 ? '+' : '';
            return sign + v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        varianceClass(item) {
            if (item.counted_quantity === null || item.counted_quantity === '') return 'text-gray-400';
            const v = parseFloat(item.variance) || 0;
            if (v > 0) return 'text-emerald-600 font-semibold';
            if (v < 0) return 'text-red-600 font-semibold';
            return 'text-gray-400';
        },

        rowClass(item) {
            if (item.counted_quantity === null || item.counted_quantity === '') return 'hover:bg-gray-50';
            const v = parseFloat(item.variance) || 0;
            if (v > 0) return 'bg-emerald-50 hover:bg-emerald-100';
            if (v < 0) return 'bg-red-50 hover:bg-red-100';
            return 'bg-gray-50';
        },

        formatQty(n) {
            if (n === null || n === undefined) return '—';
            return parseFloat(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        submitCount() {
            // The hidden form's template inputs are already bound via x-model.
            // We need to flush Alpine's DOM updates, then submit.
            this.$nextTick(() => {
                document.getElementById('countForm').submit();
            });
        },
    };
}
</script>
@endpush
