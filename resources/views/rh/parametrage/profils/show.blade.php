@extends('layouts.erp')
@section('title', $profil->code . ' — Rubriques')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.profils.index') }}" class="hover:text-gray-700">Profils</a>
    <span class="mx-1">/</span><span>{{ $profil->code }}</span>
@endsection

@section('content')
@php
$catColors = [
    'cadre' => 'indigo', 'non_cadre' => 'blue', 'dirigeant' => 'violet',
    'interim' => 'amber', 'stagiaire' => 'emerald', 'autre' => 'gray',
];
$color = $catColors[$profil->categorie] ?? 'gray';
$catLabels = [
    'salaire' => 'Salaire', 'prime' => 'Primes', 'indemnite' => 'Indemnités',
    'absence' => 'Absences', 'avance' => 'Avances', 'pret' => 'Prêts',
    'impot' => 'Impôts', 'cnss' => 'CNSS', 'avantage' => 'Avantages en nature', 'autre' => 'Autre',
];
@endphp

{{-- En-tête --}}
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-[4px] flex items-center justify-center flex-shrink-0
            {{ $color === 'indigo' ? 'bg-emerald-100' : ($color === 'blue' ? 'bg-blue-100' : ($color === 'violet' ? 'bg-violet-100' : ($color === 'amber' ? 'bg-amber-100' : ($color === 'emerald' ? 'bg-emerald-100' : 'bg-gray-100')))) }}">
            <svg class="w-6 h-6 {{ $color === 'indigo' ? 'text-emerald-700' : ($color === 'blue' ? 'text-blue-600' : ($color === 'violet' ? 'text-violet-600' : ($color === 'amber' ? 'text-amber-600' : ($color === 'emerald' ? 'text-emerald-600' : 'text-gray-500')))) }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-[16px] font-bold text-gray-900">{{ $profil->libelle }}</h1>
                <code class="text-sm font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $profil->code }}</code>
                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium
                    {{ $color === 'indigo' ? 'bg-emerald-100 text-emerald-800' : ($color === 'blue' ? 'bg-blue-100 text-blue-700' : ($color === 'violet' ? 'bg-violet-100 text-violet-700' : ($color === 'amber' ? 'bg-amber-100 text-amber-700' : ($color === 'emerald' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600')))) }}">
                    {{ $profil->categorie_label }}
                </span>
                @if($profil->is_default)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Par défaut</span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $profil->plan?->libelle ?? 'Sans plan associé' }}
                · {{ $profil->rubrics_count }} rubrique(s)
                · {{ $profil->contracts_count }} contrat(s)
            </p>
        </div>
    </div>
    <div class="flex gap-2">
        {{-- Sync depuis le plan --}}
        @if($profil->plan_id)
        <form method="POST" action="{{ route('rh.profils.sync-plan', $profil) }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-[4px] text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sync depuis le plan
            </button>
        </form>
        @endif
        <a href="{{ route('rh.profils.edit', $profil) }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
            Modifier le profil
        </a>
    </div>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-3 gap-6">

    {{-- Colonne principale : rubriques du profil --}}
    <div class="col-span-2 space-y-4">

        @if($rubricsByCategorie->isEmpty())
        <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
            <p class="text-gray-400">Aucune rubrique dans ce profil.</p>
            @if($profil->plan_id)
            <p class="text-xs text-gray-400 mt-1">Cliquez sur <strong>Sync depuis le plan</strong> pour hériter les rubriques du plan associé.</p>
            @else
            <p class="text-xs text-gray-400 mt-1">Ajoutez des rubriques depuis le panneau de droite.</p>
            @endif
        </div>
        @else

        {{-- Stats --}}
        <div class="grid grid-cols-4 gap-3">
            <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
                <p class="text-[16px] font-bold text-gray-900">{{ $profil->rubrics_count }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Rubriques</p>
            </div>
            <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
                <p class="text-[16px] font-bold text-green-600">{{ $profil->rubrics->where('pivot.is_active', true)->where('sens', 'addition')->count() }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Gains actifs</p>
            </div>
            <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
                <p class="text-[16px] font-bold text-red-500">{{ $profil->rubrics->where('pivot.is_active', true)->where('sens', 'deduction')->count() }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Déductions actives</p>
            </div>
            <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
                <p class="text-[16px] font-bold text-orange-500">{{ $profil->rubrics->where('pivot.is_active', false)->count() }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Désactivées</p>
            </div>
        </div>

        @foreach($rubricsByCategorie as $cat => $rubrics)
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">{{ $catLabels[$cat] ?? ucfirst($cat) }}</h2>
                <span class="text-xs text-gray-400">{{ $rubrics->count() }} rubrique(s)</span>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($rubrics->sortBy('display_order') as $rubric)
                <div x-data="{ editing: false }" class="px-5 py-3 hover:bg-gray-50 transition-colors {{ !$rubric->pivot->is_active ? 'opacity-50' : '' }}">

                    {{-- Vue normale --}}
                    <div x-show="!editing" class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Sens --}}
                            @if($rubric->sens === 'addition')
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-green-100 text-green-700 flex-shrink-0">+</span>
                            @else
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-red-100 text-red-600 flex-shrink-0">−</span>
                            @endif

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <code class="text-xs font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ $rubric->code }}</code>
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ $rubric->libelle }}</span>
                                    @if(!$rubric->pivot->is_active)
                                        <span class="text-xs text-gray-400 italic">désactivée</span>
                                    @endif
                                </div>
                                {{-- Valeur effective --}}
                                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                                    @if($rubric->pivot->override_calc_type || $rubric->pivot->override_fixed_amount !== null || $rubric->pivot->override_rate !== null)
                                        <span class="text-amber-600 font-medium">Surcharge :</span>
                                        @if($rubric->pivot->override_fixed_amount !== null)
                                            <span>{{ number_format($rubric->pivot->override_fixed_amount, 0, ',', ' ') }} FCFA</span>
                                        @elseif($rubric->pivot->override_rate !== null)
                                            <span>{{ $rubric->pivot->override_rate }} %</span>
                                        @else
                                            <span>{{ $rubric->pivot->override_calc_type }}</span>
                                        @endif
                                    @else
                                        <span>Hérité : {{ $rubric->calc_type_label }}
                                            @if($rubric->calc_type === 'fixe' && $rubric->fixed_amount)
                                                — {{ number_format($rubric->fixed_amount, 0, ',', ' ') }} FCFA
                                            @elseif($rubric->calc_type === 'taux' && $rubric->rate)
                                                — {{ $rubric->rate }} %
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button @click="editing = true"
                                    class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Surcharger</button>
                            <form method="POST" action="{{ route('rh.profils.rubrics.remove', [$profil, $rubric]) }}"
                                  onsubmit="return confirm('Retirer cette rubrique du profil ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600">Retirer</button>
                            </form>
                        </div>
                    </div>

                    {{-- Formulaire de surcharge --}}
                    <div x-show="editing" x-cloak>
                        <form method="POST" action="{{ route('rh.profils.rubrics.update', [$profil, $rubric]) }}"
                              class="bg-[#eef5f0] border border-emerald-100 rounded-[4px] p-4 mt-1">
                            @csrf @method('PUT')
                            <p class="text-xs font-semibold text-emerald-800 mb-3">Surcharge pour <code>{{ $rubric->code }}</code></p>
                            <div class="grid grid-cols-3 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Type calcul (surcharge)</label>
                                    <select name="override_calc_type"
                                            class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-300">
                                        <option value="">— Hérité ({{ $rubric->calc_type }}) —</option>
                                        <option value="fixe" @selected($rubric->pivot->override_calc_type === 'fixe')>Montant fixe</option>
                                        <option value="taux" @selected($rubric->pivot->override_calc_type === 'taux')>Taux %</option>
                                        <option value="formule" @selected($rubric->pivot->override_calc_type === 'formule')>Formule</option>
                                        <option value="manuel" @selected($rubric->pivot->override_calc_type === 'manuel')>Manuel</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Montant fixe (FCFA)</label>
                                    <input type="number" name="override_fixed_amount"
                                           value="{{ $rubric->pivot->override_fixed_amount }}" min="0"
                                           placeholder="Ex : 25000"
                                           class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono focus:ring-1 focus:ring-emerald-300">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">Taux (%)</label>
                                    <input type="number" name="override_rate"
                                           value="{{ $rubric->pivot->override_rate }}" min="0" max="100" step="0.01"
                                           placeholder="Ex : 15.00"
                                           class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono focus:ring-1 focus:ring-emerald-300">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="text-xs text-gray-500 block mb-1">Formule personnalisée</label>
                                <input type="text" name="override_formula"
                                       value="{{ $rubric->pivot->override_formula }}"
                                       placeholder="Ex : salaire_base * 0.15 + 5000"
                                       class="w-full border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono focus:ring-1 focus:ring-emerald-300">
                            </div>
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-xs text-gray-600">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1"
                                           {{ $rubric->pivot->is_active ? 'checked' : '' }}
                                           class="w-3.5 h-3.5 text-emerald-700 border-gray-300 rounded">
                                    Rubrique active dans ce profil
                                </label>
                                <div class="flex gap-2">
                                    <button type="submit"
                                            class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-xs font-medium hover:bg-emerald-800">
                                        Enregistrer
                                    </button>
                                    <button type="button" @click="editing = false"
                                            class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-[4px] text-xs font-medium">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endif
    </div>

    {{-- Colonne droite : rubriques disponibles à ajouter --}}
    <div class="col-span-1 space-y-4">

        {{-- Contrats utilisant ce profil --}}
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Contrats liés</p>
            @if($profil->contracts_count === 0)
                <p class="text-sm text-gray-400 italic">Aucun contrat n'utilise ce profil.</p>
            @else
                @foreach($profil->contracts()->with('employee')->limit(5)->get() as $contract)
                <div class="flex items-center gap-2 py-1.5 border-b border-gray-50 last:border-0">
                    <div class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-bold text-gray-500">{{ substr($contract->employee?->prenom ?? '?', 0, 1) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate">
                            {{ $contract->employee?->prenom }} {{ $contract->employee?->nom }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $contract->type }} · {{ number_format($contract->base_salary, 0, ',', ' ') }} FCFA</p>
                    </div>
                </div>
                @endforeach
                @if($profil->contracts_count > 5)
                    <p class="text-xs text-gray-400 mt-2">+ {{ $profil->contracts_count - 5 }} autre(s)</p>
                @endif
            @endif
        </div>

        {{-- Ajouter des rubriques --}}
        @if($availableRubrics->isNotEmpty())
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">Ajouter une rubrique</h3>
                <p class="text-xs text-gray-400 mt-0.5">Rubriques du plan non encore incluses</p>
            </div>
            <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @foreach($availableRubrics as $rubric)
                <div class="flex items-center justify-between px-3 py-2.5">
                    <div>
                        <code class="text-xs font-mono text-gray-600">{{ $rubric->code }}</code>
                        <p class="text-xs text-gray-500 mt-0.5 truncate max-w-[160px]">{{ $rubric->libelle }}</p>
                    </div>
                    <form method="POST" action="{{ route('rh.profils.rubrics.add', $profil) }}">
                        @csrf
                        <input type="hidden" name="rubric_id" value="{{ $rubric->id }}">
                        <button type="submit"
                                class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-700 rounded-[4px] text-sm font-bold hover:bg-emerald-200 transition-colors">
                            +
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($profil->plan_id && $availableRubrics->isEmpty() && $profil->rubrics_count > 0)
        <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] px-3 py-1.5 text-xs text-emerald-700">
            <p class="font-semibold mb-0.5">✓ Toutes les rubriques du plan sont incluses</p>
            <p>Ce profil hérite de toutes les rubriques actives du plan {{ $profil->plan?->code }}.</p>
        </div>
        @endif

    </div>
</div>
@endsection
