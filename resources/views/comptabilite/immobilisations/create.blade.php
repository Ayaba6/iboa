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
<div class="max-w-3xl mx-auto space-y-3"
     x-data="immobilisationForm()"
     x-init="init()">

    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle immobilisation</h1>
        <p class="text-sm text-gray-500 mt-0.5">Créez un actif fixe et son plan d'amortissement SYSCOHADA</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-[4px] px-3 py-1.5 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('comptabilite.immobilisations.store') }}" class="space-y-3">
        @csrf

        {{-- Informations générales --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations générales</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Désignation <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="Ex. : Ordinateur portable Dell XPS 15">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie <span class="text-red-500">*</span></label>
                    <select name="category" x-model="category" @change="applyDefaults()" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        @foreach($categoryLabels as $val => $label)
                            <option value="{{ $val }}" {{ old('category', 'materiel_informatique') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                    <input type="text" name="vendor" value="{{ old('vendor') }}"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="Ex. : CFAO Technologies">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date d'acquisition <span class="text-red-500">*</span></label>
                    <input type="date" name="acquisition_date" value="{{ old('acquisition_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de mise en service <span class="text-red-500">*</span></label>
                    <input type="date" name="commissioning_date" value="{{ old('commissioning_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                    <p class="text-xs text-gray-500 mt-0.5">Détermine le prorata temporis de la 1ère année.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Coût d'acquisition (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required min="1" step="1"
                           x-model.number="cost"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valeur résiduelle (FCFA)</label>
                    <input type="number" name="residual_value" value="{{ old('residual_value', 0) }}" min="0" step="1"
                           x-model.number="residual"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="0">
                    <p class="text-xs text-gray-500 mt-0.5">Valeur estimée en fin de vie utile.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Réf. facture / bon d'achat</label>
                <input type="text" name="invoice_ref" value="{{ old('invoice_ref') }}"
                       class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                       placeholder="Ex. : FAC-2026-1234">
            </div>
        </div>

        {{-- Amortissement --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Paramètres d'amortissement</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Méthode <span class="text-red-500">*</span></label>
                    <select name="depreciation_method" x-model="method" required
                            class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                        @foreach($methodLabels as $val => $label)
                            <option value="{{ $val }}" {{ old('depreciation_method', 'lineaire') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée (années) <span class="text-red-500">*</span></label>
                    <input type="number" name="useful_life_years" min="0" max="99" required
                           x-model.number="years"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                           placeholder="5">
                    <p class="text-xs text-gray-500 mt-0.5">0 = non amortissable (terrain).</p>
                </div>
            </div>

            {{-- Indicateur taux --}}
            <template x-if="years > 0 && cost > 0">
                <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-3 text-sm text-blue-800">
                    <span class="font-medium">Taux annuel :</span>
                    <span x-text="(100 / years).toFixed(2) + ' %'"></span>
                    <span class="mx-2">·</span>
                    <span class="font-medium">Dotation annuelle :</span>
                    <span x-text="formatAmount(Math.round((cost - residual) / years)) + ' FCFA'"></span>
                    <template x-if="method === 'degressif'">
                        <span class="text-blue-600 ml-1">(base dégressive — 1ère année)</span>
                    </template>
                </div>
            </template>
        </div>

        {{-- Comptes GL --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Comptes comptables (SYSCOHADA)</h2>
            <p class="text-xs text-gray-500">Les comptes sont pré-remplis selon la catégorie — vous pouvez les modifier.</p>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte immobilisation <span class="text-red-500">*</span></label>
                    <input type="text" name="asset_account" x-model="assetAccount" required maxlength="10"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 font-mono"
                           placeholder="ex: 2454">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte amortissement</label>
                    <input type="text" name="depr_account" x-model="deprAccount" maxlength="10"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 font-mono"
                           placeholder="ex: 28454">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Compte charge dotation</label>
                    <input type="text" name="charge_account" x-model="chargeAccount" maxlength="10"
                           class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500 font-mono"
                           placeholder="ex: 6813">
                </div>
            </div>

            {{-- Aide mémo comptes --}}
            <div class="text-xs text-gray-500 bg-gray-50 rounded-[4px] p-3 space-y-0.5">
                <p><span class="font-medium">2x</span> — Immobilisations (2120=Logiciels, 2310=Bâtiments, 244x=Mobilier/Matériel, 245x=Informatique)</p>
                <p><span class="font-medium">28x</span> — Amortissements cumulés (281x incorp., 283x-285x corp.)</p>
                <p><span class="font-medium">6811</span> — Dotations amort. immob. incorporelles · <span class="font-medium">6813</span> — Dotations amort. immob. corporelles</p>
            </div>
        </div>

        {{-- Notes --}}
        <div class="bg-white border border-gray-300 rounded-[4px] p-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes / observations</label>
            <textarea name="notes" rows="3"
                      class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500"
                      placeholder="Emplacement, numéro de série, informations complémentaires…">{{ old('notes') }}</textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('comptabilite.immobilisations.index') }}"
               class="border border-gray-300 text-gray-700 text-sm font-medium px-5 py-1.5 rounded-[4px] hover:bg-gray-50">Annuler</a>
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-6 py-1.5 rounded-[4px]">
                Créer l'immobilisation
            </button>
        </div>
    </form>

</div>

<script>
const categoryDefaults = @json($categoryDefaults);

function immobilisationForm() {
    return {
        category: '{{ old('category', 'materiel_informatique') }}',
        years: {{ old('useful_life_years', 3) }},
        cost: {{ old('acquisition_cost', 0) }},
        residual: {{ old('residual_value', 0) }},
        method: '{{ old('depreciation_method', 'lineaire') }}',
        assetAccount:  '{{ old('asset_account', '2454') }}',
        deprAccount:   '{{ old('depr_account',  '28454') }}',
        chargeAccount: '{{ old('charge_account','6813') }}',

        init() {
            if (!{{ old('asset_account') ? 'true' : 'false' }}) {
                this.applyDefaults();
            }
        },

        applyDefaults() {
            const d = categoryDefaults[this.category];
            if (!d) return;
            this.assetAccount  = d[0];
            this.deprAccount   = d[1];
            this.chargeAccount = d[2];
            this.years         = d[3];
        },

        formatAmount(n) {
            return n.toLocaleString('fr-FR');
        }
    };
}
</script>
@endsection
