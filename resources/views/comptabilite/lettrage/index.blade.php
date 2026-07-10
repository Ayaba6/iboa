@extends('layouts.erp')
@section('title', 'Lettrage des comptes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lettrage</span>
@endsection

@section('content')
<div x-data="lettrageApp()" class="space-y-3">

    {{-- ── En-tête ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Lettrage des comptes tiers</h1>
            <p class="text-sm text-gray-400 mt-0.5">Appariement des factures et règlements — comptes de classe 4</p>
        </div>
        <div class="text-xs text-gray-400">Comptes de tiers (classe 4)</div>
    </div>

    {{-- ── Sélecteur de compte ──────────────────────────────────────────────── --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sélectionner un compte tiers</label>
                <select name="account_id" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-violet-500">
                    <option value="">Choisir un compte...</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}" {{ ($selectedAccount?->id) == $account->id ? 'selected' : '' }}>
                        {{ $account->code }} — {{ $account->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-5 py-1.5 rounded-[4px] transition-colors">
                Charger
            </button>
        </div>
    </form>

    @if($selectedAccount)

    {{-- ── Barre de stats ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- % Lettré + barre de progression --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 mb-1">Taux de lettrage</p>
            <div class="flex items-end justify-between mb-2">
                <p class="text-[16px] font-bold {{ $stats['pct_lettered'] >= 80 ? 'text-emerald-600' : ($stats['pct_lettered'] >= 40 ? 'text-amber-600' : 'text-red-500') }}">
                    {{ $stats['pct_lettered'] }}%
                </p>
                <p class="text-xs text-gray-400 pb-0.5">{{ $stats['lettered'] }}/{{ $stats['total'] }} lignes</p>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="h-2 rounded-full {{ $stats['pct_lettered'] >= 80 ? 'bg-emerald-500' : ($stats['pct_lettered'] >= 40 ? 'bg-amber-400' : 'bg-red-400') }} transition-all"
                     style="width: {{ $stats['pct_lettered'] }}%"></div>
            </div>
        </div>

        {{-- Solde résiduel --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 mb-1">Solde résiduel non lettré</p>
            <p class="text-[17px] font-bold tabular-nums {{ $stats['solde_residuel'] == 0 ? 'text-emerald-600' : 'text-orange-600' }}">
                {{ number_format(abs($stats['solde_residuel']), 0, ',', ' ') }}
                <span class="text-sm font-normal text-gray-400">FCFA</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">
                @if($stats['solde_residuel'] > 0) Solde débiteur
                @elseif($stats['solde_residuel'] < 0) Solde créditeur
                @else Compte soldé ✓
                @endif
            </p>
        </div>

        {{-- Débit non lettré --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 mb-1">Débit non lettré</p>
            <p class="text-[17px] font-bold tabular-nums text-blue-600">
                {{ number_format($stats['unlettered_debit'], 0, ',', ' ') }}
                <span class="text-sm font-normal text-gray-400">FCFA</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['unlettered'] }} ligne(s) en attente</p>
        </div>

        {{-- Crédit non lettré --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 mb-1">Crédit non lettré</p>
            <p class="text-[17px] font-bold tabular-nums text-emerald-600">
                {{ number_format($stats['unlettered_credit'], 0, ',', ' ') }}
                <span class="text-sm font-normal text-gray-400">FCFA</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['groups_count'] }} groupe(s) lettré(s)</p>
        </div>
    </div>

    {{-- ── Alerte paires exactes disponibles ──────────────────────────────── --}}
    @php $pairsCount = count($exactPairs) / 2; @endphp
    @if($pairsCount > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-1.5 flex items-center gap-3 text-sm">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <span class="text-amber-800">
            <strong>{{ $pairsCount }} paire(s) exacte(s) détectée(s)</strong> — les lignes marquées ⚡ ont une contrepartie au même montant. Cliquez sur le badge pour sélectionner la paire.
        </span>
        <button type="button" @click="selectAllPairs()"
                class="ml-auto flex-shrink-0 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-[4px] transition-colors">
            Sélectionner toutes les paires
        </button>
    </div>
    @endif

    {{-- ── Grille principale ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- ── Lignes non lettrées ──────────────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden flex flex-col">

            {{-- Header --}}
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h2 class="font-semibold text-gray-800">
                    Lignes non lettrées
                    <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $lines->count() }})</span>
                </h2>
                <div class="flex gap-2 items-center flex-wrap">
                    <span class="text-xs text-gray-500" x-text="`${selected.length} sélectionnée(s)`"></span>
                    <button type="button" @click="autoLettrage()"
                            class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-[4px] transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Auto-lettrer
                    </button>
                    <button type="button" @click="applyLettrage()"
                            :disabled="selected.length < 2 || !isBalanced"
                            :title="!isBalanced && selected.length >= 2 ? 'Débit ≠ Crédit — sélection déséquilibrée' : ''"
                            class="text-xs bg-emerald-700 hover:bg-emerald-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                        Lettrer la sélection
                    </button>
                </div>
            </div>

            {{-- Balance indicator --}}
            <div class="px-5 py-2 bg-gray-50 border-b border-gray-100 flex gap-4 text-xs">
                <span>Débit : <strong class="text-blue-700 tabular-nums" x-text="fmt(selectedDebit)"></strong></span>
                <span>Crédit : <strong class="text-green-700 tabular-nums" x-text="fmt(selectedCredit)"></strong></span>
                <span x-show="selected.length >= 2"
                      :class="isBalanced ? 'text-emerald-600 font-bold' : 'text-red-500 font-medium'">
                    <span x-text="isBalanced ? '✓ Équilibré' : `✗ Écart : ${fmt(Math.abs(selectedDebit - selectedCredit))}`"></span>
                </span>
                <span x-show="selected.length < 2" class="text-gray-300">Sélectionnez ≥ 2 lignes</span>
            </div>

            {{-- Filtres --}}
            <div class="px-3 py-1.5 border-b border-gray-100 flex gap-2 flex-wrap">
                <div class="relative flex-1 min-w-32">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" placeholder="Rechercher…"
                           class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-[4px] focus:ring-1 focus:ring-violet-400 focus:border-violet-400">
                </div>
                <div class="flex rounded-[4px] border border-gray-200 overflow-hidden text-xs">
                    <button type="button" @click="typeFilter='all'"
                            :class="typeFilter==='all' ? 'bg-violet-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-3 py-1.5 font-medium transition-colors">Tout</button>
                    <button type="button" @click="typeFilter='debit'"
                            :class="typeFilter==='debit' ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-3 py-1.5 font-medium border-x border-gray-200 transition-colors">Débit</button>
                    <button type="button" @click="typeFilter='credit'"
                            :class="typeFilter==='credit' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-3 py-1.5 font-medium transition-colors">Crédit</button>
                </div>
                <select x-model="sortBy" class="text-xs border border-gray-200 rounded-[4px] px-2 py-1.5 focus:ring-1 focus:ring-violet-400">
                    <option value="date">Trier : Date</option>
                    <option value="amount_asc">Montant ↑</option>
                    <option value="amount_desc">Montant ↓</option>
                    <option value="label">Libellé</option>
                </select>
                <button type="button" x-show="selected.length > 0" @click="clearSelection()"
                        class="text-xs text-gray-400 hover:text-red-500 px-2 py-1.5 rounded-[4px] hover:bg-red-50 transition-colors">
                    Désélectionner
                </button>
            </div>

            {{-- Liste des lignes --}}
            <div class="divide-y divide-gray-50 overflow-y-auto flex-1" style="max-height: 460px">
                @forelse($lines as $line)
                @php
                    $hasPair  = array_key_exists($line->id, $exactPairs);
                    $pairId   = $hasPair ? $exactPairs[$line->id] : null;
                    $isOverdue = $line->due_date && $line->due_date->isPast() && $line->credit == 0;
                @endphp
                <label
                    class="flex items-start gap-3 px-3 py-1.5 cursor-pointer hover:bg-gray-50 transition-colors"
                    :class="{
                        'bg-violet-50 border-l-2 border-violet-400': selected.includes({{ $line->id }}),
                        'bg-amber-50/60': !selected.includes({{ $line->id }}) && isPairHighlighted({{ $line->id }}),
                    }"
                    data-line-id="{{ $line->id }}"
                    data-label="{{ strtolower($line->label) }}"
                    data-debit="{{ $line->debit ?? 0 }}"
                    data-credit="{{ $line->credit ?? 0 }}"
                    data-date="{{ $line->journalEntry?->entry_date?->format('Y-m-d') ?? '' }}"
                    x-show="lineVisible($el)"
                    x-bind:key="{{ $line->id }}">

                    <input type="checkbox"
                           class="mt-1 rounded accent-violet-600 w-3.5 h-3.5 flex-shrink-0"
                           value="{{ $line->id }}"
                           x-model="selected"
                           @change="onLineCheck({{ $line->id }}, {{ (float)($line->debit ?? 0) }}, {{ (float)($line->credit ?? 0) }})">

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $line->label }}</p>
                            @if($hasPair)
                            <button type="button"
                                    @click.prevent="selectPair({{ $line->id }}, {{ $pairId }})"
                                    class="flex-shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-amber-100 hover:bg-amber-200 text-amber-700 text-xs font-bold rounded transition-colors"
                                    title="Sélectionner cette paire équilibrée">
                                ⚡ paire
                            </button>
                            @endif
                            @if($isOverdue)
                            <span class="flex-shrink-0 inline-flex items-center px-1.5 py-0.5 bg-red-100 text-red-600 text-xs rounded">
                                En retard
                            </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $line->journalEntry?->entry_date?->format('d/m/Y') }}
                            · {{ $line->journalEntry?->number }}
                            @if($line->due_date)
                            · <span class="{{ $isOverdue ? 'text-red-500 font-medium' : '' }}">Éch. {{ $line->due_date->format('d/m/Y') }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="text-right flex-shrink-0">
                        @if($line->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-blue-700">D {{ number_format($line->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-emerald-700">C {{ number_format($line->credit, 0, ',', ' ') }}</p>
                        @endif
                    </div>
                </label>
                @empty
                <div class="px-4 py-12 text-center">
                    <svg class="w-10 h-10 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-gray-400 font-medium">Toutes les lignes sont lettrées ✓</p>
                </div>
                @endforelse

                {{-- Message "aucun résultat" après filtrage --}}
                <div x-show="filteredCount === 0 && {{ $lines->count() }} > 0"
                     class="px-4 py-8 text-center text-sm text-gray-400">
                    Aucune ligne ne correspond aux filtres.
                    <button type="button" @click="resetFilters()" class="text-violet-600 hover:underline ml-1">Réinitialiser</button>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-2.5 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-xs text-gray-500">
                <span x-text="`${filteredCount} ligne(s) affichée(s)`"></span>
                <span class="tabular-nums">
                    Débit : <strong class="text-blue-700">{{ number_format($stats['unlettered_debit'], 0, ',', ' ') }}</strong>
                    · Crédit : <strong class="text-emerald-700">{{ number_format($stats['unlettered_credit'], 0, ',', ' ') }}</strong>
                </span>
            </div>
        </div>

        {{-- ── Lignes lettrées ──────────────────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">
                    Lignes lettrées
                    <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $letteredGroups->count() }} groupe(s))</span>
                </h2>
                @if($letteredGroups->count() > 0)
                <span class="text-xs text-gray-400">{{ $stats['lettered'] }} ligne(s)</span>
                @endif
            </div>

            <div class="divide-y divide-gray-100 overflow-y-auto flex-1" style="max-height: 530px">
                @forelse($letteredGroups as $ref => $group)
                @php
                    $groupDebit   = $group->sum('debit');
                    $groupCredit  = $group->sum('credit');
                    $isLegacyAuto = str_contains($ref, 'AUTO');
                    $isLegacy     = str_contains($ref, 'LTR');
                    $groupDate    = $group->first()->journalEntry?->entry_date;
                    $letteredAt   = $group->first()->lettered_at;
                    $letteredBy   = $group->first()->letteredBy?->name;
                @endphp
                <div class="px-3 py-1.5 hover:bg-gray-50 transition-colors">
                    {{-- Groupe header --}}
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-block bg-violet-100 text-violet-700 text-xs font-bold px-2.5 py-0.5 rounded font-mono tracking-widest">{{ $ref }}</span>
                            @if($isLegacyAuto)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Auto
                            </span>
                            @elseif(!$isLegacy)
                            <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-500 text-xs rounded">Manuel</span>
                            @endif
                            <span class="text-xs text-gray-400 tabular-nums font-medium">
                                {{ number_format($groupDebit ?: $groupCredit, 0, ',', ' ') }} FCFA
                            </span>
                            @if($letteredAt)
                            <span class="text-xs text-gray-300" title="{{ $letteredBy }}">
                                · {{ $letteredAt->format('d/m/Y') }}
                                @if($letteredBy) par {{ $letteredBy }} @endif
                            </span>
                            @endif
                        </div>
                        <button type="button" @click="removeLettrage('{{ $ref }}')"
                                class="text-xs text-red-400 hover:text-red-600 font-medium hover:bg-red-50 px-2 py-0.5 rounded transition-colors">
                            Délettrer
                        </button>
                    </div>

                    {{-- Lignes du groupe --}}
                    @foreach($group as $line)
                    <div class="flex items-center justify-between py-1 pl-4 border-l-2 border-violet-100">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-700 truncate">{{ $line->label }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $line->journalEntry?->entry_date?->format('d/m/Y') }}
                                · {{ $line->journalEntry?->number }}
                            </p>
                        </div>
                        <div class="text-right ml-3 flex-shrink-0">
                            @if($line->debit > 0)
                            <p class="text-xs font-semibold tabular-nums text-blue-600">D {{ number_format($line->debit, 0, ',', ' ') }}</p>
                            @else
                            <p class="text-xs font-semibold tabular-nums text-emerald-600">C {{ number_format($line->credit, 0, ',', ' ') }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @empty
                <div class="px-4 py-12 text-center">
                    <svg class="w-10 h-10 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <p class="text-sm text-gray-400">Aucune ligne lettrée.</p>
                    <p class="text-xs text-gray-300 mt-1">Utilisez Auto-lettrer ou sélectionnez des lignes équilibrées.</p>
                </div>
                @endforelse
            </div>

            {{-- Footer --}}
            @if($letteredGroups->count() > 0)
            <div class="px-5 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-400">
                {{ $stats['lettered'] }} ligne(s) lettrée(s) en {{ $stats['groups_count'] }} groupe(s)
                · Taux : <strong class="{{ $stats['pct_lettered'] >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $stats['pct_lettered'] }}%</strong>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Toast ────────────────────────────────────────────────────────────── --}}
    <div x-show="toast" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-end="opacity-0"
         :class="toastError ? 'bg-red-600' : 'bg-gray-900'"
         class="fixed bottom-6 right-6 text-white text-sm font-medium px-5 py-3 rounded-[4px] shadow-lg z-50 flex items-center gap-2"
         style="display:none">
        <span x-text="toast"></span>
    </div>

</div>

@push('scripts')
<script>
function lettrageApp() {
    // Paires exactes pré-calculées par PHP : { lineId: pairedLineId, ... }
    const exactPairs = @json($exactPairs);

    return {
        // ── État ────────────────────────────────────────────────────────────
        selected: [],
        lineData: {},          // { id: { debit, credit } }
        toast: '',
        toastError: false,

        // ── Filtres ──────────────────────────────────────────────────────────
        search: '',
        typeFilter: 'all',     // 'all' | 'debit' | 'credit'
        sortBy: 'date',

        // ── Computed ─────────────────────────────────────────────────────────
        get selectedDebit()  { return this.selected.reduce((s,id) => s + (this.lineData[id]?.debit  || 0), 0); },
        get selectedCredit() { return this.selected.reduce((s,id) => s + (this.lineData[id]?.credit || 0), 0); },
        get isBalanced() {
            return this.selected.length >= 2
                && Math.abs(this.selectedDebit - this.selectedCredit) < 0.01
                && this.selectedDebit > 0;
        },
        get filteredCount() {
            return document.querySelectorAll('[data-line-id]')
                ? [...document.querySelectorAll('[data-line-id]')].filter(el => {
                    return window.getComputedStyle(el).display !== 'none';
                }).length
                : 0;
        },

        // ── Filtrage Alpine (appelé par x-show="lineVisible($el)") ───────────
        lineVisible(el) {
            const label  = (el.dataset.label  || '').toLowerCase();
            const debit  = parseFloat(el.dataset.debit  || 0);
            const credit = parseFloat(el.dataset.credit || 0);

            // Filtre texte
            if (this.search && !label.includes(this.search.toLowerCase())) return false;

            // Filtre type
            if (this.typeFilter === 'debit'  && !(debit  > 0)) return false;
            if (this.typeFilter === 'credit' && !(credit > 0)) return false;

            return true;
        },

        // ── Paires ──────────────────────────────────────────────────────────
        isPairHighlighted(id) {
            return exactPairs.hasOwnProperty(id);
        },

        selectPair(id1, id2) {
            // Décoche d'abord si déjà sélectionnés
            [id1, id2].forEach(id => {
                const strId = String(id);
                if (!this.selected.includes(strId)) {
                    this.selected.push(strId);
                }
            });
            // S'assurer que lineData est rempli
            const el1 = document.querySelector(`[data-line-id="${id1}"]`);
            const el2 = document.querySelector(`[data-line-id="${id2}"]`);
            if (el1) this.lineData[String(id1)] = { debit: parseFloat(el1.dataset.debit), credit: parseFloat(el1.dataset.credit) };
            if (el2) this.lineData[String(id2)] = { debit: parseFloat(el2.dataset.debit), credit: parseFloat(el2.dataset.credit) };
        },

        selectAllPairs() {
            const seen = new Set();
            Object.entries(exactPairs).forEach(([a, b]) => {
                const key = [Math.min(+a,+b), Math.max(+a,+b)].join('-');
                if (seen.has(key)) return;
                seen.add(key);
                this.selectPair(+a, +b);
            });
        },

        clearSelection() {
            this.selected = [];
        },

        resetFilters() {
            this.search = '';
            this.typeFilter = 'all';
            this.sortBy = 'date';
        },

        // ── Handlers ────────────────────────────────────────────────────────
        onLineCheck(id, debit, credit) {
            this.lineData[String(id)] = { debit, credit };
        },

        fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)); },

        showToast(msg, error = false) {
            this.toast = msg;
            this.toastError = error;
            setTimeout(() => this.toast = '', 4500);
        },

        // ── Actions AJAX ────────────────────────────────────────────────────
        async applyLettrage() {
            if (this.selected.length < 2) return;
            if (!this.isBalanced) {
                this.showToast('Le lettrage doit être équilibré (débit = crédit).', true);
                return;
            }
            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.apply') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ line_ids: this.selected.map(Number) }),
                });
                const data = await resp.json();
                if (data.ok) {
                    this.showToast(`✓ Lettrage ${data.ref} appliqué`);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.showToast(data.message, true);
                }
            } catch (e) {
                this.showToast('Erreur réseau. Réessayez.', true);
            }
        },

        async removeLettrage(ref) {
            const ok = await window.erpConfirm({
                message: `Supprimer le lettrage ${ref} ? Les lignes repasseront en « non lettrées ».`,
                confirmLabel: 'Supprimer',
                isDanger: true,
            });
            if (!ok) return;
            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.remove') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ ref }),
                });
                const data = await resp.json();
                if (data.ok) {
                    this.showToast(`Lettrage ${ref} supprimé.`);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.showToast(data.message, true);
                }
            } catch (e) {
                this.showToast('Erreur réseau. Réessayez.', true);
            }
        },

        async autoLettrage() {
            const accountId = {{ $selectedAccount?->id ?? 0 }};
            if (!accountId) { this.showToast('Sélectionnez d\'abord un compte.', true); return; }
            const ok = await window.erpConfirm({
                message: 'Lancer le lettrage automatique ? L\'algorithme apparie les couples et groupes débit/crédit ayant le même montant total.',
                title: 'Lettrage automatique',
                confirmLabel: 'Lancer',
                isDanger: false,
            });
            if (!ok) return;

            const btn = this.$el.querySelector('[\\@click="autoLettrage()"]');
            this.showToast('⏳ Analyse en cours…');

            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.auto-apply') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ account_id: accountId }),
                });
                const data = await resp.json();
                if (data.ok) {
                    this.showToast(data.message);
                    if (data.matched > 0) setTimeout(() => window.location.reload(), 1800);
                } else {
                    this.showToast(data.message || 'Erreur lors du lettrage automatique.', true);
                }
            } catch (e) {
                this.showToast('Erreur réseau. Réessayez.', true);
            }
        },
    };
}
</script>
@endpush
@endsection
