@extends('layouts.erp')
@section('title', 'Nouveau mouvement de stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.movements') }}" class="hover:text-gray-700">Mouvements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-3"
     x-data="{
         movType: '{{ old('movement_type', request()->query('type', '')) }}',
         hasLot: false, hasSerial: false, hasExpiry: false, isKit: false,
         products: {!! $products->mapWithKeys(fn($p) => [$p->id => ['lot' => $p->has_lot_number, 'serial' => $p->has_serial_number, 'expiry' => $p->has_expiry_date, 'kit' => $p->type === 'compose']])->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
         onProductChange(id) {
             const p = this.products[id];
             if (p) { this.hasLot = p.lot; this.hasSerial = p.serial; this.hasExpiry = p.expiry; this.isKit = p.kit; }
             else { this.hasLot = false; this.hasSerial = false; this.hasExpiry = false; this.isKit = false; }
         }
     }"
     x-init="onProductChange($el.querySelector('#product_id')?.value)">

    {{-- Header --}}
    <div>
        <div class="flex items-center gap-3 mb-1">
            <h1 class="text-[16px] font-bold text-gray-900"
                x-text="{
                    'entree':      'Entrée en stock',
                    'sortie':      'Sortie de stock',
                    'transfert':   'Transfert inter-dépôts',
                    'ajustement':  'Ajustement de stock',
                    'retour_client':     'Retour client',
                    'retour_fournisseur':'Retour fournisseur',
                }[movType] || 'Nouveau mouvement de stock'">
                Nouveau mouvement de stock
            </h1>
        </div>
        <p class="text-sm text-gray-500">Enregistrer un mouvement manuel de stock</p>
        {{-- Type quick-select chips --}}
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach([
                ['entree',     'Entrée',      'bg-emerald-100 text-emerald-700 border-emerald-300', 'bg-emerald-600 text-white border-emerald-600'],
                ['sortie',     'Sortie',      'bg-red-100 text-red-700 border-red-300',             'bg-red-600 text-white border-red-600'],
                ['transfert',  'Transfert',   'bg-blue-100 text-blue-700 border-blue-300',          'bg-emerald-700 text-white border-blue-600'],
                ['ajustement', 'Ajustement',  'bg-orange-100 text-orange-700 border-orange-300',    'bg-orange-500 text-white border-orange-500'],
            ] as [$val, $lbl, $inactive, $active])
            <button type="button"
                    @click="movType = '{{ $val }}'; $el.closest('form') && ($el.closest('[x-data]').querySelector('#movement_type').value = '{{ $val }}')"
                    :class="movType === '{{ $val }}' ? '{{ $active }}' : '{{ $inactive }}'"
                    class="border rounded-full px-3 py-1 text-xs font-medium transition-colors">
                {{ $lbl }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('stocks.movement.store') }}" method="POST"
          class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        @csrf

        {{-- Bandeau SAGE --}}
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[15px] font-bold text-gray-900">Mouvement de stock : Création</h2>
            <div class="flex items-center gap-2">
                <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                <a href="{{ route('stocks.movements') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        <div class="p-6 space-y-3">

        {{-- Product --}}
        <div>
            <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">
                Article <span class="text-red-500">*</span>
            </label>
            <select id="product_id" name="product_id" required
                    @change="onProductChange($event.target.value)"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('product_id') border-red-500 @enderror">
                <option value="">— Sélectionner un article —</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}"
                            data-price="{{ $product->purchase_price }}"
                            {{ old('product_id', request()->query('product_id')) == $product->id ? 'selected' : '' }}>
                        {{ $product->reference ? '[' . $product->reference . '] ' : '' }}{{ $product->name }}
                        @if($product->type === 'compose') (Kit) @endif
                    </option>
                @endforeach
            </select>
            @error('product_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-orange-600" x-show="isKit">
                Cet article est un kit — les composants seront automatiquement consommés lors d'une sortie.
            </p>
        </div>

        {{-- Warehouse source --}}
        <div>
            <label for="warehouse_id" class="block text-sm font-medium text-gray-700 mb-1">
                Entrepôt <span x-show="movType === 'transfert'" class="text-gray-400 font-normal">(source)</span>
                <span class="text-red-500">*</span>
            </label>
            <select id="warehouse_id" name="warehouse_id" required
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('warehouse_id') border-red-500 @enderror">
                <option value="">— Sélectionner un entrepôt —</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}{{ $wh->code ? ' (' . $wh->code . ')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Movement type --}}
        <div>
            <label for="movement_type" class="block text-sm font-medium text-gray-700 mb-1">
                Type de mouvement <span class="text-red-500">*</span>
            </label>
            <select id="movement_type" name="movement_type" required
                    x-model="movType"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('movement_type') border-red-500 @enderror">
                <option value="">— Sélectionner —</option>
                @foreach($movementTypes as $value => $label)
                    <option value="{{ $value }}" {{ old('movement_type') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('movement_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Destination warehouse (transfert only) --}}
        <div x-show="movType === 'transfert'" x-cloak>
            <label for="dest_warehouse_id" class="block text-sm font-medium text-gray-700 mb-1">
                Entrepôt de destination <span class="text-red-500">*</span>
            </label>
            <select id="dest_warehouse_id" name="dest_warehouse_id"
                    :required="movType === 'transfert'"
                    class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('dest_warehouse_id') border-red-500 @enderror">
                <option value="">— Entrepôt destination —</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ old('dest_warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}{{ $wh->code ? ' (' . $wh->code . ')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('dest_warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Quantity & Unit cost --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                    Quantité <span class="text-red-500">*</span>
                </label>
                <input type="number" id="quantity" name="quantity"
                       value="{{ old('quantity') }}"
                       :min="movType === 'ajustement' ? null : '1'"
                       step="1" inputmode="numeric" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('quantity') border-red-500 @enderror"
                       placeholder="0">
                <p class="mt-1 text-xs text-gray-500" x-show="movType === 'ajustement'">
                    Positive pour ajouter, négative pour retirer.
                </p>
                @error('quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="unit_cost" class="block text-sm font-medium text-gray-700 mb-1">
                    Coût unitaire (FCFA)
                </label>
                <input type="number" id="unit_cost" name="unit_cost"
                       value="{{ old('unit_cost') }}"
                       min="0" step="1"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('unit_cost') border-red-500 @enderror"
                       placeholder="0">
                <p class="mt-1 text-xs text-gray-400">Laissez vide pour conserver le CMP.</p>
                @error('unit_cost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Lot / Série / Péremption --}}
        <div class="border border-gray-200 rounded-[4px] p-4 space-y-4 bg-[#f7faf8]">
            <p class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Traçabilité (optionnel)</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="lot_number" class="block text-sm font-medium text-gray-700 mb-1">
                        N° de lot
                        <span x-show="hasLot" class="text-emerald-600 text-xs">(recommandé)</span>
                    </label>
                    <input type="text" id="lot_number" name="lot_number"
                           value="{{ old('lot_number') }}" maxlength="50"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 @error('lot_number') border-red-500 @enderror"
                           placeholder="LOT-001">
                    @error('lot_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="serial_number" class="block text-sm font-medium text-gray-700 mb-1">
                        N° de série
                        <span x-show="hasSerial" class="text-emerald-600 text-xs">(recommandé)</span>
                    </label>
                    <input type="text" id="serial_number" name="serial_number"
                           value="{{ old('serial_number') }}" maxlength="50"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 @error('serial_number') border-red-500 @enderror"
                           placeholder="SN-XXXXX">
                    @error('serial_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Date de péremption
                        <span x-show="hasExpiry" class="text-emerald-600 text-xs">(recommandé)</span>
                    </label>
                    <input type="date" id="expiry_date" name="expiry_date"
                           value="{{ old('expiry_date') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 @error('expiry_date') border-red-500 @enderror">
                    @error('expiry_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Date & Reference --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="movement_date" class="block text-sm font-medium text-gray-700 mb-1">
                    Date du mouvement <span class="text-red-500">*</span>
                </label>
                <input type="date" id="movement_date" name="movement_date"
                       value="{{ old('movement_date', now()->format('Y-m-d')) }}"
                       required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('movement_date') border-red-500 @enderror">
                @error('movement_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="reference" class="block text-sm font-medium text-gray-700 mb-1">
                    Référence / N° document
                </label>
                <input type="text" id="reference" name="reference"
                       value="{{ old('reference') }}"
                       maxlength="100"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('reference') border-red-500 @enderror"
                       placeholder="BL-001, BC-123...">
                @error('reference')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Reason code for ajustement --}}
        <div x-show="movType === 'ajustement'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">Motif de l'ajustement</label>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    'Inventaire physique',
                    'Correction saisie',
                    'Casse / détérioration',
                    'Vol / perte',
                    'Don / destruction',
                    'Démarrage système',
                ] as $reason)
                <button type="button"
                        @click="$refs.notesField.value = '{{ $reason }}'"
                        class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs px-2.5 py-1 rounded-full transition-colors">
                    {{ $reason }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea id="notes" name="notes" rows="2" x-ref="notesField"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('notes') border-red-500 @enderror"
                      placeholder="Motif, informations complémentaires...">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('stocks.movements') }}"
               class="text-sm text-gray-600 hover:text-gray-900 hover:underline">
                Annuler
            </a>
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-2.5 rounded-[4px] transition-colors">
                Enregistrer le mouvement
            </button>
        </div>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
// Auto-fill unit_cost from selected product's purchase_price
document.getElementById('product_id').addEventListener('change', function () {
    const selected  = this.options[this.selectedIndex];
    const price     = selected.dataset.price;
    const costInput = document.getElementById('unit_cost');
    if (price && (!costInput.value || costInput.value === '0')) {
        costInput.value = price;
    }
});
</script>
@endpush
