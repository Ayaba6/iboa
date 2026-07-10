@extends('layouts.erp')
@section('title', 'Comptabilité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptabilité</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
@endphp

<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Comptabilité</h1>
            <p class="text-sm text-gray-500">
                Exercice : <span class="font-medium text-gray-700">{{ $fiscalYear?->label ?? 'non défini' }}</span>
                @if($fiscalYear)
                    · {{ $fiscalYear->starts_at->format('d/m/Y') }} → {{ $fiscalYear->ends_at->format('d/m/Y') }}
                    @if($fiscalYear->status !== 'ouvert')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-100 text-orange-700 ml-1">{{ ucfirst($fiscalYear->status) }}</span>
                    @endif
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('accounting.write')
            <a href="{{ route('comptabilite.journaux.create') }}"
               class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle écriture
            </a>
            @endcan
            <a href="{{ route('comptabilite.journaux.export-pdf') }}" class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">
                Export PDF
            </a>
        </div>
    </div>

    {{-- KPIs principaux --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {{-- Résultat --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat de l'exercice</p>
            <p class="mt-2 text-[19px] font-bold tabular-nums {{ $kpis['resultat'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $kpis['resultat'] >= 0 ? '+' : '' }}{{ $fmt($kpis['resultat']) }}
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Produits {{ $fmt($kpis['produits']) }} − Charges {{ $fmt($kpis['charges']) }}
            </p>
        </div>

        {{-- Trésorerie --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Trésorerie nette</p>
            <p class="mt-2 text-[19px] font-bold tabular-nums {{ $kpis['tresorerie'] >= 0 ? 'text-blue-600' : 'text-red-600' }}">
                {{ $fmt($kpis['tresorerie']) }}
            </p>
            <p class="text-xs text-gray-500 mt-2">Comptes 52 · 53 · 57 (banques, instr., caisse)</p>
        </div>

        {{-- Activité du mois --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Activité — {{ now()->translatedFormat('F Y') }}</p>
            <p class="mt-2 text-[19px] font-bold tabular-nums text-gray-900">{{ $monthly['validees_mois'] }}</p>
            <p class="text-xs text-gray-500 mt-2">
                écritures validées · volume {{ $fmt($monthly['volume_mois']) }}
                @if($monthly['brouillons'] > 0)
                    · <span class="text-amber-600 font-medium">{{ $monthly['brouillons'] }} brouillon{{ $monthly['brouillons']>1?'s':'' }}</span>
                @endif
            </p>
        </div>

        {{-- Créances --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Créances clients</p>
            <p class="mt-2 text-[16px] font-bold tabular-nums text-amber-600">{{ $fmt($kpis['creances']) }}</p>
            <p class="text-xs text-gray-500 mt-2">Compte 41 — ce que les clients vous doivent</p>
        </div>

        {{-- Dettes --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Dettes fournisseurs</p>
            <p class="mt-2 text-[16px] font-bold tabular-nums text-orange-600">{{ $fmt($kpis['dettes']) }}</p>
            <p class="text-xs text-gray-500 mt-2">Compte 40 — ce que vous devez aux fournisseurs</p>
        </div>

        {{-- Solde commercial --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Position commerciale nette</p>
            <p class="mt-2 text-[16px] font-bold tabular-nums text-gray-900">{{ $fmt($kpis['creances'] - $kpis['dettes']) }}</p>
            <p class="text-xs text-gray-500 mt-2">Créances − Dettes</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Top comptes du mois --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Top comptes mouvementés ce mois</h2>
                <a href="{{ route('comptabilite.grand-livre') }}" class="text-xs text-violet-600 hover:underline">Grand livre →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Compte</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Débit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Crédit</th>
                        <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Volume</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($topAccounts as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5">
                            <span class="font-mono font-semibold text-violet-700">{{ $row->code }}</span>
                            <span class="text-gray-600 ml-2 text-xs">{{ $row->name }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ $fmt($row->sd) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ $fmt($row->sc) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium text-gray-900">{{ $fmt($row->volume) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">Aucune activité ce mois.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Brouillons à traiter --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Brouillons à valider</h2>
                <a href="{{ route('comptabilite.journaux.index', ['status' => 'brouillon']) }}" class="text-xs text-violet-600 hover:underline">Tous →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($drafts as $d)
                <a href="{{ route('comptabilite.journaux.show', $d) }}" class="block px-3 py-1.5 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-semibold text-violet-700">{{ $d->number }}</span>
                        <span class="text-xs text-gray-500">{{ $d->entry_date?->format('d/m/Y') }}</span>
                    </div>
                    <p class="text-sm text-gray-900 mt-0.5 truncate">{{ $d->description }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $d->journalType?->code }} · {{ $fmt($d->total_debit) }}
                        @if(!$d->isBalanced())
                            <span class="text-red-600 ml-1">⚠ déséquilibré</span>
                        @endif
                    </p>
                </a>
                @empty
                <div class="px-4 py-8 text-center text-gray-400 text-sm">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Aucun brouillon en attente.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Accès rapides --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Accès rapides</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 text-sm">
            <a href="{{ route('comptabilite.journaux.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📒 Journaux</a>
            <a href="{{ route('comptabilite.grand-livre') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📖 Grand livre</a>
            <a href="{{ route('comptabilite.balance') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">⚖ Balance</a>
            <a href="{{ route('comptabilite.balance-auxiliaire') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">👥 Balance aux.</a>
            <a href="{{ route('comptabilite.bilan') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📊 Bilan</a>
            <a href="{{ route('comptabilite.compte-de-resultat') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📈 Résultat</a>
            <a href="{{ route('comptabilite.sig') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📐 SIG</a>
            <a href="{{ route('comptabilite.plan-comptable.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">🗂 Plan comptable</a>
            <a href="{{ route('comptabilite.lettrage.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">🔗 Lettrage</a>
            <a href="{{ route('comptabilite.rapprochement.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">🏦 Rapprochement</a>
            <a href="{{ route('comptabilite.tva.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📋 TVA</a>
            <a href="{{ route('comptabilite.fec.export') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📤 Export FEC</a>
            <a href="{{ route('comptabilite.periods.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">🔒 Périodes</a>
            <a href="{{ route('settings.fiscal-years.index') }}" class="border border-gray-200 rounded-[4px] p-3 hover:bg-gray-50 text-center">📅 Exercices</a>
        </div>
    </div>

</div>
@endsection
