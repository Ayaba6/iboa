@extends('layouts.erp')
@section('title', 'Affectation du résultat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.bilan') }}" class="hover:text-gray-700">Bilan</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Affectation du résultat</span>
@endsection

@section('content')
<div x-data="affectationApp()" class="space-y-3 max-w-4xl mx-auto">

    {{-- ── En-tête ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Affectation du résultat</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                Génère une OD de clôture vers compte 13 — norme SYSCOHADA
            </p>
        </div>
        <a href="{{ route('comptabilite.bilan', request()->only('fiscal_year_id')) }}"
           class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-[4px]">
            ← Retour au bilan
        </a>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-[4px] px-3 py-1.5">
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Sélecteur d'exercice ─────────────────────────────────────────────── --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Exercice</label>
            <select name="fiscal_year_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-[4px] px-3 py-1.5 text-sm">
                <option value="">— Soldes cumulés —</option>
                @foreach($fiscalYears as $fy)
                <option value="{{ $fy->id }}" {{ $selectedFy?->id == $fy->id ? 'selected' : '' }}>
                    {{ $fy->label }} @if($fy->is_current) ★ @endif
                </option>
                @endforeach
            </select>
        </div>
    </form>

    {{-- ── Résultat net ─────────────────────────────────────────────────────── --}}
    <div class="rounded-[4px] border-2 p-6 text-center {{ $netResult >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }}">
        <p class="text-sm font-medium {{ $netResult >= 0 ? 'text-emerald-600' : 'text-red-500' }} mb-1">
            {{ $netResult >= 0 ? '📈 Bénéfice net de l\'exercice' : '📉 Perte nette de l\'exercice' }}
            @if($selectedFy) — {{ $selectedFy->label }} @endif
        </p>
        <p class="text-4xl font-bold tabular-nums {{ $netResult >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
            {{ $netResult < 0 ? '(' : '' }}{{ number_format(abs($netResult), 0, ',', ' ') }} FCFA{{ $netResult < 0 ? ')' : '' }}
        </p>
        <p class="text-xs text-gray-400 mt-2">
            @if($compte13)
            → Sera inscrit en compte <strong>13 — {{ $compte13->name }}</strong>
            @else
            ⚠️ Compte 13 introuvable dans le plan comptable
            @endif
        </p>
    </div>

    @if($netResult === 0)
    <div class="bg-gray-50 border border-gray-300 rounded-[4px] px-4 py-8 text-center text-gray-500">
        <p class="text-lg font-semibold">Résultat nul — aucune affectation nécessaire.</p>
    </div>
    @else

    {{-- ── Explication SYSCOHADA ────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] px-3 py-1.5 text-xs text-blue-800 space-y-1">
        <p class="font-semibold">Écriture générée (OD brouillon) :</p>
        @if($netResult < 0)
        <p>
            <span class="font-mono bg-blue-100 px-1 rounded">Débit</span> Compte(s) de capitaux propres
            / <span class="font-mono bg-blue-100 px-1 rounded">Crédit</span> 13 — Résultat net (perte : {{ number_format(abs($netResult), 0, ',', ' ') }} FCFA)
        </p>
        <p class="text-blue-600">💡 La perte réduit les capitaux propres (ex : imputer au compte 12 — Report à nouveau débiteur)</p>
        @else
        <p>
            <span class="font-mono bg-blue-100 px-1 rounded">Débit</span> 13 — Résultat net
            / <span class="font-mono bg-blue-100 px-1 rounded">Crédit</span> Compte(s) d'affectation (réserves, RAN, dividendes…)
        </p>
        <p class="text-blue-600">💡 Le bénéfice peut être affecté à : réserve légale (10%), dividendes, report à nouveau (solde)</p>
        @endif
    </div>

    {{-- ── Formulaire d'affectation ─────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('comptabilite.affectation-resultat.store') }}"
          @submit.prevent="submitForm()">
        @csrf

        @if($selectedFy)
        <input type="hidden" name="fiscal_year_id" value="{{ $selectedFy->id }}">
        @else
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-1.5 text-sm text-amber-800">
            ⚠️ Veuillez sélectionner un exercice comptable pour créer l'affectation.
        </div>
        @endif

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">

            {{-- Header --}}
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Lignes d'affectation</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs" :class="isBalanced ? 'text-emerald-600 font-semibold' : 'text-red-500'">
                        Total : <strong x-text="fmt(totalAffecte)"></strong> FCFA
                        <span x-show="!isBalanced" class="ml-1">
                            — reste : <strong x-text="fmt(reste)"></strong>
                        </span>
                        <span x-show="isBalanced">✓ Équilibré</span>
                    </span>
                    <button type="button" @click="addLigne()"
                            class="text-xs bg-violet-100 hover:bg-violet-200 text-violet-700 px-2.5 py-1 rounded-[4px] font-medium">
                        + Ligne
                    </button>
                </div>
            </div>

            {{-- Lignes --}}
            <div class="divide-y divide-gray-50">
                <template x-for="(ligne, i) in lignes" :key="i">
                    <div class="px-5 py-3 flex items-center gap-3 flex-wrap">
                        <div class="w-44">
                            <label class="block text-xs text-gray-500 mb-1">Compte</label>
                            <select :name="`lignes[${i}][account_id]`" x-model="ligne.account_id"
                                    class="w-full border border-gray-200 rounded-[4px] px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-400">
                                <option value="">Choisir…</option>
                                @foreach($comptes as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} — {{ Str::limit($c->name, 30) }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" :name="`lignes[${i}][account_id]`" :value="ligne.account_id">
                        </div>
                        <div class="flex-1 min-w-48">
                            <label class="block text-xs text-gray-500 mb-1">Libellé</label>
                            <input type="text" :name="`lignes[${i}][label]`" x-model="ligne.label"
                                   placeholder="Libellé de l'affectation"
                                   class="w-full border border-gray-200 rounded-[4px] px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-400">
                        </div>
                        <div class="w-40">
                            <label class="block text-xs text-gray-500 mb-1">Montant (FCFA)</label>
                            <input type="number" :name="`lignes[${i}][montant]`" x-model.number="ligne.montant"
                                   min="0" step="1" placeholder="0"
                                   class="w-full border border-gray-200 rounded-[4px] px-2 py-1.5 text-xs focus:ring-1 focus:ring-violet-400 text-right tabular-nums">
                        </div>
                        <div class="flex-shrink-0 pt-5">
                            <button type="button" @click="removeLigne(i)" x-show="lignes.length > 1"
                                    class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Bouton ajouter suggéré --}}
            <div class="px-5 py-3 border-t border-gray-50 bg-gray-50">
                <div class="flex gap-2 flex-wrap">
                    @if($netResult > 0)
                    <button type="button" @click="addSuggestion('reserveLegale')"
                            class="text-xs bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 px-3 py-1.5 rounded-[4px]">
                        + Réserve légale (10%)
                    </button>
                    <button type="button" @click="addSuggestion('ran')"
                            class="text-xs bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 px-3 py-1.5 rounded-[4px]">
                        + Report à nouveau (solde)
                    </button>
                    @else
                    <button type="button" @click="addSuggestion('ran')"
                            class="text-xs bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 px-3 py-1.5 rounded-[4px]">
                        + Report à nouveau (perte totale)
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Date + submit --}}
        <div class="bg-white rounded-[4px] border border-gray-300 px-5 py-4 flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de l'écriture</label>
                <input type="date" name="date_affectation"
                       value="{{ $selectedFy ? $selectedFy->ends_at->format('Y-m-d') : now()->format('Y-m-d') }}"
                       class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-violet-500 focus:border-violet-500">
            </div>
            <div class="flex-1"></div>
            <div class="flex gap-3">
                <a href="{{ route('comptabilite.bilan') }}"
                   class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-[4px]">
                    Annuler
                </a>
                <button type="submit"
                        :disabled="!isBalanced || !{{ $selectedFy ? 'true' : 'false' }}"
                        class="px-5 py-2 text-sm font-semibold text-white bg-emerald-700 hover:bg-emerald-800
                               disabled:opacity-40 disabled:cursor-not-allowed rounded-[4px]">
                    Créer l'OD d'affectation
                </button>
            </div>
        </div>
    </form>

    @endif

</div>

@push('scripts')
<script>
function affectationApp() {
    const netResult  = {{ abs($netResult) }};
    const isBenefice = {{ $netResult >= 0 ? 'true' : 'false' }};

    // Comptes disponibles (pré-remplis côté PHP)
    const comptes = @json($comptes->map(fn($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name]));

    return {
        lignes: [
            { account_id: '', label: isBenefice ? 'Affectation résultat net' : 'Report à nouveau — perte', montant: 0 },
        ],

        get totalAffecte() {
            return this.lignes.reduce((s, l) => s + (parseInt(l.montant) || 0), 0);
        },
        get reste() {
            return netResult - this.totalAffecte;
        },
        get isBalanced() {
            return Math.abs(this.reste) < 1;
        },

        fmt(n) {
            return new Intl.NumberFormat('fr-FR').format(Math.abs(Math.round(n)));
        },

        addLigne() {
            this.lignes.push({ account_id: '', label: '', montant: 0 });
        },

        removeLigne(i) {
            if (this.lignes.length > 1) this.lignes.splice(i, 1);
        },

        addSuggestion(type) {
            const reserveLegale = Math.round(netResult * 0.1);
            const ranSolde      = this.reste;

            if (type === 'reserveLegale' && reserveLegale > 0) {
                // Cherche compte 111
                const c = comptes.find(c => c.code === '111');
                this.lignes.push({
                    account_id: c?.id || '',
                    label: 'Dotation réserve légale (10%)',
                    montant: Math.min(reserveLegale, this.reste),
                });
            } else if (type === 'ran') {
                // Cherche compte 12
                const c = comptes.find(c => c.code === '12');
                this.lignes.push({
                    account_id: c?.id || '',
                    label: isBenefice ? 'Report à nouveau — solde bénéficiaire' : 'Report à nouveau — perte',
                    montant: Math.abs(ranSolde) > 0 ? Math.abs(ranSolde) : netResult,
                });
            }
        },

        submitForm() {
            if (!this.isBalanced) {
                alert('Le total des affectations (' + this.fmt(this.totalAffecte) + ' FCFA) doit être égal au résultat net (' + this.fmt(netResult) + ' FCFA).');
                return;
            }
            this.$el.submit();
        },
    };
}
</script>
@endpush
@endsection
