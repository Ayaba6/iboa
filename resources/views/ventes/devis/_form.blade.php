@php
    $quote            ??= null;
    $selectedClient   ??= null;
    $clientExemptions ??= [];
    $q = $quote;
@endphp

{{-- PHP → JS : données brutes utilisées par Alpine --}}
<script>
window._quoteFormData = {
    quote:             @json($quote ? $quote->load('items') : null),
    products:          @json($products ?? []),
    clients:           @json($clients ?? []),
    oldItems:          @json(old('items', [])),
    oldGlobalDiscount: @json(old('global_discount_amount', 0)),
    oldGlobalPct:      @json(old('global_discount_percent', 0)),
    selectedClientId:  @json(old('client_id', $quote?->client_id ?? $selectedClient)),
    clientExemptions:  @json($clientExemptions),
    defaultTaxRate:    @json((float) (optional(collect($taxRatesVente ?? [])->firstWhere('rate', '>', 0))->rate ?? 18)),
    orderItemsUrl:     @json(route('ventes.devis.order-items')),
};
</script>
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

<div x-data="quoteForm()" x-ref="root" x-cloak class="space-y-3">

<div class="bg-white border border-gray-300 rounded-[4px]">
{{-- Bandeau + actions --}}
<div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
    <h2 class="text-[15px] font-bold text-gray-900">
        {{ $q ? 'Devis : Modification' : 'Nouveau devis' }}
        @if($q)<span class="font-mono text-emerald-700 ml-1">{{ $q->number }}</span>@endif
    </h2>
    <div class="flex items-center gap-2">
        <button type="submit" @click="submitted = true" :disabled="submitting || totalTtc < 0"
                class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 disabled:opacity-60 px-4 py-1.5 rounded-[4px] transition-colors"
                x-text="submitting ? 'Enregistrement…' : 'Enregistrer'">Enregistrer</button>
        <a href="{{ route('ventes.devis.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
    </div>
</div>

<x-validation-errors class="m-4 mb-0" />

