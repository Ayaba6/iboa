@extends('layouts.erp')
@section('title', 'Situation comptable')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Situation comptable</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Situation comptable</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tableau de bord financier au {{ now()->format('d/m/Y') }}</p>
        </div>
        <div class="flex gap-3">
            @if($brouillonCount > 0)
            <a href="{{ route('comptabilite.brouillard') }}"
               class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                {{ $brouillonCount }} brouillon(s) à valider
            </a>
            @endif
            <a href="{{ route('comptabilite.bilan') }}"
               class="h-8 inline-flex items-center text-[12px] text-emerald-700 hover:bg-emerald-50 font-semibold border border-emerald-300 bg-white px-3 rounded-[4px] transition-colors">
                Bilan →
            </a>
            <a href="{{ route('comptabilite.compte-de-resultat') }}"
               class="h-8 inline-flex items-center text-[12px] text-emerald-700 hover:bg-emerald-50 font-semibold border border-emerald-300 bg-white px-3 rounded-[4px] transition-colors">
                CDR →
            </a>
        </div>
    </div>

    {{-- KPI Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Trésorerie --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 font-medium uppercase mb-1">Trésorerie nette</p>
            <p class="text-[16px] font-bold tabular-nums {{ $totalTresorerie >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($totalTresorerie, 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA — Classe 5</p>
        </div>

        {{-- Clients --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 font-medium uppercase mb-1">Créances clients</p>
            <p class="text-[16px] font-bold tabular-nums text-blue-700">
                {{ number_format(max(0, $totalClients), 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA — Comptes 41x</p>
        </div>

        {{-- Fournisseurs --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-4">
            <p class="text-xs text-gray-500 font-medium uppercase mb-1">Dettes fournisseurs</p>
            <p class="text-[16px] font-bold tabular-nums text-orange-700">
                {{ number_format(max(0, $totalFournisseurs), 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA — Comptes 40x</p>
        </div>

        {{-- Résultat --}}
        <div class="rounded-[4px] border-2 p-4 {{ $resultat >= 0 ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
            <p class="text-xs font-medium uppercase mb-1 {{ $resultat >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $resultat >= 0 ? 'Bénéfice net' : 'Perte nette' }}
            </p>
            <p class="text-[16px] font-bold tabular-nums {{ $resultat >= 0 ? 'text-green-700' : 'text-red-700' }}">
                {{ number_format(abs($resultat), 0, ',', ' ') }}
            </p>
            <p class="text-xs mt-0.5 {{ $resultat >= 0 ? 'text-green-400' : 'text-red-400' }}">FCFA YTD</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Bilan synthétique --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Synthèse bilancielle</h2>
            </div>
            @php
                // Soldes RÉELS — un solde de sens anormal (ex. stock créditeur)
                // s'affiche en rouge avec alerte au lieu d'être écrasé à zéro.
                $fmtSigned = fn($v) => number_format($v, 0, ',', ' ');
                $totalActif  = $totalImmobilisations + $totalStocks + $totalClients + $totalTresorerie;
                $totalPassif = $totalCapitaux + $totalFournisseurs + $totalDettesFiscales + $resultat;
                $bilanEquilibre = abs($totalActif - $totalPassif) < 2;
            @endphp
            <div class="p-4 space-y-3">
                @foreach ([
                    ['Immobilisations (classe 2)', $totalImmobilisations, 'text-gray-600', false],
                    ['Stocks (classe 3)',          $totalStocks,          'text-gray-600', true],
                    ['Créances clients',           $totalClients,         'text-blue-600 font-medium', true],
                ] as [$label, $value, $labelCls, $flagNegative])
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm {{ $labelCls }}">
                        {{ $label }}
                        @if($flagNegative && $value < 0)
                        <span class="ml-1 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded px-1.5 py-0.5 align-middle">solde anormal</span>
                        @endif
                    </span>
                    <span class="tabular-nums font-semibold {{ $value < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $fmtSigned($value) }} FCFA</span>
                </div>
                @endforeach
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-emerald-600 font-medium">Trésorerie (classe 5)</span>
                    <span class="tabular-nums font-semibold {{ $totalTresorerie >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $fmtSigned($totalTresorerie) }} FCFA
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-t-2 border-gray-200 mt-2">
                    <span class="text-sm font-bold text-gray-800">TOTAL ACTIF</span>
                    <span class="tabular-nums font-bold {{ $totalActif < 0 ? 'text-red-700' : 'text-gray-900' }} text-lg">
                        {{ $fmtSigned($totalActif) }} FCFA
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50 mt-2">
                    <span class="text-sm text-gray-600">Capitaux propres (classe 1)</span>
                    <span class="tabular-nums font-semibold {{ $totalCapitaux < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $fmtSigned($totalCapitaux) }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-orange-600 font-medium">Dettes fournisseurs</span>
                    <span class="tabular-nums font-semibold text-orange-700">{{ $fmtSigned($totalFournisseurs) }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-orange-600 font-medium">Dettes fiscales &amp; sociales</span>
                    <span class="tabular-nums font-semibold text-orange-700">{{ $fmtSigned($totalDettesFiscales) }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm {{ $resultat >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium">Résultat de l'exercice</span>
                    <span class="tabular-nums font-semibold {{ $resultat >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $fmtSigned($resultat) }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-t-2 border-gray-200">
                    <span class="text-sm font-bold text-gray-800">TOTAL PASSIF</span>
                    <span class="tabular-nums font-bold {{ $totalPassif < 0 ? 'text-red-700' : 'text-gray-900' }} text-lg">
                        {{ $fmtSigned($totalPassif) }} FCFA
                    </span>
                </div>
                <div class="flex items-center justify-center pt-1">
                    @if($bilanEquilibre)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-3 py-1">
                        ✓ Bilan équilibré (actif = passif)
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-full px-3 py-1">
                        ⚠ Écart actif/passif : {{ $fmtSigned(abs($totalActif - $totalPassif)) }} FCFA
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Résultat synthétique --}}
        <div class="space-y-4">
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Résultat de l'exercice</h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-blue-600 font-medium">Total Produits (classe 7)</span>
                        <span class="tabular-nums font-semibold text-blue-700">{{ number_format(max(0,$totalProduits), 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-red-600 font-medium">Total Charges (classe 6)</span>
                        <span class="tabular-nums font-semibold text-red-700">{{ number_format(max(0,$totalCharges), 0, ',', ' ') }} FCFA</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-t-2 border-gray-200">
                        <span class="text-sm font-bold text-gray-800">{{ $resultat >= 0 ? 'BÉNÉFICE NET' : 'PERTE NETTE' }}</span>
                        <span class="tabular-nums font-bold text-lg {{ $resultat >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ number_format(abs($resultat), 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>
            </div>

            {{-- Trésorerie détail --}}
            @if($cashAccounts->isNotEmpty())
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Détail trésorerie</h2>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        @foreach($cashAccounts as $acc)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2.5 w-full">
                                <span class="font-mono text-blue-600 font-semibold text-xs">{{ $acc->code }}</span>
                                <span class="text-gray-700 ml-2">{{ $acc->name }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold whitespace-nowrap {{ $acc->net >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ number_format($acc->net, 0, ',', ' ') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

    </div>

    {{-- Recent entries --}}
    @if($recentEntries->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">Dernières écritures validées</h2>
            <a href="{{ route('comptabilite.journaux.index', ['status' => 'valide']) }}"
               class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Voir tout →</a>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#3b4248]">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Date</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Numéro</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase w-full">Description</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-semibold text-white uppercase whitespace-nowrap">Montant (XOF)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($recentEntries as $entry)
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('comptabilite.journaux.show', $entry) }}'">
                    <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ $entry->entry_date?->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 font-mono font-semibold text-blue-600 text-xs whitespace-nowrap">{{ $entry->number }}</td>
                    <td class="px-3 py-2.5 text-gray-700 w-full">
                        {{ Str::limit($entry->description, 60) }}
                        @if($entry->reference)
                        <span class="text-gray-400 text-xs ml-1">· {{ $entry->reference }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">
                        {{ number_format($entry->total_debit, 0, ',', ' ') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Arrêté au : <span class="text-white font-semibold">{{ now()->format('d/m/Y') }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
