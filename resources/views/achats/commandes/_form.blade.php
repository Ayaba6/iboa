@php $purchaseOrder ??= null; $warehouses ??= collect(); $currencies ??= ['XOF']; $po = $purchaseOrder; @endphp
{{-- [FIX Turbo] Données du formulaire dans un îlot JSON (non exécuté) plutôt qu'une
     variable window globale : Turbo ne réévalue pas les <script> au restore de snapshot,
     donc une globale garderait les données d'une AUTRE commande (TVA « fantôme » 19/17).
     L'îlot fait partie du DOM → Alpine le relit à FRAIS à chaque init / turbo:load. --}}
<script type="application/json" id="po-form-data">{!! json_encode([
    'order'      => $purchaseOrder ? $purchaseOrder->load('items') : null,
    'suppliers'  => $suppliers ?? [],
    'products'   => $products ?? [],
    'prItemsUrl' => route('achats.commandes.pr-items'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@php
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

<div x-data="purchaseOrderForm()" x-cloak class="space-y-3">

<div class="bg-white border border-gray-300 rounded-[4px]">
    {{-- Bandeau + actions --}}
    <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
        <h2 class="text-[15px] font-bold text-gray-900">
            Commande fournisseur : {{ $po ? 'Modification' : 'Création' }}
            @if($po)<span class="font-mono text-emerald-700 ml-1">{{ $po->number }}</span>@endif
        </h2>
        <div class="flex items-center gap-2">
            <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('achats.commandes.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
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
                                @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id', $po?->supplier_id) == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('supplier_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Contact fournisseur</label>
                        <div class="relative"><select name="supplier_contact_id" class="{{ $lk }}"><option value="">—</option>@foreach($supplierContacts ?? [] as $ct)<option value="{{ $ct->id }}" @selected(old('supplier_contact_id', $po?->supplier_contact_id)==$ct->id)>{{ trim(($ct->civility ? $ct->civility.' ' : '').$ct->first_name.' '.$ct->last_name) }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Adresse de livraison</label>
                        <textarea name="delivery_address" rows="2" class="{{ $txa }}" placeholder="Usine – Ouagadougou">{{ old('delivery_address', $po?->delivery_address) }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Devise <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="currency_code" class="{{ $lk }}">@php $cur = old('currency_code', $po->currency_code ?? 'XOF'); @endphp @foreach($currencies as $cu)<option value="{{ $cu }}" @selected($cur===$cu)>{{ $cu }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Acheteur</label>
                        <div class="relative"><select name="buyer_id" class="{{ $lk }}"><option value="">—</option>@foreach($buyers ?? [] as $b)<option value="{{ $b->id }}" @selected(old('buyer_id', $po?->buyer_id)==$b->id)>{{ $b->name }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                </div>

                {{-- Colonne 2 : document --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Type de document <span class="text-red-600">*</span></label>
                        <input type="text" value="Bon de commande" class="{{ $inp }} bg-gray-50 text-gray-600" readonly>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">N° BC <span class="text-red-600">*</span></label>
                        <input type="text" value="{{ $po?->number ?: 'Auto à la création' }}" class="{{ $inp }} font-mono bg-gray-50 text-gray-500" readonly>
                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ ($po?->status ?? 'brouillon') === 'brouillon' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' }}">● {{ ucfirst($po?->status ?? 'Brouillon') }}</span>
                    </div>
                    <div><label class="{{ $lbl }}">Date de commande <span class="text-red-600">*</span></label><input type="date" name="issued_at" required value="{{ old('issued_at', $po?->ordered_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Livraison prévue</label><input type="date" name="expected_at" value="{{ old('expected_at', $po?->expected_at?->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Référence interne</label><input type="text" name="reference" maxlength="50" value="{{ old('reference', $po->reference ?? '') }}" class="{{ $inp }} font-mono" placeholder="REF-2026-001"></div>
                    <div><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $po?->project_reference) }}" class="{{ $inp }} font-mono" placeholder="PROJ-2026-0008"></div>
                </div>

                {{-- Colonne 3 : réception / paiement --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Dépôt de réception <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="depot_reception_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_reception_id', $po->depot_reception_id ?? '') == $w->id)>{{ $w->code }} — {{ $w->name }}{{ ($w->can_purchase ?? true) ? '' : ' (non achat)' }}</option>@endforeach</select>{!! $caret !!}</div>
                        @error('depot_reception_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="{{ $lbl }}">Liste de prix</label><input type="text" name="price_list" maxlength="60" value="{{ old('price_list', $po?->price_list ?? 'Tarif fournisseur 2026') }}" class="{{ $inp }}"></div>
                    <div>
                        <label class="{{ $lbl }}">Conditions de paiement</label>
                        <div class="relative"><select name="payment_terms" class="{{ $lk }}">
                            <option value="">— Choisir —</option>
                            {{-- [FIX affichage] PHP caste les clés « 30 »/« 60 »/« 90 » en int : la
                                 comparaison stricte === contre la valeur string enregistrée échouait, donc
                                 aucune option sélectionnée (le champ semblait vide/non sauvé). Cast string. --}}
                            @foreach(['immediate' => 'Paiement immédiat', '30' => '30 jours', '60' => '60 jours', '90' => '90 jours', 'end_of_month' => 'Fin de mois'] as $val => $label)<option value="{{ $val }}" {{ (string) old('payment_terms', $po?->payment_terms ?? '30') === (string) $val ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Mode de paiement</label>
                        @php $pmm = old('payment_method', $po?->payment_method ?? 'virement'); @endphp
                        <div class="relative"><select name="payment_method" class="{{ $lk }}">
                            <option value="virement" @selected($pmm==='virement')>Virement bancaire</option>
                            <option value="cheque" @selected($pmm==='cheque')>Chèque</option>
                            <option value="especes" @selected($pmm==='especes')>Espèces</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Priorité</label>
                        @php $pr = old('priority', $po?->priority ?? 'normale'); @endphp
                        <div class="relative"><select name="priority" class="{{ $lk }}">
                            <option value="normale" @selected($pr==='normale')>Normale</option>
                            <option value="haute" @selected($pr==='haute')>Haute</option>
                            <option value="basse" @selected($pr==='basse')>Basse</option>
                        </select>{!! $caret !!}</div>
                    </div>
                </div>

                {{-- Colonne 4 : prix / divers --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Taux de change</label>
                        <div class="flex gap-1">
                            <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $po?->exchange_rate ?? 1) }}" class="{{ $inpR }}">
                            <input type="text" value="XOF" class="{{ $inp }} w-16 font-mono bg-gray-50 text-gray-500" readonly>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <div>
                            <label class="{{ $lbl }}">Prix / Devise</label>
                            @php $pm = old('price_mode', $po?->price_mode ?? 'ht'); @endphp
                            <div class="relative"><select name="price_mode" class="{{ $lk }}">
                                <option value="ht" @selected($pm==='ht')>HT</option>
                                <option value="ttc" @selected($pm==='ttc')>TTC</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer pb-1.5">
                            <input type="hidden" name="net_prices" value="0">
                            <input type="checkbox" name="net_prices" value="1" class="{{ $chk }}" {{ old('net_prices', $po?->net_prices) ? 'checked' : '' }}>
                            <span class="text-[11.5px] font-semibold text-gray-700">Prix nets</span>
                        </label>
                    </div>
                    <div><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="4" class="{{ $txa }}" placeholder="Instructions au fournisseur…">{{ old('notes', $po?->notes) }}</textarea></div>
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
                    <button type="button" @click="importFromPr()" :disabled="importing" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇩ Ajouter depuis demande d'achat</button>
                    <div class="relative"><select x-ref="prSelect" class="{{ $lk }} !h-7 w-44 text-[12px]"><option value="">— DA approuvée —</option>@foreach($purchaseRequests ?? [] as $prq)<option value="{{ $prq->id }}">{{ $prq->number }}</option>@endforeach</select>{!! $caret !!}</div>
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
                                    {{-- [FIX affichage TVA] min-width : sans lui la colonne se comprimait à ~22px,
                                         le padding + spinner masquaient la valeur (« 18 » présent mais clippé,
                                         visible seulement au focus). --}}
                                    <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][tax_rate_value]'" x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right" style="min-width:52px;padding-left:4px;padding-right:4px"></td>
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
                    <div class="px-3 py-1.5 border-t border-gray-100">
                        <textarea name="terms" rows="2" placeholder="Ajouter un commentaire sur les lignes…" class="{{ $txa }}">{{ old('terms', $po->terms ?? '') }}</textarea>
                    </div>
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

    {{-- ═══════════ BAS DE PAGE [Maquette : 3 cartes] ═══════════ --}}
    <div class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-12 gap-4">
        <section class="border border-gray-200 rounded-[4px] xl:col-span-5">
            <div class="{{ $secH }}">Informations complémentaires</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-6 gap-x-3 gap-y-3">
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Transporteur</label><input type="text" name="carrier" maxlength="80" value="{{ old('carrier', $po?->carrier) }}" class="{{ $inp }}" placeholder="TRANSPORT PLUS"></div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">N° de véhicule</label><input type="text" name="vehicle_number" maxlength="30" value="{{ old('vehicle_number', $po?->vehicle_number) }}" class="{{ $inp }} font-mono uppercase" placeholder="11 BF 2567"></div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Lieu de livraison</label><input type="text" name="delivery_location" maxlength="100" value="{{ old('delivery_location', $po?->delivery_location) }}" class="{{ $inp }}" placeholder="Usine – Ouagadougou"></div>
                <div class="sm:col-span-2">
                    <label class="{{ $lbl }}">Incoterm</label>
                    @php $it = old('incoterm', $po?->incoterm ?? 'EXW'); @endphp
                    <div class="relative"><select name="incoterm" class="{{ $lk }}">
                        <option value="EXW" @selected($it==='EXW')>EXW – Ex Works</option>
                        <option value="FCA" @selected($it==='FCA')>FCA – Free Carrier</option>
                        <option value="CIF" @selected($it==='CIF')>CIF – Cost Insurance Freight</option>
                        <option value="DAP" @selected($it==='DAP')>DAP – Delivered At Place</option>
                    </select>{!! $caret !!}</div>
                </div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Poids total (kg)</label><input type="number" step="0.01" min="0" name="total_weight_kg" value="{{ old('total_weight_kg', $po?->total_weight_kg) }}" class="{{ $inpR }}"></div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Pied de page (PDF)</label><input type="text" name="footer_note" maxlength="255" value="{{ old('footer_note', $po?->footer_note) }}" class="{{ $inp }}"></div>
            </div>
        </section>

        <section class="border border-gray-200 rounded-[4px] xl:col-span-4">
            <div class="{{ $secH }}">Pièces jointes</div>
            <div class="p-4 space-y-3">
                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                       class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                              file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                              file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                <p class="text-[11px] text-gray-400">Proforma fournisseur, spécifications — max 5 Mo par fichier.</p>
                @if($po && $po->attachments->isNotEmpty())
                <div class="space-y-1.5">
                    @foreach($po->attachments as $att)
                    <div class="flex items-center justify-between border border-gray-200 rounded-[3px] px-2.5 py-1.5 text-[12.5px]">
                        <span class="flex items-center gap-2 text-gray-700 truncate"><span class="text-red-500">📄</span><span class="font-mono truncate">{{ $att->filename }}</span></span>
                        <span class="text-gray-400 tabular-nums whitespace-nowrap ml-2">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>

        <section class="border border-gray-200 rounded-[4px] xl:col-span-3">
            <div class="{{ $secH }}">Suivi</div>
            <div class="p-4 grid grid-cols-2 gap-x-3 gap-y-3">
                <div><label class="{{ $lbl }}">Statut</label><input type="text" value="{{ ucfirst($po?->status ?? 'Brouillon') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Approbation</label><input type="text" value="{{ ucfirst(str_replace('_', ' ', $po?->approval_status ?? '—')) }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Créée par</label><input type="text" value="{{ $po?->createdBy?->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ $po?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
            </div>
        </section>
    </div>

    {{-- Bandeau info workflow --}}
    <div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
        <span class="text-emerald-700">ⓘ</span>
        Selon le montant, le BC suit le <strong class="text-emerald-800">circuit d'approbation</strong> (seuils), puis <strong class="text-emerald-800">réception</strong> (entrée stock) et <strong class="text-emerald-800">facture fournisseur</strong> (3-way matching).
    </div>
</div>
</div>

@push('scripts')
<script>
function purchaseOrderForm() {
    // Données lues à FRAIS depuis l'îlot JSON du DOM (pas une globale window) → jamais
    // périmées après un restore Turbo, donc plus de TVA « fantôme » d'une autre commande.
    const el = document.getElementById('po-form-data');
    const { order: po, products, prItemsUrl } = el ? JSON.parse(el.textContent) : { order: null, products: [], prItemsUrl: '' };
    return {
        items: po && po.items && po.items.length ? po.items.map(i => ({
            product_id:       i.product_id   ?? '',
            description:      i.description  ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            // [TVA-FIX] != null au lieu de || 18 : un taux 0 % (exonéré) enregistré ne
            // doit pas réafficher 18 %.
            tax_rate_value:   i.tax_rate_value != null ? parseFloat(i.tax_rate_value) : 18,
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
        /** [Maquette] Charge les lignes de la DA approuvée sélectionnée. */
        async importFromPr() {
            const prId = this.$refs.prSelect?.value;
            if (!prId) { alert('Sélectionnez d\'abord une demande d\'achat approuvée (liste à droite du bouton).'); return; }
            this.importing = true;
            try {
                const res = await fetch(prItemsUrl + '?purchase_request_id=' + prId, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { alert('Impossible de charger les lignes.'); return; }
                const data = await res.json();
                if (!data.length) { alert('Aucune ligne à importer.'); return; }
                if (this.items.length === 1 && !this.items[0].description && !this.items[0].product_id) this.items = [];
                data.forEach(l => this.items.push({
                    product_id: l.product_id ?? '', description: l.description ?? '',
                    quantity: parseFloat(l.quantity) || 1, unit_price: parseFloat(l.unit_price) || 0,
                    discount_percent: 0, tax_rate_value: 18,
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