{{-- ═══════════ INFORMATIONS GÉNÉRALES [Maquette : 4 colonnes] ═══════════ --}}
<div class="p-4">
    <section class="border border-gray-200 rounded-[4px]">
        <div class="{{ $secH }}">Informations générales</div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
            {{-- Colonne 1 : client --}}
            <div class="sm:col-span-3 space-y-3">
                <div>
                    <label class="{{ $lbl }}">Client <span class="text-red-600">*</span></label>
                    <div class="relative">
                        <select name="client_id" x-model="client_id" @change="onClientChange()" required class="{{ $lk }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ old('client_id', $q?->client_id ?? $selectedClient) == $client->id ? 'selected' : '' }}>{{ $client->trade_name ?? $client->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}
                    </div>
                    @error('client_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                    <p x-show="selectedClient" class="text-[11.5px] text-gray-500 mt-1" x-text="[selectedClient?.address, selectedClient?.city].filter(Boolean).join(' – ')"></p>
                    <div x-show="isClientTaxExempt" x-cloak class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5">Client exonéré de TVA — TVA forcée à 0%</div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Contact</label>
                    <div class="relative"><select name="contact_id" class="{{ $lk }}"><option value="">—</option>@foreach($contacts ?? [] as $ct)<option value="{{ $ct->id }}" @selected(old('contact_id', $q?->contact_id)==$ct->id)>{{ trim(($ct->civility ? $ct->civility.' ' : '').$ct->first_name.' '.$ct->last_name) }}</option>@endforeach</select>{!! $caret !!}</div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Adresse de livraison</label>
                    <textarea name="delivery_address" rows="2" class="{{ $txa }}" placeholder="Chantier – Kossodo&#10;Ouagadougou&#10;Burkina Faso">{{ old('delivery_address', $q?->delivery_address) }}</textarea>
                </div>
                <div>
                    <label class="{{ $lbl }}">Devise <span class="text-red-600">*</span></label>
                    <div class="relative"><select name="currency_code" class="{{ $lk }}">
                        @foreach(['XOF'=>'XOF – Franc CFA','EUR'=>'Euro (EUR)','USD'=>'Dollar (USD)'] as $cc=>$cl)<option value="{{ $cc }}" {{ old('currency_code', $q?->currency_code ?? 'XOF') === $cc ? 'selected' : '' }}>{{ $cl }}</option>@endforeach
                    </select>{!! $caret !!}</div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Commercial</label>
                    <div class="relative"><select name="sales_rep_id" class="{{ $lk }}"><option value="">—</option>@foreach($salesReps ?? [] as $sr)<option value="{{ $sr->id }}" @selected(old('sales_rep_id', $q?->sales_rep_id)==$sr->id)>{{ $sr->name }}</option>@endforeach</select>{!! $caret !!}</div>
                </div>
            </div>

            {{-- Colonne 2 : document --}}
            <div class="sm:col-span-3 space-y-3">
                <div>
                    <label class="{{ $lbl }}">Type de document <span class="text-red-600">*</span></label>
                    <input type="text" value="Devis" class="{{ $inp }} bg-gray-50 text-gray-600" readonly>
                </div>
                <div>
                    <label class="{{ $lbl }}">N° devis <span class="text-red-600">*</span></label>
                    <input type="text" value="{{ $q?->number ?: 'Auto à la création' }}" class="{{ $inp }} font-mono bg-gray-50 text-gray-500" readonly>
                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ ($q?->status ?? 'brouillon') === 'brouillon' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' }}">● {{ ucfirst($q?->status ?? 'Brouillon') }}</span>
                </div>
                <div><label class="{{ $lbl }}">Date du devis <span class="text-red-600">*</span></label><input type="date" name="issued_at" required value="{{ old('issued_at', $q?->issued_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="{{ $lbl }} flex items-center gap-1.5">Date de validité <span class="text-red-600">*</span>
                            <span x-show="validityLabel" x-text="validityLabel?.text" :class="validityLabel?.cls" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"></span>
                        </label>
                        <input type="date" name="expires_at" x-model="expires_at" value="{{ old('expires_at', $q?->expires_at?->format('Y-m-d')) }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Durée de validité</label>
                        @php $vd = old('validity_duration', $q?->validity_duration ?? '30'); @endphp
                        <div class="relative"><select name="validity_duration" @change="applyValidityDuration($event.target.value)" class="{{ $lk }}">
                            <option value="15" @selected($vd==='15')>15 jours</option>
                            <option value="30" @selected($vd==='30')>30 jours</option>
                            <option value="60" @selected($vd==='60')>60 jours</option>
                            <option value="90" @selected($vd==='90')>90 jours</option>
                        </select>{!! $caret !!}</div>
                    </div>
                </div>
                <div><label class="{{ $lbl }}">Référence client</label><input type="text" name="reference" maxlength="50" value="{{ old('reference', $q?->reference) }}" class="{{ $inp }}" placeholder="Réf. du client (facultatif)"></div>
                {{-- [FIX BUG-001] TVA par défaut : appliquée aux lignes sans taux ; « Appliquer » la pousse sur toutes. --}}
                <div>
                    <label class="{{ $lbl }}">TVA par défaut</label>
                    <div class="flex items-center gap-1.5">
                        <div class="relative flex-1"><select x-model.number="defaultTaxRate" :disabled="isClientTaxExempt" class="{{ $lk }}">
                            <option value="0">0 %</option>
                            @foreach($taxRatesVente ?? [] as $tr)<option value="{{ (float) $tr->rate }}">{{ (float) $tr->rate }} %</option>@endforeach
                            @if(empty($taxRatesVente ?? []))<option value="18">18 %</option>@endif
                        </select>{!! $caret !!}</div>
                        <button type="button" @click="applyTaxToAll()" x-show="!isClientTaxExempt" class="text-[11px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-2 h-8 rounded-[3px] whitespace-nowrap">Appliquer</button>
                    </div>
                </div>
                <div><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $q?->project_reference) }}" class="{{ $inp }} font-mono" placeholder="PROJ-2026-0008 – Construction Hangar"></div>
            </div>

            {{-- Colonne 3 : prix / paiement --}}
            <div class="sm:col-span-3 space-y-3">
                <div>
                    <label class="{{ $lbl }}">Entrepôt / Dépôt <span class="text-red-600">*</span></label>
                    <div class="relative"><select name="warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses ?? [] as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id', $q?->warehouse_id)==$w->id)>{{ $w->code }} – {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                </div>
                <div><label class="{{ $lbl }}">Liste de prix <span class="text-red-600">*</span></label><input type="text" name="price_list" maxlength="60" value="{{ old('price_list', $q?->price_list ?? 'Tarif standard 2026') }}" class="{{ $inp }}"></div>
                <div>
                    <label class="{{ $lbl }}">Remise globale (%)</label>
                    <input type="number" min="0" max="100" step="0.5" x-model.number="global_discount_percent" class="{{ $inpR }}">
                    <input type="hidden" name="global_discount_percent" :value="global_discount_percent || 0">
                    <input type="hidden" name="global_discount_amount" :value="effectiveGlobalDiscount">
                </div>
                <div>
                    <label class="{{ $lbl }}">Conditions de paiement</label>
                    <div class="relative"><select name="payment_terms" class="{{ $lk }}">
                        <option value="">— Choisir —</option>
                        @foreach(['immediate' => 'Paiement immédiat', '30' => '30 jours', '60' => '60 jours', '90' => '90 jours', 'end_of_month' => 'Fin de mois'] as $val => $label)<option value="{{ $val }}" {{ (string) old('payment_terms', $q?->payment_terms ?? '30') === (string) $val ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                    </select>{!! $caret !!}</div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Mode de paiement</label>
                    @php $pmm = old('payment_method', $q?->payment_method ?? 'virement'); @endphp
                    <div class="relative"><select name="payment_method" class="{{ $lk }}">
                        <option value="virement" @selected($pmm==='virement')>Virement bancaire</option>
                        <option value="cheque" @selected($pmm==='cheque')>Chèque</option>
                        <option value="especes" @selected($pmm==='especes')>Espèces</option>
                        <option value="mobile_money" @selected($pmm==='mobile_money')>Mobile money</option>
                    </select>{!! $caret !!}</div>
                </div>
                <div><label class="{{ $lbl }}">Représentant fiscal</label><input type="text" name="fiscal_representative" maxlength="100" value="{{ old('fiscal_representative', $q?->fiscal_representative) }}" class="{{ $inp }}" placeholder="OA METAL INDUSTRIE"></div>
            </div>

            {{-- Colonne 4 : fiscal / divers --}}
            <div class="sm:col-span-3 space-y-3">
                <div>
                    <label class="{{ $lbl }}">Taux de change</label>
                    <div class="flex gap-1">
                        <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $q?->exchange_rate ?? 1) }}" class="{{ $inpR }}">
                        <input type="text" value="XOF" class="{{ $inp }} w-16 font-mono bg-gray-50 text-gray-500" readonly>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 items-end">
                    <div>
                        <label class="{{ $lbl }}">Prix / Devise</label>
                        @php $pm = old('price_mode', $q?->price_mode ?? 'ttc'); @endphp
                        <div class="relative"><select name="price_mode" class="{{ $lk }}">
                            <option value="ttc" @selected($pm==='ttc')>TTC</option>
                            <option value="ht" @selected($pm==='ht')>HT</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer pb-1.5">
                        <input type="hidden" name="net_prices" value="0">
                        <input type="checkbox" name="net_prices" value="1" class="{{ $chk }}" {{ old('net_prices', $q?->net_prices) ? 'checked' : '' }}>
                        <span class="text-[11.5px] font-semibold text-gray-700">Prix nets</span>
                    </label>
                </div>
                <div><label class="{{ $lbl }}">Régime fiscal</label><input type="text" name="fiscal_regime" maxlength="40" value="{{ old('fiscal_regime', $q?->fiscal_regime) }}" class="{{ $inp }}" placeholder="Régime réel normal"></div>
                <div>
                    <label class="{{ $lbl }}">Taxes</label>
                    @php $dtl = old('default_tax_label', $q?->default_tax_label ?? 'TVA 18%'); @endphp
                    <div class="relative"><select name="default_tax_label" class="{{ $lk }}">
                        <option value="TVA 18%" @selected($dtl==='TVA 18%')>TVA 18%</option>
                        <option value="Exonéré" @selected($dtl==='Exonéré')>Exonéré</option>
                    </select>{!! $caret !!}</div>
                </div>
                <div><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="2" class="{{ $txa }}" placeholder="Merci de votre confiance.">{{ old('notes', $q?->notes) }}</textarea></div>
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
                <button type="button" @click="addItem()" title="Ajoute une ligne — recherchez ensuite dans le catalogue via la colonne Article" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⊞ Ajouter depuis catalogue</button>
                <button type="button" @click="importFromOrder()" :disabled="importing" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇩ Ajouter depuis commande</button>
                <div class="relative"><select x-ref="orderSelect" class="{{ $lk }} !h-7 w-44 text-[12px]"><option value="">— Commande source —</option>@foreach($orders ?? [] as $ord)<option value="{{ $ord->id }}">{{ $ord->reference ?: $ord->number }}</option>@endforeach</select>{!! $caret !!}</div>
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
                            <th class="px-2 py-2 w-14"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(item, index) in items" :key="item._key">
                            <tr class="hover:bg-gray-50 group" @focusin="activeIndex = index" :class="activeIndex === index ? 'bg-emerald-50/40' : ''">
                                <td class="px-2 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                                @include('ventes.partials._product_combobox', ['accentColor' => 'blue', 'formName' => 'quote'])
                                <td class="px-2 py-2">
                                    <input type="text" :name="'items[' + index + '][description]'" x-model="item.description" placeholder="Désignation..."
                                           :class="item.description.trim() === '' && submitted ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                           class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 min-w-[160px]">
                                </td>
                                <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" min="1" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" min="0" step="1" class="{{ $tdIn }} text-right"></td>
                                <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][discount_percent]'" x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-700 font-medium text-xs whitespace-nowrap" x-text="formatNum(lineHt(item))"></td>
                                <td class="px-2 py-2"><input type="number" :name="'items[' + index + '][tax_rate_value]'" x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} text-right"></td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-600 text-xs whitespace-nowrap" x-text="formatNum(lineTax(item))"></td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-900 font-semibold text-xs whitespace-nowrap" x-text="formatNum(lineTtc(item))"></td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" @click="duplicateItem(index)" title="Dupliquer" class="text-gray-300 hover:text-emerald-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" title="Supprimer" class="text-gray-300 hover:text-red-500 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="px-3 py-1.5 border-t border-gray-100">
                    <textarea name="terms" rows="2" placeholder="Ajouter un commentaire sur les lignes…" class="{{ $txa }}">{{ old('terms', $q?->terms) }}</textarea>
                </div>
            </div>

            {{-- Totaux [Maquette : panneau droit] --}}
            <div class="xl:col-span-3 p-4 bg-[#f7faf8] space-y-2.5">
                <div class="flex justify-between text-[13px] text-gray-600"><span>Total HT</span><span class="tabular-nums font-medium" x-text="formatNum(subtotalHt) + ' XOF'"></span></div>
                <div class="flex justify-between text-[13px]" :class="effectiveGlobalDiscount > 0 ? 'text-orange-600' : 'text-gray-400'"><span>Remise globale</span><span class="tabular-nums font-medium" x-text="formatNum(effectiveGlobalDiscount) + ' XOF'"></span></div>
                <div class="flex justify-between text-[13px] text-gray-600 border-t border-gray-200 pt-2"><span>Base imposable</span><span class="tabular-nums font-medium" x-text="formatNum(subtotalHt) + ' XOF'"></span></div>
                <div class="flex justify-between text-[13px] text-gray-600"><span>Total TVA</span><span class="tabular-nums font-medium" x-text="formatNum(totalTax) + ' XOF'"></span></div>
                <p x-show="effectiveGlobalDiscount > subtotalHt + totalTax" class="text-xs text-red-500">⚠ La remise dépasse le total — montant ramené à 0.</p>
                <div class="border-t-2 border-emerald-200 pt-2.5">
                    <p class="text-[12px] font-bold text-gray-700">Total TTC</p>
                    <p class="text-[17px] font-bold text-emerald-700 tabular-nums" x-text="formatNum(totalTtc) + ' XOF'"></p>
                </div>
                <div x-show="selectedClient?.default_discount > 0" class="pt-1 text-[11.5px] text-amber-700">
                    Remise client par défaut : <strong x-text="(selectedClient?.default_discount || 0) + ' %'"></strong>
                    <button type="button" @click="applyClientDiscount()" class="ml-1 bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium rounded px-2 py-0.5 transition-colors">Appliquer</button>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- ═══════════ MARGE / CUMUL [Maquette SAGE X3 : feedback stock ligne active] ═══════════ --}}
