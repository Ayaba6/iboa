@extends('layouts.erp')
@section('title', 'Paramètres comptables')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramètres comptables</span>
@endsection

@section('content')
@php
    $lbl  = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $th   = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
    $sw   = "relative w-9 h-5 flex-shrink-0 bg-gray-200 peer-checked:bg-emerald-600 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:bg-white after:rounded-full after:shadow after:transition-transform peer-checked:after:translate-x-4";
    $s    = $settings;
    $acct = fn ($v, $default = '— Sélectionner —') => $accounts->firstWhere('id', $v)?->code . ($accounts->firstWhere('id', $v) ? ' — '.$accounts->firstWhere('id', $v)->name : '');
    // Comptes par défaut à afficher : [colonne, label]
    $defaultAccounts = [
        ['account_client_collectif', 'Compte client collectif'],
        ['account_fournisseur_collectif', 'Compte fournisseur collectif'],
        ['account_ventes', 'Compte ventes'],
        ['account_achats', 'Compte achats'],
        ['account_tva_collectee', 'Compte TVA collectée'],
        ['account_tva_deductible', 'Compte TVA déductible'],
        ['account_stock_mp', 'Compte stock matières premières'],
        ['account_stock_pf', 'Compte stock produits finis'],
        ['account_variation_stock', 'Compte variation de stock'],
        ['account_caisse', 'Compte caisse'],
        ['account_banque', 'Compte banque'],
    ];
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-emerald-900">Paramètres comptables</h1>
            <p class="text-[11.5px] text-gray-500">Référentiel {{ $s->referentiel }} — comptes par défaut, règles de comptabilisation, analytique</p>
        </div>
        <button type="submit" form="form-acc" class="text-[13px] font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-3 py-2.5 rounded-[4px] text-[13px]">✓ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2.5 rounded-[4px] text-[13px]">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form id="form-acc" method="POST" action="{{ route('comptabilite.parametres.update') }}" class="space-y-4">
        @csrf @method('PUT')

        {{-- Informations générales --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Informations générales</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Code paramètre</label>
                    <input type="text" name="code" maxlength="30" value="{{ old('code', $s->code ?? 'CPT-'.now()->format('Y').'-0001') }}" class="{{ $inp }} font-mono">
                </div>
                <div>
                    <label class="{{ $lbl }}">Référentiel comptable <span class="text-red-500">*</span></label>
                    <select name="referentiel" class="{{ $inp }}">
                        @foreach(['SYSCOHADA', 'SYSCOHADA révisé', 'IFRS', 'Autre'] as $ref)
                        <option value="{{ $ref }}" @selected(old('referentiel', $s->referentiel) === $ref)>{{ $ref }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Date d'effet</label>
                    <input type="date" name="effective_date" value="{{ old('effective_date', $s->effective_date?->toDateString() ?? now()->startOfYear()->toDateString()) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Statut</label>
                    <select name="status" class="{{ $inp }}">
                        @foreach(['brouillon' => 'Brouillon', 'actif' => 'Actif', 'archive' => 'Archivé'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('status', $s->status) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Société</label>
                    <input type="text" value="{{ $company?->name }}" disabled class="{{ $inp }} !bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label class="{{ $lbl }}">Régime fiscal</label>
                    <select name="regime_fiscal" class="{{ $inp }}">
                        <option value="">—</option>
                        @foreach(['Régime réel normal', 'Régime réel simplifié', 'Régime du forfait'] as $rf)
                        <option value="{{ $rf }}" @selected(old('regime_fiscal', $s->regime_fiscal) === $rf)>{{ $rf }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Plan comptable</label>
                    <input type="text" name="plan_comptable" maxlength="60" value="{{ old('plan_comptable', $s->plan_comptable) }}" class="{{ $inp }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Commentaire</label>
                    <input type="text" name="comment" maxlength="1000" value="{{ old('comment', $s->comment) }}" class="{{ $inp }}" placeholder="Paramétrage comptable principal.">
                </div>
                <div>
                    <label class="{{ $lbl }}">Exercice fiscal</label>
                    <select name="fiscal_year_id" class="{{ $inp }}">
                        <option value="">—</option>
                        @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected(old('fiscal_year_id', $s->fiscal_year_id) == $fy->id)>{{ $fy->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Devise de base</label>
                    <input type="text" name="base_currency" maxlength="10" value="{{ old('base_currency', $s->base_currency) }}" class="{{ $inp }} font-mono uppercase">
                </div>
            </div>
        </div>

        {{-- Comptes par défaut | Règles de comptabilisation --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden xl:col-span-2">
                <div class="{{ $secH }}">Comptes par défaut</div>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3">
                    @foreach($defaultAccounts as [$col, $label])
                    <div>
                        <label class="{{ $lbl }}">{{ $label }}</label>
                        <select name="{{ $col }}" class="{{ $inp }} font-mono text-[12px]">
                            <option value="">— Plan SYSCOHADA (auto) —</option>
                            @foreach($accounts as $a)
                            <option value="{{ $a->id }}" @selected(old($col, $s->{$col}) == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 px-4 pb-3">Non renseigné = compte SYSCOHADA standard résolu automatiquement par le moteur comptable.</p>
            </div>

            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="{{ $secH }}">Règles de comptabilisation</div>
                <div class="divide-y divide-gray-100">
                    @foreach([
                        'auto_ecriture_vente'         => 'Génération auto des écritures de vente',
                        'auto_ecriture_achat'         => 'Génération auto des écritures d\'achat',
                        'auto_comptabilisation_stock' => 'Comptabilisation automatique des stocks',
                        'validation_obligatoire'      => 'Validation obligatoire avant comptabilisation',
                        'interdire_periode_cloturee'  => 'Interdire les écritures sur période clôturée',
                        'lettrage_auto'               => 'Lettrage automatique',
                        'rapprochement_actif'         => 'Rapprochement bancaire activé',
                        'analytique_obligatoire'      => 'Analytique obligatoire',
                    ] as $key => $label)
                    <label class="flex items-center justify-between gap-3 cursor-pointer px-3 py-1.5 hover:bg-gray-50">
                        <span class="text-[12px] font-medium text-gray-700 leading-tight">{{ $label }}</span>
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" {{ old($key, $s->{$key}) ? 'checked' : '' }} class="sr-only peer">
                        <span class="{{ $sw }}"></span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Paramètres analytiques --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $secH }}">Paramètres analytiques</div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-x-5 gap-y-3">
                <div>
                    <label class="{{ $lbl }}">Section analytique obligatoire</label>
                    <select name="section_analytique_obligatoire" class="{{ $inp }}">
                        <option value="1" @selected(old('section_analytique_obligatoire', $s->section_analytique_obligatoire))>Oui</option>
                        <option value="0" @selected(! old('section_analytique_obligatoire', $s->section_analytique_obligatoire))>Non</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">Centre de coût par défaut</label>
                    <select name="centre_cout_defaut_id" class="{{ $inp }}">
                        <option value="">—</option>
                        @foreach($costCenters as $cc)
                        <option value="{{ $cc->id }}" @selected(old('centre_cout_defaut_id', $s->centre_cout_defaut_id) == $cc->id)>{{ $cc->code }} — {{ $cc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="{{ $lbl }}">Axe analytique 1</label><input type="text" name="axe_analytique_1" maxlength="40" value="{{ old('axe_analytique_1', $s->axe_analytique_1) }}" class="{{ $inp }}" placeholder="Production"></div>
                <div><label class="{{ $lbl }}">Axe analytique 2</label><input type="text" name="axe_analytique_2" maxlength="40" value="{{ old('axe_analytique_2', $s->axe_analytique_2) }}" class="{{ $inp }}" placeholder="Commercial"></div>
                <div><label class="{{ $lbl }}">Axe analytique 3</label><input type="text" name="axe_analytique_3" maxlength="40" value="{{ old('axe_analytique_3', $s->axe_analytique_3) }}" class="{{ $inp }}" placeholder="Chantier / Projet"></div>
            </div>
        </div>
    </form>

    {{-- Journaux comptables (lecture — écran dédié) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between {{ $secH }}">
            <span>Journaux comptables</span>
            <a href="{{ route('comptabilite.journal-types.index') }}" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case">Gérer →</a>
        </div>
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left w-24">Code journal</th>
                <th class="{{ $th }} text-left">Intitulé</th>
                <th class="{{ $th }} text-left w-28">Type</th>
                <th class="{{ $th }} text-center w-20">Actif</th>
            </tr></thead>
            <tbody>
                @forelse($journalTypes as $jt)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                    <td class="px-3 py-1.5 font-mono font-semibold text-emerald-800">{{ $jt->code }}</td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $jt->name }}</td>
                    <td class="px-3 py-1.5 text-gray-500">{{ ucfirst($jt->type) }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $jt->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $jt->is_active ? 'Actif' : 'Inactif' }}</span></td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucun journal — <a href="{{ route('comptabilite.journal-types.index') }}" class="text-emerald-700 hover:underline">créer</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Taxes et TVA (lecture — écran dédié) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between {{ $secH }}">
            <span>Taxes et TVA</span>
            <a href="{{ route('settings.tax-rates.index') }}" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case">Gérer →</a>
        </div>
        <table class="w-full text-[12.5px]">
            <thead><tr class="bg-[#eef5f0] border-b border-gray-300">
                <th class="{{ $th }} text-left w-28">Code</th>
                <th class="{{ $th }} text-left">Intitulé</th>
                <th class="{{ $th }} text-right w-24">Taux</th>
                <th class="{{ $th }} text-left w-28">Type</th>
                <th class="{{ $th }} text-center w-20">Actif</th>
            </tr></thead>
            <tbody>
                @forelse($taxRates as $tx)
                <tr class="border-b border-gray-100 last:border-0 odd:bg-white even:bg-gray-50/40">
                    <td class="px-3 py-1.5 font-mono font-semibold text-emerald-800">{{ $tx->short_name }}</td>
                    <td class="px-3 py-1.5 text-gray-700">{{ $tx->name }}</td>
                    <td class="px-3 py-1.5 text-right font-mono">{{ number_format((float) $tx->rate, 2, ',', '') }} %</td>
                    <td class="px-3 py-1.5 text-gray-500">{{ $tx->type === 'retenue' ? 'Retenue' : 'TVA' }}</td>
                    <td class="px-3 py-1.5 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tx->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $tx->is_active ? 'Actif' : 'Inactif' }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune taxe configurée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
