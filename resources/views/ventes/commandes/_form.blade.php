@php
    $order ??= null; $selectedClient ??= null; $clientExemptions ??= []; $warehouses ??= collect(); $currencies ??= ['XOF']; $o = $order;
    // [FIX BUG-001] Taux TVA par défaut (société) appliqué aux lignes dont l'article n'a pas de taux.
    // Taux marqué « par défaut » en priorité (sinon 2% BIC sortait avant 18% au tri).
    $defaultTaxRate = (float) (optional(collect($taxRatesVente ?? [])->firstWhere('is_default', true))->rate
        ?? optional(collect($taxRatesVente ?? [])->firstWhere('rate', '>', 0))->rate ?? 18);
@endphp
<script>
window._orderFormData = {
    order:             @json($order ? $order->load('items') : null),
    products:          @json($products ?? []),
    oldItems:          @json(old('items', [])),
    oldGlobalDiscount: @json(old('global_discount_amount', 0)),
    selectedClient:    @json($selectedClient),
    clientExemptions:  @json($clientExemptions),
    defaultTaxRate:    @json($defaultTaxRate),
    quoteItemsUrl:     @json(route('ventes.commandes.quote-items')),
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
    $tdIn  = 'no-spin w-full h-8 border border-gray-300 rounded-[3px] px-2 py-0 text-[13px] tabular-nums bg-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500';
@endphp

<div x-data="orderFormVentes()" x-cloak class="space-y-3">

<div class="bg-white border border-gray-300 rounded-[4px]">
    {{-- Bandeau + actions --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
        <h2 class="text-[22px] font-bold text-gray-900 leading-tight">
            Commande client : {{ $o ? 'Modification' : 'Création' }}
            @if($o)<span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $o->number }}</span>@endif
        </h2>
        <div class="flex items-center gap-1.5">
            {{-- NB : PAS de :disabled ici. Un <button type="submit"> qui devient
                 disabled pendant son propre clic annule la soumission native du
                 navigateur (aucune requête envoyée). L'anti-double-soumission est
                 assuré côté serveur par _idempotency_key (x-form-guard). --}}
            <button type="submit" @click="submitting = true"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors"
                    x-text="submitting ? 'Enregistrement…' : 'Enregistrer'">Enregistrer</button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            <a href="{{ route('ventes.commandes.index') }}"
               class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

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
                            <select name="client_id" required x-model="clientId" @change="onClientChange()" class="{{ $lk }}">
                                <option value="">— Sélectionner —</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ old('client_id', $o?->client_id ?? $selectedClient) == $client->id ? 'selected' : '' }}>{{ $client->name }}{{ $client->trade_name ? ' — '.$client->trade_name : '' }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                        @error('client_id')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
                        <div x-show="isClientTaxExempt" x-cloak class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5">Client exonéré de TVA — TVA forcée à 0%</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Contact</label>
                        <div class="relative"><select name="contact_id" class="{{ $lk }}"><option value="">—</option>@foreach($contacts ?? [] as $ct)<option value="{{ $ct->id }}" @selected(old('contact_id', $o?->contact_id)==$ct->id)>{{ trim(($ct->civility ? $ct->civility.' ' : '').$ct->first_name.' '.$ct->last_name) }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Adresse de livraison</label>
                        <textarea name="delivery_address" rows="2" class="{{ $txa }}" placeholder="Chantier – Kossodo&#10;Ouagadougou">{{ old('delivery_address', $o?->delivery_address) }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Adresse de facturation</label>
                        <textarea name="billing_address" rows="2" class="{{ $txa }}" placeholder="01 BP 2359 Ouagadougou 01">{{ old('billing_address', $o?->billing_address) }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Commercial</label>
                        <div class="relative"><select name="sales_rep_id" class="{{ $lk }}"><option value="">—</option>@foreach($salesReps ?? [] as $sr)<option value="{{ $sr->id }}" @selected(old('sales_rep_id', $o?->sales_rep_id)==$sr->id)>{{ $sr->name }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                </div>

                {{-- Colonne 2 : document --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Type de document <span class="text-red-600">*</span></label>
                        <input type="text" value="Commande client" class="{{ $inp }} bg-gray-50 text-gray-600" readonly>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">N° commande <span class="text-red-600">*</span></label>
                        <input type="text" value="{{ $o?->number ?: 'Auto à la création' }}" class="{{ $inp }} font-mono bg-gray-50 text-gray-500" readonly>
                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full {{ ($o?->status ?? 'brouillon') === 'brouillon' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' }}">● {{ ucfirst($o?->status ?? 'Brouillon') }}</span>
                    </div>
                    <div><label class="{{ $lbl }}">Date commande <span class="text-red-600">*</span></label><input type="date" name="issued_at" required value="{{ old('issued_at', isset($o) ? $o->issued_at?->format('Y-m-d') : now()->format('Y-m-d')) }}" class="{{ $inp }}"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="{{ $lbl }}">Date validité</label><input type="date" name="expires_at" value="{{ old('expires_at', isset($o) ? $o->expires_at?->format('Y-m-d') : '') }}" class="{{ $inp }}"></div>
                        <div><label class="{{ $lbl }}">Livraison prévue</label><input type="date" name="delivery_date" value="{{ old('delivery_date', isset($o) ? $o->delivery_date?->format('Y-m-d') : '') }}" class="{{ $inp }}"></div>
                    </div>
                    <div><label class="{{ $lbl }}">Référence client</label><input type="text" name="reference" maxlength="50" value="{{ old('reference', $o->reference ?? '') }}" class="{{ $inp }}" placeholder="Réf. du client (facultatif)"></div>
                    <div><label class="{{ $lbl }}">Projet</label><input type="text" name="project_reference" maxlength="60" value="{{ old('project_reference', $o?->project_reference) }}" class="{{ $inp }} font-mono" placeholder="PROJ-2026-0008 – Construction Hangar"></div>
                </div>

                {{-- Colonne 3 : prix / paiement --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Entrepôt / Dépôt <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="delivery_warehouse_id" class="{{ $lk }}"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('delivery_warehouse_id', $o?->delivery_warehouse_id)==$w->id)>{{ $w->code }} – {{ $w->name }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Liste de prix <span class="text-red-600">*</span></label><input type="text" name="price_list" maxlength="60" value="{{ old('price_list', $o?->price_list ?? 'Tarif standard 2026') }}" class="{{ $inp }}"></div>
                    {{-- [FIX BUG-001] TVA par défaut : appliquée aux lignes sans taux article ; « Appliquer » la pousse sur toutes les lignes. --}}
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
                        <p class="text-[10px] text-gray-400 mt-0.5" x-show="!isClientTaxExempt">Nouvelles lignes + articles sans taux.</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Conditions de paiement</label>
                        <div class="relative"><select name="payment_terms" class="{{ $lk }}">
                            <option value="">— Choisir —</option>
                            @foreach(['immediate' => 'Paiement immédiat', '30' => '30 jours', '60' => '60 jours', '90' => '90 jours', 'end_of_month' => 'Fin de mois'] as $val => $label)<option value="{{ $val }}" {{ (string) old('payment_terms', $o?->payment_terms ?? '30') === (string) $val ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Mode de paiement</label>
                        @php $pmm = old('payment_method', $o?->payment_method ?? 'virement'); @endphp
                        <div class="relative"><select name="payment_method" class="{{ $lk }}">
                            <option value="virement" @selected($pmm==='virement')>Virement bancaire</option>
                            <option value="cheque" @selected($pmm==='cheque')>Chèque</option>
                            <option value="especes" @selected($pmm==='especes')>Espèces</option>
                            <option value="mobile_money" @selected($pmm==='mobile_money')>Mobile money</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Représentant fiscal</label><input type="text" name="fiscal_representative" maxlength="100" value="{{ old('fiscal_representative', $o?->fiscal_representative) }}" class="{{ $inp }}" placeholder="OA METAL INDUSTRIE"></div>
                    <div>
                        <label class="{{ $lbl }}">Priorité</label>
                        @php $pr = old('priority', $o?->priority ?? 'normale'); @endphp
                        <div class="relative"><select name="priority" class="{{ $lk }}">
                            <option value="normale" @selected($pr==='normale')>Normale</option>
                            <option value="haute" @selected($pr==='haute')>Haute</option>
                            <option value="basse" @selected($pr==='basse')>Basse</option>
                        </select>{!! $caret !!}</div>
                    </div>
                </div>

                {{-- Colonne 4 : fiscal / divers --}}
                <div class="sm:col-span-3 space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Devise <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="currency_code" class="{{ $lk }}">@php $cur = old('currency_code', $o->currency_code ?? 'XOF'); @endphp @foreach($currencies as $cu)<option value="{{ $cu }}" @selected($cur===$cu)>{{ $cu }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Taux de change</label>
                        <div class="flex gap-1">
                            <input type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', $o?->exchange_rate ?? 1) }}" class="{{ $inpR }}">
                            <input type="text" value="XOF" class="{{ $inp }} w-16 font-mono bg-gray-50 text-gray-500" readonly>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <div>
                            <label class="{{ $lbl }}">Prix / Devise</label>
                            @php $pm = old('price_mode', $o?->price_mode ?? 'ttc'); @endphp
                            <div class="relative"><select name="price_mode" class="{{ $lk }}">
                                <option value="ttc" @selected($pm==='ttc')>TTC</option>
                                <option value="ht" @selected($pm==='ht')>HT</option>
                            </select>{!! $caret !!}</div>
                        </div>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer pb-1.5">
                            <input type="hidden" name="net_prices" value="0">
                            <input type="checkbox" name="net_prices" value="1" class="{{ $chk }}" {{ old('net_prices', $o?->net_prices) ? 'checked' : '' }}>
                            <span class="text-[11.5px] font-semibold text-gray-700">Prix nets</span>
                        </label>
                    </div>
                    <div><label class="{{ $lbl }}">Régime fiscal</label><input type="text" name="fiscal_regime" maxlength="40" value="{{ old('fiscal_regime', $o?->fiscal_regime) }}" class="{{ $inp }}" placeholder="Régime réel normal"></div>
                    <div>
                        <label class="{{ $lbl }}">Taxes</label>
                        @php $dtl = old('default_tax_label', $o?->default_tax_label ?? 'TVA 18%'); @endphp
                        <div class="relative"><select name="default_tax_label" class="{{ $lk }}">
                            <option value="TVA 18%" @selected($dtl==='TVA 18%')>TVA 18%</option>
                            <option value="Exonéré" @selected($dtl==='Exonéré')>Exonéré</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Observations</label><textarea name="notes" rows="2" class="{{ $txa }}" placeholder="Merci de votre confiance.">{{ old('notes', $o->notes ?? '') }}</textarea></div>
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
                    <button type="button" @click="importFromQuote()" :disabled="importing" class="text-[12px] font-semibold text-gray-600 border border-gray-300 bg-white hover:bg-gray-50 px-3 py-1 rounded-[3px]">⇩ Ajouter depuis devis</button>
                    <div class="relative"><select x-ref="quoteSelect" class="{{ $lk }} !h-7 w-44 text-[12px]"><option value="">— Devis source —</option>@foreach($quotes ?? [] as $qt)<option value="{{ $qt->id }}">{{ $qt->number }}</option>@endforeach</select>{!! $caret !!}</div>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-12">
                <div class="xl:col-span-9 overflow-x-auto border-r border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-[#3b4248]">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-8">N°</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-32">Article</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap">Désignation</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-16" title="Nombre de tôles — vide pour un article standard">Nb tôles</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-16" title="Longueur unitaire (m)">Long. m</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-16" title="Métrage total = nb × longueur (auto)">Qté / ml</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-24">P.U. HT</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-14">Rem. %</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-24">Net HT</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-24">TVA</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-24">Mt TVA</th>
                                <th class="px-2 py-1.5 text-right text-[11px] font-semibold text-white uppercase tracking-wide whitespace-nowrap w-24">Total TTC</th>
                                <th class="px-2 py-1.5 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(item, index) in items" :key="item._key">
                                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                                    <td class="px-2 py-1 text-center text-gray-400 tabular-nums text-[12px]" x-text="index + 1"></td>
                                    @include('ventes.partials._product_combobox', ['accentColor' => 'blue', 'formName' => 'order'])
                                    <td class="px-2 py-1"><input type="text" :name="'items[' + index + '][description]'" x-model="item.description" placeholder="Désignation…" class="{{ $tdIn }} min-w-[88px]"></td>
                                    {{-- [§5 TÔLE BAC] nb tôles × longueur → métrage (Qté figée si tôle) --}}
                                    <td class="px-2 py-1"><input type="number" :name="'items[' + index + '][nb_toles]'" x-model.number="item.nb_toles" @input="syncSheet(item)" min="0" step="1" inputmode="numeric" placeholder="—" class="{{ $tdIn }} min-w-[40px] text-right"></td>
                                    <td class="px-2 py-1"><input type="number" :name="'items[' + index + '][metrage_par_tole]'" x-model.number="item.metrage_par_tole" @input="syncSheet(item)" min="0" step="0.01" placeholder="—" class="{{ $tdIn }} min-w-[40px] text-right"></td>
                                    <td class="px-2 py-1"><input type="number" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" :readonly="isSheet(item)" :class="isSheet(item) ? 'bg-gray-100 text-gray-500' : ''" min="0.01" step="0.01" inputmode="decimal" class="{{ $tdIn }} min-w-[40px] text-right"></td>
                                    <td class="px-2 py-1"><input type="number" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" min="0" step="1" class="{{ $tdIn }} min-w-[64px] text-right"></td>
                                    <td class="px-2 py-1"><input type="number" :name="'items[' + index + '][discount_percent]'" x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric" class="{{ $tdIn }} min-w-[44px] text-right"></td>
                                    <td class="px-2 py-1 text-right tabular-nums text-gray-700 font-medium text-[12.5px] whitespace-nowrap" x-text="formatNum(lineHt(item))"></td>
                                    <td class="px-2 py-1">
                                        <template x-if="isClientTaxExempt">
                                            <div class="w-full h-8 flex items-center justify-end border border-amber-200 bg-amber-50 rounded-[3px] px-2 text-[13px] text-amber-700 font-medium cursor-not-allowed select-none">0 %<input type="hidden" :name="'items[' + index + '][tax_rate_value]'" value="0"></div>
                                        </template>
                                        <template x-if="!isClientTaxExempt">
                                            <div class="relative">
                                                <select :name="'items[' + index + '][tax_rate_value]'" x-model.number="item.tax_rate_value" class="{{ $tdIn }} appearance-none bg-none min-w-[56px] pl-1.5 pr-5 text-right">
                                                    <option value="0">0 %</option>
                                                    @foreach($taxRatesVente ?? [] as $tr)<option value="{{ (float) $tr->rate }}">{{ rtrim(rtrim(number_format($tr->rate, 2, ',', ''), '0'), ',') }} %</option>@endforeach
                                                    @if(empty($taxRatesVente ?? []))<option value="18">18 %</option>@endif
                                                </select>
                                                <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-2 py-1 text-right tabular-nums text-gray-600 text-[12.5px] whitespace-nowrap" x-text="formatNum(lineTax(item))"></td>
                                    <td class="px-2 py-1 text-right tabular-nums text-gray-900 font-semibold text-[12.5px] whitespace-nowrap" x-text="formatNum(lineTtc(item))"></td>
                                    <td class="px-2 py-1 text-center">
                                        <button type="button" @click="removeItem(index)" x-show="items.length > 1" title="Supprimer la ligne" class="text-gray-300 hover:text-red-500 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="px-3 py-1.5 border-t border-gray-100">
                        <textarea name="terms" rows="2" placeholder="Ajouter un commentaire sur les lignes…" class="{{ $txa }}">{{ old('terms', $o->terms ?? '') }}</textarea>
                    </div>
                </div>

                {{-- Totaux [Maquette : panneau droit] --}}
                <div class="xl:col-span-3 p-4 bg-[#f7faf8] space-y-2.5">
                    <div class="flex justify-between text-[13px] text-gray-600"><span>Total HT</span><span class="tabular-nums font-medium" x-text="formatNum(grossHt) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px]" :class="lineDiscountTotal > 0 ? 'text-red-600' : 'text-gray-400'"><span>Total remise</span><span class="tabular-nums font-medium" x-text="(lineDiscountTotal > 0 ? '- ' : '') + formatNum(lineDiscountTotal) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px] text-gray-600 border-t border-gray-200 pt-2"><span>Base imposable</span><span class="tabular-nums font-medium" x-text="formatNum(subtotalHt) + ' XOF'"></span></div>
                    <div class="flex justify-between text-[13px] text-gray-600"><span>Total TVA</span><span class="tabular-nums font-medium" x-text="formatNum(totalTax) + ' XOF'"></span></div>
                    <div class="flex items-center justify-between text-[12px] text-gray-500 gap-2">
                        <label class="whitespace-nowrap">Remise globale</label>
                        <input type="number" name="global_discount_amount" x-model.number="global_discount_amount" min="0" step="1" :max="subtotalHt + totalTax" class="w-28 border border-gray-300 rounded px-2 py-1 text-[12px] text-right focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
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
        {{-- Informations complémentaires --}}
        <section class="border border-gray-200 rounded-[4px] xl:col-span-5">
            <div class="{{ $secH }}">Informations complémentaires</div>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-6 gap-x-3 gap-y-3">
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Transporteur</label><input type="text" name="carrier" maxlength="80" value="{{ old('carrier', $o?->carrier) }}" class="{{ $inp }}" placeholder="TRANSPORT PLUS"></div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">N° de véhicule</label><input type="text" name="vehicle_number" maxlength="30" value="{{ old('vehicle_number', $o?->vehicle_number) }}" class="{{ $inp }} font-mono uppercase" placeholder="11 BF 2567"></div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Lieu de livraison</label><input type="text" name="delivery_location" maxlength="100" value="{{ old('delivery_location', $o?->delivery_location) }}" class="{{ $inp }}" placeholder="Chantier – Kossodo"></div>
                <div class="sm:col-span-2">
                    <label class="{{ $lbl }}">Incoterm</label>
                    @php $it = old('incoterm', $o?->incoterm ?? 'EXW'); @endphp
                    <div class="relative"><select name="incoterm" class="{{ $lk }}">
                        <option value="EXW" @selected($it==='EXW')>EXW – Ex Works</option>
                        <option value="FCA" @selected($it==='FCA')>FCA – Free Carrier</option>
                        <option value="CPT" @selected($it==='CPT')>CPT – Carriage Paid To</option>
                        <option value="DAP" @selected($it==='DAP')>DAP – Delivered At Place</option>
                    </select>{!! $caret !!}</div>
                </div>
                <div class="sm:col-span-2"><label class="{{ $lbl }}">Poids total (kg)</label><input type="number" step="0.01" min="0" name="total_weight_kg" value="{{ old('total_weight_kg', $o?->total_weight_kg) }}" class="{{ $inpR }}" placeholder="4 820,000"></div>
                <div class="sm:col-span-2">
                    <label class="{{ $lbl }}">Pied de page (PDF)</label>
                    <input type="text" name="footer_note" maxlength="255" value="{{ old('footer_note', $o?->footer_note) }}" class="{{ $inp }}">
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
                @if($o && $o->attachments->isNotEmpty())
                <div class="space-y-1.5">
                    @foreach($o->attachments as $att)
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
                <div><label class="{{ $lbl }}">Statut</label><input type="text" value="{{ ucfirst($o?->status ?? 'Brouillon') }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Créée par</label><input type="text" value="{{ $o?->createdBy?->name ?? auth()->user()->name }}" class="{{ $inp }} bg-gray-50 text-gray-600" readonly></div>
                <div><label class="{{ $lbl }}">Date de création</label><input type="text" value="{{ $o?->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
                <div><label class="{{ $lbl }}">Dernière modification</label><input type="text" value="{{ $o?->updated_at?->format('d/m/Y H:i') ?? '—' }}" class="{{ $inp }} bg-gray-50 text-gray-600 font-mono" readonly></div>
            </div>
        </section>
    </div>

    {{-- Bandeau info conversion --}}
    <div class="mx-4 mb-4 flex items-center gap-2 px-3 py-2 rounded-[4px] bg-[#eef5f0] border border-emerald-100 text-[12px] text-gray-600">
        <span class="text-emerald-700">ⓘ</span>
        Après validation, la commande alimente la <strong class="text-emerald-800">production</strong> (OF) puis la <strong class="text-emerald-800">livraison</strong> et la <strong class="text-emerald-800">facturation</strong>.
    </div>
</div>
</div>


{{-- ── Barre de contexte pied de page [X3] ─────────────────────────────────── --}}
<div class="mt-3 bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
    <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
    <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
    <span class="border-l border-white/10 pl-6">Document : <span class="text-white font-semibold">{{ $o?->number ?? 'Commande (brouillon)' }}</span></span>
    <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
    <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
</div>

@push('scripts')
<script>
function orderFormVentes() {
    const { order, products, oldItems, oldGlobalDiscount, selectedClient, clientExemptions, quoteItemsUrl } = window._orderFormData;
    const dtr = parseFloat(window._orderFormData.defaultTaxRate) || 18;

    let _nextKey = 1;

    function mapItem(i) {
        return {
            _key:             _nextKey++,
            product_id:       i.product_id       ?? '',
            description:      i.description      ?? '',
            quantity:         parseFloat(i.quantity) || 1,
            // [§5 TÔLE BAC] nombre de tôles × longueur unitaire → métrage
            nb_toles:         parseFloat(i.nb_toles) || 0,
            metrage_par_tole: parseFloat(i.metrage_par_tole) || 0,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   i.tax_rate_value != null ? parseFloat(i.tax_rate_value) : 0,
            _ps_open:   false,
            _ps_search: '',
            _ps_rect:   null,
        };
    }

    let initialItems;
    if (order && order.items && order.items.length) {
        initialItems = order.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: dtr })];
    }

    return {
        items:                  initialItems,
        global_discount_amount: parseFloat(order ? order.global_discount_amount : oldGlobalDiscount) || 0,
        products,
        clientId:               String(order?.client_id ?? selectedClient ?? ''),
        clientExemptions:       clientExemptions || {},
        defaultTaxRate:         dtr,
        submitting:             false,
        importing:              false,
        _nextKey,

        /** [FIX BUG-001] Applique le taux TVA par défaut à toutes les lignes. */
        applyTaxToAll() {
            if (this.isClientTaxExempt) return;
            this.items = this.items.map(item => ({ ...item, tax_rate_value: this.defaultTaxRate }));
        },

        get isClientTaxExempt() {
            if (!this.clientId) return false;
            return !!this.clientExemptions[this.clientId];
        },
        onClientChange() {
            if (this.isClientTaxExempt) {
                this.items = this.items.map(item => ({ ...item, tax_rate_value: 0 }));
            }
        },
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
                const rate = this.isClientTaxExempt ? 0 : (parseFloat(i.tax_rate_value) || 0);
                return sum + Math.round(ht * rate / 100);
            }, 0);
        },
        get totalTtc() {
            return Math.max(0, this.subtotalHt + this.totalTax - (this.global_discount_amount || 0));
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
        // [§5 TÔLE BAC] métrage total = nb tôles × longueur unitaire (arrondi 2 déc.)
        syncSheet(item) {
            const n = parseFloat(item.nb_toles) || 0;
            const l = parseFloat(item.metrage_par_tole) || 0;
            if (n > 0 && l > 0) { item.quantity = Math.round(n * l * 100) / 100; }
        },
        isSheet(item) {
            return (parseFloat(item.nb_toles) || 0) > 0 && (parseFloat(item.metrage_par_tole) || 0) > 0;
        },
        addItem() {
            this.items.push({ _key: this._nextKey++, product_id: '', description: '', quantity: 1, nb_toles: 0, metrage_par_tole: 0, unit_price: 0, discount_percent: 0, tax_rate_value: this.isClientTaxExempt ? 0 : this.defaultTaxRate, _ps_open: false, _ps_search: '', _ps_rect: null });
        },
        removeItem(index) { this.items.splice(index, 1); },
        /** [Maquette] Charge les lignes du devis sélectionné. */
        async importFromQuote() {
            const quoteId = this.$refs.quoteSelect?.value;
            if (!quoteId) { alert('Sélectionnez d\'abord un devis source (liste à droite du bouton).'); return; }
            this.importing = true;
            try {
                const res = await fetch(quoteItemsUrl + '?quote_id=' + quoteId, { headers: { 'Accept': 'application/json' } });
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
        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                if (!this.items[index].description.trim()) this.items[index].description = p.name;
                this.items[index].unit_price = parseFloat(p.sale_price) || 0;
                // [FIX BUG-001] Article sans taux TVA → taux par défaut société (pas 0%).
                this.items[index].tax_rate_value = this.isClientTaxExempt ? 0 : (p.tax_rate?.rate != null ? parseFloat(p.tax_rate.rate) : this.defaultTaxRate);
            }
        },
        // XOF sans centimes : décimales affichées seulement si présentes (gain de place colonnes)
        formatNum(n) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Math.round((n || 0) * 100) / 100); },
        formatFcfa(n) { return this.formatNum(n) + ' FCFA'; }
    };
}
</script>
@endpush