<div class="p-4 pt-0">
    <section class="border border-gray-200 rounded-[4px]">
        <div class="{{ $secH }}">Marge / Cumul</div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-5 gap-x-5 gap-y-3">
            <div>
                <label class="{{ $lbl }}">Désignation</label>
                <p class="text-[13px] text-gray-700 truncate h-8 flex items-center" x-text="activeStock.designation || '—'"></p>
            </div>
            <div>
                <label class="{{ $lbl }}">Stock réel</label>
                <p class="text-[13px] font-mono tabular-nums font-semibold h-8 flex items-center px-2 rounded-[3px]"
                   :class="activeStock.product ? 'bg-amber-100 text-amber-900' : 'text-gray-400'"
                   x-text="activeStock.product ? formatNum(activeStock.reel) : '—'"></p>
            </div>
            <div>
                <label class="{{ $lbl }}">Référence</label>
                <p class="text-[13px] font-mono text-gray-700 truncate h-8 flex items-center" x-text="activeStock.reference || '—'"></p>
            </div>
            <div>
                <label class="{{ $lbl }}">Stock à terme</label>
                <p class="text-[13px] font-mono tabular-nums font-semibold h-8 flex items-center px-2 rounded-[3px]"
                   :class="activeStock.product ? (activeStock.terme < 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-900') : 'text-gray-400'"
                   x-text="activeStock.product ? formatNum(activeStock.terme) : '—'"></p>
            </div>
            <div>
                <label class="{{ $lbl }}">HT lignes</label>
                <p class="text-[13px] font-mono tabular-nums font-semibold text-gray-900 h-8 flex items-center" x-text="formatNum(subtotalHt) + ' XOF'"></p>
            </div>
        </div>
    </section>
