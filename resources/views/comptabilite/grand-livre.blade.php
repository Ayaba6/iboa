@extends('layouts.erp')
@section('title', 'Grand livre')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Grand livre</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $company    = currentCompany();
    $fiscalYear = $company?->currentFiscalYear;
    $hasFilters = ($accountId || $classId || $search || $dateFrom || $dateTo
        || $filters['journal_type_id'] || $filters['partner'] || $filters['piece_from'] || $filters['piece_to']
        || $filters['account_from'] || $filters['account_to'] || ! $filters['validated_only']);
@endphp
<div class="space-y-3">

    {{-- Header — boutons maquette X3 --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Grand livre</h1>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="filter-form"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">
                Rechercher
            </button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">
                    Exporter ▾
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-[4px] shadow-lg z-20 min-w-[140px]">
                    <a href="{{ route('comptabilite.grand-livre.export', request()->query()) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50">Excel (.xlsx)</a>
                    <a href="{{ route('comptabilite.grand-livre.pdf', request()->query()) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50 border-t border-gray-100">PDF</a>
                </div>
            </div>
            <a href="{{ route('comptabilite.dashboard') }}"
               class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- Zone de filtres — 3 rangées, ordre maquette X3 --}}
    @php
        $mois = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
    @endphp
    <form method="GET" id="filter-form" class="bg-white rounded-[4px] border border-gray-200 p-4">
        {{-- account_id conservé pour la compat des liens « voir les lignes » --}}
        @if($accountId)<input type="hidden" name="account_id" value="{{ $accountId }}">@endif
        <div class="grid grid-cols-12 gap-x-3 gap-y-3">
            {{-- Rangée 1 : Société / Site / Exercice / Période du / Période au --}}
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">{{ $company?->code ?? 'Société principale' }}</p>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Exercice <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $fiscalYear?->label ?? date('Y') }}" class="{{ $inpRo }} tabular-nums" readonly>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période du</label>
                <div class="relative">
                    <select name="period_from" class="{{ $lk }}">
                        <option value="">—</option>
                        @foreach($mois as $m => $ml)
                        <option value="{{ $m }}" @selected($filters['period_from'] == $m)>{{ sprintf('%02d', $m) }} — {{ $ml }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Période au</label>
                <div class="relative">
                    <select name="period_to" class="{{ $lk }}">
                        <option value="">—</option>
                        @foreach($mois as $m => $ml)
                        <option value="{{ $m }}" @selected($filters['period_to'] == $m)>{{ sprintf('%02d', $m) }} — {{ $ml }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>

            {{-- Rangée 2 : Compte général du / au / Journal / Tiers / Devise --}}
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Compte général du <span class="text-red-500">*</span></label>
                <input type="text" name="account_from" value="{{ $filters['account_from'] }}" placeholder="401100" class="{{ $inp }} font-mono" list="accounts-list">
                @php $fromAcc = $filters['account_from'] ? $accounts->firstWhere('code', $filters['account_from']) : null; @endphp
                <p class="text-[12px] text-gray-500 mt-0.5">{{ $fromAcc?->name ?? '' }}&nbsp;</p>
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Compte général au <span class="text-red-500">*</span></label>
                <input type="text" name="account_to" value="{{ $filters['account_to'] }}" placeholder="401199" class="{{ $inp }} font-mono" list="accounts-list">
                @php $toAcc = $filters['account_to'] ? $accounts->firstWhere('code', $filters['account_to']) : null; @endphp
                <p class="text-[12px] text-gray-500 mt-0.5">{{ $toAcc?->name ?? '' }}&nbsp;</p>
            </div>
            <datalist id="accounts-list">
                @foreach($accounts as $acc)<option value="{{ $acc->code }}">{{ $acc->name }}</option>@endforeach
            </datalist>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Journal</label>
                <div class="relative">
                    <select name="journal_type_id" class="{{ $lk }}">
                        <option value="">—</option>
                        @foreach($journalTypes as $jt)
                        <option value="{{ $jt->id }}" @selected($filters['journal_type_id'] == $jt->id)>{{ $jt->code }} — {{ $jt->name }}</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Tiers</label>
                <input type="text" name="partner" value="{{ $filters['partner'] }}" placeholder="FOURN001" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA</p>
            </div>

            {{-- Rangée 3 : Date comptable du / au / Pièce du / au / validées uniquement --}}
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Date comptable du <span class="text-red-500">*</span></label>
                <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Date comptable au <span class="text-red-500">*</span></label>
                <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="{{ $inp }}">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Pièce du</label>
                <input type="text" name="piece_from" value="{{ $filters['piece_from'] }}" placeholder="EC-2026-0001" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Pièce au</label>
                <input type="text" name="piece_to" value="{{ $filters['piece_to'] }}" placeholder="EC-2026-0999" class="{{ $inp }} font-mono">
            </div>
            <div class="col-span-6 sm:col-span-2 flex items-end pb-1">
                <label class="flex items-center gap-1.5 text-[12.5px] text-gray-700 cursor-pointer">
                    <input type="hidden" name="validated_only" value="0">
                    <input type="checkbox" name="validated_only" value="1" @checked($filters['validated_only'])
                           class="w-3.5 h-3.5 rounded border-gray-400 text-emerald-600 focus:ring-emerald-400">
                    Écritures validées uniquement
                </label>
            </div>
        </div>
        @if($hasFilters)
        <div class="mt-2 pt-2 border-t border-gray-100 flex justify-between items-center">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Recherche libellé / n° pièce…" class="h-8 border border-gray-300 rounded-[3px] px-2 text-[13px] w-64">
            <a href="{{ route('comptabilite.grand-livre') }}" class="text-[12px] text-gray-500 hover:text-gray-700 underline">Réinitialiser les filtres</a>
        </div>
        @else
        <div class="mt-2 pt-2 border-t border-gray-100">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Recherche libellé / n° pièce…" class="h-8 border border-gray-300 rounded-[3px] px-2 text-[13px] w-64">
        </div>
        @endif
    </form>

    {{-- ── Mode plat consolidé (plage de comptes) ou mono-compte ──────────── --}}
    @if($flatMode || $account)
    @php
        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $balance     = $totalDebit - $totalCredit;
        $opening     = $openingBalance ?? 0;
        $closing     = $opening + $balance;
        $nbPieces    = $lines->pluck('journal_entry_id')->unique()->count();
        $compteLabel = $account
            ? $account->code
            : trim(($filters['account_from'] ?: '…') . ' → ' . ($filters['account_to'] ?: '…'));
        $compteSub = $account ? $account->name : 'plage de comptes';
    @endphp

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden"
         x-data="{ fs: false }" :class="fs ? 'fixed inset-2 z-50 shadow-2xl overflow-auto' : ''">
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gray-50">
            <span class="text-[12px] text-gray-500">{{ $lines->count() }} ligne(s) — {{ $nbPieces ?? $lines->pluck('journal_entry_id')->unique()->count() }} écriture(s)</span>
            <span class="flex items-center gap-2">
                <span class="text-[11px] text-gray-400">{{ $filters['validated_only'] ? 'Écritures validées' : 'Validées + brouillons' }} · XOF</span>
                <button type="button" @click="fs = !fs" @keydown.escape.window="fs = false"
                        class="w-6 h-6 flex items-center justify-center border border-gray-300 text-gray-500 hover:bg-gray-100 rounded-[3px]"
                        :title="fs ? 'Quitter le plein écran (Échap)' : 'Plein écran'">
                    <svg x-show="!fs" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    <svg x-show="fs" x-cloak class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>
        </div>
        @include('comptabilite._grand-livre-table', ['lines' => $lines, 'withAccount' => true])
    </div>

    {{-- [Maquette X3] Bandeau bas : compte / ouverture / mouvements / clôture / nb écritures --}}
    <div class="bg-white border border-gray-200 rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-5 gap-3 items-center text-center">
        <div>
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Compte sélectionné</p>
            <p class="text-[17px] font-bold text-emerald-700 font-mono leading-tight">{{ $compteLabel }}</p>
            <p class="text-[11px] text-gray-400 truncate">{{ $compteSub }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Solde d'ouverture</p>
            <p class="text-[17px] font-bold tabular-nums leading-tight {{ $opening < 0 ? 'text-red-600' : 'text-gray-900' }}">
                {{ $opening < 0 ? '-' : '' }}{{ number_format(abs($opening), 0, ',', ' ') }}
            </p>
            <p class="text-[11px] text-gray-400">XOF {{ $dateFrom ? 'au ' . \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '(depuis l\'origine)' }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Mouvements de la période</p>
            <p class="text-[15px] font-bold tabular-nums leading-tight">
                <span class="text-blue-700">D {{ number_format($totalDebit, 0, ',', ' ') }}</span>
                <span class="text-gray-300 mx-1">·</span>
                <span class="text-red-700">C {{ number_format($totalCredit, 0, ',', ' ') }}</span>
            </p>
            <p class="text-[11px] text-gray-400">XOF</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Solde de clôture</p>
            <p class="text-[17px] font-bold tabular-nums leading-tight {{ $closing < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                {{ $closing < 0 ? '-' : '' }}{{ number_format(abs($closing), 0, ',', ' ') }}
            </p>
            <p class="text-[11px] text-gray-400">XOF {{ $dateTo ? 'au ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '' }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Nombre d'écritures</p>
            <p class="text-[17px] font-bold tabular-nums text-gray-900 leading-tight">{{ $nbPieces }}</p>
            <p class="text-[11px] text-gray-400">écritures comptables</p>
        </div>
    </div>

    {{-- ── Multi-account mode (cartes par compte) ─────────────────────────── --}}
    @elseif($accountGroups->isNotEmpty())
    @php
        $currentClassNum = null;
        $grandDebit      = $accountGroups->sum('total_debit');
        $grandCredit     = $accountGroups->sum('total_credit');
        $grandBalance    = $grandDebit - $grandCredit;
    @endphp

    <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2"
         x-data="{
             allOpen: false,
             toggleAll() {
                 this.allOpen = !this.allOpen;
                 document.querySelectorAll('[data-gl-account]').forEach(el => {
                     el._x_dataStack[0].open = this.allOpen;
                 });
             }
         }">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]">
            <span class="text-gray-500">
                <span class="font-semibold text-gray-900">{{ $accountGroups->count() }}</span> compte(s) avec mouvements
            </span>
            <button type="button" @click="toggleAll()"
                    class="h-7 inline-flex items-center gap-1.5 text-[12px] text-emerald-700 hover:text-emerald-900 border border-emerald-300 hover:border-emerald-500 rounded-[4px] px-2.5 transition-colors">
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="allOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                <span x-text="allOpen ? 'Tout replier' : 'Tout déplier'">Tout déplier</span>
            </button>
            <span class="ml-auto flex flex-wrap gap-4 tabular-nums font-semibold">
                <span class="text-blue-700">Total D : {{ number_format($grandDebit, 0, ',', ' ') }}</span>
                <span class="text-red-700">Total C : {{ number_format($grandCredit, 0, ',', ' ') }}</span>
                @if($grandBalance != 0)
                <span class="{{ $grandBalance >= 0 ? 'text-emerald-700' : 'text-orange-700' }}">
                    Solde : {{ number_format(abs($grandBalance), 0, ',', ' ') }} {{ $grandBalance >= 0 ? 'D' : 'C' }}
                </span>
                @else
                <span class="text-gray-400">Équilibré</span>
                @endif
            </span>
        </div>
    </div>

    @foreach($accountGroups as $group)
    @php $classNum = substr($group['account']->code, 0, 1); @endphp

    @if($classNum !== $currentClassNum)
    @php $currentClassNum = $classNum; @endphp
    <div class="px-4 py-2 bg-[#eef5f0] border border-emerald-100 rounded-[4px] text-[12px] font-bold text-emerald-900 uppercase tracking-wide">
        Classe {{ $classNum }} — {{ $classes->firstWhere('number', (int) $classNum)?->name ?? '' }}
    </div>
    @endif

    @php
        $bal      = $group['total_debit'] - $group['total_credit'];
        $maxLines = 8;
        $preview  = $group['lines']->take($maxLines);
        $hasMore  = $group['lines']->count() > $maxLines;
        $moreCount = $group['lines']->count() - $maxLines;
    @endphp
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden"
         x-data="{ open: false }"
         data-gl-account>

        <button type="button" @click="open = !open"
                class="w-full px-3 py-1.5 bg-gray-50 border-b border-gray-100 hover:bg-emerald-50/50 transition-colors text-left">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform duration-200"
                         :class="open ? 'rotate-0' : '-rotate-90'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span class="font-mono font-bold text-blue-700 flex-shrink-0 text-[13px]">{{ $group['account']->code }}</span>
                    <span class="text-gray-800 font-medium truncate text-[13px]">{{ $group['account']->name }}</span>
                    <span class="flex-shrink-0 text-[11px] text-gray-400 bg-gray-200 rounded-full px-2 py-0.5">
                        {{ $group['lines']->count() }} ligne(s)
                    </span>
                </div>
                <div class="flex-shrink-0 flex gap-4 text-[12px] tabular-nums" @click.stop>
                    <span class="text-blue-700 font-semibold">D: {{ number_format($group['total_debit'], 0, ',', ' ') }}</span>
                    <span class="text-red-700 font-semibold">C: {{ number_format($group['total_credit'], 0, ',', ' ') }}</span>
                    @if($bal != 0)
                    <span class="{{ $bal >= 0 ? 'text-emerald-700' : 'text-orange-700' }} font-semibold">
                        {{ number_format(abs($bal), 0, ',', ' ') }} {{ $bal >= 0 ? 'D' : 'C' }}
                    </span>
                    @else
                    <span class="text-gray-400 font-semibold">Équilibré</span>
                    @endif
                </div>
            </div>
        </button>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            @include('comptabilite._grand-livre-table', ['lines' => $preview])

            @if($hasMore)
            <div class="px-3 py-2 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                <span class="text-[12px] text-gray-500">
                    Affichage limité à {{ $maxLines }} lignes sur {{ $group['lines']->count() }}
                </span>
                <a href="{{ route('comptabilite.grand-livre', array_merge(request()->query(), ['account_id' => $group['account']->id])) }}"
                   class="inline-flex items-center gap-1 text-[12px] font-semibold text-emerald-700 hover:text-emerald-900 transition-colors">
                    Voir les {{ $moreCount }} ligne(s) restante(s) →
                </a>
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @else
    <div class="bg-white rounded-[4px] border border-gray-300 py-16 text-center">
        <div class="flex flex-col items-center gap-3 text-gray-400">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-medium">Aucun mouvement comptable trouvé</p>
            @if($hasFilters)
            <a href="{{ route('comptabilite.grand-livre') }}"
               class="text-emerald-700 hover:text-emerald-900 text-sm">Effacer les filtres</a>
            @endif
        </div>
    </div>
    @endif

    {{-- [Maquette X3] Barre de contexte pied de page --}}
    @php
        $periodeLabel = ($filters['period_from'] || $filters['period_to'])
            ? sprintf('%02d à %02d (%s à %s)',
                (int) ($filters['period_from'] ?: 1), (int) ($filters['period_to'] ?: 12),
                mb_substr($mois[(int) ($filters['period_from'] ?: 1)], 0, 4) . '.',
                mb_substr($mois[(int) ($filters['period_to'] ?: 12)], 0, 4) . '.')
            : (($dateFrom || $dateTo)
                ? (($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '…') . ' → ' . ($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '…'))
                : 'Toutes');
    @endphp
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Exercice : <span class="text-white font-semibold">{{ $fiscalYear?->label ?? date('Y') }}</span></span>
        <span class="border-l border-white/10 pl-6">Période : <span class="text-white font-semibold">{{ $periodeLabel }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
