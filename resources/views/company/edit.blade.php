@extends('layouts.erp')
@section('title', 'Paramétrage société')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramétrage société</span>
@endsection

@section('content')
<div x-data="{ tab: '{{ old('_tab', 'adresse') === 'general' ? 'adresse' : old('_tab', 'adresse') }}' }" class="space-y-3">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Paramétrage de la société</h1>
            <p class="text-sm text-gray-500 mt-1">Configurez les informations de votre entreprise</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" form="form-general" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
            <a href="{{ route('company.edit') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-1.5 rounded-[4px] transition-colors">Annuler</a>
        </div>
        @if($company->logo)
        <img src="{{ url(Storage::url($company->logo)) }}" alt="Logo" class="h-14 rounded-[4px] object-contain border border-gray-200 p-1">
        @endif
    </div>

    {{-- Informations générales — toujours visibles [Maquette] --}}
    <div class="bg-white rounded-[4px] border border-gray-300">
        <form id="form-general" action="{{ route('company.update.general') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="p-6 space-y-3">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="general">

            <h2 class="text-base font-semibold text-emerald-800 mb-1">Informations générales</h2>
            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Carte logo [Maquette] --}}
                <div class="lg:w-56 flex-shrink-0">
                    <div class="border border-gray-200 rounded-[4px] p-4 flex flex-col items-center gap-3">
                        @if($company->logo)
                        <img src="{{ url(Storage::url($company->logo)) }}" alt="Logo" class="h-24 object-contain">
                        @else
                        <div class="h-24 w-full flex items-center justify-center text-gray-300 text-sm">Aucun logo</div>
                        @endif
                        <label class="w-full text-center text-[13px] font-semibold text-emerald-700 border border-dashed border-emerald-400 rounded-[4px] px-3 py-2 cursor-pointer hover:bg-emerald-50 transition-colors">
                            &#8682; Changer le logo
                            <input type="file" name="logo" accept="image/*" class="hidden">
                        </label>
                        <p class="text-[11px] text-gray-400">PNG, JPG &mdash; 2 Mo max.</p>
                    </div>
                </div>

                {{-- Grille 3 colonnes [Maquette] --}}
                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code société</label>
                        <input type="text" name="company_code" maxlength="30" value="{{ old('company_code', $company->company_code) }}" placeholder="OMA-BF-001"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono uppercase focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Raison sociale <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $company->name) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 @error('name') border-red-500 @enderror">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sigle</label>
                        <input type="text" name="sigle" maxlength="20" value="{{ old('sigle', $company->sigle) }}" placeholder="OAMI"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm uppercase focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Forme juridique</label>
                        @php $lf = old('legal_form', $company->legal_form); @endphp
                        <select name="legal_form" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value="">&mdash; Sélectionner &mdash;</option>
                            @foreach(['SARL' => 'SARL', 'SA' => 'Société Anonyme (SA)', 'SAS' => 'Société par Actions Simplifiée (SAS)', 'EI' => 'Entreprise Individuelle', 'SUARL' => 'SUARL', 'GIE' => 'GIE', 'Association' => 'Association'] as $fv => $fl)
                            <option value="{{ $fv }}" @selected($lf===$fv)>{{ $fl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">IFU</label>
                        <input type="text" name="ifu" value="{{ old('ifu', $company->ifu) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">RCCM</label>
                        <input type="text" name="rccm" value="{{ old('rccm', $company->rccm) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CNSS / N° employeur</label>
                        <input type="text" name="cnss_number" maxlength="40" value="{{ old('cnss_number', $company->cnss_number) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activité principale</label>
                        <input type="text" name="main_activity" maxlength="120" value="{{ old('main_activity', $company->main_activity) }}" placeholder="Fabrication de structures métalliques"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Devise de base</label>
                        <select name="default_currency_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            @foreach($currencies ?? [] as $cur)
                            <option value="{{ $cur->id }}" @selected(old('default_currency_id', $company->default_currency_id)==$cur->id)>{{ $cur->code }} &ndash; {{ $cur->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Langue</label>
                        @php $lang = old('language', $company->language ?? 'fr'); @endphp
                        <select name="language" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value="fr" @selected($lang==='fr')>Français (France)</option>
                            <option value="en" @selected($lang==='en')>English</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                        <input type="text" name="country" value="{{ old('country', $company->country ?? 'Burkina Faso') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                        <input type="text" name="city" value="{{ old('city', $company->city) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuseau horaire</label>
                        @php $tz = old('timezone', $company->timezone ?? 'GMT'); @endphp
                        <select name="timezone" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value="GMT" @selected($tz==='GMT')>(GMT) Afrique de l'Ouest &mdash; Ouagadougou / Dakar</option>
                            <option value="GMT+1" @selected($tz==='GMT+1')>(GMT+1) Afrique centrale</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date d'ouverture</label>
                        <input type="date" name="opened_at" value="{{ old('opened_at', optional($company->opened_at)->format('Y-m-d')) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                        @php $cst = old('status', $company->status ?? 'active'); @endphp
                        <select name="status" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 {{ $cst === 'active' ? 'text-emerald-700 font-semibold' : '' }}">
                            <option value="active" @selected($cst==='active')>Active</option>
                            <option value="inactive" @selected($cst==='inactive')>Inactive</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email principal</label>
                        <input type="email" name="email" value="{{ old('email', $company->email) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="text" name="phone" value="{{ old('phone', $company->phone) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
                        <input type="url" name="website" value="{{ old('website', $company->website) }}" placeholder="https://www.oametal.bf"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
            </div>

            {{-- [Maquette] Commentaire --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                <textarea name="notes" rows="2" maxlength="1000"
                          placeholder="Société spécialisée dans la fabrication et la construction métallique…"
                          class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">{{ old('notes', $company->notes) }}</textarea>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
            @foreach([
                ['key' => 'adresse',      'label' => 'Adresse'],
                ['key' => 'fiscalite',    'label' => 'Fiscalité'],
                ['key' => 'banques',      'label' => 'Banques'],
                ['key' => 'documents',    'label' => 'Documents'],
                ['key' => 'numerotation', 'label' => 'Numérotation'],
                ['key' => 'branding',     'label' => 'Branding'],
                ['key' => 'options',      'label' => 'Paramètres avancés'],
                ['key' => 'suivi',        'label' => 'Suivi'],
            ] as $t)
            <button @click="tab = '{{ $t['key'] }}'; document.getElementById('sec-{{ $t['key'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    :class="tab === '{{ $t['key'] }}' ? 'border-emerald-600 text-emerald-800 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="whitespace-nowrap py-3 px-1 border-b-2 text-sm transition-colors">
                {{ $t['label'] }}
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══════════ Grille de cartes [Maquette] — rangée 1 ═══════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
    {{-- ═══════════ Tab: Adresse et contacts [Maquette] ═══════════ --}}
    <div id="sec-adresse" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-4">
        <form action="{{ route('company.update.general') }}" method="POST" data-turbo="false" class="p-6 space-y-3">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="adresse">
            <input type="hidden" name="name" value="{{ $company->name }}">

            <h2 class="text-base font-semibold text-gray-900">Adresse et contacts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse complète</label>
                    <textarea name="address" rows="2" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">{{ old('address', $company->address) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secteur / Quartier</label>
                    <input type="text" name="district" maxlength="80" value="{{ old('district', $company->district) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code postal</label>
                    <input type="text" name="postal_code" maxlength="20" value="{{ old('postal_code', $company->postal_code) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">BP</label>
                    <input type="text" name="po_box" maxlength="20" value="{{ old('po_box', $company->po_box) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact principal</label>
                    <input type="text" name="main_contact" maxlength="100" value="{{ old('main_contact', $company->main_contact) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email comptable</label>
                    <input type="email" name="accounting_email" maxlength="120" value="{{ old('accounting_email', $company->accounting_email) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone secondaire</label>
                    <input type="text" name="phone2" value="{{ old('phone2', $company->phone2) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom commercial</label>
                    <input type="text" name="trade_name" value="{{ old('trade_name', $company->trade_name) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slogan</label>
                    <input type="text" name="slogan" value="{{ old('slogan', $company->slogan) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>

    {{-- ═══════════ Tab: Fiscalité & obligations [Maquette] ═══════════ --}}
    <div id="sec-fiscalite" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-4">
        <form action="{{ route('company.update.general') }}" method="POST" data-turbo="false" class="p-6 space-y-3">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="fiscalite">
            {{-- updateGeneral requiert name --}}
            <input type="hidden" name="name" value="{{ $company->name }}">

            <h2 class="text-base font-semibold text-gray-900">Fiscalité et obligations</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Régime fiscal</label>
                    @php $fr = old('fiscal_regime', $company->fiscal_regime ?? 'reel_normal'); @endphp
                    <select name="fiscal_regime" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="reel_normal" @selected($fr==='reel_normal')>Régime réel normal</option>
                        <option value="reel_simplifie" @selected($fr==='reel_simplifie')>Régime réel simplifié</option>
                        <option value="cme" @selected($fr==='cme')>Contribution micro-entreprise</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TVA par défaut</label>
                    @php $vm = old('vat_mode', $company->vat_mode ?? 'collectee'); @endphp
                    <select name="vat_mode" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="collectee" @selected($vm==='collectee')>Collectée</option>
                        <option value="exoneree" @selected($vm==='exoneree')>Exonérée</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Taux TVA (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="default_vat_rate" value="{{ old('default_vat_rate', $company->default_vat_rate ?? 18) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm text-right font-mono focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Centre des impôts</label>
                    <input type="text" name="tax_center" maxlength="80" value="{{ old('tax_center', $company->tax_center) }}"
                           placeholder="Ouagadougou - Centre"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Régime retenue</label>
                    @php $wr = old('withholding_regime', $company->withholding_regime ?? 'source_tva'); @endphp
                    <select name="withholding_regime" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="source_tva" @selected($wr==='source_tva')>À la source / TVA</option>
                        <option value="aucun" @selected($wr==='aucun')>Aucun</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nature contribuable</label>
                    @php $tt = old('taxpayer_type', $company->taxpayer_type ?? 'personne_morale'); @endphp
                    <select name="taxpayer_type" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="personne_morale" @selected($tt==='personne_morale')>Personne morale</option>
                        <option value="personne_physique" @selected($tt==='personne_physique')>Personne physique</option>
                    </select>
                </div>
            </div>

            <p class="text-xs text-gray-400">Exercice fiscal en cours : géré dans Paramètres &rarr; <a href="{{ route('settings.fiscal-years.index') }}" class="text-emerald-700 font-medium hover:underline">Exercices fiscaux</a>. Adresse et contacts : onglet Adresse.</p>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>

    {{-- ═══════════ Carte: Coordonnées bancaires [Maquette] ═══════════ --}}
    <div id="sec-banques" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-4">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Coordonnées bancaires</h2>
            <button type="button" @click="document.getElementById('sec-banque-complet')?.scrollIntoView({ behavior: 'smooth' })"
                    class="text-xs text-emerald-700 font-semibold hover:underline">+ Ajouter un compte</button>
        </div>
        <div class="p-3 overflow-x-auto">
            @php $accounts = $company->bankAccounts ?? collect(); @endphp
            @if($accounts->isNotEmpty())
            <table class="w-full text-[12.5px]">
                <thead><tr class="bg-[#eef5f0] text-emerald-900">
                    <th class="text-center font-bold px-1.5 py-1.5 border-b border-gray-300 w-7">#</th>
                    <th class="text-left font-bold px-1.5 py-1.5 border-b border-gray-300">Banque</th>
                    <th class="text-left font-bold px-1.5 py-1.5 border-b border-gray-300">Intitulé</th>
                    <th class="text-left font-bold px-1.5 py-1.5 border-b border-gray-300">IBAN / Compte</th>
                    <th class="text-left font-bold px-1.5 py-1.5 border-b border-gray-300">SWIFT</th>
                    <th class="text-center font-bold px-1.5 py-1.5 border-b border-gray-300">Principal</th>
                    <th class="text-center font-bold px-1.5 py-1.5 border-b border-gray-300">Statut</th>
                </tr></thead>
                <tbody>
                    @foreach($accounts as $acc)
                    <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                        <td class="px-1.5 py-2 text-center text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                        <td class="px-1.5 py-2 font-semibold text-gray-700">{{ $acc->bank_name }}</td>
                        <td class="px-1.5 py-2 text-gray-600">{{ $acc->account_holder }}</td>
                        <td class="px-1.5 py-2 font-mono text-[11px] text-gray-600 whitespace-nowrap">{{ $acc->iban ?: $acc->account_number }}</td>
                        <td class="px-1.5 py-2 font-mono text-[11.5px] text-gray-600">{{ $acc->swift_bic ?: '—' }}</td>
                        <td class="px-1.5 py-2 text-center {{ $acc->is_default ? 'text-emerald-600' : 'text-gray-300' }}">{{ $acc->is_default ? '★' : '☆' }}</td>
                        <td class="px-1.5 py-2 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $acc->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $acc->is_active ? 'Actif' : 'Inactif' }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-[13px] text-gray-400 px-2 py-3">Aucun compte bancaire — utilisez « + Ajouter un compte ».</p>
            @endif
        </div>
    </div>

    </div>

    {{-- ═══════════ Grille de cartes [Maquette] — rangée 2 ═══════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
    {{-- ═══════════ Tab: Numérotation des documents [Maquette] ═══════════ --}}
    <div id="sec-numerotation" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-3 overflow-hidden">
        <div class="flex items-center justify-between gap-2 px-3 py-1.5 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Numérotation des documents</h2>
            <a href="{{ route('settings.sequences.index') }}" class="text-[13px] text-emerald-700 font-medium hover:underline whitespace-nowrap">Gérer →</a>
        </div>
        <div class="max-h-[420px] overflow-y-auto">
            @if(($sequences ?? collect())->isNotEmpty())
            <table class="w-full text-[11.5px]">
                <thead class="sticky top-0"><tr class="bg-[#eef5f0] text-emerald-900">
                    <th class="text-left font-bold px-1 py-1.5 border-b border-gray-300">Document</th>
                    <th class="text-left font-bold px-1 py-1.5 border-b border-gray-300">Préfixe</th>
                    <th class="text-right font-bold px-1 py-1.5 border-b border-gray-300">N°</th>
                    <th class="text-left font-bold px-1 py-1.5 border-b border-gray-300">Format</th>
                    <th class="text-center font-bold px-1 py-1.5 border-b border-gray-300">Auto</th>
                </tr></thead>
                <tbody>
                    @foreach($sequences as $seq)
                    <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                        <td class="px-1 py-1.5 text-gray-700 truncate max-w-[92px]">{{ ucfirst(str_replace('_', ' ', $seq->document_type)) }}</td>
                        <td class="px-1 py-1.5 font-mono text-emerald-800">{{ $seq->prefix }}</td>
                        <td class="px-1 py-1.5 text-right font-mono tabular-nums text-gray-600">{{ str_pad((string) $seq->last_number, max(1, (int) $seq->padding), '0', STR_PAD_LEFT) }}</td>
                        <td class="px-1 py-1.5 font-mono text-[10px] text-gray-500 whitespace-nowrap">{{ rtrim($seq->prefix, $seq->year_separator ?: '-') }}{{ $seq->include_year ? ($seq->year_separator ?: '-').date($seq->year_format === 'yy' ? 'y' : 'Y') : '' }}{{ $seq->year_separator ?: '-' }}{{ str_repeat('#', max(1, (int) $seq->padding)) }}</td>
                        <td class="px-1 py-1.5 text-center">
                            <span class="relative inline-block w-7 h-4 align-middle rounded-full {{ $seq->numbering_mode !== 'manuel' ? 'bg-emerald-600' : 'bg-gray-300' }}" title="{{ $seq->numbering_mode }}"><span class="absolute top-0.5 w-3 h-3 bg-white rounded-full shadow {{ $seq->numbering_mode !== 'manuel' ? 'right-0.5' : 'left-0.5' }}"></span></span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-sm text-gray-400 p-4">Aucune séquence configurée — <a href="{{ route('settings.sequences.index') }}" class="text-emerald-700 font-medium hover:underline">créer la numérotation</a>.</p>
            @endif
        </div>
    </div>

    {{-- ═══════════ Carte: Branding & documents [Maquette] ═══════════ --}}
    <div id="sec-branding" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-3">
        <div class="px-3 py-1.5 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Branding & documents</h2>
        </div>
        <form action="{{ route('company.update.documents') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="p-3">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="branding">
            @php $dsB = $company->documentSetting; @endphp
            <div class="grid grid-cols-3 gap-2">
                <div class="border border-gray-200 rounded-[4px] p-2 text-center flex flex-col">
                    <p class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wide">Logo</p>
                    <div class="flex-1 flex items-center justify-center py-2 min-h-[52px]">
                        @if($company->logo)<img src="{{ url(Storage::url($company->logo)) }}" class="max-h-10 object-contain">@else<span class="text-[12px] text-gray-300">—</span>@endif
                    </div>
                    <button type="button" @click="window.scrollTo({top:0, behavior:'smooth'})" class="text-[12px] text-emerald-700 font-semibold hover:underline">Remplacer</button>
                </div>
                <div class="border border-gray-200 rounded-[4px] p-2 text-center flex flex-col">
                    <p class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wide">Cachet</p>
                    <div class="flex-1 flex items-center justify-center py-2 min-h-[52px]">
                        @if($dsB?->stamp_image)<img src="{{ url(Storage::url($dsB->stamp_image)) }}" class="max-h-10 object-contain">@else<span class="text-[12px] text-gray-300">—</span>@endif
                    </div>
                    <label class="text-[12px] text-emerald-700 font-semibold hover:underline cursor-pointer">Remplacer<input type="file" name="stamp_image" accept="image/*" class="hidden" onchange="this.form.requestSubmit()"></label>
                </div>
                <div class="border border-gray-200 rounded-[4px] p-2 text-center flex flex-col">
                    <p class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wide">Signature DG</p>
                    <div class="flex-1 flex items-center justify-center py-2 min-h-[52px]">
                        @if($dsB?->signature_image)<img src="{{ url(Storage::url($dsB->signature_image)) }}" class="max-h-10 object-contain">@else<span class="text-[12px] text-gray-300">—</span>@endif
                    </div>
                    <label class="text-[12px] text-emerald-700 font-semibold hover:underline cursor-pointer">Remplacer<input type="file" name="signature_image" accept="image/*" class="hidden" onchange="this.form.requestSubmit()"></label>
                </div>
                <div class="border border-gray-200 rounded-[4px] p-2 text-center flex flex-col">
                    <p class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wide">En-tête PDF</p>
                    <div class="flex-1 flex items-center justify-center py-2 min-h-[52px]">
                        <span class="text-[11.5px] truncate max-w-full {{ $company->pdf_header_path ? 'text-emerald-700 font-medium' : 'text-gray-300' }}">{{ $company->pdf_header_path ? basename($company->pdf_header_path) : '—' }}</span>
                    </div>
                    <label class="text-[12px] text-emerald-700 font-semibold hover:underline cursor-pointer">{{ $company->pdf_header_path ? 'Remplacer' : 'Ajouter' }}<input type="file" name="pdf_header_file" accept=".pdf,.png,.jpg,.jpeg" class="hidden" onchange="this.form.requestSubmit()"></label>
                </div>
                <div class="border border-gray-200 rounded-[4px] p-2 text-center flex flex-col">
                    <p class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wide">Pied de page PDF</p>
                    <div class="flex-1 flex items-center justify-center py-2 min-h-[52px]">
                        <span class="text-[11.5px] truncate max-w-full {{ $company->pdf_footer_path ? 'text-emerald-700 font-medium' : 'text-gray-300' }}">{{ $company->pdf_footer_path ? basename($company->pdf_footer_path) : '—' }}</span>
                    </div>
                    <label class="text-[12px] text-emerald-700 font-semibold hover:underline cursor-pointer">{{ $company->pdf_footer_path ? 'Remplacer' : 'Ajouter' }}<input type="file" name="pdf_footer_file" accept=".pdf,.png,.jpg,.jpeg" class="hidden" onchange="this.form.requestSubmit()"></label>
                </div>
            </div>
            <p class="text-[11.5px] text-gray-400 mt-2">Formats acceptés : PNG, JPG, PDF — 2 Mo max. par fichier.</p>
        </form>
    </div>

    {{-- ═══════════ Tab: Paramètres avancés / Options globales [Maquette] ═══════════ --}}
    <div id="sec-options" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-4">
        <div class="px-3 py-1.5 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Options globales</h2>
        </div>
        <form action="{{ route('company.update.general') }}" method="POST" data-turbo="false">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="options">
            <input type="hidden" name="name" value="{{ $company->name }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-4 px-1 py-1">
                @foreach([
                    'multi_sites'          => 'Multi-sites',
                    'vat_management'       => 'Gestion TVA',
                    'validation_workflow'  => 'Validation workflow',
                    'electronic_signature' => 'Signature électronique',
                    'auto_pdf_print'       => 'Impression auto PDF',
                    'email_notifications'  => 'Notifications email',
                    'secondary_currency'   => 'Devise secondaire',
                    'maintenance_mode'     => 'Mode maintenance',
                ] as $opt => $optLbl)
                <label class="flex items-center justify-between gap-3 cursor-pointer px-3 py-2 rounded-[3px] hover:bg-gray-50">
                    <span class="text-[12.5px] font-medium text-gray-700 truncate">{{ $optLbl }}</span>
                    <input type="hidden" name="{{ $opt }}" value="0">
                    <input type="checkbox" name="{{ $opt }}" value="1" {{ old($opt, $company->{$opt}) ? 'checked' : '' }} class="sr-only peer">
                    <span class="relative w-8 h-[18px] flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[14px] after:h-[14px] after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-[14px]"></span>
                </label>
                @endforeach
            </div>

            <div class="flex items-center justify-between gap-3 px-3 py-1.5 border-t border-gray-200 bg-gray-50/60">
                <p class="text-[11.5px] text-gray-400 leading-tight">Le mode maintenance bloque l'accès aux utilisateurs non administrateurs.</p>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-4 py-1.5 rounded-full transition-colors whitespace-nowrap">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    {{-- ═══════════ Tab: Suivi [Maquette] ═══════════ --}}
    <div id="sec-suivi" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24 xl:col-span-2">
        <div class="px-3 py-1.5 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Suivi</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <div class="px-3 py-2.5">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Statut</p>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[12px] font-semibold {{ ($company->status ?? 'active') === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($company->status ?? 'Active') }}</span>
            </div>
            <div class="px-3 py-2.5">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Créée par</p>
                <p class="text-[13px] text-gray-700">{{ auth()->user()->name }}</p>
            </div>
            <div class="px-3 py-2.5">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Date de création</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $company->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div class="px-3 py-2.5">
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Dernière modification</p>
                <p class="text-[13px] text-gray-700 font-mono tabular-nums">{{ $company->updated_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
        </div>
    </div>
    </div>

    {{-- ═══════════ Sections détaillées (pleine largeur) ═══════════ --}}
    <!-- Tab: Légal -->
    <div id="sec-legal" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24">
        <form action="{{ route('company.update.legal') }}" method="POST" class="p-6 space-y-3">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="legal">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forme juridique</label>
                    <select name="legal_form" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        <option value="">-- Sélectionner --</option>
                        @foreach(['SARL', 'SA', 'SAS', 'EI', 'SUARL', 'GIE', 'Association'] as $form)
                        <option value="{{ $form }}" {{ old('legal_form', $company->legal_form) === $form ? 'selected' : '' }}>{{ $form }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RCCM</label>
                    <input type="text" name="rccm" value="{{ old('rccm', $company->rccm) }}" placeholder="BF-OUA-2020-B-12345"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IFU / Numéro fiscal</label>
                    <input type="text" name="ifu" value="{{ old('ifu', $company->ifu) }}" placeholder="00123456789"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIF</label>
                    <input type="text" name="nif" value="{{ old('nif', $company->nif) }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capital social (FCFA)</label>
                    <input type="number" name="share_capital" value="{{ old('share_capital', $company->share_capital) }}" min="0" step="100000"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assujetti à la TVA</label>
                    <div class="flex items-center gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="is_vat_subject" value="1" {{ old('is_vat_subject', $company->is_vat_subject) ? 'checked' : '' }}
                                   class="text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm">Oui</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="is_vat_subject" value="0" {{ !old('is_vat_subject', $company->is_vat_subject) ? 'checked' : '' }}
                                   class="text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm">Non</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Taux TVA par défaut (%)</label>
                    <input type="number" name="vat_number" value="{{ old('vat_number', $company->vat_number ?? 18) }}" min="0" max="100" step="0.5"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Documents -->
    <div id="sec-documents" class="bg-white rounded-[4px] border border-gray-300 scroll-mt-24">
        <form action="{{ route('company.update.documents') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="p-6 space-y-3">
            @csrf @method('PUT')
            @php $ds = $company->documentSetting; @endphp
            <input type="hidden" name="_tab" value="documents">

            {{-- Mise en page --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Mise en page</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div x-data="{ color: '{{ old('primary_color', $ds?->primary_color ?? '#1e40af') }}' }">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Couleur principale</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" x-model="color"
                                   class="w-12 h-10 border border-gray-300 rounded cursor-pointer">
                            <input type="text" x-model="color" placeholder="#1e40af"
                                   class="flex-1 border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Police</label>
                        <select name="font_family" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            @foreach(['DejaVu Sans' => 'DejaVu Sans (défaut)', 'DejaVu Serif' => 'DejaVu Serif', 'Helvetica' => 'Helvetica', 'Times New Roman' => 'Times New Roman'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('font_family', $ds?->font_family ?? 'DejaVu Sans') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format page</label>
                        <select name="page_size" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            @foreach(['A4', 'A5', 'Letter'] as $size)
                            <option value="{{ $size }}" {{ old('page_size', $ds?->page_size ?? 'A4') === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Orientation</label>
                        <select name="orientation" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                            <option value="portrait" {{ old('orientation', $ds?->orientation ?? 'portrait') === 'portrait' ? 'selected' : '' }}>Portrait</option>
                            <option value="landscape" {{ old('orientation', $ds?->orientation) === 'landscape' ? 'selected' : '' }}>Paysage</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input type="hidden" name="show_logo" value="0">
                        <input type="checkbox" id="show_logo" name="show_logo" value="1" {{ old('show_logo', $ds?->show_logo ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <label for="show_logo" class="text-sm font-medium text-gray-700 cursor-pointer">Afficher le logo</label>
                    </div>

                    <div x-data="{ wm: {{ old('show_watermark', $ds?->show_watermark ?? false) ? 'true' : 'false' }} }" class="flex flex-col gap-2">
                        <div class="flex items-center gap-3 pt-6">
                            <input type="hidden" name="show_watermark" value="0">
                            <input type="checkbox" id="show_watermark" name="show_watermark" value="1" x-model="wm"
                                   class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                            <label for="show_watermark" class="text-sm font-medium text-gray-700 cursor-pointer">Afficher un filigrane</label>
                        </div>
                        <input type="text" name="watermark_text" x-show="wm" value="{{ old('watermark_text', $ds?->watermark_text ?? 'CONFIDENTIEL') }}"
                               placeholder="Texte du filigrane"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            {{-- Colonnes affichées sur les documents --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Colonnes affichées sur les documents</h3>
                @php
                    $defaultCols = ['reference','description','quantity','unit_price','discount','tax','total_ht','total_ttc'];
                    $savedCols   = old('product_columns', $ds?->product_columns ?? $defaultCols);
                    $colLabels   = [
                        'reference'   => 'Référence',
                        'description' => 'Description',
                        'longueur'    => 'Longueur',
                        'epaisseur'   => 'Épaisseur',
                        'quantity'    => 'Quantité',
                        'unit_price'  => 'Prix unitaire',
                        'discount'    => 'Remise %',
                        'tax'         => 'TVA %',
                        'total_ht'    => 'Total HT',
                        'total_ttc'   => 'Total TTC',
                    ];
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($colLabels as $colKey => $colLabel)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="product_columns[]" value="{{ $colKey }}"
                               {{ in_array($colKey, (array)$savedCols) ? 'checked' : '' }}
                               class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
                        <span class="text-sm text-gray-700">{{ $colLabel }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Textes --}}
            <div class="border-t border-gray-100 pt-5 grid grid-cols-1 gap-4">
                <h3 class="text-sm font-semibold text-gray-700">Textes</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pied de page personnalisé</label>
                    <textarea name="footer_text" rows="2"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">{{ old('footer_text', $ds?->footer_text) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conditions générales de vente (CGV)</label>
                    <textarea name="terms_conditions" rows="4"
                              class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">{{ old('terms_conditions', $ds?->terms_conditions) }}</textarea>
                </div>
            </div>

            {{-- Signature & cachet --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Signature & cachet</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom signataire</label>
                        <input type="text" name="signature_name" value="{{ old('signature_name', $ds?->signature_name) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titre du signataire</label>
                        <input type="text" name="signature_title" value="{{ old('signature_title', $ds?->signature_title) }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image de signature</label>
                        <input type="file" name="signature_image" accept="image/*"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded">
                        @if($ds?->signature_image)
                        <p class="text-xs text-gray-400 mt-1">Signature existante — laisser vide pour conserver</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cachet (tampon)</label>
                        <input type="file" name="stamp_image" accept="image/*"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-emerald-50 file:text-emerald-700 file:rounded">
                        @if($ds?->stamp_image)
                        <p class="text-xs text-gray-400 mt-1">Cachet existant — laisser vide pour conserver</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px] transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Banque -->
    <div id="sec-banque-complet" x-data="{ showForm: false, editId: null, editData: {} }" class="space-y-4 scroll-mt-24">

        <!-- Add button -->
        <div class="flex justify-end">
            <button @click="showForm = true; editId = null; editData = {}"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter un compte
            </button>
        </div>

        <!-- Form -->
        <div x-show="showForm" x-cloak class="bg-white rounded-[4px] border border-emerald-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4" x-text="editId ? 'Modifier le compte' : 'Nouveau compte bancaire'"></h3>
            {{--
                URL : on utilise toujours route() côté Blade (respecte le base path /iboa/public).
                Pour l'update on injecte editId dans l'URL via x-bind:action.
                Le _method=PUT est dans un <template x-if> → retiré du DOM en mode création (sinon Laravel l'interprète toujours).
            --}}
            <form x-bind:action="editId
                    ? '{{ url('parametrage/banque') }}/' + editId
                    : '{{ route('company.bank.store') }}'"
                  method="POST" data-turbo="false"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <template x-if="editId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banque <span class="text-red-500">*</span></label>
                    <input type="text" name="bank_name" :value="editData.bank_name" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titulaire <span class="text-red-500">*</span></label>
                    <input type="text" name="account_holder" :value="editData.account_holder" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de compte <span class="text-red-500">*</span></label>
                    <input type="text" name="account_number" :value="editData.account_number" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agence</label>
                    <input type="text" name="branch" :value="editData.branch"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                    <input type="text" name="iban" :value="editData.iban"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SWIFT/BIC</label>
                    <input type="text" name="swift_bic" :value="editData.swift_bic"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="flex items-center gap-3">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" id="is_default" name="is_default" value="1" :checked="editData.is_default"
                           class="w-4 h-4 text-emerald-600 rounded">
                    <label for="is_default" class="text-sm font-medium text-gray-700">Compte principal</label>
                </div>
                {{-- [PONT BANCAIRE] Crée le compte de trésorerie opérationnel associé --}}
                <div class="md:col-span-2 flex items-start gap-3 bg-[#eef5f0] border border-emerald-100 rounded-[4px] p-3">
                    <input type="hidden" name="sync_treasury" value="0">
                    <input type="checkbox" id="sync_treasury" name="sync_treasury" value="1" :checked="editData.cash_account_id"
                           class="w-4 h-4 mt-0.5 text-emerald-700 rounded">
                    <label for="sync_treasury" class="text-sm text-gray-700">
                        <span class="font-medium text-emerald-800">Créer le compte de trésorerie associé</span><br>
                        <span class="text-xs text-gray-500">Rend ce compte opérationnel (rapprochement bancaire, soldes, transactions) — évite la double saisie.</span>
                    </label>
                </div>
                <div class="md:col-span-2 flex gap-3 justify-end">
                    <button type="button" @click="showForm = false"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px] hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px]">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing accounts list -->
        @if($company->bankAccounts->isEmpty())
        <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center text-gray-400 text-sm">
            Aucun compte bancaire enregistré.
        </div>
        @else
        <div class="space-y-3">
            @foreach($company->bankAccounts as $account)
            <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-gray-900 text-sm">{{ $account->bank_name }}</span>
                        @if($account->is_default)
                        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full font-medium">Principal</span>
                        @endif
                        @unless($account->is_active)
                        <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">Inactif</span>
                        @endunless
                        @if($account->cash_account_id)
                        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full font-medium" title="Compte de trésorerie opérationnel lié">⇄ Trésorerie</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600">{{ $account->account_holder }} — {{ $account->account_number }}</p>
                    @if($account->branch)<p class="text-xs text-gray-400">{{ $account->branch }}</p>@endif
                    @if($account->iban)<p class="text-xs text-gray-400 font-mono">IBAN: {{ $account->iban }}</p>@endif
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="showForm = true; editId = {{ $account->id }}; editData = {{ $account->toJson() }}"
                            class="text-gray-400 hover:text-emerald-700 p-1.5 rounded hover:bg-emerald-50 transition-colors" title="Modifier">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <form action="{{ route('company.bank.destroy', $account) }}" method="POST" onsubmit="return confirm('Supprimer ce compte ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-gray-400 hover:text-red-600 p-1.5 rounded hover:bg-red-50 transition-colors" title="Supprimer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