</div>

{{-- ═══════════ BAS DE PAGE [Maquette : 3 cartes] ═══════════ --}}
<div class="p-4 pt-0 grid grid-cols-1 xl:grid-cols-12 gap-4">
    {{-- Informations complémentaires --}}
    <section class="border border-gray-200 rounded-[4px] xl:col-span-5">
        <div class="{{ $secH }}">Informations complémentaires</div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-6 gap-x-3 gap-y-3">
            <div class="sm:col-span-2">
                <label class="{{ $lbl }}">Source</label>
                @php $so = old('source', $q?->source ?? 'prospection'); @endphp
                <div class="relative"><select name="source" class="{{ $lk }}">
                    <option value="prospection" @selected($so==='prospection')>Prospection</option>
                    <option value="appel_offres" @selected($so==='appel_offres')>Appel d'offres</option>
                    <option value="client_existant" @selected($so==='client_existant')>Client existant</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lbl }}">Origine</label>
                @php $or = old('origin', $q?->origin ?? 'telephone'); @endphp
                <div class="relative"><select name="origin" class="{{ $lk }}">
                    <option value="telephone" @selected($or==='telephone')>Téléphone</option>
                    <option value="email" @selected($or==='email')>Email</option>
                    <option value="visite" @selected($or==='visite')>Visite</option>
                    <option value="site_web" @selected($or==='site_web')>Site web</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $lbl }}">Priorité</label>
                @php $pr = old('priority', $q?->priority ?? 'normale'); @endphp
                <div class="relative"><select name="priority" class="{{ $lk }}">
                    <option value="normale" @selected($pr==='normale')>Normale</option>
                    <option value="haute" @selected($pr==='haute')>Haute</option>
                    <option value="basse" @selected($pr==='basse')>Basse</option>
                </select>{!! $caret !!}</div>
            </div>
            <div class="sm:col-span-2"><label class="{{ $lbl }}">Bon de livraison souhaité le</label><input type="date" name="desired_delivery_date" value="{{ old('desired_delivery_date', optional($q?->desired_delivery_date)->format('Y-m-d')) }}" class="{{ $inp }}"></div>
            <div class="sm:col-span-2"><label class="{{ $lbl }}">Lieu de livraison</label><input type="text" name="delivery_location" maxlength="100" value="{{ old('delivery_location', $q?->delivery_location) }}" class="{{ $inp }}" placeholder="Chantier – Kossodo"></div>
            <div class="sm:col-span-2">
                <label class="{{ $lbl }}">Incoterm</label>
                @php $it = old('incoterm', $q?->incoterm ?? 'EXW'); @endphp
                <div class="relative"><select name="incoterm" class="{{ $lk }}">
                    <option value="EXW" @selected($it==='EXW')>EXW – Ex Works</option>
                    <option value="FCA" @selected($it==='FCA')>FCA – Free Carrier</option>
                    <option value="CPT" @selected($it==='CPT')>CPT – Carriage Paid To</option>
                    <option value="DAP" @selected($it==='DAP')>DAP – Delivered At Place</option>
                </select>{!! $caret !!}</div>
            </div>
        </div>
    </section>

    {{-- Pièces jointes --}}
    <section class="border border-gray-200 rounded-[4px] xl:col-span-4">
        <div class="{{ $secH }}">Pièces jointes</div>
        <div class="p-4 space-y-3">
            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                   class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                          file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                          file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
            <p class="text-[11px] text-gray-400">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
            @if($q && $q->attachments->isNotEmpty())
            <div class="space-y-1.5">
                @foreach($q->attachments as $att)
                <div class="flex items-center justify-between border border-gray-200 rounded-[3px] px-2.5 py-1.5 text-[12.5px]">
                    <span class="flex items-center gap-2 text-gray-700 truncate"><span class="text-red-500">📄</span><span class="font-mono truncate">{{ $att->filename }}</span></span>
                    <span class="text-gray-400 tabular-nums whitespace-nowrap ml-2">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>

    {{-- Suivi --}}
    <section class="border border-gray-200 rounded-[4px] xl:col-span-3">
        <div class="{{ $secH }}">Suivi</div>
        <div class="p-4 grid grid-cols-2 gap-x-3 gap-y-3">
            <div><label class="{{ $lbl }}">Statut</label><input type="text" value="{{ ucfirst($q?->status ?? 'Brouillon') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
            <div><label class="{{ $lbl }}">Créé par</label><input type="text" value="{{ $q?->createdBy?->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
            <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ $q?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
            <div><label class="{{ $lbl }}">Dernière modification</label><input type="text" value="{{ $q?->updated_at?->format('d/m/Y H:i') ?? '—' }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
        </div>
        <div class="px-4 pb-3">
            <label class="{{ $lbl }}">Mention pied de page (PDF)</label>
            <textarea name="footer_note" rows="2" class="{{ $txa }}" placeholder="Merci de votre confiance.">{{ old('footer_note', $q?->footer_note) }}</textarea>
        </div>
    </section>
