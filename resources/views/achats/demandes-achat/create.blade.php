@extends('layouts.erp')
@section('title', 'Nouvelle demande d\'achat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.demandes-achat.index') }}" class="hover:text-gray-700">Demandes d'achat</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
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
     x-data="purchaseRequestForm()">

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px] mb-3">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('achats.demandes-achat.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="bg-white border border-gray-300 rounded-[4px]">
            {{-- Bandeau SAGE --}}
            <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <h2 class="text-[15px] font-bold text-gray-900">Demande d'achat : Création</h2>
                <div class="flex items-center gap-2">
                    <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                    <a href="{{ route('achats.demandes-achat.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
                </div>
            </div>

            {{-- ═══════════ INFORMATIONS GÉNÉRALES [Maquette : page unique] ═══════════ --}}
            <div class="p-4">
                <section class="border border-gray-200 rounded-[4px]">
                    <div class="{{ $secH }}">Informations générales</div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                        {{-- [UI — doublon retiré] Le numéro auto en lecture seule répétait le titre
                             de la page. Son astérisque rouge était de plus dépourvu de sens sur un
                             champ non saisissable. Le statut est conservé. --}}
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Statut</label>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">● Brouillon</span>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Demandeur</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Département / Service <span class="text-red-600">*</span></label><input type="text" name="department" value="{{ old('department') }}" placeholder="Ex: Production, Comptabilité…" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Priorité</label>
                            @php $pr = old('priority', 'normale'); @endphp
                            <div class="relative"><select name="priority" class="{{ $lk }}">
                                <option value="normale" @selected($pr==='normale')>Normale</option>
                                <option value="haute" @selected($pr==='haute')>Haute</option>
                                <option value="basse" @selected($pr==='basse')>Basse</option>
                            </select>{!! $caret !!}</div>
                        </div>

                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Date souhaitée</label><input type="date" name="needed_at" value="{{ old('needed_at') }}" class="{{ $inp }}"></div>
                        <div class="sm:col-span-3">
                            <label class="{{ $lbl }}">Dépôt de destination</label>
                            <div class="relative"><select name="warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses ?? [] as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id')==$w->id)>{{ $w->code }} – {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                        </div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference') }}" class="{{ $inp }} font-mono" placeholder="Ex. : PROJ-2026-0008"></div>
                        <div class="sm:col-span-3"><label class="{{ $lbl }}">Justification / Objet <span class="text-red-600">*</span></label><input type="text" name="justification" value="{{ old('justification') }}" placeholder="Raison de la demande…" class="{{ $inp }}"></div>

                        <div class="sm:col-span-12"><label class="{{ $lbl }}">Notes</label><textarea name="notes" rows="2" class="{{ $txa }}" placeholder="Informations complémentaires…">{{ old('notes') }}</textarea></div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ ARTICLES DEMANDÉS [Maquette] ═══════════ --}}
            <div class="p-4 pt-0">
                <section class="border border-gray-200 rounded-[4px] overflow-hidden">
                    <div class="{{ $secH }} flex items-center justify-between">
                        <span>Articles demandés</span>
                        <button type="button" @click="addLine()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter un article</button>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-12">
                        <div class="xl:col-span-9 overflow-x-auto border-r border-gray-200 p-4 pb-0">
                            <table class="w-full text-[13px]">
                                <thead class="bg-[#eef5f0] border-b border-gray-300">
                                    <tr>
                                        <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-8">#</th>
                                        <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-5/12">Article / Description</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-1/12">Quantité</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-2/12">Prix estimé</th>
                                        <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-2/12">Total estimé</th>
                                        <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-2/12">Notes</th>
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
                                                    <option value="">— Sélectionner —</option>
                                                    @foreach($products as $product)
                                                    <option value="{{ $product->id }}"
                                                            data-price="{{ $product->purchase_price ?? 0 }}"
                                                            data-name="{{ $product->name }}">
                                                        {{ $product->name }} @if($product->reference)({{ $product->reference }})@endif
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <input type="text" :name="`items[${index}][description]`" x-model="line.description" placeholder="Description..." class="{{ $tdIn }}">
                                            </td>
                                            <td class="px-2 py-2"><input type="number" :name="`items[${index}][quantity]`" x-model="line.quantity" @input="calcLine(index)" min="1" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                            <td class="px-2 py-2"><input type="number" :name="`items[${index}][estimated_price]`" x-model="line.estimated_price" @input="calcLine(index)" min="0" step="1" class="{{ $tdIn }} text-right"></td>
                                            <td class="px-2 py-2 text-right font-medium tabular-nums text-[12px] whitespace-nowrap" x-text="formatAmount(line.total)"></td>
                                            <td class="px-2 py-2"><input type="text" :name="`items[${index}][notes]`" x-model="line.notes" placeholder="Note..." class="{{ $tdIn }}"></td>
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

                        {{-- Total [panneau droit] --}}
                        <div class="xl:col-span-3 p-4 bg-[#f7faf8] space-y-2.5">
                            <div class="flex justify-between text-[13px] text-gray-600"><span x-text="lines.length + ' article(s)'"></span></div>
                            <div class="border-t-2 border-emerald-200 pt-2.5">
                                <p class="text-[12px] font-bold text-gray-700">Total estimé</p>
                                <p class="text-[20px] font-bold text-emerald-700 tabular-nums" x-text="formatAmount(totalEstimated)"></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ═══════════ BAS DE PAGE [Maquette : 2 cartes] ═══════════ --}}
            <div class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-12 gap-4">
                <section class="border border-gray-200 rounded-[4px] xl:col-span-7">
                    <div class="{{ $secH }}">Pièces jointes</div>
                    <div class="p-4 space-y-2">
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                                      file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                      file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400">Devis fournisseur, spécifications — max 5 Mo par fichier.</p>
                    </div>
                </section>
                <section class="border border-gray-200 rounded-[4px] xl:col-span-5">
                    <div class="{{ $secH }}">Suivi</div>
                    <div class="p-4 grid grid-cols-2 gap-x-3 gap-y-3">
                        <div><label class="{{ $lbl }}">Statut</label><input type="text" value="Brouillon" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                        <div><label class="{{ $lbl }}">Créée par</label><input type="text" value="{{ auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                        <div class="col-span-2"><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ now()->format('d/m/Y H:i') }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
                    </div>
                </section>
            </div>

            {{-- Bandeau info workflow --}}
            <div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
                <span class="text-emerald-700">ⓘ</span>
                Après soumission, la demande suit le <strong class="text-emerald-800">circuit d'approbation</strong> selon les seuils, puis peut être convertie en <strong class="text-emerald-800">appel d'offres</strong> ou <strong class="text-emerald-800">bon de commande</strong>.
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function purchaseRequestForm() {
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
            this.$nextTick(() => {
                this.lines.forEach((line, i) => {
                    if (!line.product_id) return;
                    const sel = document.querySelectorAll('[data-product-select]')[i];
                    if (sel) sel.value = line.product_id;
                });
            });
            @else
            this.addLine();
            @endif
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
