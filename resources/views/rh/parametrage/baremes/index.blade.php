@extends('layouts.erp')
@section('title', 'Barèmes IUTS / ITS')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Barèmes fiscaux</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Barèmes fiscaux</h1>
        <p class="text-sm text-gray-500 mt-1">Tranches IUTS/ITS par pays — calculées automatiquement à la paie</p>
    </div>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-3 gap-6">

    {{-- Colonne gauche : barèmes --}}
    <div class="col-span-2 space-y-3">

        @forelse($brackets as $impot => $tranches)
        @php $impotLabel = ['iuts' => 'IUTS — Impôt Unique sur les Traitements et Salaires', 'its' => 'ITS — Impôt sur les Traitements et Salaires', 'autre' => 'Autre barème fiscal'][$impot] ?? ucfirst($impot); @endphp
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700">{{ $impotLabel }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $tranches->count() }} tranche(s) — {{ $tranches->first()?->pays ?? '' }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100">
                        <tr class="text-left text-xs text-gray-400 font-semibold uppercase tracking-wide">
                            <th class="px-3 py-2.5">#</th>
                            <th class="px-3 py-2.5">De (FCFA)</th>
                            <th class="px-3 py-2.5">À (FCFA)</th>
                            <th class="px-3 py-2.5 text-right">Taux</th>
                            <th class="px-3 py-2.5 text-right">Fixe</th>
                            <th class="px-3 py-2.5 text-center">Statut</th>
                            <th class="px-3 py-2.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($tranches as $b)
                        <tr class="hover:bg-gray-50 transition-colors" x-data="{ editing: false }">
                            {{-- Affichage normal --}}
                            <td class="px-3 py-1.5 text-xs text-gray-400" x-show="!editing">{{ $b->ordre }}</td>
                            <td class="px-3 py-1.5 font-mono text-gray-700" x-show="!editing">{{ number_format($b->tranche_min, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 font-mono text-gray-700" x-show="!editing">{{ number_format($b->tranche_max, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right font-semibold text-gray-900" x-show="!editing">{{ $b->taux }} %</td>
                            <td class="px-3 py-1.5 text-right text-gray-500" x-show="!editing">{{ $b->montant_fixe ? number_format($b->montant_fixe, 0, ',', ' ') : '—' }}</td>
                            <td class="px-3 py-1.5 text-center" x-show="!editing">
                                @if($b->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-center" x-show="!editing">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="editing = true" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Modifier</button>
                                    <form method="POST" action="{{ route('rh.baremes.destroy', $b) }}"
                                          onsubmit="return confirm('Supprimer cette tranche ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Suppr.</button>
                                    </form>
                                </div>
                            </td>

                            {{-- Formulaire inline d'édition --}}
                            <td colspan="7" class="px-3 py-1.5 bg-[#eef5f0]" x-show="editing" x-cloak>
                                <form method="POST" action="{{ route('rh.baremes.update', $b) }}" class="flex flex-wrap gap-2 items-end">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="pays" value="{{ $b->pays }}">
                                    <input type="hidden" name="country_code" value="{{ $b->country_code }}">
                                    <input type="hidden" name="impot" value="{{ $b->impot }}">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">Ordre</label>
                                        <input type="number" name="ordre" value="{{ $b->ordre }}" min="1"
                                               class="w-14 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">De (FCFA)</label>
                                        <input type="number" name="tranche_min" value="{{ $b->tranche_min }}" min="0"
                                               class="w-24 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">À (FCFA)</label>
                                        <input type="number" name="tranche_max" value="{{ $b->tranche_max }}"
                                               class="w-24 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">Taux (%)</label>
                                        <input type="number" name="taux" value="{{ $b->taux }}" step="0.01" min="0" max="100"
                                               class="w-16 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">Montant fixe</label>
                                        <input type="number" name="montant_fixe" value="{{ $b->montant_fixe }}" min="0"
                                               class="w-24 border border-gray-300 rounded-[4px] px-2 py-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-0.5">Actif</label>
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" {{ $b->is_active ? 'checked' : '' }}
                                               class="w-4 h-4 text-emerald-700 border-gray-300 rounded">
                                    </div>
                                    <div class="flex gap-1.5">
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-xs font-medium hover:bg-emerald-800">OK</button>
                                        <button type="button" @click="editing = false" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-[4px] text-xs font-medium hover:bg-gray-50">Annuler</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
            <p class="text-gray-400">Aucun barème défini.</p>
        </div>
        @endforelse

        {{-- Formulaire d'ajout de tranche --}}
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-700">Ajouter une tranche</h2>
            </div>
            <form method="POST" action="{{ route('rh.baremes.store') }}" class="px-5 py-4">
                @csrf
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                </div>
                @endif
                <div class="grid grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Pays <span class="text-red-500">*</span></label>
                        <input type="text" name="pays" value="{{ old('pays', 'Burkina Faso') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Code pays <span class="text-red-500">*</span></label>
                        <input type="text" name="country_code" value="{{ old('country_code', 'BF') }}" maxlength="5"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm uppercase font-mono">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Type d'impôt <span class="text-red-500">*</span></label>
                        <select name="impot" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                            <option value="iuts" @selected(old('impot') === 'iuts')>IUTS</option>
                            <option value="its" @selected(old('impot') === 'its')>ITS</option>
                            <option value="autre" @selected(old('impot') === 'autre')>Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Ordre <span class="text-red-500">*</span></label>
                        <input type="number" name="ordre" value="{{ old('ordre', ($brackets->flatten()->max('ordre') ?? 0) + 1) }}" min="1"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">De (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="tranche_min" value="{{ old('tranche_min', 0) }}" min="0"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">À (FCFA) <span class="text-red-500">*</span></label>
                        <input type="number" name="tranche_max" value="{{ old('tranche_max') }}"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Taux (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="taux" value="{{ old('taux', 0) }}" step="0.01" min="0" max="100"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Montant fixe</label>
                        <input type="number" name="montant_fixe" value="{{ old('montant_fixe', 0) }}" min="0"
                               class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 text-emerald-700 border-gray-300 rounded">
                        Tranche active
                    </label>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter la tranche
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Colonne droite : simulateur IUTS --}}
    <div class="col-span-1">
        <div class="bg-white rounded-[4px] border border-gray-300 shadow-sm sticky top-6" x-data="{
            salaire: '',
            parts: 1,
            result: null,
            loading: false,
            async simulate() {
                if (!this.salaire) return;
                this.loading = true;
                try {
                    const r = await fetch('{{ route('rh.baremes.simulate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ salaire_imposable: this.salaire, nb_parts: this.parts })
                    });
                    this.result = await r.json();
                } catch(e) {
                    this.result = null;
                } finally {
                    this.loading = false;
                }
            }
        }">
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-violet-100 rounded-[4px] flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Simulateur IUTS</h3>
                        <p class="text-xs text-gray-400">Test instantané du barème</p>
                    </div>
                </div>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Salaire imposable (FCFA)</label>
                    <input type="number" x-model="salaire" @input.debounce.400ms="simulate()"
                           placeholder="Ex : 350 000"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono focus:ring-1 focus:ring-violet-300 focus:border-violet-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Nombre de parts : <span class="font-semibold text-gray-900" x-text="parts"></span>
                    </label>
                    <input type="range" x-model="parts" min="1" max="10" step="0.5" @input="simulate()"
                           class="w-full accent-violet-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-0.5">
                        <span>1</span><span>5</span><span>10</span>
                    </div>
                </div>

                {{-- Résultat --}}
                <div x-show="result" x-cloak class="bg-violet-50 rounded-[4px] border border-violet-100 p-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-violet-600 font-medium">IUTS calculé</span>
                        <span class="font-mono font-bold text-violet-800 text-lg" x-text="result?.iuts_formatted"></span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-violet-100">
                        <span class="text-xs text-violet-500">Taux moyen</span>
                        <span class="text-xs font-semibold text-violet-700" x-text="result?.taux_moyen"></span>
                    </div>
                </div>

                <div x-show="loading" x-cloak class="text-center py-2">
                    <div class="inline-block w-4 h-4 border-2 border-violet-300 border-t-violet-600 rounded-full animate-spin"></div>
                </div>

                <div x-show="!result && !loading && salaire" x-cloak
                     class="bg-amber-50 border border-amber-100 rounded-[4px] px-3 py-2 text-xs text-amber-700">
                    Aucun barème actif — vérifiez vos tranches.
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
