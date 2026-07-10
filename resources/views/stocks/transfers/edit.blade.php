@extends('layouts.erp')
@section('title', 'Modifier transfert ' . $transfer->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.transfers.index') }}" class="hover:text-gray-700">Transferts</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.transfers.show', $transfer) }}" class="hover:text-gray-700">{{ $transfer->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3" x-data="transferForm()" x-init="init()">

    <h1 class="text-[16px] font-bold text-gray-900">Modifier le transfert <span class="font-mono text-blue-700">{{ $transfer->number }}</span></h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form action="{{ route('stocks.transfers.update', $transfer) }}" method="POST" class="space-y-3">
        @csrf @method('PUT')

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-base font-semibold text-gray-800 mb-4">En-tête</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dépôt source <span class="text-red-500">*</span></label>
                    <select name="from_warehouse_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('from_warehouse_id', $transfer->from_warehouse_id)==$w->id?'selected':'' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Dépôt destination <span class="text-red-500">*</span></label>
                    <select name="to_warehouse_id" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ old('to_warehouse_id', $transfer->to_warehouse_id)==$w->id?'selected':'' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Date prévue <span class="text-red-500">*</span></label>
                    <input type="date" name="transfer_date" required value="{{ old('transfer_date', $transfer->transfer_date?->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Motif (optionnel)</label>
                    <input type="text" name="reason" maxlength="255" value="{{ old('reason', $transfer->reason) }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-gray-800">Articles à transférer</h2>
                <button type="button" @click="addItem()" class="text-sm text-blue-600 hover:text-blue-700 font-medium">+ Ajouter</button>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200">
                    <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase">Article</th>
                    <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                    <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase">Lot</th>
                    <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase">DLC</th>
                    <th class="pb-2 w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(item, i) in items" :key="item.id">
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-2">
                                <select :name="`items[${i}][product_id]`" x-model="item.product_id" required class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                                    <option value="">— Article —</option>
                                    @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->reference }} — {{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pr-2"><input type="number" :name="`items[${i}][quantity]`" required min="1" step="1" inputmode="numeric" x-model="item.quantity" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right"></td>
                            <td class="py-2 pr-2"><input type="text" :name="`items[${i}][lot_number]`" maxlength="100" x-model="item.lot" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></td>
                            <td class="py-2 pr-2"><input type="date" :name="`items[${i}][expiry_date]`" x-model="item.expiry" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></td>
                            <td class="py-2"><button type="button" @click="removeItem(i)" class="p-1 text-gray-400 hover:text-red-500" :disabled="items.length===1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('stocks.transfers.show', $transfer) }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Enregistrer</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function transferForm() {
    return {
        items: {{ \Illuminate\Support\Js::from($transfer->items->map(fn($i)=>['product_id'=>$i->product_id,'quantity'=>(float)$i->quantity,'lot'=>$i->lot_number,'expiry'=>$i->expiry_date?->format('Y-m-d')])) }},
        nextId: 0,
        init() { this.items = this.items.map(i => ({ id: this.nextId++, ...i })); while (this.items.length < 1) this.addItem(); },
        addItem() { this.items.push({ id: this.nextId++, product_id: '', quantity: 1, lot: '', expiry: '' }); },
        removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); },
    };
}
</script>
@endpush
@endsection
