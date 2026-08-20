{{--
  Formulaire fournisseur — fiche « Fournisseurs : Création complète » style SAGE X3.
  Onglets : Général · Adresses · Achats · Finance · Réception · Contacts · Comptabilité · Documents.
  Variables : $taxRates, $warehouses ; $supplier en édition.
--}}
@php
    $s = $supplier ?? null;
    $isEdit = isset($supplier);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chkLb = 'text-[12.5px] font-semibold text-gray-700 select-none';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';

    $bool = fn($field, $default = false) => (bool) old($field, $s?->{$field} ?? $default);
    $contactsInit = old('contacts', $s && $s->contacts->isNotEmpty()
        ? $s->contacts->map(fn($x) => ['last_name'=>$x->last_name,'first_name'=>$x->first_name,'job_title'=>$x->job_title,'phone'=>$x->phone,'email'=>$x->email,'is_primary'=>(bool)$x->is_primary])->toArray()
        : []);
    $addressesInit = old('addresses', $s && $s->addresses->isNotEmpty()
        ? $s->addresses->map(fn($x) => ['type'=>$x->type,'label'=>$x->label,'address'=>$x->address,'city'=>$x->city,'country'=>$x->country,'is_default'=>(bool)$x->is_default])->toArray()
        : []);
@endphp

