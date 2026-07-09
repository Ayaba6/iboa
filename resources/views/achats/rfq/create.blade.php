@extends('layouts.erp')
@section('title', 'Nouvelle RFQ')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.rfq.index') }}" class="hover:text-gray-700">RFQ</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3" x-data="rfqForm()" x-init="init()">
    <h1 class="text-[16px] font-bold text-gray-900">Nouvelle demande de devis</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('achats.rfq.store') }}" class="space-y-3">
        @csrf

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Informations générales</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required maxlength="255" value="{{ old('title') }}"
                           placeholder="Ex. : Approvisionnement papier A4 - Q3 2026"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Date limite de réponse</label>
                    <input type="date" name="deadline" value="{{ old('deadline', now()->addDays(7)->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Notes / Cahier des charges</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <h2 class="text-base font-semibold text-gray-800 mb-3">Fournisseurs à consulter <span class="text-red-500">*</span></h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-60 overflow-y-auto border border-gray-200 rounded-[4px] p-3">
                @foreach($suppliers as $s)
                <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-2 py-1">
                    <input type="checkbox" name="supplier_ids[]" value="{{ $s->id }}" class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm">{{ $s->name }} <span class="text-xs text-gray-400">({{ $s->code }})</span></span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-800">Lignes à coter</h2>
                <button type="button" @click="addItem()" class="text-sm text-blue-600 font-medium">+ Ajouter une ligne</button>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200">
                    <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-4/12">Article (catalogue)</th>
                    <th class="pb-2 text-left text-xs font-semibold text-gray-500 uppercase w-5/12">Description</th>
                    <th class="pb-2 text-right text-xs font-semibold text-gray-500 uppercase w-2/12">Quantité</th>
                    <th class="pb-2 w-8"></th>
                </tr></thead>
                <tbody>
                    <template x-for="(item, i) in items" :key="item.id">
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-2">
                                <select :name="`items[${i}][product_id]`" x-model="item.product_id" @change="autoFillDescription(i)" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                                    <option value="">— Hors catalogue —</option>
                                    @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-name="{{ $p->reference }} — {{ $p->name }}">{{ $p->reference }} — {{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pr-2">
                                <input type="text" :name="`items[${i}][description]`" x-model="item.description" required maxlength="255" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-2">
                                <input type="number" :name="`items[${i}][quantity]`" required min="1" step="1" inputmode="numeric" x-model="item.quantity" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right">
                            </td>
                            <td class="py-2"><button type="button" @click="removeItem(i)" class="p-1 text-gray-400 hover:text-red-500" :disabled="items.length===1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('achats.rfq.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-1.5 rounded-[4px]">Annuler</a>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">Créer la RFQ</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function rfqForm() {
    return {
        items: [], nextId: 0,
        init() { this.addItem(); },
        addItem() { this.items.push({ id: this.nextId++, product_id: '', description: '', quantity: 1 }); },
        removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); },
        autoFillDescription(i) {
            const sel = document.querySelectorAll('select')[1 + i];
            const opt = sel?.selectedOptions[0];
            if (opt && opt.dataset.name && !this.items[i].description) {
                this.items[i].description = opt.dataset.name;
            }
        },
    };
}
</script>
@endpush
@endsection
