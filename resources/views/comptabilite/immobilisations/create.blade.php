@extends('layouts.erp')
@section('title', 'Nouvelle immobilisation')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.immobilisations.index') }}" class="hover:text-gray-700">Immobilisations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle immobilisation</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'text-[13px] font-bold text-emerald-700 mb-3';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $panel = 'bg-white border border-gray-200 rounded-[4px] p-4';
    $company = currentCompany();
@endphp

<div class="max-w-[1500px]" x-data="assetForm()">

    <form method="POST" action="{{ route('comptabilite.immobilisations.store') }}"
          x-ref="form" @submit="submitting = true" class="space-y-3">
        @csrf

        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Nouvelle immobilisation</h1>
            <div class="flex items-center gap-1.5">
                <button type="submit" :disabled="submitting"
                        class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-5 py-2 rounded-[4px] transition-colors">
                    <span x-text="submitting ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
                <button type="button" onclick="window.print()"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
                <a href="{{ route('comptabilite.immobilisations.index') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Annuler</a>
            </div>
        </div>

        {{-- Onglets-ancres --}}
        <nav class="flex items-stretch border-b border-gray-200 gap-1 -mt-1">
            @foreach(['entete' => 'En-tête', 'compta' => 'Comptabilité', 'amort' => 'Amortissement', 'affect' => 'Affectation', 'docs' => 'Documents'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'; $refs['sec_{{ $tk }}']?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="px-3 py-2 text-[14px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        <x-validation-errors />
        @if(session('error'))
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded-[4px] text-[13px]">{{ session('error') }}</div>
        @endif

        {{-- ═══ Rangée 1 : 1. Identification | 2. Valeurs financières ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section x-ref="sec_entete" class="{{ $panel }} xl:col-span-7 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">1.</span> Identification générale</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                        <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                        <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Code immobilisation <span class="text-red-500">*</span></label>
                        <input type="text" value="Auto à la création" class="{{ $inpRo }} font-mono text-[12px]" readonly>
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Désignation <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                               placeholder="Machine de découpe laser fibre 3kW" class="{{ $inp }}">
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Famille <span class="text-red-500">*</span></label>
                        <input type="text" name="famille" maxlength="30" value="{{ old('famille', 'MACH') }}" placeholder="MACH" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Machines de production</p>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Catégorie <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="category" required class="{{ $lk }}" x-model="category" @change="applyCategoryDefaults()">
                                @foreach($categoryLabels as $v => $l)
                                <option value="{{ $v }}" @selected(old('category') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Type</label>
                        <input type="text" name="asset_type" maxlength="30" value="{{ old('asset_type', 'MAT-PROD') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Matériel de production</p>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Numéro de série</label>
                        <input type="text" name="serial_number" maxlength="60" value="{{ old('serial_number') }}" placeholder="LD-FIBER-3KW-2456" class="{{ $inp }} font-mono">
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Fournisseur</label>
                        <input type="text" name="vendor" maxlength="255" value="{{ old('vendor') }}" placeholder="LASER-DISTRIBUTION SARL" class="{{ $inp }}">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Réf. facture</label>
                        <input type="text" name="invoice_ref" maxlength="100" value="{{ old('invoice_ref') }}" placeholder="FACT-…" class="{{ $inp }} font-mono">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Date d'acquisition <span class="text-red-500">*</span></label>
                        <input type="date" name="acquisition_date" required value="{{ old('acquisition_date', date('Y-m-d')) }}" class="{{ $inp }}">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Mise en service <span class="text-red-500">*</span></label>
                        <input type="date" name="commissioning_date" required value="{{ old('commissioning_date', date('Y-m-d')) }}" x-model="miseEnService" class="{{ $inp }}">
                    </div>

                    <div class="col-span-12">
                        <label class="{{ $lbl }}">Description</label>
                        <textarea name="description" rows="2" maxlength="1000"
                                  class="w-full px-2 py-1.5 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('description') }}</textarea>
                    </div>
                </div>
            </section>

            <section x-ref="sec_compta" class="{{ $panel }} xl:col-span-5 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">2.</span> Valeurs financières &amp; comptabilité</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                        <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA</p>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Valeur d'acquisition <span class="text-red-500">*</span></label>
                        <input type="number" name="acquisition_cost" required min="1" step="1"
                               x-model.number="acquisition" value="{{ old('acquisition_cost') }}"
                               class="{{ $inp }} text-right tabular-nums">
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Frais accessoires</label>
                        <input type="number" name="accessory_cost" min="0" step="1"
                               x-model.number="frais" value="{{ old('accessory_cost', 0) }}"
                               class="{{ $inp }} text-right tabular-nums">
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Montant HT</label>
                        <input type="text" :value="fmt(montantHT)" class="{{ $inpRo }} text-right tabular-nums" readonly>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">TVA (18%)</label>
                        <input type="text" :value="fmt(tva)" class="{{ $inpRo }} text-right tabular-nums" readonly>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Valeur résiduelle</label>
                        <input type="number" name="residual_value" min="0" step="1"
                               x-model.number="residuelle" value="{{ old('residual_value', 0) }}"
                               class="{{ $inp }} text-right tabular-nums">
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Base amortissable</label>
                        <input type="text" :value="fmt(baseAmortissable)" class="{{ $inpRo }} text-right tabular-nums font-semibold" readonly>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Centre de coût <span class="text-red-500">*</span></label>
                        <input type="text" name="centre_analytique" maxlength="30" value="{{ old('centre_analytique', 'CC100') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Production</p>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Compte d'immobilisation <span class="text-red-500">*</span></label>
                        <input type="text" name="asset_account" required maxlength="10" x-model="cptImmo" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Matériel &amp; outillage</p>
                    </div>

                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Compte d'amortissement <span class="text-red-500">*</span></label>
                        <input type="text" name="depr_account" maxlength="10" x-model="cptAmort" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Amort. matériel</p>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Compte de dotation <span class="text-red-500">*</span></label>
                        <input type="text" name="charge_account" maxlength="10" x-model="cptDotation" class="{{ $inp }} font-mono">
                        <p class="text-[12px] text-gray-500 mt-0.5">Dotations aux amortissements</p>
                    </div>
                    <div class="col-span-4">
                        <label class="{{ $lbl }}">Statut</label>
                        <input type="text" value="Actif" class="{{ $inpRo }}" readonly>
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ Rangée 2 : 3. Amortissement + 5. Plan | 4. Affectation + 6. Docs ═══ --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-3 items-start">

            <section x-ref="sec_amort" class="{{ $panel }} xl:col-span-7 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">3.</span> Amortissement</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Mode <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="depreciation_method" required x-model="mode" class="{{ $lk }}">
                                <option value="lineaire">Linéaire</option>
                                <option value="degressif">Dégressif</option>
                            </select>{!! $caret !!}
                        </div>
                        <p class="text-[12px] text-gray-500 mt-0.5" x-text="mode === 'lineaire' ? 'Linéaire constant' : 'Dégressif fiscal'"></p>
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <label class="{{ $lbl }}">Durée <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <input type="number" name="useful_life_years" required min="1" max="99"
                                   x-model.number="duree"
                                   class="{{ $inp }} rounded-r-none text-right tabular-nums">
                            <span class="inline-flex items-center h-8 px-2 border border-l-0 border-gray-200 rounded-r-[3px] bg-gray-50 text-[12px] text-gray-500">ans</span>
                        </div>
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <label class="{{ $lbl }}">Taux</label>
                        <div class="flex">
                            <input type="text" :value="tauxPct" class="{{ $inpRo }} rounded-r-none text-right tabular-nums" readonly>
                            <span class="inline-flex items-center h-8 px-2 border border-l-0 border-gray-200 rounded-r-[3px] bg-gray-50 text-[12px] text-gray-500">%</span>
                        </div>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Périodicité <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="periodicity" class="{{ $lk }}">
                                @foreach(['mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'annuelle' => 'Annuelle'] as $pv => $pl)
                                <option value="{{ $pv }}" @selected(old('periodicity', 'mensuelle') === $pv)>{{ $pl }}</option>
                                @endforeach
                            </select>{!! $caret !!}
                        </div>
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <label class="{{ $lbl }}">Taux dégressif</label>
                        <input type="number" name="degressive_rate" min="0" max="99.99" step="0.01"
                               value="{{ old('degressive_rate', 0) }}" :disabled="mode !== 'degressif'"
                               class="{{ $inp }} text-right tabular-nums disabled:bg-gray-100 disabled:text-gray-400">
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Début amortissement</label>
                        <input type="text" :value="miseEnService || '—'" class="{{ $inpRo }} tabular-nums" readonly>
                        <p class="text-[12px] text-gray-500 mt-0.5">= date de mise en service</p>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Fin prévue</label>
                        <input type="text" :value="finPrevue" class="{{ $inpRo }} tabular-nums" readonly>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Dotation annuelle</label>
                        <input type="text" :value="fmt(dotationAnnuelle)" class="{{ $inpRo }} text-right tabular-nums font-semibold" readonly>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <label class="{{ $lbl }}">Dotation mensuelle</label>
                        <input type="text" :value="fmt(dotationMensuelle)" class="{{ $inpRo }} text-right tabular-nums" readonly>
                    </div>

                    <div class="col-span-12">
                        <label class="flex items-center gap-1.5 text-[12.5px] text-gray-700 cursor-pointer">
                            <input type="hidden" name="prorata_temporis" value="0">
                            <input type="checkbox" name="prorata_temporis" value="1" checked
                                   class="w-3.5 h-3.5 rounded border-gray-400 text-emerald-600 focus:ring-emerald-400">
                            Prorata temporis — première annuité au prorata de la date de mise en service
                        </label>
                    </div>
                </div>

                {{-- 5. Plan d'amortissement prévisionnel LIVE --}}
                <h2 class="{{ $secH }} mt-4"><span class="text-gray-400 font-normal">5.</span> Plan d'amortissement prévisionnel</h2>
                <div class="overflow-x-auto border border-gray-200 rounded-[4px]">
                    <table class="min-w-full text-[13px] border-collapse">
                        <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                            <tr>
                                <th class="px-3 py-1.5 text-left">Exercice</th>
                                <th class="px-3 py-1.5 text-right">Dotation</th>
                                <th class="px-3 py-1.5 text-right">Cumul</th>
                                <th class="px-3 py-1.5 text-right">VNC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in plan" :key="row.annee">
                                <tr class="odd:bg-white even:bg-gray-50/40">
                                    <td class="px-3 py-1 tabular-nums font-medium" x-text="row.annee"></td>
                                    <td class="px-3 py-1 text-right tabular-nums" x-text="fmt(row.dotation)"></td>
                                    <td class="px-3 py-1 text-right tabular-nums text-gray-600" x-text="fmt(row.cumul)"></td>
                                    <td class="px-3 py-1 text-right tabular-nums font-medium" x-text="fmt(row.vnc)"></td>
                                </tr>
                            </template>
                            <tr x-show="plan.length === 0">
                                <td colspan="4" class="px-3 py-4 text-center text-gray-400 text-[12px]">Saisissez valeur, durée et mise en service pour prévisualiser le plan.</td>
                            </tr>
                        </tbody>
                        <tfoot x-show="plan.length > 0">
                            <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold">
                                <td class="px-3 py-1.5 text-[11px] uppercase text-gray-500">Total</td>
                                <td class="px-3 py-1.5 text-right font-mono tabular-nums" x-text="fmt(baseAmortissable)"></td>
                                <td class="px-3 py-1.5 text-right font-mono tabular-nums" x-text="fmt(baseAmortissable)"></td>
                                <td class="px-3 py-1.5 text-right font-mono tabular-nums" x-text="fmt(residuelle || 0)"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="text-[12px] text-gray-500 mt-1.5">Prévisualisation indicative — le plan définitif est généré par le service comptable à l'enregistrement.</p>
            </section>

            <section x-ref="sec_affect" class="{{ $panel }} xl:col-span-5 scroll-mt-24">
                <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">4.</span> Affectation / Localisation</h2>
                <div class="grid grid-cols-12 gap-x-3 gap-y-3">
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Service</label>
                        <input type="text" name="service_code" maxlength="30" value="{{ old('service_code', 'PRD') }}" class="{{ $inp }} font-mono uppercase">
                        <p class="text-[12px] text-gray-500 mt-0.5">Production</p>
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Responsable</label>
                        <input type="text" name="responsable" maxlength="100" value="{{ old('responsable') }}" placeholder="DUPONT" class="{{ $inp }}">
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Utilisateur</label>
                        <input type="text" name="utilisateur" maxlength="100" value="{{ old('utilisateur') }}" placeholder="MARTIN" class="{{ $inp }}">
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Localisation</label>
                        <input type="text" name="localisation" maxlength="60" value="{{ old('localisation') }}" placeholder="USINE-OUAGA" class="{{ $inp }} font-mono uppercase">
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Bâtiment</label>
                        <input type="text" name="batiment" maxlength="60" value="{{ old('batiment') }}" placeholder="BAT-A" class="{{ $inp }} font-mono uppercase">
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Bureau / atelier</label>
                        <input type="text" name="bureau" maxlength="60" value="{{ old('bureau') }}" placeholder="AT-05" class="{{ $inp }} font-mono uppercase">
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Projet</label>
                        <input type="text" name="projet" maxlength="60" value="{{ old('projet') }}" class="{{ $inp }}">
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Code activité</label>
                        <input type="text" name="code_activite" maxlength="30" value="{{ old('code_activite') }}" class="{{ $inp }} font-mono uppercase">
                    </div>
                    <div class="col-span-6 sm:col-span-4">
                        <label class="{{ $lbl }}">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="{{ $inp }}">
                    </div>
                </div>

                {{-- 6. Documents --}}
                <div x-ref="sec_docs" class="mt-4 scroll-mt-24">
                    <h2 class="{{ $secH }}"><span class="text-gray-400 font-normal">6.</span> Documents / Pièces jointes</h2>
                    <p class="text-[12.5px] text-gray-500 bg-gray-50 border border-gray-200 rounded-[4px] px-3 py-2.5">
                        Les pièces (facture fournisseur, bon de livraison, fiche technique, photos, contrat de maintenance)
                        s'attachent sur la fiche de l'immobilisation après création.
                    </p>
                </div>
            </section>
        </div>

        {{-- ═══ Bandeau bas 5 zones LIVE (maquette) ═══ --}}
        <div class="bg-white border border-gray-200 rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-5 gap-3 items-center text-center">
            <div>
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur brute</p>
                <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight" x-text="fmt(valeurBrute) + ' XOF'"></p>
                <p class="text-[11px] text-gray-400">Acquisition + frais</p>
            </div>
            <div class="border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Cumul amortissements</p>
                <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight">0 XOF</p>
                <p class="text-[11px] text-gray-400">À ce jour</p>
            </div>
            <div class="border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur nette comptable</p>
                <p class="text-[17px] font-bold tabular-nums text-blue-800 leading-tight" x-text="fmt(valeurBrute) + ' XOF'"></p>
                <p class="text-[11px] text-gray-400" x-text="'Au ' + (miseEnService || '—')"></p>
            </div>
            <div class="border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Durée restante</p>
                <p class="text-[17px] font-bold tabular-nums text-emerald-700 leading-tight" x-text="(duree || 0) + ' ans'"></p>
                <p class="text-[11px] text-gray-400" x-text="'Fin prévue : ' + finPrevue"></p>
            </div>
            <div class="border-l border-gray-100">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Dotation annuelle</p>
                <p class="text-[17px] font-bold tabular-nums text-emerald-700 leading-tight" x-text="fmt(dotationAnnuelle) + ' XOF'"></p>
                <p class="text-[11px] text-gray-400" x-text="'Mensuelle : ' + fmt(dotationMensuelle) + ' XOF'"></p>
            </div>
        </div>

        {{-- Barre de contexte pied de page --}}
        <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
            <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
            <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
            <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">Aucun</span></span>
            <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
            <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
        </div>

    </form>
</div>

@push('scripts')
<script>
function assetForm() {
    return {
        tab: 'entete',
        submitting: false,
        category: '{{ old('category', 'materiel_industriel') }}',
        acquisition: {{ (int) old('acquisition_cost', 0) }},
        frais: {{ (int) old('accessory_cost', 0) }},
        residuelle: {{ (int) old('residual_value', 0) }},
        duree: {{ (int) old('useful_life_years', 5) }},
        mode: '{{ old('depreciation_method', 'lineaire') }}',
        miseEnService: '{{ old('commissioning_date', date('Y-m-d')) }}',
        cptImmo: '{{ old('asset_account', '241000') }}',
        cptAmort: '{{ old('depr_account', '284100') }}',
        cptDotation: '{{ old('charge_account', '681100') }}',
        categoryDefaults: @json($categoryDefaults ?? []),

        applyCategoryDefaults() {
            const d = this.categoryDefaults[this.category];
            if (!d) return;
            if (d.asset_account)  this.cptImmo = d.asset_account;
            if (d.depr_account)   this.cptAmort = d.depr_account;
            if (d.charge_account) this.cptDotation = d.charge_account;
            if (d.useful_life_years) this.duree = d.useful_life_years;
        },

        get valeurBrute()      { return (this.acquisition || 0) + (this.frais || 0); },
        get montantHT()        { return Math.round((this.acquisition || 0) / 1.18); },
        get tva()              { return (this.acquisition || 0) - this.montantHT; },
        get baseAmortissable() { return Math.max(0, this.valeurBrute - (this.residuelle || 0)); },
        get tauxPct()          { return this.duree > 0 ? (100 / this.duree).toFixed(2).replace('.', ',') : '—'; },
        get dotationAnnuelle() { return this.duree > 0 ? Math.round(this.baseAmortissable / this.duree) : 0; },
        get dotationMensuelle(){ return Math.round(this.dotationAnnuelle / 12); },
        get finPrevue() {
            if (!this.miseEnService || !this.duree) return '—';
            const d = new Date(this.miseEnService);
            d.setFullYear(d.getFullYear() + this.duree);
            d.setDate(d.getDate() - 1);
            return d.toLocaleDateString('fr-FR');
        },
        get plan() {
            if (!this.miseEnService || !this.duree || this.baseAmortissable <= 0) return [];
            const start = new Date(this.miseEnService);
            const y0 = start.getFullYear();
            const moisAn1 = 12 - start.getMonth();   // prorata 1re année
            const rows = []; let cumul = 0;
            for (let i = 0; i <= this.duree; i++) {
                let dot;
                if (i === 0)               dot = Math.round(this.dotationAnnuelle * moisAn1 / 12);
                else if (i === this.duree) dot = this.baseAmortissable - cumul;   // solde final
                else                       dot = this.dotationAnnuelle;
                if (dot <= 0) break;
                cumul += dot;
                rows.push({ annee: y0 + i, dotation: dot, cumul, vnc: this.valeurBrute - cumul });
                if (cumul >= this.baseAmortissable) break;
            }
            return rows;
        },
        fmt(v) { return new Intl.NumberFormat('fr-FR').format(Math.round(v || 0)); },
    };
}
</script>
@endpush
@endsection
