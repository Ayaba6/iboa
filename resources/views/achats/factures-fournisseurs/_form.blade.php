@php
    $invoice ??= null;
    $fi = $invoice;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = $inp . ' text-right font-mono tabular-nums';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $txa   = 'w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white resize-none focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $tdIn  = 'w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500';
@endphp
<script>
window._supplierInvoiceFormData = {
    invoice:    @json($invoice ? $invoice->load('items') : null),
    suppliers:  @json($suppliers ?? []),
    products:   @json($products ?? []),
    poItemsUrl: @json(route('achats.factures-fournisseurs.po-items')),
};
</script>
<div x-data="supplierInvoiceForm()" x-cloak class="space-y-3">

<div class="bg-white border border-gray-300 rounded-[4px]">
    {{-- Bandeau SAGE --}}
    <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
        <h2 class="text-[15px] font-bold text-gray-900">
            Facture fournisseur : {{ $fi ? 'Modification' : 'Création' }}
            @if($fi)<span class="font-mono text-emerald-700 ml-1">{{ $fi->number }}</span>@endif
        </h2>
        <div class="flex items-center gap-2">
            <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('achats.factures-fournisseurs.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
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
                            <select name="supplier_id" required class="{{ $lk }}">
                                <option value="">— Sélectionner —</option>
                                @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id', $fi?->supplier_id) == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('supplier_id')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Contact fournisseur</label>
                        <div class="relative"><select name="supplier_contact_id" class="{{ $lk }}"><option value="">—</option>@foreach($supplierContacts ?? [] as $ct)<option value="{{ $ct->id }}" @selected(old('supplier_contact_id', $fi?->supplier_contact_id)==$ct->id)>{{ trim(($ct->civility ? $ct->civility.' ' : '').$ct->first_name.' '.$ct->last_name) }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Devise <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="currency_code" class="{{ $lk }}">
                            @foreach(['XOF'=>'XOF – Franc CFA','EUR'=>'Euro (EUR)','USD'=>'Dollar (USD)'] as $cc=>$cl)<option value="{{ $cc }}" {{ old('currency_code', $fi?->currency_code ?? 'XOF') === $cc ? 'selected' : '' }}>{{ $cl }}</option>@endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Taux de change</label>
                        <div class="flex gap-1">
                            <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $fi?->exchange_rate ?? 1) }}" class="{{ $inpR }}">
                            <input type="text" value="XOF" class="{{ $inp }} w-16 font-mono bg-gray-50 text-gray-500" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Acheteur</label>
                        <div class="relative"><select name="buyer_id" class="{{ $lk }}"><option value="">—</option>@foreach($buyers ?? [] as $b)<option value="{{ $b->id }}" @selected(old('buyer_id', $fi?->buyer_id)==$b->id)>{{ $b->name }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                </div>

                {{-- Colonne 2 : document --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Type de document <span class="text-red-600">*</span></label>
                        <input type="text" value="Facture fournisseur" class="{{ $inp }} bg-gray-50 text-gray-600" readonly>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">N° interne <span class="text-red-600">*</span></label>
                        <input type="text" value="{{ $fi?->number ?: 'Auto à la création' }}" class="{{ $inp }} font-mono bg-gray-50 text-gray-500" readonly>
                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ ($fi?->status ?? 'brouillon') === 'brouillon' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' }}">● {{ ucfirst($fi?->status ?? 'Brouillon') }}</span>
                    </div>
                    <div><label class="{{ $lbl }}">N° facture fournisseur <span class="text-red-600">*</span></label><input type="text" name="supplier_invoice_number" value="{{ old('supplier_invoice_number', $fi?->supplier_invoice_number) }}" placeholder="Réf. reçue du fournisseur" class="{{ $inp }} font-mono">@error('supplier_invoice_number')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $lbl }}">Date de réception <span class="text-red-600">*</span></label><input type="date" name="received_at" required value="{{ old('received_at', $fi?->received_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="{{ $lbl }}">Date d'échéance</label><input type="date" name="due_at" value="{{ old('due_at', $fi?->due_at?->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                        <div>
                            <label class="{{ $lbl }}">Type d'échéance</label>
                            @php $dt = old('due_type', $fi?->due_type ?? ''); @endphp
                            <div class="relative"><select name="due_type" class="{{ $lk }}">
                                <option value="">—</option>
                                <option value="a_reception" @selected($dt==='a_reception')>À réception</option>
                                <option value="30_jours" @selected($dt==='30_jours')>30 jours</option>
                                <option value="30_jours_fin_de_mois" @selected($dt==='30_jours_fin_de_mois')>30 jours fin de mois</option>
                                <option value="60_jours" @selected($dt==='60_jours')>60 jours</option>
                            </select>{!! $caret !!}</div>
                        </div>
                    </div>
                </div>

                {{-- Colonne 3 : rattachement / paiement --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Bon de commande lié</label>
                        <div class="relative"><select name="purchase_order_id" x-ref="poSelect" class="{{ $lk }}"><option value="">—</option>@foreach($purchaseOrders ?? [] as $bc)<option value="{{ $bc->id }}" @selected(old('purchase_order_id', $fi?->purchase_order_id)==$bc->id)>{{ $bc->number }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Réception liée</label>
                        <div class="relative"><select name="reception_id" class="{{ $lk }}"><option value="">—</option>@foreach($receptions ?? [] as $rc)<option value="{{ $rc->id }}" @selected(old('reception_id', $fi?->reception_id)==$rc->id)>{{ $rc->number }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Conditions de paiement</label>
                        <div class="relative"><select name="payment_terms" class="{{ $lk }}">
                            <option value="">— Choisir —</option>
                            @foreach(['immediate' => 'Paiement immédiat', '30' => '30 jours', '60' => '60 jours', '90' => '90 jours', 'end_of_month' => 'Fin de mois'] as $val => $label)<option value="{{ $val }}" {{ (string) old('payment_terms', $fi?->payment_terms ?? '30') === (string) $val ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Mode de paiement</label>
                        @php $pmm = old('payment_method', $fi?->payment_method ?? 'virement'); @endphp
                        <div class="relative"><select name="payment_method" class="{{ $lk }}">
                            <option value="virement" @selected($pmm==='virement')>Virement bancaire</option>
                            <option value="cheque" @selected($pmm==='cheque')>Chèque</option>
                            <option value="especes" @selected($pmm==='especes')>Espèces</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Banque bénéficiaire</label><input type="text" name="beneficiary_bank" maxlength="100" value="{{ old('beneficiary_bank', $fi?->beneficiary_bank) }}" class="{{ $inp }}" placeholder="Banque du fournisseur"></div>
                </div>

                {{-- Colonne 4 : fiscal / divers --}}
                <div class="sm:col-span-3 space-y-3">
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <div>
                            <label class="{{ $lbl }}">Prix / Devise</label>
                            @php $pm = old('price_mode', $fi?->price_mode ?? 'ht'); @endphp
                            <div class="relative"><select name="price_mode" class="{{ $lk }}">
                                <option value="ht" @selected($pm==='ht')>HT</option>
                                <option value="ttc" @selected($pm==='ttc')>TTC</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer pb-1.5">
                            <input type="hidden" name="net_prices" value="0">
                            <input type="checkbox" name="net_prices" value="1" class="{{ $chk }}" {{ old('net_prices', $fi?->net_prices) ? 'checked' : '' }}>
                            <span class="text-[11.5px] font-semibold text-gray-700">Prix nets</span>
                        </label>
                    </div>
                    <div><label class="{{ $lbl }}">Régime fiscal</label><input type="text" name="fiscal_regime" maxlength="40" value="{{ old('fiscal_regime', $fi?->fiscal_regime) }}" class="{{ $inp }}" placeholder="Régime réel normal"></div>
                    <div>
                        <label class="{{ $lbl }}">Taxes</label>
                        @php $dtl = old('default_tax_label', $fi?->default_tax_label ?? 'TVA 18%'); @endphp
                        <div class="relative"><select name="default_tax_label" class="{{ $lk }}">
                            <option value="TVA 18%" @selected($dtl==='TVA 18%')>TVA 18%</option>
                            <option value="Exonéré" @selected($dtl==='Exonéré')>Exonéré</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $fi?->project_reference) }}" class="{{ $inp }} font-mono" placeholder="PROJ-2026-0008"></div>
                    <div>
                        <label class="{{ $lbl }}">Priorité</label>
                        @php $pr = old('priority', $fi?->priority ?? 'normale'); @endphp
                        <div class="relative"><select name="priority" class="{{ $lk }}">
                            <option value="normale" @selected($pr==='normale')>Normale</option>
                            <option value="haute" @selected($pr==='haute')>Haute</option>
                            <option value="basse" @selected($pr==='basse')>Basse</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="2" class="{{ $txa }}">{{ old('notes', $fi?->notes) }}</textarea></div>
                </div>
            </div>
        </section>
    </div>

    {{-- ═══════════ DÉTAIL DES ARTICLES [Maquette] ═══════════ --}}
    <div class="p-4 pt-0">
        <section class="border border-gray-200 rounded-[4px] overflow-hidden">
            <div class="{{ $secH }} flex items-center justify-between">
                <span>Détail des articles</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="addItem()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter une ligne</button>
                    <button type="button" @click="importFromPo()" :disabled="importing" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇩ Ajouter depuis BC</button>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-12">
                <div class="xl:col-span-9 overflow-x-auto border-r border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-[#eef5f0] border-b border-gray-300">
                            <tr>
                                <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-8">#</th>
                                <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-40">Article</th>
                                <th class="px-2 py-2 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Désignation</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-20">Quantité</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-24">Prix unitaire HT</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-16">Remise (%)</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-24">Prix net HT</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-16">TVA (%)</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-24">Montant TVA</th>
                                <th class="px-2 py-2 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-24">Total TTC</th>
                                <th class="px-2 py-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                                    <td class="px-2 py-2">
                                        <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="onProductChange(index)" class="{{ $tdIn }} min-w-[150px]">
                                            <option value="">— Produit —</option>
                                            <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2"><input type="text" :name="'items[' + index + '][description]'" x-model="item.description" placeholder="Désignation..." class="{{ $tdIn }} min-w-[160px]"></td>
                                    <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" min="1" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                    <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" min="0" step="1" class="{{ $tdIn }} text-right"></td>
                                    <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][discount_percent]'" x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                    <td class="px-2 py-2 text-right tabular-nums text-gray-700 font-medium text-xs whitespace-nowrap" x-text="formatNum(lineHt(item))"></td>
                                    <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][tax_rate_value]'" x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                    <td class="px-2 py-2 text-right tabular-nums text-gray-600 text-xs whitespace-nowrap" x-text="formatNum(lineTax(item))"></td>
                                    <td class="px-2 py-2 text-right tabular-nums text-gray-900 font-semibold text-xs whitespace-nowrap" x-text="formatNum(lineTtc(item))"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-gray-300 hover:text-red-500 transition-colors">
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
                    <div class="flex justify-between text-[13px] text-gray-600"><span>Total HT</span><span class="tabular-nums font-medium" x-text="formatNum(grossHt) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px]" :class="lineDiscountTotal > 0 ? 'text-red-600' : 'text-gray-400'"><span>Total remise</span><span class="tabular-nums font-medium" x-text="(lineDiscountTotal > 0 ? '- ' : '') + formatNum(lineDiscountTotal) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px] text-gray-600 border-t border-gray-200 pt-2"><span>Base imposable</span><span class="tabular-nums font-medium" x-text="formatNum(subtotalHt) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px] text-gray-600"><span>Total TVA</span><span class="tabular-nums font-medium" x-text="formatNum(totalTax) + ' XOF'"></span></div>
                    <div class="border-t-2 border-emerald-200 pt-2.5">
                        <p class="text-[12px] font-bold text-gray-700">Total TTC</p>
                        <p class="text-[17px] font-bold text-emerald-700 tabular-nums" x-text="formatNum(totalTtc) + ' XOF'"></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ═══════════ BAS DE PAGE [Maquette : 2 cartes] ═══════════ --}}
    <div class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-12 gap-4">
        <section class="border border-gray-200 rounded-[4px] xl:col-span-7">
            <div class="{{ $secH }}">Pièces jointes</div>
            <div class="p-4 space-y-3">
                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                       class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                              file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                              file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                <p class="text-[11px] text-gray-400">Facture PDF originale du fournisseur — max 5 Mo par fichier.</p>
                @if($fi && $fi->attachments->isNotEmpty())
                <div class="space-y-1.5">
                    @foreach($fi->attachments as $att)
                    <div class="flex items-center justify-between border border-gray-200 rounded-[3px] px-2.5 py-1.5 text-[12.5px]">
                        <span class="flex items-center gap-2 text-gray-700 truncate"><span class="text-red-500">📄</span><span class="font-mono truncate">{{ $att->filename }}</span></span>
                        <span class="text-gray-400 tabular-nums whitespace-nowrap ml-2">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>

        <section class="border border-gray-200 rounded-[4px] xl:col-span-5">
            <div class="{{ $secH }}">Suivi</div>
            <div class="p-4 grid grid-cols-2 gap-x-3 gap-y-3">
                <div><label class="{{ $lbl }}">Statut</label><input type="text" value="{{ ucfirst($fi?->status ?? 'Brouillon') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Créée par</label><input type="text" value="{{ $fi?->createdBy?->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ $fi?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
                <div><label class="{{ $lbl }}">Dernière modification</label><input type="text" value="{{ $fi?->updated_at?->format('d/m/Y H:i') ?? '—' }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
            </div>
        </section>
    </div>

    {{-- Bandeau info workflow --}}
    <div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
        <span class="text-emerald-700">ⓘ</span>
        Contrôle <strong class="text-emerald-800">3-way matching</strong> : BC + réception + facture avant paiement. La validation génère l'écriture <strong class="text-emerald-800">601 / 4452 / 401</strong>.
    </div>
</div>
</div>

@push('scripts')
<script>
function supplierInvoiceForm() {
    const { invoice, products, poItemsUrl } = window._supplierInvoiceFormData;
    return {
        items: invoice && invoice.items && invoice.items.length ? invoice.items.map(i => ({
            product_id:       i.product_id   ?? '',
            description:      i.description  ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   parseFloat(i.tax_rate_value)   || 18,
        })) : [{product_id: '', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18}],
        products,
        importing: false,

        get grossHt() {
            return this.items.reduce((sum, i) => sum + Math.round(i.quantity * i.unit_price), 0);
        },
        get subtotalHt() {
            return this.items.reduce((sum, i) => sum + Math.round(i.quantity * i.unit_price * (1 - i.discount_percent / 100)), 0);
        },
        get lineDiscountTotal() {
            return Math.max(0, this.grossHt - this.subtotalHt);
        },
        get totalTax() {
            return this.items.reduce((sum, i) => {
                const ht = i.quantity * i.unit_price * (1 - i.discount_percent / 100);
                return sum + Math.round(ht * i.tax_rate_value / 100);
            }, 0);
        },
        get totalTtc() { return this.subtotalHt + this.totalTax; },
        lineHt(item) { return Math.round(item.quantity * item.unit_price * (1 - item.discount_percent / 100)); },
        lineTax(item) { return Math.round(this.lineHt(item) * item.tax_rate_value / 100); },
        lineTtc(item) { return this.lineHt(item) + this.lineTax(item); },
        addItem() { this.items.push({product_id: '', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18}); },
        removeItem(index) { this.items.splice(index, 1); },
        /** [Maquette] Charge les lignes du BC lié sélectionné. */
        async importFromPo() {
            const poId = this.$refs.poSelect?.value;
            if (!poId) { alert('Sélectionnez d\'abord un bon de commande lié (Informations générales).'); return; }
            this.importing = true;
            try {
                const res = await fetch(poItemsUrl + '?purchase_order_id=' + poId, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { alert('Impossible de charger les lignes.'); return; }
                const data = await res.json();
                if (!data.length) { alert('Aucune ligne à importer.'); return; }
                if (this.items.length === 1 && !this.items[0].description && !this.items[0].product_id) this.items = [];
                data.forEach(l => this.items.push({
                    product_id: l.product_id ?? '', description: l.description ?? '',
                    quantity: parseFloat(l.quantity) || 1, unit_price: parseFloat(l.unit_price) || 0,
                    discount_percent: parseFloat(l.discount_percent) || 0,
                    tax_rate_value: parseFloat(l.tax_rate_value) || 18,
                }));
            } finally { this.importing = false; }
        },
        onProductChange(index) {
            const p = this.products.find(p => p.id == this.items[index].product_id);
            if (p) {
                this.items[index].description = p.name;
                this.items[index].unit_price  = parseFloat(p.purchase_price) || 0;
            }
        },
        formatNum(n) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.round((n || 0) * 100) / 100); },
        formatFcfa(n) { return this.formatNum(n) + ' FCFA'; }
    }
}
</script>
@endpush
