@extends('layouts.erp')
@section('title', 'Bilan SYSCOHADA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bilan SYSCOHADA</span>
@endsection

@section('content')
@php
    $lbl   = 'block text-[12px] font-semibold text-gray-800 mb-1 whitespace-nowrap overflow-hidden';
    $inp   = 'w-full h-8 px-2 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpRo = 'w-full h-8 px-2 border border-gray-300 rounded-[3px] text-[14px] bg-gray-100 text-gray-700';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-gray-400 rounded-[3px] text-[14px] text-gray-900 bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>';
    $company = currentCompany();
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    // Compte d'amortissement / provision : 28x 29x 39x 49x 59x
    $isAmort = fn ($code) => in_array(substr($code, 0, 2), ['28', '29', '39', '49', '59']);
    $bilanEcart = $totalActif - $totalPassif;
@endphp
<div class="space-y-3">

    {{-- Header — boutons maquette --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Bilan SYSCOHADA</h1>
        <div class="flex items-center gap-1.5">
            <button type="submit" form="bilan-form"
                    class="text-[14px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-[4px] transition-colors">Rechercher</button>
            <button type="button" onclick="window.print()"
                    class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Imprimer</button>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Exporter ▾</button>
                <div x-show="open" x-cloak class="absolute right-0 mt-1 bg-white border border-gray-200 rounded-[4px] shadow-lg z-20 min-w-[140px]">
                    <a href="{{ route('comptabilite.bilan.pdf', request()->only('fiscal_year_id')) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50">PDF</a>
                    @if(isset($netResult) && $netResult !== 0)
                    <a href="{{ route('comptabilite.affectation-resultat', request()->only('fiscal_year_id')) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50 border-t border-gray-100">Affecter le résultat</a>
                    @endif
                    <a href="{{ route('comptabilite.compte-de-resultat', request()->only('fiscal_year_id')) }}" class="block px-3 py-2 text-[13px] text-gray-700 hover:bg-emerald-50 border-t border-gray-100">Compte de résultat</a>
                </div>
            </div>
            <a href="{{ route('comptabilite.dashboard') }}"
               class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Abandon</a>
        </div>
    </div>

    {{-- Zone de filtres — fiche maquette --}}
    <form method="GET" id="bilan-form" action="{{ route('comptabilite.bilan') }}" class="bg-white rounded-[4px] border border-gray-200 p-4">
        <div class="grid grid-cols-12 gap-x-3 gap-y-3">
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Société <span class="text-red-500">*</span></label>
                <input type="text" value="{{ $company?->name }}" class="{{ $inpRo }}" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Société principale</p>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Site <span class="text-red-500">*</span></label>
                <input type="text" value="01" class="{{ $inpRo }} font-mono" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Site principal</p>
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Exercice <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select name="fiscal_year_id" class="{{ $lk }}">
                        <option value="">Tous exercices (cumul)</option>
                        @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected($selectedFy?->id == $fy->id)>{{ $fy->label }}@if($fy->is_current) ★ @endif</option>
                        @endforeach
                    </select>{!! $caret !!}
                </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Devise <span class="text-red-500">*</span></label>
                <input type="text" value="XOF" class="{{ $inpRo }} font-mono" readonly>
                <p class="text-[12px] text-gray-500 mt-0.5">Franc CFA</p>
            </div>
            <div class="col-span-6 sm:col-span-2">
                <label class="{{ $lbl }}">Date d'arrêté <span class="text-red-500">*</span></label>
                <input type="date" name="date_arrete" value="{{ $dateArrete ?? '' }}" class="{{ $inp }}">
            </div>

            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Type d'état</label>
                <input type="text" value="Bilan" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-6 sm:col-span-3">
                <label class="{{ $lbl }}">Modèle</label>
                <input type="text" value="SYSCOHADA Normal" class="{{ $inpRo }}" readonly>
            </div>
            <div class="col-span-12 sm:col-span-6 flex items-end pb-1 gap-4">
                <span class="text-[12.5px] text-gray-500">Écritures validées uniquement — les brouillons n'impactent jamais le bilan.</span>
                @if($bilanEcart != 0)
                <span class="text-[12.5px] font-semibold text-red-600">⚠ Écart Actif/Passif : {{ $fmt(abs($bilanEcart)) }} F</span>
                @endif
            </div>
        </div>
    </form>

    {{-- ═══ Tableaux ACTIF / PASSIF format SYSCOHADA ═══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 items-start">

        {{-- ACTIF : Brut / Amort.-Prov. / Net N / Net N-1 --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="min-w-full text-[13px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">ACTIF</th>
                        <th class="px-3 py-1.5 text-right">Brut</th>
                        <th class="px-3 py-1.5 text-right">Amort./Prov.</th>
                        <th class="px-3 py-1.5 text-right">Net N</th>
                        @if($prevFy)<th class="px-3 py-1.5 text-right">Net N-1</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $totBrutActif = 0; $totAmortActif = 0; $totNetActif = 0; @endphp
                    @foreach($actifX3 as $rubrique => $postes)
                    @php
                        $rBrut  = array_sum(array_column($postes, 0));
                        $rAmort = array_sum(array_column($postes, 1));
                        $rNet   = $rBrut - $rAmort;
                        $totBrutActif += $rBrut; $totAmortActif += $rAmort; $totNetActif += $rNet;
                        $rPrevNet = $actifX3Prev
                            ? array_sum(array_map(fn ($p) => $p[0] - $p[1], $actifX3Prev[$rubrique] ?? []))
                            : null;
                    @endphp
                    <tr class="bg-[#eef5f0] font-bold text-[12px] uppercase tracking-wide text-emerald-900">
                        <td class="px-3 py-1.5">{{ $rubrique }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($rBrut) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $rAmort ? $fmt($rAmort) : '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($rNet) }}</td>
                        @if($prevFy)<td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ $rPrevNet !== null ? $fmt($rPrevNet) : '—' }}</td>@endif
                    </tr>
                    @foreach($postes as $poste => [$brut, $amort, $indent])
                    @php
                        $net = $brut - $amort;
                        $prevPoste = $actifX3Prev[$rubrique][$poste] ?? null;
                        $prevNet = $prevPoste ? ($prevPoste[0] - $prevPoste[1]) : 0;
                    @endphp
                    @if($brut != 0 || $amort != 0 || $prevNet != 0)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-blue-50/40 transition-colors">
                        <td class="px-3 py-1 {{ $indent ? 'pl-10' : 'pl-6' }} text-gray-700 text-[12.5px]">{{ $poste }}</td>
                        <td class="px-3 py-1 text-right tabular-nums {{ $brut < 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $brut ? $fmt($brut) : '—' }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-600">{{ $amort ? $fmt($amort) : '—' }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-medium {{ $net < 0 ? 'text-red-600' : '' }}">{{ $fmt($net) }}</td>
                        @if($prevFy)<td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ $prevNet != 0 ? $fmt($prevNet) : '—' }}</td>@endif
                    </tr>
                    @endif
                    @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $prevTotNetActif = $actifX3Prev
                            ? array_sum(array_map(fn ($ps) => array_sum(array_map(fn ($p) => $p[0] - $p[1], $ps)), $actifX3Prev))
                            : null;
                    @endphp
                    <tr class="text-white font-bold" style="background:#1d4ed8">
                        <td class="px-3 py-1.5 text-[11px] uppercase tracking-wide">Total actif</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($totBrutActif) }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $totAmortActif ? $fmt($totAmortActif) : '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($totNetActif) }}</td>
                        @if($prevFy)<td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $prevTotNetActif !== null ? $fmt($prevTotNetActif) : '—' }}</td>@endif
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        {{-- PASSIF : Montant N / Montant N-1 --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="overflow-x-auto">
            <table class="min-w-full text-[13px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">PASSIF</th>
                        <th class="px-3 py-1.5 text-right">Montant N</th>
                        @if($prevFy)<th class="px-3 py-1.5 text-right">Montant N-1</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $totNetPassif = 0; @endphp
                    @foreach($passifX3 as $rubrique => $postes)
                    @php
                        $rNet = array_sum(array_column($postes, 0));
                        $totNetPassif += $rNet;
                        $rPrev = $passifX3Prev
                            ? array_sum(array_column($passifX3Prev[$rubrique] ?? [], 0))
                            : null;
                    @endphp
                    <tr class="bg-[#eef5f0] font-bold text-[12px] uppercase tracking-wide text-emerald-900">
                        <td class="px-3 py-1.5">{{ $rubrique }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $rNet < 0 ? 'text-red-700' : '' }}">
                            {{ $rNet < 0 ? '(' . $fmt(abs($rNet)) . ')' : $fmt($rNet) }}
                        </td>
                        @if($prevFy)<td class="px-3 py-1.5 text-right tabular-nums text-emerald-700">{{ $rPrev !== null ? $fmt($rPrev) : '—' }}</td>@endif
                    </tr>
                    @foreach($postes as $poste => [$montant, $indent])
                    @php
                        $prevMontant = $passifX3Prev[$rubrique][$poste][0] ?? 0;
                        $isLossLine = str_contains($poste, 'Résultat') && $montant < 0;
                    @endphp
                    @if($montant != 0 || $prevMontant != 0)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/40 transition-colors {{ $isLossLine ? '!bg-red-50' : '' }}">
                        <td class="px-3 py-1 {{ $indent ? 'pl-10' : 'pl-6' }} text-[12.5px] {{ $isLossLine ? 'text-red-700' : 'text-gray-700' }}">
                            {{ $poste }}
                            @if(str_contains($poste, 'Résultat'))
                            <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-[3px] font-semibold {{ $isLossLine ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-700' }}">calculé</span>
                            @endif
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums font-medium whitespace-nowrap {{ $montant < 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $montant < 0 ? '(' . $fmt(abs($montant)) . ')' : $fmt($montant) }}
                        </td>
                        @if($prevFy)
                        <td class="px-3 py-1 text-right tabular-nums text-gray-500">{{ $prevMontant != 0 ? $fmt($prevMontant) : '—' }}</td>
                        @endif
                    </tr>
                    @endif
                    @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $prevTotNetPassif = $passifX3Prev
                            ? array_sum(array_map(fn ($ps) => array_sum(array_column($ps, 0)), $passifX3Prev))
                            : null;
                    @endphp
                    <tr class="text-white font-bold" style="background:#065f46">
                        <td class="px-3 py-1.5 text-[11px] uppercase tracking-wide">Total passif</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($totNetPassif) }}</td>
                        @if($prevFy)<td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $prevTotNetPassif !== null ? $fmt($prevTotNetPassif) : '—' }}</td>@endif
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
    </div>

    {{-- ═══ Bandeau bas 4 zones (maquette X3) ═══ --}}
    <div class="bg-white border {{ $bilanEcart == 0 ? 'border-emerald-200' : 'border-red-300' }} rounded-[4px] p-3 grid grid-cols-2 lg:grid-cols-4 gap-3 items-center text-center">
        <div>
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total actif</p>
            <p class="text-[17px] font-bold tabular-nums text-blue-800 leading-tight">{{ $fmt($totalActif) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] text-gray-400">au {{ $dateArrete ? \Carbon\Carbon::parse($dateArrete)->format('d/m/Y') : now()->format('d/m/Y') }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Total passif</p>
            <p class="text-[17px] font-bold tabular-nums text-emerald-800 leading-tight">{{ $fmt($totalPassif) }} <span class="text-[11px] font-normal text-gray-400">XOF</span></p>
            <p class="text-[11px] {{ $bilanEcart == 0 ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold' }}">
                {{ $bilanEcart == 0 ? '⚖ équilibré' : '⚠ écart ' . $fmt(abs($bilanEcart)) }}
            </p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Résultat net</p>
            <p class="text-[17px] font-bold tabular-nums leading-tight {{ $netResult < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                {{ $netResult < 0 ? '-' : '' }}{{ $fmt(abs($netResult)) }} <span class="text-[11px] font-normal text-gray-400">XOF</span>
            </p>
            <p class="text-[11px] text-gray-400">{{ $selectedFy?->label ? 'Exercice ' . $selectedFy->label : 'cumul' }}</p>
        </div>
        <div class="border-l border-gray-100">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Trésorerie nette</p>
            <p class="text-[17px] font-bold tabular-nums leading-tight {{ $tresoNette < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                {{ $tresoNette < 0 ? '-' : '' }}{{ $fmt(abs($tresoNette)) }} <span class="text-[11px] font-normal text-gray-400">XOF</span>
            </p>
            <p class="text-[11px] text-gray-400">Disponibilités − banques créditrices</p>
        </div>
    </div>

    {{-- Barre de contexte pied de page --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Exercice : <span class="text-white font-semibold">{{ $selectedFy?->label ?? 'Cumul' }}</span></span>
        <span class="border-l border-white/10 pl-6">Date d'arrêté : <span class="text-white font-semibold">{{ $dateArrete ? \Carbon\Carbon::parse($dateArrete)->format('d/m/Y') : '—' }}</span></span>
        <span class="border-l border-white/10 pl-6">Devise : <span class="text-white font-semibold">XOF</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