</div>

{{-- Bandeau info conversion [Maquette] --}}
<div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
    <span class="text-emerald-700">ⓘ</span>
    Après validation du devis, il sera converti en <strong class="text-emerald-800">commande client</strong> si accepté par le client.
</div>

</div>{{-- /card --}}
</div>{{-- /x-data root --}}

@push('scripts')
<script>
function quoteForm() {
    const { quote, products, clients, oldItems, oldGlobalDiscount, oldGlobalPct, selectedClientId, clientExemptions, orderItemsUrl } = window._quoteFormData;
    const dtr = parseFloat(window._quoteFormData.defaultTaxRate) || 18;

    const clientsMap = {};
    clients.forEach(c => { clientsMap[String(c.id)] = c; });

    let nextKey = 1;

    function mapItem(i) {
        return {
            _key:             nextKey++,
            product_id:       i.product_id        ?? '',
            description:      i.description       ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            // [TVA-FIX] ?? 0 au lieu de || 18 : 0% ne doit pas devenir 18%
            tax_rate_value:   i.tax_rate_value != null ? parseFloat(i.tax_rate_value) : 0,
            _ps_open:   false,
            _ps_search: '',
            _ps_rect:   null,
        };
    }

    let initialItems;
    if (quote && quote.items && quote.items.length) {
        initialItems = quote.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: dtr })];
    }

    const initialPct = quote ? (parseFloat(quote.global_discount_percent) || 0) : (parseFloat(oldGlobalPct) || 0);

    return {
        items:                   initialItems,
        activeIndex:             0,
        client_id:               String(selectedClientId || ''),
        expires_at:              '{{ old('expires_at', $quote?->expires_at?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d')) }}',
        global_discount_percent: initialPct,
        products,
        clientsMap,
        defaultTaxRate:          dtr,
        applyTaxToAll() { if (this.isClientTaxExempt) return; this.items = this.items.map(item => ({ ...item, tax_rate_value: this.defaultTaxRate })); },
        submitting:  false,
        submitted:   false,
        importing:   false,
        _nextKey:    nextKey,

        get selectedClient() {
            return this.client_id ? this.clientsMap[String(this.client_id)] || null : null;
        },
        /** [Maquette SAGE X3 : Marge/Cumul] Stock réel + à terme de l'article de la ligne active. */
        get activeStock() {
            const item = this.items[this.activeIndex] || this.items[this.items.length - 1];
            const p = item && item.product_id
                ? this.products.find(pr => String(pr.id) === String(item.product_id))
                : null;
            if (!p) return { product: null, designation: item?.description || '', reference: '', reel: 0, terme: 0 };
            const reel = parseFloat(p.stock_qty) || 0;
            const reserved = parseFloat(p.reserved_qty) || 0;
            return {
                product: p,
                designation: item.description || p.name,
                reference: p.reference || '',
                reel,
                terme: reel - reserved,
            };
        },
        get validityLabel() {
            if (!this.expires_at) return null;
            const diff = Math.ceil((new Date(this.expires_at) - new Date()) / 86400000);
            if (diff < 0)  return { text: 'Expiré',              cls: 'text-red-700 bg-red-100' };
            if (diff === 0) return { text: 'Expire aujourd\'hui', cls: 'text-orange-700 bg-orange-100' };
            if (diff <= 7)  return { text: 'Dans ' + diff + 'j', cls: 'text-orange-600 bg-orange-100' };
            if (diff <= 30) return { text: 'Dans ' + diff + 'j', cls: 'text-amber-600 bg-amber-100' };
            return { text: 'Dans ' + diff + 'j', cls: 'text-green-700 bg-green-100' };
        },
        /** [Maquette] Durée de validité → recalcule la date d'expiration. */
        applyValidityDuration(days) {
            const issued = document.querySelector('[name=issued_at]')?.value;
            const base = issued ? new Date(issued) : new Date();
            base.setDate(base.getDate() + parseInt(days, 10));
            this.expires_at = base.toISOString().slice(0, 10);
        },
        get subtotalHt() {
            return this.items.reduce((sum, i) => sum + Math.round(i.quantity * i.unit_price * (1 - i.discount_percent / 100)), 0);
        },
        get totalTax() {
            return this.items.reduce((sum, i) => {
                const ht = i.quantity * i.unit_price * (1 - i.discount_percent / 100);
                const rate = this.isClientTaxExempt ? 0 : (parseFloat(i.tax_rate_value) || 0);
                return sum + Math.round(ht * rate / 100);
            }, 0);
        },
        get effectiveGlobalDiscount() {
            return Math.round(this.subtotalHt * (this.global_discount_percent || 0) / 100);
        },
        get totalTtc() {
            return Math.max(0, this.subtotalHt + this.totalTax - this.effectiveGlobalDiscount);
        },
        lineHt(item) {
            return Math.round(item.quantity * item.unit_price * (1 - item.discount_percent / 100));
        },
        lineTax(item) {
            const rate = this.isClientTaxExempt ? 0 : (parseFloat(item.tax_rate_value) || 0);
            return Math.round(this.lineHt(item) * rate / 100);
        },
        lineTtc(item) {
            return this.lineHt(item) + this.lineTax(item);
        },
        addItem() {
            this.items.push({ _key: this._nextKey++, product_id: '', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: this.isClientTaxExempt ? 0 : this.defaultTaxRate, _ps_open: false, _ps_search: '', _ps_rect: null });
        },
        removeItem(index) { if (this.items.length > 1) this.items.splice(index, 1); },
        duplicateItem(index) {
            const src = this.items[index];
            this.items.splice(index + 1, 0, { ...src, _key: this._nextKey++ });
        },
        /** [Maquette] Charge les lignes de la commande sélectionnée. */
        async importFromOrder() {
            const orderId = this.$refs.orderSelect?.value;
            if (!orderId) { alert('Sélectionnez d\'abord une commande source (liste à droite du bouton).'); return; }
            this.importing = true;
            try {
                const res = await fetch(orderItemsUrl + '?order_id=' + orderId, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { alert('Impossible de charger les lignes.'); return; }
                const data = await res.json();
                if (!data.length) { alert('Aucune ligne à importer.'); return; }
                if (this.items.length === 1 && !this.items[0].description && !this.items[0].product_id) this.items = [];
                data.forEach(l => this.items.push({
                    _key: this._nextKey++,
                    product_id: l.product_id ?? '', description: l.description ?? '',
                    quantity: parseFloat(l.quantity) || 1, unit_price: parseFloat(l.unit_price) || 0,
                    discount_percent: parseFloat(l.discount_percent) || 0,
                    tax_rate_value: this.isClientTaxExempt ? 0 : (parseFloat(l.tax_rate_value) || 0),
                    _ps_open: false, _ps_search: '', _ps_rect: null,
                }));
            } finally { this.importing = false; }
        },
        get isClientTaxExempt() {
            if (!this.client_id) return false;
            return !!(clientExemptions || {})[this.client_id];
        },
        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                if (!this.items[index].description.trim()) this.items[index].description = p.name;
                this.items[index].unit_price = parseFloat(p.sale_price) || 0;
                this.items[index].tax_rate_value = this.isClientTaxExempt ? 0 : (p.tax_rate?.rate != null ? parseFloat(p.tax_rate.rate) : (this.defaultTaxRate ?? dtr));
                this.fetchAdvisedPrice(index);
            }
        },
        // [Parametrage Vente] prix conseille : tarif client/famille/categorie + remises
        // parametrees resolus cote serveur (SalesPricingService).
        async fetchAdvisedPrice(index) {
            const item = this.items[index];
            if (!item.product_id) return;
            try {
                const params = new URLSearchParams({
                    product_id: item.product_id,
                    client_id: this.client_id || '',
                    qty: item.quantity || 1,
                });
                if (item.unit_id) params.set('unit_id', item.unit_id);
                const r = await fetch('{{ route('ventes.api.prix') }}?' + params, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                if (this.items[index]?.product_id !== item.product_id) return; // ligne changee entre-temps
                // [FIX double-remise] Le devis modelise prix BRUT + remise ligne separement.
                // On injecte donc le prix brut (base_price) dans unit_price et la remise
                // conseillee dans discount_percent — jamais le prix deja remise, sinon la
                // remise s'appliquerait deux fois au calcul du total ligne.
                this.items[index].unit_price = parseFloat(d.base_price) || (parseFloat(d.price) || 0);
                // fetchAdvisedPrice n'est declenche qu'au CHANGEMENT de produit : la remise
                // conseillee gouverne alors entierement la remise ligne (0 si aucune), pour
                // eviter qu'une remise heritee du produit precedent persiste a tort.
                if (d.discount_percent > 0) {
                    this.items[index].discount_percent = parseFloat(d.discount_percent);
                    this.items[index]._advised = (d.discount_name || 'Remise') + ' ' + d.discount_percent + ' % (' + d.source + ')';
                } else {
                    this.items[index].discount_percent = 0;
                    this.items[index]._advised = d.source !== 'prix de base' ? d.source : null;
                }
                this.items[index]._below_floor = !!d.below_floor;
            } catch (e) { /* reseau indisponible : prix de base conserve */ }
        },
        onClientChange() {
            if (this.isClientTaxExempt) {
                this.items = this.items.map(item => ({ ...item, tax_rate_value: 0 }));
            }
        },
        applyClientDiscount() {
            const disc = parseFloat(this.selectedClient?.default_discount || 0);
            if (disc > 0) this.items.forEach(i => { i.discount_percent = disc; });
        },
        formatNum(n) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.round((n || 0) * 100) / 100); },
        formatFcfa(n) { return this.formatNum(n) + ' FCFA'; },
    };
}
</script>
@endpush
