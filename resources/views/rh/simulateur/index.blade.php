@extends('layouts.erp')
@section('title', 'Simulateur de salaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">RH &ndash; Paie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Simulateur</span>
@endsection

@section('content')
<div x-data="simulateur()" class="max-w-6xl mx-auto space-y-3">

    {{-- En-tete --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900 flex items-center gap-2">
                <span>🧮</span> Simulateur de salaire inverse
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Saisissez le net souhaite &mdash; le simulateur calcule brut, cotisations et cout employeur.
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-[4px] px-3 py-2 flex-shrink-0">
            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            CNSS sal.&nbsp;<strong class="text-gray-600">{{ $payroll->cnss_employee_rate }}%</strong>
            &nbsp;&middot;&nbsp;
            CNSS pat.&nbsp;<strong class="text-gray-600">{{ $payroll->cnss_employer_rate }}%</strong>
            &nbsp;&middot;&nbsp;
            Abattement IUTS&nbsp;<strong class="text-gray-600">{{ $payroll->iuts_abattement_rate }}%</strong>
        </div>
    </div>

    {{-- ═══ GRILLE SALARIALE ════════════════════════════════════════════════ --}}
    <div x-data="{ grilleOpen: false }" class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <button @click="grilleOpen = !grilleOpen"
                class="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-gray-50 transition-colors">
            <span class="flex items-center gap-2.5 font-semibold text-gray-800 text-sm">
                <span class="text-base">📊</span>
                Grille salariale indicative &mdash; Burkina Faso
                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-blue-100 text-blue-700">
                    9 categories
                </span>
            </span>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                 :class="grilleOpen ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="grilleOpen" x-collapse x-cloak>
            <div class="px-5 pb-5 pt-1">
                <p class="text-xs text-gray-400 mb-4">Cliquez sur une categorie pour pre-remplir le simulateur avec le net median de la fourchette.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($grille as $cat)
                    @php
                        $colorMap = [
                            'gray'    => 'border-gray-200 hover:border-gray-400 hover:bg-gray-50',
                            'slate'   => 'border-slate-200 hover:border-slate-400 hover:bg-slate-50',
                            'blue'    => 'border-blue-200 hover:border-blue-400 hover:bg-blue-50',
                            'indigo'  => 'border-emerald-200 hover:border-indigo-400 hover:bg-emerald-50',
                            'violet'  => 'border-violet-200 hover:border-violet-400 hover:bg-violet-50',
                            'emerald' => 'border-emerald-200 hover:border-emerald-400 hover:bg-emerald-50',
                            'teal'    => 'border-emerald-200 hover:border-teal-400 hover:bg-emerald-50',
                            'orange'  => 'border-orange-200 hover:border-orange-400 hover:bg-orange-50',
                            'red'     => 'border-red-200 hover:border-red-400 hover:bg-red-50',
                        ];
                        $badgeMap = [
                            'gray'    => 'bg-gray-100 text-gray-600',
                            'slate'   => 'bg-slate-100 text-slate-600',
                            'blue'    => 'bg-blue-100 text-blue-700',
                            'indigo'  => 'bg-emerald-100 text-emerald-800',
                            'violet'  => 'bg-violet-100 text-violet-700',
                            'emerald' => 'bg-emerald-100 text-emerald-700',
                            'teal'    => 'bg-emerald-100 text-emerald-800',
                            'orange'  => 'bg-orange-100 text-orange-700',
                            'red'     => 'bg-red-100 text-red-700',
                        ];
                        $cls   = $colorMap[$cat['color']] ?? $colorMap['gray'];
                        $badge = $badgeMap[$cat['color']] ?? $badgeMap['gray'];
                    @endphp
                    <button
                        @click="selectCategorie({{ json_encode($cat) }}); grilleOpen = false"
                        class="text-left border-2 rounded-[4px] p-3.5 transition-all duration-150 cursor-pointer {{ $cls }}"
                        :class="selectedCat === '{{ $cat['code'] }}' ? 'ring-2 ring-offset-1 ring-blue-400' : ''"
                    >
                        <div class="flex items-start justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="text-lg leading-none">{{ $cat['icon'] }}</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold {{ $badge }}">
                                    {{ $cat['code'] }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-400 font-mono">
                                {{ number_format($cat['net_min']/1000, 0, ',', ' ') }}k &ndash; {{ number_format($cat['net_max']/1000, 0, ',', ' ') }}k
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $cat['label'] }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $cat['desc'] }}</p>
                        <p class="text-xs font-mono font-bold text-gray-700 mt-1.5">
                            Net median : {{ number_format($cat['net_mid'], 0, ',', ' ') }} F
                        </p>
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ CORPS PRINCIPAL (formulaire + resultats) ══════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

        {{-- FORMULAIRE ────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Categorie selectionnee --}}
            <div x-show="selectedCat" x-cloak
                 class="flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-2.5 text-sm text-blue-700">
                <span class="text-base" x-text="selectedIcon"></span>
                <span class="font-semibold" x-text="selectedLabel"></span>
                <button @click="selectedCat=''; selectedLabel=''; selectedIcon=''"
                        class="ml-auto text-blue-400 hover:text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- 1. Net souhaite --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                    Net a payer souhaite
                </h2>
                <div class="relative">
                    <input type="number" x-model.number="form.net_souhaite"
                           @input.debounce.600ms="simulate()"
                           min="1" step="1000" placeholder="300 000"
                           class="w-full pl-4 pr-16 py-3 text-[17px] font-bold text-gray-900 border-2 border-emerald-400 rounded-[4px]
                                  focus:outline-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500
                                  bg-emerald-50 placeholder-gray-300">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">FCFA</span>
                </div>
                {{-- Slider rapide --}}
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach([150000,300000,500000,750000,1000000,1500000] as $quick)
                    <button @click="form.net_souhaite={{ $quick }}; simulate()"
                            type="button"
                            class="px-2 py-1 text-xs rounded-[4px] border border-gray-200 text-gray-500 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                            :class="form.net_souhaite === {{ $quick }} ? 'border-emerald-400 bg-emerald-50 text-emerald-700 font-semibold' : ''">
                        {{ number_format($quick/1000, 0, ',', ' ') }}k
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- 2. Situation familiale --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                    Situation familiale
                </h2>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    @foreach(['celibataire'=>'Celibataire','marie'=>'Marie(e)','veuf'=>'Veuf/Veuve'] as $val=>$label)
                    <label class="cursor-pointer">
                        <input type="radio" x-model="form.family_status" value="{{ $val }}" @change="simulate()" class="sr-only peer">
                        <div class="text-center px-1 py-1.5 rounded-[4px] border border-gray-200 text-xs font-medium text-gray-600
                                    peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                    hover:border-blue-300 transition-colors">
                            {{ $label }}
                        </div>
                    </label>
                    @endforeach
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-2">Nombre de charges (enfants)</label>
                    <div class="flex items-center gap-3">
                        <button @click="form.nb_children=Math.max(0,form.nb_children-1);simulate()" type="button"
                                class="w-8 h-8 rounded-[4px] bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 font-bold">&minus;</button>
                        <span class="text-[17px] font-bold text-gray-900 w-8 text-center" x-text="form.nb_children"></span>
                        <button @click="form.nb_children=Math.min(15,form.nb_children+1);simulate()" type="button"
                                class="w-8 h-8 rounded-[4px] bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 font-bold">+</button>
                        <span class="text-xs text-gray-400 ml-1" x-text="'= ' + nbPartsLabel + ' part(s)'"></span>
                    </div>
                </div>
            </div>

            {{-- 3. Primes & retenues --}}
            <div class="bg-white rounded-[4px] border border-gray-300 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-emerald-100 text-emerald-800 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                    Primes &amp; retenues
                </h2>
                <div class="space-y-3">
                    @foreach([
                        ['prime_imposable',    'Primes imposables (dans le brut)',  'Transport, logement taxable…'],
                        ['prime_non_imposable','Primes non imposables (hors brut)', 'Panier, deplacement, exonerees…'],
                        ['avances',            'Avances / Retenues sur salaire',    ''],
                    ] as [$field, $label, $hint])
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ $label }}</label>
                        <div class="relative">
                            <input type="number" x-model.number="form.{{ $field }}"
                                   @input.debounce.500ms="simulate()"
                                   min="0" step="1000" placeholder="0"
                                   class="w-full pl-3 pr-14 py-2 border border-gray-200 rounded-[4px] text-sm focus:outline-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                        @if($hint)<p class="text-xs text-gray-400 mt-0.5">{{ $hint }}</p>@endif
                    </div>
                    @endforeach

                    {{-- Ancienneté (% du salaire de base) — modèle Sage Paie RH --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ancienneté</label>
                        <div class="relative">
                            <input type="number" x-model.number="form.anciennete_pct"
                                   @input.debounce.500ms="simulate()"
                                   min="0" max="25" step="0.5" placeholder="0"
                                   class="w-full pl-3 pr-10 py-2 border border-gray-200 rounded-[4px] text-sm focus:outline-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-500">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">BF : 2 %/an, plafond 25 %. Décompose base / prime d'ancienneté.</p>
                    </div>
                </div>
            </div>

            <button @click="simulate()" :disabled="loading || !form.net_souhaite"
                    class="w-full py-3 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-sm font-semibold rounded-[4px] transition-colors flex items-center justify-center gap-2">
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="loading ? 'Calcul…' : 'Calculer'"></span>
            </button>
        </div>

        {{-- RESULTATS ──────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- Vide --}}
            <div x-show="!result && !loading" x-cloak
                 class="bg-white rounded-[4px] border border-dashed border-gray-200 p-16 text-center flex flex-col items-center gap-3">
                <span class="text-5xl">🧮</span>
                <p class="text-gray-400 text-sm">Saisissez un net souhaite ou choisissez une categorie</p>
            </div>

            {{-- Chargement --}}
            <div x-show="loading" x-cloak
                 class="bg-white rounded-[4px] border border-gray-300 p-16 flex items-center justify-center gap-3 text-gray-400">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Recherche binaire en cours&hellip;
            </div>

            <template x-if="result">
                <div class="space-y-4">

                    {{-- Alerte : PNI couvre deja le net (brut = 0) --}}
                    <div x-show="result.salaire_brut === 0"
                         class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-1.5 text-sm text-blue-800">
                        <svg class="w-5 h-5 flex-shrink-0 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>La prime non imposable (<strong x-text="fmt(form.prime_non_imposable)+' F'"></strong>)
                        couvre integralement le net cible. Le salaire brut taxable est <strong>0 F</strong> — pas de CNSS ni d'IUTS.</span>
                    </div>

                    {{-- Alerte ecart --}}
                    <div x-show="!result.exact && result.salaire_brut > 0"
                         class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-1.5 text-sm text-amber-800">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Net exact impossible : ecart de <strong x-text="fmt(result.ecart)"></strong> F.
                        Propose : <strong x-text="fmt(result.net_calcule)"></strong> F.</span>
                    </div>

                    {{-- KPIs --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4">
                            <p class="text-xs text-blue-500 font-medium uppercase tracking-wide mb-1">Salaire brut</p>
                            <p class="text-[16px] font-bold text-blue-700 tabular-nums" x-text="fmt(result.salaire_brut)+' F'"></p>
                            <p class="text-xs text-blue-400 mt-1">Base : <span x-text="fmt(result.salaire_base)+' F'"></span></p>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-4">
                            <p class="text-xs text-emerald-500 font-medium uppercase tracking-wide mb-1">Net a payer</p>
                            <p class="text-[16px] font-bold text-emerald-700 tabular-nums" x-text="fmt(result.net_calcule)+' F'"></p>
                            <p class="text-xs text-emerald-400 mt-1">Cible : <span x-text="fmt(result.net_souhaite)+' F'"></span></p>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
                            <p class="text-xs text-red-500 font-medium uppercase tracking-wide mb-1">CNSS salarie</p>
                            <p class="text-[17px] font-bold text-red-700 tabular-nums" x-text="fmt(result.cnss_employee)+' F'"></p>
                            <p class="text-xs text-red-400 mt-1" x-text="result.cnss_employee_rate+'% du brut plafonne'"></p>
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-[4px] p-4">
                            <p class="text-xs text-purple-500 font-medium uppercase tracking-wide mb-1">IUTS</p>
                            <p class="text-[17px] font-bold text-purple-700 tabular-nums" x-text="fmt(result.iuts)+' F'"></p>
                            <p class="text-xs text-purple-400 mt-1" x-text="(result.family_charges ?? 0)+' charge(s) de famille'"></p>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-4">
                            <p class="text-xs text-emerald-700 font-medium uppercase tracking-wide mb-1">Salaire net imposable</p>
                            <p class="text-[17px] font-bold text-emerald-800 tabular-nums" x-text="fmt(result.salaire_net_imposable)+' F'"></p>
                            <p class="text-xs text-teal-400 mt-1">Brut &minus; CNSS</p>
                        </div>
                        <div class="bg-violet-50 border border-violet-200 rounded-[4px] p-4">
                            <p class="text-xs text-violet-600 font-medium uppercase tracking-wide mb-1">Base imposable IUTS</p>
                            <p class="text-[17px] font-bold text-violet-700 tabular-nums" x-text="fmt(result.base_iuts)+' F'"></p>
                            <p class="text-xs text-violet-400 mt-1" x-text="'Apres abattement '+result.abattement_rate+'%'"></p>
                        </div>
                        <div class="col-span-2 bg-gray-900 rounded-[4px] p-4 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Cout total employeur</p>
                                <p class="text-[16px] font-bold text-white tabular-nums" x-text="fmt(result.cout_employeur)+' F'"></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Brut + CNSS patronal (<span x-text="fmt(result.cnss_employer)+' F'"></span>)
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-3xl">💼</span>
                                {{-- Bouton Export PDF --}}
                                <button @click="exportPdf()"
                                        :disabled="pdfLoading"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-medium rounded-[4px] transition-colors disabled:opacity-50">
                                    <svg x-show="pdfLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!pdfLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span x-text="pdfLoading ? 'Generation…' : 'Telecharger PDF'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Detail ligne par ligne --}}
                    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                        <div class="px-3 py-1.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Detail du calcul</h3>
                            <span class="text-xs text-gray-400">Norme BF (CNSS + IUTS)</span>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <template x-for="(row, idx) in result.detail" :key="idx">
                                <div>
                                    <div x-show="row.section === 'employeur' && (idx === 0 || result.detail[idx-1].section !== 'employeur')"
                                         class="px-4 py-1.5 bg-gray-800 text-xs text-gray-300 uppercase tracking-wide font-semibold">
                                        Charge patronale
                                    </div>
                                    <div class="flex items-center justify-between px-3 py-2.5"
                                         :class="row.bold ? 'bg-gray-50' : 'bg-white'">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 text-center font-mono text-sm font-bold"
                                                  :class="{'text-emerald-600':row.signe==='+','text-red-500':row.signe==='-','text-gray-400':row.signe==='='}"
                                                  x-text="row.signe"></span>
                                            <span class="text-sm"
                                                  :class="row.bold ? 'font-semibold text-gray-900' : 'text-gray-600'"
                                                  x-text="row.label"></span>
                                        </div>
                                        <span class="font-mono text-sm tabular-nums"
                                              :class="{
                                                  'font-bold text-emerald-700': row.color==='green',
                                                  'font-bold text-blue-700':   row.color==='blue',
                                                  'font-bold text-white bg-gray-900 px-2 py-0.5 rounded': row.color==='rose',
                                                  'text-red-600':    row.color==='red',
                                                  'text-purple-600': row.color==='purple',
                                                  'text-amber-600':  row.color==='amber',
                                                  'text-emerald-600':row.color==='emerald',
                                                  'text-emerald-700': row.color==='indigo',
                                                  'text-orange-600': row.color==='orange',
                                                  'text-gray-700':   row.color==='gray',
                                                  'font-bold text-emerald-800':   row.color==='teal',
                                                  'text-violet-600': row.color==='violet',
                                                  'text-slate-500':  row.color==='slate',
                                              }"
                                              x-text="(row.montant>0||row.bold)?fmt(row.montant)+' F':'—'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Taux effectifs --}}
                    <div class="bg-white rounded-[4px] border border-gray-300 p-4">
                        <h3 class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide mb-3">Taux effectifs</h3>
                        <div class="space-y-2.5">
                            <template x-for="item in tauxEffectifs" :key="item.label">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500 w-44 flex-shrink-0" x-text="item.label"></span>
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700"
                                             :class="item.color"
                                             :style="'width:'+Math.min(item.pct,100)+'%'"></div>
                                    </div>
                                    <span class="text-xs font-mono font-semibold text-gray-700 w-12 text-right"
                                          x-text="item.pct.toFixed(1)+'%'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </template>
        </div>
    </div>

</div>

{{-- Formulaire hidden pour export PDF (soumission standard, pas fetch) --}}
<form id="pdf-export-form" method="POST" action="{{ route('rh.paie.simulateur.pdf') }}" target="_blank" class="hidden">
    @csrf
    <input type="hidden" name="net_souhaite"        id="pdf_net_souhaite">
    <input type="hidden" name="family_status"        id="pdf_family_status">
    <input type="hidden" name="nb_children"          id="pdf_nb_children">
    <input type="hidden" name="prime_imposable"      id="pdf_prime_imposable">
    <input type="hidden" name="prime_non_imposable"  id="pdf_prime_non_imposable">
    <input type="hidden" name="avances"              id="pdf_avances">
    <input type="hidden" name="anciennete_pct"       id="pdf_anciennete_pct">
    <input type="hidden" name="categorie_label"      id="pdf_categorie_label">
</form>
@endsection

@push('scripts')
<script>
function simulateur() {
    return {
        form: {
            net_souhaite:        null,
            family_status:       'celibataire',
            nb_children:         0,
            prime_imposable:     0,
            prime_non_imposable: 0,
            avances:             0,
            anciennete_pct:      0,
        },
        result:       null,
        loading:      false,
        pdfLoading:   false,
        error:        null,
        selectedCat:  '',
        selectedLabel:'',
        selectedIcon: '',
        _abortCtrl:   null,

        init() {},

        get nbPartsLabel() {
            const parts = { celibataire:{{ $payroll->parts_base_single ?? 1 }}, marie:{{ $payroll->parts_base_married ?? 2 }}, veuf:{{ $payroll->parts_base_widowed ?? 2 }} };
            const ppc   = {{ $payroll->parts_per_child ?? 0.5 }};
            const max   = {{ $payroll->nb_parts_max    ?? 6.5 }};
            return Math.min((parts[this.form.family_status] ?? 1) + this.form.nb_children * ppc, max).toFixed(1);
        },

        get tauxEffectifs() {
            if (!this.result?.salaire_brut) return [];
            const b = this.result.salaire_brut, t = this.result.cout_employeur;
            return [
                { label:'CNSS salarie / brut',      pct: this.result.cnss_employee         / b * 100, color:'bg-red-400' },
                { label:'Net imposable / brut',      pct: this.result.salaire_net_imposable / b * 100, color:'bg-teal-400' },
                { label:'IUTS / brut',               pct: this.result.iuts                 / b * 100, color:'bg-purple-400' },
                { label:'Pression fiscale totale',   pct:(this.result.cnss_employee + this.result.iuts) / b * 100, color:'bg-blue-400' },
                { label:'Net a payer / cout emp.',   pct: this.result.net_calcule           / t * 100, color:'bg-emerald-400' },
            ];
        },

        selectCategorie(cat) {
            this.form.net_souhaite = cat.net_mid;
            this.selectedCat   = cat.code;
            this.selectedLabel = cat.label;
            this.selectedIcon  = cat.icon;
            this.simulate();
        },

        async simulate() {
            if (!this.form.net_souhaite || this.form.net_souhaite <= 0) { this.result = null; return; }

            // Annule la requete precedente si elle est encore en vol
            if (this._abortCtrl) this._abortCtrl.abort();
            this._abortCtrl = new AbortController();
            const signal = this._abortCtrl.signal;

            this.loading = true; this.error = null;
            try {
                const resp = await fetch('{{ route("rh.paie.simulateur.calculate") }}', {
                    method: 'POST',
                    signal,
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.form),
                });
                if (!resp.ok) { const e = await resp.json().catch(()=>({})); throw new Error(e.message ?? 'Erreur serveur'); }
                this.result = await resp.json();
            } catch(e) {
                if (e.name !== 'AbortError') { this.error = e.message; this.result = null; }
                // AbortError = requete annulee volontairement, on l'ignore silencieusement
            } finally {
                this.loading = false;
            }
        },

        exportPdf() {
            if (!this.result) return;
            this.pdfLoading = true;
            // Remplir le formulaire hidden et soumettre en nouvelle fenetre
            document.getElementById('pdf_net_souhaite').value       = this.form.net_souhaite;
            document.getElementById('pdf_family_status').value      = this.form.family_status;
            document.getElementById('pdf_nb_children').value        = this.form.nb_children;
            document.getElementById('pdf_prime_imposable').value    = this.form.prime_imposable || 0;
            document.getElementById('pdf_prime_non_imposable').value= this.form.prime_non_imposable || 0;
            document.getElementById('pdf_avances').value            = this.form.avances || 0;
            document.getElementById('pdf_anciennete_pct').value     = this.form.anciennete_pct || 0;
            document.getElementById('pdf_categorie_label').value    = this.selectedLabel || '';
            document.getElementById('pdf-export-form').submit();
            // Reset le spinner apres un delai (la soumission ouvre un onglet)
            setTimeout(() => { this.pdfLoading = false; }, 2000);
        },

        fmt(n) {
            return Math.round(Number(n)||0).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
        },
    };
}
</script>
@endpush
