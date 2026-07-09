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
            <h1 class="text-[16px] font-bold text-gray-900">Situation comptable</h1>
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
               class="text-sm text-violet-600 hover:text-violet-800 font-medium border border-violet-200 px-3 py-1.5 rounded-[4px]">
                Bilan →
            </a>
            <a href="{{ route('comptabilite.compte-de-resultat') }}"
               class="text-sm text-violet-600 hover:text-violet-800 font-medium border border-violet-200 px-3 py-1.5 rounded-[4px]">
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
            <div class="p-4 space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Immobilisations (classe 2)</span>
                    <span class="tabular-nums font-semibold text-gray-900">{{ number_format(max(0,$totalImmobilisations), 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Stocks (classe 3)</span>
                    <span class="tabular-nums font-semibold text-gray-900">{{ number_format(max(0,$totalStocks), 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-blue-600 font-medium">Créances clients</span>
                    <span class="tabular-nums font-semibold text-blue-700">{{ number_format(max(0,$totalClients), 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-emerald-600 font-medium">Trésorerie (classe 5)</span>
                    <span class="tabular-nums font-semibold {{ $totalTresorerie >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ number_format($totalTresorerie, 0, ',', ' ') }} FCFA
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-t-2 border-gray-200 mt-2">
                    <span class="text-sm font-bold text-gray-800">TOTAL ACTIF</span>
                    <span class="tabular-nums font-bold text-gray-900 text-lg">
                        {{ number_format(max(0,$totalImmobilisations) + max(0,$totalStocks) + max(0,$totalClients) + max(0,$totalTresorerie), 0, ',', ' ') }} FCFA
                    </span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50 mt-2">
                    <span class="text-sm text-gray-600">Capitaux propres (classe 1)</span>
                    <span class="tabular-nums font-semibold text-gray-900">{{ number_format(max(0,$totalCapitaux), 0, ',', ' ') }} FCFA</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-50">
                    <span class="text-sm text-orange-600 font-medium">Dettes fournisseurs</span>
                    <span class="tabular-nums font-semibold text-orange-700">{{ number_format(max(0,$totalFournisseurs), 0, ',', ' ') }} FCFA</span>
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
                                <span class="font-mono text-violet-600 font-semibold text-xs">{{ $acc->code }}</span>
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
               class="text-xs text-violet-600 hover:text-violet-800 font-medium">Voir tout →</a>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                    <th class="px-3 py-1.5 text-left text-xs font-semibold text-gray-500 uppercase w-full">Description</th>
                    <th class="px-3 py-1.5 text-right text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">Montant</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($recentEntries as $entry)
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('comptabilite.journaux.show', $entry) }}'">
                    <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ $entry->entry_date?->format('d/m/Y') }}</td>
                    <td class="px-3 py-2.5 font-mono font-semibold text-violet-700 text-xs whitespace-nowrap">{{ $entry->number }}</td>
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

</div>
@endsection