<form action="{{ $isEdit ? route('suppliers.update', $s) : route('suppliers.store') }}"
      method="POST" enctype="multipart/form-data"
      x-data="supplierForm({ tab: 'general', contacts: {{ Js::from($contactsInit) }}, addresses: {{ Js::from($addressesInit) }} })"
      class="space-y-3">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-validation-errors />

    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[15px] font-bold text-gray-900">
                Fournisseurs : Création complète
                @if($isEdit)<span class="font-mono text-emerald-700 ml-1">{{ $s->code }}</span>@endif
            </h2>
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                <a href="{{ route('suppliers.index') }}"
                   class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach([
                'general' => 'Général', 'adresses' => 'Adresses', 'achats' => 'Achats',
                'finance' => 'Finance', 'reception' => 'Réception', 'contacts' => 'Contacts',
                'compta' => 'Comptabilité', 'docs' => 'Documents',
            ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $label }}</button>
            @endforeach
        </nav>

        {{-- ═══════════ GÉNÉRAL ═══════════ --}}
        <div x-show="tab === 'general'" class="p-4 space-y-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">1. Identification</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Site</label>
                        <div class="relative"><select name="site_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('site_id', $s->site_id ?? '') == $w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Code fournisseur</label><input type="text" name="code" maxlength="30" value="{{ old('code', $s->code ?? '') }}" class="{{ $inp }} font-mono uppercase" placeholder="Auto si vide"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Type tiers</label>
                        <div class="relative"><select name="type" class="{{ $lk }}">
                            @php $t = old('type', $s->type ?? 'entreprise'); @endphp
                            <option value="entreprise" @selected($t==='entreprise')>Entreprise</option>
                            <option value="particulier" @selected($t==='particulier')>Particulier</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Catégorie</label><input type="text" name="category" maxlength="60" value="{{ old('category', $s->category ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-4"><label class="{{ $lbl }}">Raison sociale <span class="text-red-600">*</span></label><input type="text" name="name" maxlength="150" required value="{{ old('name', $s->name ?? '') }}" class="{{ $inp }} font-medium"></div>

                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Sigle / Nom court</label><input type="text" name="trade_name" maxlength="100" value="{{ old('trade_name', $s->trade_name ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Statut</label>
                        <div class="relative"><select name="is_active" class="{{ $lk }}">
                            <option value="1" @selected(old('is_active', $s->is_active ?? true))>Actif</option>
                            <option value="0" @selected(! old('is_active', $s->is_active ?? true))>Inactif</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">NIF / IFU</label><input type="text" name="ifu" maxlength="50" value="{{ old('ifu', $s->ifu ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">RCCM</label><input type="text" name="rccm" maxlength="50" value="{{ old('rccm', $s->rccm ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Numéro contribuable</label><input type="text" name="numero_contribuable" maxlength="30" value="{{ old('numero_contribuable', $s->numero_contribuable ?? '') }}" class="{{ $inp }} font-mono"></div>

                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Groupe fournisseur</label><input type="text" name="groupe_fournisseur" maxlength="60" value="{{ old('groupe_fournisseur', $s->groupe_fournisseur ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Secteur d'activité</label><input type="text" name="secteur_activite" maxlength="100" value="{{ old('secteur_activite', $s->secteur_activite ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Devise</label>
                        <div class="relative"><select name="currency" class="{{ $lk }}">@php $cur = old('currency', $s->currency ?? 'XOF'); @endphp @foreach(['XOF','XAF','EUR','USD'] as $cu)<option value="{{ $cu }}" @selected($cur===$cu)>{{ $cu }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Langue</label>
                        <div class="relative"><select name="language" class="{{ $lk }}">@php $lg = old('language', $s->language ?? 'FR'); @endphp<option value="FR" @selected($lg==='FR')>FR</option><option value="EN" @selected($lg==='EN')>EN</option></select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Pays</label><input type="text" name="country" maxlength="100" value="{{ old('country', $s->country ?? 'BF') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-4 flex flex-wrap items-end gap-x-6 gap-y-2 pb-1">
                        @foreach(['soumis_tva'=>'Soumis TVA','blocage_achat'=>'Blocage achat'] as $bn => $blab)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $bn }}" value="0">
                            <input type="checkbox" name="{{ $bn }}" value="1" class="{{ $chk }}" {{ $bool($bn, $bn === 'soumis_tva') ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">{{ $blab }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">2. Coordonnées</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-5 gap-4">
                    <div><label class="{{ $lbl }}">Téléphone principal</label><input type="text" name="phone" maxlength="20" value="{{ old('phone', $s->phone ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Téléphone secondaire</label><input type="text" name="phone2" maxlength="20" value="{{ old('phone2', $s->phone2 ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Mobile</label><input type="text" name="mobile" maxlength="20" value="{{ old('mobile', $s->mobile ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Email</label><input type="email" name="email" maxlength="150" value="{{ old('email', $s->email ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Site web</label><input type="text" name="website" maxlength="150" value="{{ old('website', $s->website ?? '') }}" class="{{ $inp }}"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ ADRESSES ═══════════ --}}
        <div x-show="tab === 'adresses'" x-cloak class="p-4 space-y-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">3. Adresse principale</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Adresse ligne 1</label><input type="text" name="address" maxlength="255" value="{{ old('address', $s->address ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Adresse ligne 2</label><input type="text" name="address_line2" maxlength="200" value="{{ old('address_line2', $s->address_line2 ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Boîte postale</label><input type="text" name="boite_postale" maxlength="60" value="{{ old('boite_postale', $s->boite_postale ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Code postal</label><input type="text" name="postal_code" maxlength="20" value="{{ old('postal_code', $s->postal_code ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Ville</label><input type="text" name="city" maxlength="100" value="{{ old('city', $s->city ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Quartier</label><input type="text" name="quartier" maxlength="100" value="{{ old('quartier', $s->quartier ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Région</label><input type="text" name="region" maxlength="100" value="{{ old('region', $s->region ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Pays</label><input type="text" name="country" maxlength="100" value="{{ old('country', $s->country ?? 'BF') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">GPS latitude</label><input type="number" step="0.000001" name="gps_lat" value="{{ old('gps_lat', $s->gps_lat ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">GPS longitude</label><input type="number" step="0.000001" name="gps_lng" value="{{ old('gps_lng', $s->gps_lng ?? '') }}" class="{{ $inpR }}"></div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Adresses secondaires</span>
                    <button type="button" @click="addAddress()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter</button>
                </div>
                <div class="p-4">
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-8">#</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Intitulé</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Adresse</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Ville</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Pays</th>
                            <th class="text-center font-bold px-2 py-1.5 border-b border-gray-200 w-16">Défaut</th>
                            <th class="w-8 border-b border-gray-200"></th>
                        </tr></thead>
                        <tbody>
                            <template x-if="addresses.length === 0"><tr><td colspan="7" class="px-3 py-3 text-center text-gray-400 text-[12px]">Aucune adresse secondaire.</td></tr></template>
                            <template x-for="(a, i) in addresses" :key="i">
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1 text-gray-400" x-text="i + 1"></td>
                                    <td class="px-2 py-1"><input type="hidden" :name="`addresses[${i}][type]`" value="livraison"><input type="text" :name="`addresses[${i}][label]`" x-model="a.label" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`addresses[${i}][address]`" x-model="a.address" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`addresses[${i}][city]`" x-model="a.city" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`addresses[${i}][country]`" x-model="a.country" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1 text-center"><input type="hidden" :name="`addresses[${i}][is_default]`" value="0"><input type="checkbox" :name="`addresses[${i}][is_default]`" value="1" x-model="a.is_default" class="{{ $chk }}"></td>
                                    <td class="px-2 py-1 text-center"><button type="button" @click="removeAddress(i)" class="text-red-500 hover:text-red-700">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- ═══════════ ACHATS ═══════════ --}}
        <div x-show="tab === 'achats'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">4. Paramètres d'achat</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div><label class="{{ $lbl }}">Canal</label><input type="text" name="canal" maxlength="60" value="{{ old('canal', $s->canal ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Famille tarifaire</label><input type="text" name="famille_tarifaire" maxlength="60" value="{{ old('famille_tarifaire', $s->famille_tarifaire ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Remise par défaut (%)</label><input type="number" step="0.01" min="0" max="100" name="default_discount" value="{{ old('default_discount', $s->default_discount ?? 0) }}" class="{{ $inpR }}"></div>
                    <div>
                        <label class="{{ $lbl }}">Mode de règlement</label>
                        <div class="relative"><select name="payment_mode" class="{{ $lk }}">
                            @php $pm = old('payment_mode', $s->payment_mode ?? 'virement'); @endphp
                            <option value="virement" @selected($pm==='virement')>Virement</option>
                            <option value="cash" @selected($pm==='cash')>Comptant</option>
                            <option value="cheque" @selected($pm==='cheque')>Chèque</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Délai de règlement (jours)</label><input type="number" min="0" max="365" name="payment_days" value="{{ old('payment_days', $s->payment_days ?? 0) }}" class="{{ $inpR }}"></div>
                    <div>
                        <label class="{{ $lbl }}">TVA applicable</label>
                        <div class="relative"><select name="tax_rate_id" class="{{ $lk }}"><option value="">—</option>@foreach($taxRates as $tr)<option value="{{ $tr->id }}" @selected(old('tax_rate_id', $s->tax_rate_id ?? '') == $tr->id)>{{ $tr->rate }} %</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Note qualité (0-5)</label><input type="number" step="0.1" min="0" max="5" name="rating" value="{{ old('rating', $s->rating ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Délai livr. moyen (j)</label><input type="number" min="0" name="avg_delivery_days" value="{{ old('avg_delivery_days', $s->avg_delivery_days ?? '') }}" class="{{ $inpR }}"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ FINANCE ═══════════ --}}
        <div x-show="tab === 'finance'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Finance</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {{-- [Gestion] Plafond fournisseur — CHAMP UNIQUE.
                         « Encours autorisé » a été supprimé : il faisait doublon
                         avec `credit_limit` et aucun des deux n'était appliqué par
                         un contrôle. Deux champs concurrents pour un même plafond
                         ne peuvent que diverger. --}}
                    <div><label class="{{ $lbl }}">Plafond crédit</label><input type="number" min="0" step="1" name="credit_limit" value="{{ old('credit_limit', $s->credit_limit ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Banque</label><input type="text" name="banque" maxlength="100" value="{{ old('banque', $s->banque ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">SWIFT</label><input type="text" name="swift" maxlength="20" value="{{ old('swift', $s->swift ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">RIB / IBAN</label><input type="text" name="rib_iban" maxlength="40" value="{{ old('rib_iban', $s->rib_iban ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Numéro de compte</label><input type="text" name="numero_compte" maxlength="30" value="{{ old('numero_compte', $s->numero_compte ?? '') }}" class="{{ $inp }} font-mono"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ RÉCEPTION ═══════════ --}}
        <div x-show="tab === 'reception'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">5. Réception</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $lbl }}">Dépôt de réception par défaut</label>
                        <div class="relative"><select name="depot_reception_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_reception_id', $s->depot_reception_id ?? '') == $w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Mode de livraison</label><input type="text" name="mode_livraison" maxlength="60" value="{{ old('mode_livraison', $s->mode_livraison ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Transporteur</label><input type="text" name="transporteur" maxlength="100" value="{{ old('transporteur', $s->transporteur ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Délai de livraison (jours)</label><input type="number" min="0" max="365" name="delai_livraison" value="{{ old('delai_livraison', $s->delai_livraison ?? '') }}" class="{{ $inpR }}"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ CONTACTS ═══════════ --}}
        <div x-show="tab === 'contacts'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Contacts</span>
                    <button type="button" @click="addContact()" class="text-[12px] font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-[3px]">+ Ajouter</button>
                </div>
                <div class="p-4">
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200 w-8">#</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Nom</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Prénom</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Fonction</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Téléphone</th>
                            <th class="text-left font-bold px-2 py-1.5 border-b border-gray-200">Email</th>
                            <th class="text-center font-bold px-2 py-1.5 border-b border-gray-200 w-16">Principal</th>
                            <th class="w-8 border-b border-gray-200"></th>
                        </tr></thead>
                        <tbody>
                            <template x-if="contacts.length === 0"><tr><td colspan="8" class="px-3 py-3 text-center text-gray-400 text-[12px]">Aucun contact.</td></tr></template>
                            <template x-for="(ct, i) in contacts" :key="i">
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1 text-gray-400" x-text="i + 1"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`contacts[${i}][last_name]`" x-model="ct.last_name" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`contacts[${i}][first_name]`" x-model="ct.first_name" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`contacts[${i}][job_title]`" x-model="ct.job_title" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="text" :name="`contacts[${i}][phone]`" x-model="ct.phone" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1"><input type="email" :name="`contacts[${i}][email]`" x-model="ct.email" class="{{ $inp }} h-7"></td>
                                    <td class="px-2 py-1 text-center"><input type="hidden" :name="`contacts[${i}][is_primary]`" value="0"><input type="checkbox" :name="`contacts[${i}][is_primary]`" value="1" x-model="ct.is_primary" class="{{ $chk }}"></td>
                                    <td class="px-2 py-1 text-center"><button type="button" @click="removeContact(i)" class="text-red-500 hover:text-red-700">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- ═══════════ COMPTABILITÉ ═══════════ --}}
        <div x-show="tab === 'compta'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">6. Comptabilité</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div><label class="{{ $lbl }}">Compte tiers (401)</label><input type="text" name="compte_tiers" maxlength="30" value="{{ old('compte_tiers', $s->compte_tiers ?? '') }}" class="{{ $inp }} font-mono" placeholder="40110000"></div>
                    <div><label class="{{ $lbl }}">Compte collectif</label><input type="text" name="compte_collectif" maxlength="30" value="{{ old('compte_collectif', $s->compte_collectif ?? '') }}" class="{{ $inp }} font-mono" placeholder="40100000"></div>
                    <div><label class="{{ $lbl }}">Condition de paiement</label><input type="text" name="condition_paiement" maxlength="60" value="{{ old('condition_paiement', $s->condition_paiement ?? '') }}" class="{{ $inp }}" placeholder="30J FDM"></div>
                    <div><label class="{{ $lbl }}">Échéance</label><input type="text" name="echeance" maxlength="60" value="{{ old('echeance', $s->echeance ?? '') }}" class="{{ $inp }}" placeholder="Fin de mois"></div>
                    <div class="sm:col-span-4">
                        <label class="{{ $lbl }}">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes', $s->notes ?? '') }}</textarea>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══════════ DOCUMENTS ═══════════ --}}
        <div x-show="tab === 'docs'" x-cloak class="p-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Documents / pièces jointes</div>
                <div class="p-4 space-y-4">
                    @if($isEdit && $s->attachments->isNotEmpty())
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                        </tr></thead>
                        <tbody>
                            @foreach($s->attachments as $i => $att)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-3 py-1.5 text-gray-700 font-mono">{{ $att->filename }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ $att->mime_type }}</td>
                                <td class="px-3 py-1.5 text-gray-500 tabular-nums">{{ number_format($att->size / 1024, 0, ',', ' ') }} Ko</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                    <div>
                        <label class="{{ $lbl }}">Ajouter des pièces jointes</label>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                               class="w-full text-[13px] border border-[#c3d3c9] rounded-[3px] px-2 py-1.5 cursor-pointer
                                      file:mr-3 file:py-0.5 file:px-2 file:border-0 file:bg-emerald-50 file:text-emerald-700
                                      file:rounded-[2px] file:text-[12px] file:font-semibold hover:file:bg-emerald-100">
                        <p class="text-[11px] text-gray-400 mt-1">PDF, images, Word, Excel — max 5 Mo par fichier.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

@push('scripts')
<script>
function supplierForm(init) {
    return {
        tab: init.tab || 'general',
        contacts: init.contacts || [],
        addresses: init.addresses || [],
        addContact()     { this.contacts.push({ last_name: '', first_name: '', job_title: '', phone: '', email: '', is_primary: false }); },
        removeContact(i) { this.contacts.splice(i, 1); },
        addAddress()     { this.addresses.push({ type: 'livraison', label: '', address: '', city: '', country: 'BF', is_default: false }); },
        removeAddress(i) { this.addresses.splice(i, 1); },
    };
}
</script>
@endpush
