{{--
  Formulaire client — fiche « Clients : Création complète » style SAGE X3.
  Onglets : Général · Adresses · Commercial · Finance · Livraison · Contacts · Comptabilité · Documents.
  Variables : $taxRates, $warehouses, $salesReps ; $client en édition.
--}}
@php
    $c = $client ?? null;
    $isEdit = isset($client);

    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chkLb = 'text-[12.5px] font-semibold text-gray-700 select-none';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';

    $bool = fn($field, $default = false) => (bool) old($field, $c?->{$field} ?? $default);
    $contactsInit = old('contacts', $c && $c->contacts->isNotEmpty()
        ? $c->contacts->map(fn($x) => ['last_name'=>$x->last_name,'first_name'=>$x->first_name,'job_title'=>$x->job_title,'phone'=>$x->phone,'email'=>$x->email,'is_primary'=>(bool)$x->is_primary])->toArray()
        : []);
    $addressesInit = old('addresses', $c && $c->addresses->isNotEmpty()
        ? $c->addresses->map(fn($x) => ['type'=>$x->type,'label'=>$x->label,'address'=>$x->address,'city'=>$x->city,'country'=>$x->country,'is_default'=>(bool)$x->is_default])->toArray()
        : []);
@endphp

<form action="{{ $isEdit ? route('clients.update', $c) : route('clients.store') }}"
      method="POST" enctype="multipart/form-data"
      x-data="clientForm({ tab: 'general', contacts: {{ Js::from($contactsInit) }}, addresses: {{ Js::from($addressesInit) }},
          city: '{{ old('city', $c->city ?? '') }}', country: '{{ old('country', $c->country ?? 'BF') }}',
          creditLimit: '{{ old('credit_limit', $c->credit_limit ?? 0) }}', encours: '{{ old('encours_autorise', $c->encours_autorise ?? '') }}',
          compteCollectif: '{{ old('compte_collectif', $c->compte_collectif ?? '') }}' })"
      class="space-y-3">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-validation-errors />

    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <h2 class="text-[22px] font-bold text-gray-900 leading-tight">
                Clients : {{ $isEdit ? 'Modification' : 'Création complète' }}
                @if($isEdit)<span class="font-mono text-emerald-700 text-[18px] ml-1">{{ $c->code }}</span>@endif
            </h2>
            <div class="flex items-center gap-1.5">
                @if($isEdit)
                <button type="button" onclick="document.getElementById('archiveClientForm').requestSubmit()"
                        class="text-[14px] font-semibold text-red-600 border border-red-200 bg-white hover:bg-red-50 px-5 py-2 rounded-[4px] transition-colors">Archiver</button>
                @endif
                <button type="submit"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Enregistrer</button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
                <a href="{{ route('clients.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach([
                'general' => 'Général', 'adresses' => 'Adresses', 'commercial' => 'Commercial',
                'finance' => 'Finance', 'livraison' => 'Livraison', 'contacts' => 'Contacts',
                'compta' => 'Comptabilité', 'docs' => 'Documents',
            ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'; document.getElementById('sec-{{ $key }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $label }}</button>
            @endforeach
        </nav>

        {{-- ═══════════ GÉNÉRAL ═══════════ --}}
        <div id="sec-general" class="p-4 space-y-4 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">1. Identification</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Site</label>
                        <div class="relative"><select name="site_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('site_id', $c->site_id ?? '') == $w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Code client</label>
                        <input type="text" name="code" maxlength="30" value="{{ old('code', $c->code ?? '') }}" class="{{ $inp }} font-mono uppercase" placeholder="Auto si vide">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Type tiers <span class="text-red-600">*</span></label>
                        <div class="relative"><select name="type" required class="{{ $lk }}">
                            @php $t = old('type', $c->type ?? 'entreprise'); @endphp
                            <option value="entreprise" @selected($t==='entreprise')>Entreprise</option>
                            <option value="particulier" @selected($t==='particulier')>Particulier</option>
                            <option value="distributeur" @selected($t==='distributeur')>Distributeur</option>
                            <option value="minier" @selected($t==='minier')>Minier</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Catégorie client</label>
                        <input type="text" name="category" maxlength="60" value="{{ old('category', $c->category ?? '') }}" class="{{ $inp }}" placeholder="INDUS">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="{{ $lbl }}">Raison sociale <span class="text-red-600">*</span></label>
                        <input type="text" name="name" maxlength="150" required value="{{ old('name', $c->name ?? '') }}" class="{{ $inp }} font-medium" placeholder="AFRICA INDUSTRIES SA">
                    </div>

                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Sigle / Nom court</label>
                        <input type="text" name="trade_name" maxlength="100" value="{{ old('trade_name', $c->trade_name ?? '') }}" class="{{ $inp }}" placeholder="AFRICA IND.">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Statut</label>
                        <div class="relative"><select name="is_active" class="{{ $lk }}">
                            <option value="1" @selected(old('is_active', $c->is_active ?? true))>Actif</option>
                            <option value="0" @selected(! old('is_active', $c->is_active ?? true))>Inactif</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">NIF / IFU</label><input type="text" name="ifu" maxlength="50" value="{{ old('ifu', $c->ifu ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">RCCM</label><input type="text" name="rccm" maxlength="50" value="{{ old('rccm', $c->rccm ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Numéro contribuable</label><input type="text" name="numero_contribuable" maxlength="30" value="{{ old('numero_contribuable', $c->numero_contribuable ?? '') }}" class="{{ $inp }} font-mono"></div>

                    {{-- [Parité Sage X3] Bloc juridique / fiscal --}}
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Forme juridique</label>
                        <div class="relative"><select name="forme_juridique" class="{{ $lk }}">
                            @php $fj = old('forme_juridique', $c->forme_juridique ?? ''); @endphp
                            <option value="">—</option>
                            @foreach(['SARL'=>'SARL','SA'=>'Société Anonyme (SA)','SAS'=>'SAS','SUARL'=>'SUARL','EI'=>'Entreprise Individuelle','GIE'=>'GIE','Association'=>'Association','ONG'=>'ONG','Administration'=>'Administration'] as $fv=>$fl)
                            <option value="{{ $fv }}" @selected($fj===$fv)>{{ $fl }}</option>
                            @endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Régime d'imposition</label><input type="text" name="regime_imposition" maxlength="80" value="{{ old('regime_imposition', $c->regime_imposition ?? '') }}" class="{{ $inp }}" placeholder="Régime normal"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">N° agrément</label><input type="text" name="no_agrement" maxlength="60" value="{{ old('no_agrement', $c->no_agrement ?? '') }}" class="{{ $inp }} font-mono"></div>

                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Groupe client</label><input type="text" name="groupe_client" maxlength="60" value="{{ old('groupe_client', $c->groupe_client ?? '') }}" class="{{ $inp }}" placeholder="GRAND-CPT"></div>
                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Secteur d'activité</label><input type="text" name="secteur_activite" maxlength="100" value="{{ old('secteur_activite', $c->secteur_activite ?? '') }}" class="{{ $inp }}" placeholder="INDUSTRIE"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Devise</label>
                        <div class="relative"><select name="currency" class="{{ $lk }}">
                            @php $cur = old('currency', $c->currency ?? 'XOF'); @endphp
                            @foreach(['XOF','XAF','EUR','USD'] as $cu)<option value="{{ $cu }}" @selected($cur===$cu)>{{ $cu }}</option>@endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Langue</label>
                        <div class="relative"><select name="language" class="{{ $lk }}">
                            @php $lg = old('language', $c->language ?? 'FR'); @endphp
                            <option value="FR" @selected($lg==='FR')>FR</option><option value="EN" @selected($lg==='EN')>EN</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Pays</label><input type="text" name="country" x-model="country" maxlength="100" class="{{ $inp }}"></div>
                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Ville</label><input type="text" name="city" x-model="city" maxlength="100" class="{{ $inp }}"></div>
                    <div class="sm:col-span-3"><label class="{{ $lbl }}">Quartier</label><input type="text" name="quartier" maxlength="100" value="{{ old('quartier', $c->quartier ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-6 flex flex-wrap items-end gap-x-6 gap-y-2 pb-1">
                        @foreach(['is_livrable'=>'Client livrable','is_facturable'=>'Client facturable','soumis_tva'=>'Soumis TVA','blocage_commande'=>'Blocage commande'] as $bn => $blab)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="{{ $bn }}" value="0">
                            <input type="checkbox" name="{{ $bn }}" value="1" class="{{ $chk }}" {{ $bool($bn, $bn !== 'blocage_commande') ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">{{ $blab }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">2. Coordonnées</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-5 gap-4">
                    <div><label class="{{ $lbl }}">Téléphone principal</label><input type="text" name="phone" maxlength="20" value="{{ old('phone', $c->phone ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Téléphone secondaire</label><input type="text" name="phone2" maxlength="20" value="{{ old('phone2', $c->phone2 ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Email</label><input type="email" name="email" maxlength="150" value="{{ old('email', $c->email ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Site web</label><input type="text" name="website" maxlength="150" value="{{ old('website', $c->website ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Boîte postale</label><input type="text" name="boite_postale" maxlength="60" value="{{ old('boite_postale', $c->boite_postale ?? '') }}" class="{{ $inp }}"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ ADRESSES ═══════════ --}}
        <div id="sec-adresses" class="p-4 pt-0 space-y-4 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">3. Adresse principale</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Adresse ligne 1</label><input type="text" name="address" maxlength="200" value="{{ old('address', $c->address ?? '') }}" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Adresse ligne 2</label><input type="text" name="address_line2" maxlength="200" value="{{ old('address_line2', $c->address_line2 ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Code postal</label><input type="text" name="postal_code" maxlength="20" value="{{ old('postal_code', $c->postal_code ?? '') }}" class="{{ $inp }}"></div>
                    {{-- [FIX doublons] miroir de l'onglet Général — pas de name, sinon le doublon écrase --}}
                    <div><label class="{{ $lbl }}">Ville</label><input type="text" x-model="city" maxlength="100" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Région</label><input type="text" name="region" maxlength="100" value="{{ old('region', $c->region ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">Pays</label><input type="text" x-model="country" maxlength="100" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">GPS latitude</label><input type="number" step="0.000001" name="gps_lat" value="{{ old('gps_lat', $c->gps_lat ?? '') }}" class="{{ $inpR }}" placeholder="5.3470"></div>
                    <div><label class="{{ $lbl }}">GPS longitude</label><input type="number" step="0.000001" name="gps_lng" value="{{ old('gps_lng', $c->gps_lng ?? '') }}" class="{{ $inpR }}" placeholder="-4.0340"></div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>4. Adresses de livraison</span>
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
                            <template x-if="addresses.length === 0"><tr><td colspan="7" class="px-3 py-3 text-center text-gray-400 text-[12px]">Aucune adresse de livraison.</td></tr></template>
                            <template x-for="(a, i) in addresses" :key="i">
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1 text-gray-400" x-text="i + 1"></td>
                                    <td class="px-2 py-1"><input type="hidden" :name="`addresses[${i}][type]`" value="livraison"><input type="text" :name="`addresses[${i}][label]`" x-model="a.label" class="{{ $inp }} h-7" placeholder="LIV-ABJ-01"></td>
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

        {{-- ═══════════ COMMERCIAL ═══════════ --}}
        <div id="sec-commercial" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">5. Paramètres commerciaux</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $lbl }}">Représentant commercial</label>
                        <div class="relative"><select name="sales_rep_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($salesReps as $r)<option value="{{ $r->id }}" @selected(old('sales_rep_id', $c->sales_rep_id ?? '') == $r->id)>{{ $r->code }} — {{ $r->name }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Canal</label><input type="text" name="canal" maxlength="60" value="{{ old('canal', $c->canal ?? '') }}" class="{{ $inp }}" placeholder="DISTRIB"></div>
                    <div><label class="{{ $lbl }}">Zone commerciale</label><input type="text" name="zone_commerciale" maxlength="60" value="{{ old('zone_commerciale', $c->zone_commerciale ?? '') }}" class="{{ $inp }}" placeholder="ZONE-ABJ"></div>
                    <div><label class="{{ $lbl }}">Famille tarifaire</label><input type="text" name="famille_tarifaire" maxlength="60" value="{{ old('famille_tarifaire', $c->famille_tarifaire ?? '') }}" class="{{ $inp }}" placeholder="TARIF-IND"></div>
                    <div><label class="{{ $lbl }}">Remise par défaut (%)</label><input type="number" step="0.01" min="0" max="100" name="default_discount" value="{{ old('default_discount', $c->default_discount ?? 0) }}" class="{{ $inpR }}"></div>
                    <div>
                        <label class="{{ $lbl }}">Mode de règlement</label>
                        <div class="relative"><select name="payment_mode" class="{{ $lk }}">
                            @php $pm = old('payment_mode', $c->payment_mode ?? 'credit'); @endphp
                            <option value="cash" @selected($pm==='cash')>Comptant</option>
                            <option value="credit" @selected($pm==='credit')>Virement / Crédit</option>
                        </select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Délai de règlement (jours)</label><input type="number" min="0" max="365" name="payment_days" value="{{ old('payment_days', $c->payment_days ?? 0) }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Plafond crédit</label><input type="number" min="0" step="1" name="credit_limit" x-model="creditLimit" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Encours autorisé</label><input type="number" min="0" step="1" name="encours_autorise" x-model="encours" class="{{ $inpR }}"></div>
                    {{-- [Parametrage Vente] blocage commercial : refuse devis/commande/facture --}}
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_blocked" value="0">
                            <input type="checkbox" name="is_blocked" value="1" {{ old('is_blocked', $c->is_blocked ?? false) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <span class="text-[12px] font-bold text-red-700">Client bloqué</span>
                        </label>
                    </div>
                    <div class="col-span-2"><label class="{{ $lbl }}">Motif de blocage</label><input type="text" name="blocked_reason" maxlength="255" value="{{ old('blocked_reason', $c->blocked_reason ?? '') }}" class="{{ $inp }}" placeholder="Contentieux, impayés…"></div>
                    <div>
                        <label class="{{ $lbl }}">TVA applicable</label>
                        <div class="relative"><select name="tax_rate_id" class="{{ $lk }}"><option value="">—</option>@foreach($taxRates as $tr)<option value="{{ $tr->id }}" @selected(old('tax_rate_id', $c->tax_rate_id ?? '') == $tr->id)>{{ $tr->rate }} %</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Compte collectif</label><input type="text" name="compte_collectif" x-model="compteCollectif" maxlength="30" class="{{ $inp }} font-mono" placeholder="CC-CLIENTS"></div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_tax_exempt" value="0">
                            <input type="checkbox" name="is_tax_exempt" value="1" class="{{ $chk }}" {{ $bool('is_tax_exempt') ? 'checked' : '' }}>
                            <span class="{{ $chkLb }}">Client exonéré</span>
                        </label>
                    </div>

                    {{-- [Parité Sage X3] Bloc risque crédit --}}
                    <div class="col-span-full mt-1 pt-2 border-t border-gray-100">
                        <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-2">Risque crédit &amp; garanties</p>
                    </div>
                    <div><label class="{{ $lbl }}">Code risque</label><input type="text" name="code_risque" maxlength="30" value="{{ old('code_risque', $c->code_risque ?? '') }}" class="{{ $inp }}" placeholder="Bon, Surveillé…"></div>
                    <div><label class="{{ $lbl }}">Garantie (montant)</label><input type="number" min="0" step="1" name="garantie_montant" value="{{ old('garantie_montant', $c->garantie_montant ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Nature garantie</label><input type="text" name="nature_garantie" maxlength="80" value="{{ old('nature_garantie', $c->nature_garantie ?? '') }}" class="{{ $inp }}" placeholder="Caution, hypothèque…"></div>
                    <div><label class="{{ $lbl }}">Assurance crédit</label><input type="text" name="assurance_credit" maxlength="120" value="{{ old('assurance_credit', $c->assurance_credit ?? '') }}" class="{{ $inp }}"></div>
                    <div><label class="{{ $lbl }}">RRR (montant)</label><input type="number" min="0" step="1" name="rrr_montant" value="{{ old('rrr_montant', $c->rrr_montant ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">RRR (%)</label><input type="number" min="0" max="100" step="0.01" name="rrr_taux" value="{{ old('rrr_taux', $c->rrr_taux ?? '') }}" class="{{ $inpR }}"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Référence cadastrale</label><input type="text" name="reference_cadastrale" maxlength="80" value="{{ old('reference_cadastrale', $c->reference_cadastrale ?? '') }}" class="{{ $inp }} font-mono"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ FINANCE ═══════════ --}}
        <div id="sec-finance" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Finance</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {{-- [FIX doublons] miroirs de l'onglet Commercial — pas de name, sinon le doublon écrase --}}
                    <div><label class="{{ $lbl }}">Plafond crédit</label><input type="number" min="0" step="1" x-model="creditLimit" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Encours autorisé</label><input type="number" min="0" step="1" x-model="encours" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Banque</label><input type="text" name="banque" maxlength="100" value="{{ old('banque', $c->banque ?? '') }}" class="{{ $inp }}" placeholder="Coris Bank"></div>
                    <div><label class="{{ $lbl }}">SWIFT</label><input type="text" name="swift" maxlength="20" value="{{ old('swift', $c->swift ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">RIB / IBAN</label><input type="text" name="rib_iban" maxlength="40" value="{{ old('rib_iban', $c->rib_iban ?? '') }}" class="{{ $inp }} font-mono"></div>
                    <div class="sm:col-span-2"><label class="{{ $lbl }}">Numéro de compte</label><input type="text" name="numero_compte" maxlength="30" value="{{ old('numero_compte', $c->numero_compte ?? '') }}" class="{{ $inp }} font-mono"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ LIVRAISON ═══════════ --}}
        <div id="sec-livraison" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">6. Livraison</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div>
                        <label class="{{ $lbl }}">Dépôt de livraison par défaut</label>
                        <div class="relative"><select name="depot_livraison_id" class="{{ $lk }} font-mono"><option value="">—</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('depot_livraison_id', $c->depot_livraison_id ?? '') == $w->id)>{{ $w->code }}</option>@endforeach</select>{!! $caret !!}</div>
                    </div>
                    <div><label class="{{ $lbl }}">Mode de livraison</label><input type="text" name="mode_livraison" maxlength="60" value="{{ old('mode_livraison', $c->mode_livraison ?? '') }}" class="{{ $inp }}" placeholder="Ex : Route, Enlèvement…"></div>
                    <div><label class="{{ $lbl }}">Transporteur</label><input type="text" name="transporteur" maxlength="100" value="{{ old('transporteur', $c->transporteur ?? '') }}" class="{{ $inp }}" placeholder="Ex : Trans-Express"></div>
                    <div><label class="{{ $lbl }}">Délai de livraison (jours)</label><input type="number" min="0" max="365" name="delai_livraison" value="{{ old('delai_livraison', $c->delai_livraison ?? '') }}" class="{{ $inpR }}"></div>
                    <div><label class="{{ $lbl }}">Adresse livraison par défaut</label><input type="text" name="adresse_livraison_defaut" maxlength="60" value="{{ old('adresse_livraison_defaut', $c->adresse_livraison_defaut ?? '') }}" class="{{ $inp }}" placeholder="Ex : LIV-ABJ-01"></div>
                </div>
            </section>
        </div>

        {{-- ═══════════ CONTACTS ═══════════ --}}
        <div id="sec-contacts" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>7. Contacts</span>
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
        <div id="sec-compta" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">8. Comptabilité</div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div><label class="{{ $lbl }}">Compte tiers</label><input type="text" name="compte_tiers" maxlength="30" value="{{ old('compte_tiers', $c->compte_tiers ?? '') }}" class="{{ $inp }} font-mono" placeholder="41110000"></div>
                    <div><label class="{{ $lbl }}">Compte collectif</label><input type="text" x-model="compteCollectif" maxlength="30" class="{{ $inp }} font-mono" placeholder="41100000"></div>
                    <div><label class="{{ $lbl }}">Condition de paiement</label><input type="text" name="condition_paiement" maxlength="60" value="{{ old('condition_paiement', $c->condition_paiement ?? '') }}" class="{{ $inp }}" placeholder="30J FDM"></div>
                    <div><label class="{{ $lbl }}">Échéance</label><input type="text" name="echeance" maxlength="60" value="{{ old('echeance', $c->echeance ?? '') }}" class="{{ $inp }}" placeholder="Fin de mois"></div>

                    {{-- [Parité Sage X3] Tiers comptables : client facturé / payeur / groupe / risque / factor --}}
                    <div class="col-span-full mt-1 pt-2 border-t border-gray-100">
                        <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide mb-2">Tiers comptables</p>
                    </div>
                    @php
                        $tiers = $tiersClients ?? collect();
                        $tiersSel = fn ($field) => (int) old($field, $c->$field ?? 0);
                    @endphp
                    @foreach(['client_facture_id'=>'Client facturé','client_payeur_id'=>'Client payeur','client_groupe_id'=>'Client groupe','client_risque_id'=>'Client risque','factor_id'=>'Factor'] as $tf => $tlab)
                    <div>
                        <label class="{{ $lbl }}">{{ $tlab }}</label>
                        <div class="relative"><select name="{{ $tf }}" class="{{ $lk }}">
                            <option value="">—</option>
                            @foreach($tiers as $tc)
                            @if(!isset($c) || $tc->id !== $c->id)
                            <option value="{{ $tc->id }}" @selected($tiersSel($tf)===$tc->id)>{{ $tc->code }} — {{ \Illuminate\Support\Str::limit($tc->name, 28) }}</option>
                            @endif
                            @endforeach
                        </select>{!! $caret !!}</div>
                    </div>
                    @endforeach

                    <div class="sm:col-span-4">
                        <label class="{{ $lbl }}">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes', $c->notes ?? '') }}</textarea>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══════════ DOCUMENTS ═══════════ --}}
        <div id="sec-docs" class="p-4 pt-0 scroll-mt-28">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">9. Documents / pièces jointes</div>
                <div class="p-4 space-y-4">
                    @if($isEdit && $c->attachments->isNotEmpty())
                    <table class="w-full text-[12.5px] border border-gray-200">
                        <thead><tr class="bg-gray-50 text-gray-600">
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200 w-10">#</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Fichier</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Type</th>
                            <th class="text-left font-bold px-3 py-1.5 border-b border-gray-200">Taille</th>
                        </tr></thead>
                        <tbody>
                            @foreach($c->attachments as $i => $att)
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

            @if($isEdit && isset($clientDocs))
            {{-- [CDC OA-12 r.7] Liste consolidée des documents liés au client --}}
            <section class="border border-gray-200 rounded-[4px] mt-4">
                <div class="{{ $secH }}">10. Documents liés au client</div>
                <div class="p-4 grid grid-cols-1 xl:grid-cols-3 gap-4">
                    @foreach([
                        'devis'     => ['titre' => 'Derniers devis',     'route' => 'ventes.devis.show'],
                        'commandes' => ['titre' => 'Dernières commandes', 'route' => 'ventes.commandes.show'],
                        'factures'  => ['titre' => 'Dernières factures',  'route' => 'ventes.factures.show'],
                    ] as $type => $meta)
                    <div>
                        <div class="text-[12px] font-bold text-gray-700 mb-1.5">{{ $meta['titre'] }}</div>
                        <table class="w-full text-[12px] border border-gray-200">
                            <thead>
                                <tr class="bg-[#3b4248] text-white text-[11px] font-semibold uppercase whitespace-nowrap">
                                    <th class="text-left px-2 py-1.5">N°</th>
                                    <th class="text-left px-2 py-1.5">Date</th>
                                    <th class="text-right px-2 py-1.5">TTC</th>
                                    <th class="text-left px-2 py-1.5">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($clientDocs[$type] as $doc)
                                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 border-b border-gray-100 last:border-0">
                                    <td class="px-2 py-1.5 whitespace-nowrap">
                                        <a href="{{ route($meta['route'], $doc->id) }}" class="font-mono text-emerald-700 hover:underline">{{ $doc->number }}</a>
                                    </td>
                                    <td class="px-2 py-1.5 text-gray-600 whitespace-nowrap tabular-nums">{{ $doc->issued_at?->format('d/m/Y') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums whitespace-nowrap">{{ number_format((float) $doc->total_ttc, 0, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 whitespace-nowrap">
                                        <span class="inline-block px-1.5 py-0.5 rounded-[2px] text-[10.5px] font-semibold bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_', ' ', $doc->status)) }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="px-2 py-2 text-gray-400 italic">Aucun document</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @endforeach
                </div>
            </section>
            @endif
        </div>
    </div>
</form>

{{-- ── Barre de contexte pied de page [X3] ─────────────────────────────────── --}}
<div class="mt-3 bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
    <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
    <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
    <span class="border-l border-white/10 pl-6">Fiche : <span class="text-white font-semibold">{{ $isEdit ? 'Client ' . $c->code : 'Nouveau client' }}</span></span>
    @if($isEdit)<span class="border-l border-white/10 pl-6">Créée le : <span class="text-white font-semibold">{{ $c->created_at?->format('d/m/Y') }}</span>{{ $c->createdBy ? ' par ' : '' }}<span class="text-white font-semibold">{{ $c->createdBy?->name }}</span></span>@endif
    <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
    <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
</div>

@push('scripts')
<script>
function clientForm(init) {
    return {
        tab: init.tab || 'general',
        contacts: init.contacts || [],
        addresses: init.addresses || [],
        // [FIX doublons] champs affichés à DEUX endroits du formulaire : un seul submitter
        // (1re occurrence, name conservé), la 2e est un miroir x-model sans name.
        city: init.city || '',
        country: init.country || '',
        creditLimit: init.creditLimit || 0,
        encours: init.encours || '',
        compteCollectif: init.compteCollectif || '',
        addContact()     { this.contacts.push({ last_name: '', first_name: '', job_title: '', phone: '', email: '', is_primary: false }); },
        removeContact(i) { this.contacts.splice(i, 1); },
        addAddress()     { this.addresses.push({ type: 'livraison', label: '', address: '', city: '', country: 'BF', is_default: false }); },
        removeAddress(i) { this.addresses.splice(i, 1); },
    };
}
</script>
@endpush
