@extends('layouts.erp')
@section('title', 'Nouveau retour fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.retours-fournisseurs.index') }}" class="hover:text-gray-700">Retours fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono tabular-nums';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $txa   = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $tdIn  = 'w-full border border-gray-300 rounded px-2 py-1.5 text-[13px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500';
@endphp
<div class="max-w-7xl"
     x-data="supplierReturnForm()">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('achats.retours-fournisseurs.store') }}" method="POST">
        @csrf

        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">Retour fournisseur : Création</h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('achats.retours-fournisseurs.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES [Maquette : 4 colonnes] ═══════════ --}}
            <div class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        {{-- Colonne 1 : fournisseur --}}
                        <div class="sm:col-span-3 space-y-3">
                            <div>
                                <label class="{{ $lbl }}">Fournisseur <span class="text-red-600">*</span></label>
                                <div class="relative">
                                    <select name="supplier_id" required class="{{ $lk }} @error('supplier_id') border-red-500 @enderror">
                                        <option value="">— Sélectionner —</option>
                                        @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach
                                    </select>{!! $caret !!}
                                </div>
                                @error('supplier_id')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Type de retour <span class="text-red-600">*</span></label>
                                @php $rt = old('return_type', 'defectueux'); @endphp
                                <div class="relative"><select name="return_type" class="{{ $lk }}">
                                    <option value="defectueux" @selected($rt==='defectueux')>Produit défectueux</option>
                                    <option value="non_conforme" @selected($rt==='non_conforme')>Non conforme</option>
                                    <option value="erreur_commande" @selected($rt==='erreur_commande')>Erreur de commande</option>
                                    <option value="surplus" @selected($rt==='surplus')>Surplus / trop livré</option>
                                </select>{!! $caret !!}</div>
                            </div>
                            <div><label class="{{ $lbl }}">Motif du retour <span class="text-red-600">*</span></label><input type="text" name="reason" value="{{ old('reason') }}" placeholder="Produit défectueux, erreur de commande…" class="{{ $inp }}"></div>
                        </div>

                        {{-- Colonne 2 : document --}}
                        <div class="sm:col-span-3 space-y-3">
                            {{-- [UI — doublon retiré] « Type de document » en lecture seule répétait le
                                 titre de la page, sur un écran où il ne peut rien valoir d'autre. --}}
                            {{-- [UI — doublon retiré] Le numéro auto répétait le titre de la page.
                                 Le statut, seule information non redondante, est conservé. --}}
                            <div>
                                <label class="{{ $lbl }}">Statut</label>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">● Brouillon</span>
                            </div>
                            <div><label class="{{ $lbl }}">Date de retour <span class="text-red-600">*</span></label><input type="date" name="returned_at" value="{{ old('returned_at', date('Y-m-d')) }}" required class="{{ $inp }} @error('returned_at') border-red-500 @enderror">@error('returned_at')<p class="text-red-500 text-[11px] mt-1">{{ $message }}</p>@enderror</div>
                            <div><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference') }}" class="{{ $inp }} font-mono" placeholder="Ex. : PROJ-2026-0008"></div>
                        </div>

                        {{-- Colonne 3 : rattachements --}}
                        <div class="sm:col-span-3 space-y-3">
                            <div>
                                <label class="{{ $lbl }}">Bon de commande lié</label>
                                <div class="relative"><select name="purchase_order_id" class="{{ $lk }}"><option value="">—</option>@foreach($purchaseOrders ?? [] as $bc)<option value="{{ $bc->id }}" @selected(old('purchase_order_id')==$bc->id)>{{ $bc->number }}</option>@endforeach</select>{!! $caret !!}</div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Réception liée</label>
                                <div class="relative"><select name="reception_id" x-ref="receptionSelect" class="{{ $lk }}"><option value="">—</option>@foreach($receptions ?? [] as $rc)<option value="{{ $rc->id }}" @selected(old('reception_id')==$rc->id)>{{ $rc->number }}</option>@endforeach</select>{!! $caret !!}</div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Facture fournisseur liée</label>
                                <div class="relative"><select name="supplier_invoice_id" class="{{ $lk }}"><option value="">—</option>@foreach($supplierInvoices ?? [] as $fi)<option value="{{ $fi->id }}" @selected(old('supplier_invoice_id')==$fi->id)>{{ $fi->number }}</option>@endforeach</select>{!! $caret !!}</div>
                            </div>
                            <div>
                                <label class="{{ $lbl }}">Dépôt de sortie</label>
                                <div class="relative"><select name="warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses ?? [] as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id')==$w->id)>{{ $w->code }} – {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                            </div>
                        </div>

                        {{-- Colonne 4 : divers --}}
                        <div class="sm:col-span-3 space-y-3">
                            <div>
                                <label class="{{ $lbl }}">Priorité</label>
                                @php $pr = old('priority', 'normale'); @endphp
                                <div class="relative"><select name="priority" class="{{ $lk }}">
                                    <option value="normale" @selected($pr==='normale')>Normale</option>
                                    <option value="haute" @selected($pr==='haute')>Haute</option>
                                    <option value="basse" @selected($pr==='basse')>Basse</option>
                                </select>{!! $caret !!}</div>
                            </div>
                            <div><label class="{{ $lbl }}">Notes</label><textarea name="notes" rows="4" class="{{ $txa }}" placeholder="Informations complémentaires…">{{ old('notes') }}</textarea></div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ ARTICLES RETOURNÉS [Maquette] ═══════════ --}}
            <div class="p-4 pt-0">
                <section class="border border-gray-200 rounded-[4px] overflow-hidden">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Articles retournés</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="addLine()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une ligne</button>
                            <button type="button" @click="importFromReception()" :disabled="importing" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇩ Ajouter depuis réception</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-12">
                        <div class="xl:col-span-9 overflow-x-auto border-r border-gray-200 p-4 pb-2">
                            <table class="w-full text-[13px]">
                                <thead class="bg-[#eef5f0] border-b border-gray-300">
                                    <tr>
                                        <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-8">#</th>
                                        <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-5/12">Article / Description</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-1/12">Quantité</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-2/12">Prix unitaire HT</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-1/12">Remise (%)</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-2/12">Total HT</th>
                                        <th class="px-2 py-2 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(line, index) in lines" :key="line.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-2 py-2 text-gray-400 text-[12px]" x-text="index + 1"></td>
                                            <td class="px-2 py-2">
                                                <select :name="`items[${index}][product_id]`"
                                                        data-product-select
                                                        @change="onProductChange($event, index)"
                                                        class="{{ $tdIn }} mb-1">
                                                    <option value="">— Sélectionner un produit —</option>
                                                    @foreach($products as $product)
                                                    <option value="{{ $product->id }}" data-price="{{ $product->purchase_price ?? 0 }}" data-name="{{ $product->name }}">
                                                        {{ $product->name }} @if($product->reference)({{ $product->reference }})@endif
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <input type="text" :name="`items[${index}][description]`" x-model="line.description" placeholder="Description..." class="{{ $tdIn }}">
                                            </td>
                                            <td class="px-2 py-2"><input type="number" :name="`items[${index}][quantity]`" x-model="line.quantity" @input="calcLine(index)" min="1" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                            <td class="px-2 py-2"><input type="number" :name="`items[${index}][unit_price]`" x-model="line.unit_price" @input="calcLine(index)" min="0" step="1" class="{{ $tdIn }} text-right"></td>
                                            <td class="px-2 py-2">
                                                <input type="number" :name="`items[${index}][discount_percent]`" x-model="line.discount" @input="calcLine(index)" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right">
                                                <input type="hidden" :name="`items[${index}][tax_rate_value]`" value="0">
                                            </td>
                                            <td class="px-2 py-2 text-right font-medium tabular-nums text-[12px] whitespace-nowrap" x-text="formatAmount(line.total_ht)"></td>
                                            <td class="px-2 py-2 text-center">
                                                <button type="button" @click="removeLine(index)" class="text-gray-300 hover:text-red-500 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Totaux [panneau droit] --}}
                        <div class="xl:col-span-3 p-4 bg-[#f7faf8] space-y-2.5">
                            <div class="flex justify-between text-[13px] text-gray-600"><span x-text="lines.length + ' article(s)'"></span></div>
                            <div class="flex justify-between text-[13px] text-gray-600"><span>Sous-total HT</span><span class="tabular-nums font-medium" x-text="formatAmount(subtotalHt)"></span></div>
                            <div class="border-t-2 border-emerald-200 pt-2.5">
                                <p class="text-[12px] font-bold text-gray-700">Total avoir attendu</p>
                                <p class="text-[20px] font-bold text-emerald-700 tabular-nums" x-text="formatAmount(totalTtc)"></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Bandeau info workflow --}}
            <div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
                <span class="text-emerald-700">ⓘ</span>
                À la validation : <strong class="text-emerald-800">sortie de stock</strong> + génération d'un <strong class="text-emerald-800">avoir fournisseur</strong> (écriture 401 / 311).
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function supplierReturnForm() {
    return {
        lines: [],
        nextId: 0,
        importing: false,

        init() {
            this.addLine();
        },

        addLine() {
            this.lines.push({ id: this.nextId++, description: '', quantity: 1, unit_price: 0, discount: 0, total_ht: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },
        onProductChange(event, index) {
            const option = event.target.options[event.target.selectedIndex];
            const price  = parseFloat(option.dataset.price || 0);
            this.lines[index].unit_price = Math.round(price);
            if (!this.lines[index].description) this.lines[index].description = option.dataset.name || '';
            this.calcLine(index);
        },
        calcLine(index) {
            const line    = this.lines[index];
            const qty     = parseFloat(line.quantity) || 0;
            const price   = parseFloat(line.unit_price) || 0;
            const disc    = parseFloat(line.discount) || 0;
            line.total_ht = Math.round(qty * price * (1 - disc / 100));
        },
        /** [Maquette] Charge les lignes de la réception liée sélectionnée. */
        async importFromReception() {
            const recId = this.$refs.receptionSelect?.value;
            if (!recId) { alert('Sélectionnez d\'abord une réception liée (Informations générales).'); return; }
            this.importing = true;
            try {
                const res = await fetch('{{ route('achats.retours-fournisseurs.reception-items') }}?reception_id=' + recId, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { alert('Impossible de charger les lignes.'); return; }
                const data = await res.json();
                if (!data.length) { alert('Aucune ligne à importer.'); return; }
                if (this.lines.length === 1 && !this.lines[0].description) this.lines = [];
                data.forEach(l => {
                    const qty = parseFloat(l.quantity) || 1, price = Math.round(parseFloat(l.unit_price) || 0);
                    this.lines.push({ id: this.nextId++, product_id: l.product_id ?? '', description: l.description ?? '', quantity: qty, unit_price: price, discount: 0, total_ht: Math.round(qty * price) });
                });
                this.$nextTick(() => {
                    this.lines.forEach((line, i) => {
                        if (!line.product_id) return;
                        const sel = document.querySelectorAll('[data-product-select]')[i];
                        if (sel) sel.value = line.product_id;
                    });
                });
            } finally { this.importing = false; }
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
