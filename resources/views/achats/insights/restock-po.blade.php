@extends('layouts.erp')
@section('title', 'Générer PO depuis réappro')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.dashboard') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Générer PO réappro</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">⚡ Générer bons de commande depuis réappro</h1>
        <p class="text-sm text-gray-500">Sélectionnez les articles à commander. Un PO en brouillon sera créé par fournisseur.</p>
    </div>

    @if($grouped->isEmpty())
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-6 text-center text-emerald-700 text-sm">
            ✓ Aucun article ne nécessite de réapprovisionnement actuellement.
        </div>
    @else

    <form method="POST" action="{{ route('achats.dashboard.restock-po') }}" x-data="restockSelector()">
        @csrf

        <div class="space-y-4">
            @foreach($grouped as $supplierId => $items)
                @php
                    $supplierName = $items->first()->supplier_name ?? '— Fournisseur non assigné —';
                    $hasSupplier  = $supplierId !== null && $supplierId !== '';
                @endphp
                <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">
                                🏢 {{ $supplierName }}
                                @if(!$hasSupplier)
                                <span class="text-xs text-red-600 ml-2">(à attribuer manuellement)</span>
                                @endif
                            </h2>
                            <p class="text-xs text-gray-500">{{ $items->count() }} article(s) en alerte</p>
                        </div>
                        @if($hasSupplier)
                        <label class="text-xs text-gray-600 cursor-pointer flex items-center gap-1">
                            <input type="checkbox" @change="toggleAll('{{ $supplierId }}', $event.target.checked)"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-emerald-500">
                            Tout sélectionner
                        </label>
                        @endif
                    </div>
                    @if(!$hasSupplier)
                        <div class="px-5 py-3 text-xs text-red-700 bg-red-50">
                            Ces articles n'ont pas de fournisseur par défaut.
                            <a href="{{ route('stocks.index') }}" class="underline">Affecter un fournisseur</a>
                            ou créer manuellement un PO.
                        </div>
                    @else
                    <table class="w-full text-sm">
                        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                            <tr>
                                <th class="px-3 py-1.5 w-12"></th>
                                <th class="px-3 py-1.5 text-left">Article</th>
                                <th class="px-3 py-1.5 text-right">Dispo / Réappro</th>
                                <th class="px-3 py-1.5 text-right">Suggérée</th>
                                <th class="px-3 py-1.5 text-right">Qté à commander</th>
                                <th class="px-3 py-1.5 text-right">Prix unitaire</th>
                                <th class="px-3 py-1.5 text-right">Sous-total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($items as $i => $item)
                                @php $key = $supplierId . '_' . $item->id; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5">
                                        <input type="checkbox"
                                               :checked="selected[`{{ $key }}`]"
                                               @change="selected[`{{ $key }}`] = $event.target.checked"
                                               name="selected_keys[]" value="{{ $key }}"
                                               class="rounded border-gray-300 text-blue-600">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <span class="font-mono text-xs text-blue-700">{{ $item->reference }}</span>
                                        <p class="text-sm">{{ $item->name }}</p>
                                    </td>
                                    <td class="px-3 py-1.5 text-right text-xs text-orange-700 tabular-nums">
                                        {{ number_format($item->available_qty, 0, ',', ' ') }} / {{ $item->reorder_point }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-emerald-600">{{ number_format($item->suggested_qty, 0, ',', ' ') }}</td>
                                    <td class="px-3 py-1.5 text-right">
                                        <input type="hidden" :name="`items[{{ $key }}][product_id]`" value="{{ $item->id }}" :disabled="!selected[`{{ $key }}`]">
                                        <input type="hidden" :name="`items[{{ $key }}][supplier_id]`" value="{{ $supplierId }}" :disabled="!selected[`{{ $key }}`]">
                                        <input type="number" :name="`items[{{ $key }}][quantity]`"
                                               min="1" step="1" inputmode="numeric"
                                               value="{{ (int) $item->suggested_qty }}"
                                               :disabled="!selected[`{{ $key }}`]"
                                               class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right">
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <input type="number" :name="`items[{{ $key }}][unit_price]`"
                                               min="0" step="0.01"
                                               value="{{ (int) ($item->last_purchase_price ?? $item->purchase_price ?? 0) }}"
                                               :disabled="!selected[`{{ $key }}`]"
                                               class="w-28 border border-gray-300 rounded px-2 py-1 text-sm text-right">
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">
                                        {{ $fmt(($item->last_purchase_price ?? $item->purchase_price ?? 0) * $item->suggested_qty) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('stocks.dashboard.restock') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" :disabled="Object.values(selected).filter(Boolean).length === 0"
                    :class="Object.values(selected).filter(Boolean).length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-emerald-700 hover:bg-emerald-800 text-white'"
                    class="text-sm font-medium px-6 py-1.5 rounded-[4px]">
                Générer le(s) bon(s) de commande
                <span x-show="Object.values(selected).filter(Boolean).length > 0" class="text-xs">
                    (<span x-text="Object.values(selected).filter(Boolean).length"></span> articles)
                </span>
            </button>
        </div>
    </form>
    @endif
</div>

@push('scripts')
<script>
function restockSelector() {
    return {
        selected: {},
        toggleAll(supplierId, checked) {
            // Toggle all checkboxes for items of this supplier
            document.querySelectorAll(`input[name="selected_keys[]"][value^="${supplierId}_"]`).forEach(cb => {
                cb.checked = checked;
                this.selected[cb.value] = checked;
            });
        },
    };
}
</script>
@endpush
@endsection
