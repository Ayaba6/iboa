@extends('layouts.erp')
@section('title', 'Comptabilité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptabilité</span>
@endsection

@section('content')
@php
    $fmt   = fn($n) => number_format((int) $n, 0, ',', ' ');
    $band  = 'px-4 py-2 border-b border-gray-200 bg-[#eef5f0] flex items-center justify-between';
    $bandH = 'text-[12px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp

<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Comptabilité</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Exercice : <span class="font-medium text-gray-700">{{ $fiscalYear?->label ?? 'non défini' }}</span>
                @if($fiscalYear)
                    · {{ $fiscalYear->starts_at->format('d/m/Y') }} → {{ $fiscalYear->ends_at->format('d/m/Y') }}
                    @if($fiscalYear->status !== 'ouvert')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-orange-100 text-orange-700 ml-1">{{ ucfirst($fiscalYear->status) }}</span>
                    @endif
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @can('accounting.write')
            <a href="{{ route('comptabilite.journaux.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                + Nouvelle écriture
            </a>
            @endcan
            <a href="{{ route('comptabilite.journaux.export-pdf') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">
                Export PDF
            </a>
        </div>
    </div>

    {{-- KPIs denses X3 --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-1.5">
        <div class="bg-white rounded-[4px] border {{ $kpis['resultat'] >= 0 ? 'border-emerald-200' : 'border-red-300' }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Résultat de l'exercice</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums leading-none {{ $kpis['resultat'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $kpis['resultat'] >= 0 ? '+' : '' }}{{ $fmt($kpis['resultat']) }} F
            </p>
            <p class="mt-0.5 text-[11px] text-gray-400">Produits {{ $fmt($kpis['produits']) }} − Charges {{ $fmt($kpis['charges']) }}</p>
        </div>

        <div class="bg-white rounded-[4px] border {{ $kpis['tresorerie'] >= 0 ? 'border-blue-200' : 'border-red-300' }} px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Trésorerie nette</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums leading-none {{ $kpis['tresorerie'] >= 0 ? 'text-blue-700' : 'text-red-600' }}">{{ $fmt($kpis['tresorerie']) }} F</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Comptes 52 · 53 · 57</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Activité — {{ now()->translatedFormat('F Y') }}</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $monthly['validees_mois'] }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">
                écritures validées · {{ $fmt($monthly['volume_mois']) }} F
                @if($monthly['brouillons'] > 0)
                    · <span class="text-amber-600 font-semibold">{{ $monthly['brouillons'] }} brouillon{{ $monthly['brouillons']>1?'s':'' }}</span>
                @endif
            </p>
        </div>

        <div class="bg-white rounded-[4px] border border-amber-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Créances clients</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-amber-700 leading-none">{{ $fmt($kpis['creances']) }} F</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Compte 41 — dû par les clients</p>
        </div>

        <div class="bg-white rounded-[4px] border border-orange-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Dettes fournisseurs</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-orange-700 leading-none">{{ $fmt($kpis['dettes']) }} F</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Compte 40 — dû aux fournisseurs</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Position commerciale nette</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $fmt($kpis['creances'] - $kpis['dettes']) }} F</p>
            <p class="mt-0.5 text-[11px] text-gray-400">Créances − Dettes</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 items-start">

        {{-- Top comptes du mois --}}
        <div class="lg:col-span-2 bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}">
                <h2 class="{{ $bandH }}">Top comptes mouvementés ce mois</h2>
                <a href="{{ route('comptabilite.grand-livre') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Grand livre →</a>
            </div>
            <table class="w-full text-[14px] border-collapse">
                <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Compte</th>
                        <th class="px-3 py-1.5 text-right">Débit</th>
                        <th class="px-3 py-1.5 text-right">Crédit</th>
                        <th class="px-3 py-1.5 text-right">Volume</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topAccounts as $row)
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1">
                            <span class="font-mono font-semibold text-blue-600 text-[13px]">{{ $row->code }}</span>
                            <span class="text-gray-600 ml-2 text-[12px]">{{ $row->name }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ $fmt($row->sd) }}</td>
                        <td class="px-3 py-1 text-right tabular-nums text-gray-700">{{ $fmt($row->sc) }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-bold text-gray-900">{{ $fmt($row->volume) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucune activité ce mois.</td></tr>
                    @endforelse
                </tbody>
                @php $ta = collect($topAccounts); @endphp
                @if($ta->isNotEmpty())
                <tfoot>
                    <tr class="text-white font-bold" style="background:#065f46">
                        <td class="px-3 py-1.5 text-right text-[11px] uppercase">Totaux</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($ta->sum('sd')) }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($ta->sum('sc')) }}</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($ta->sum('volume')) }} F</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Brouillons à traiter --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}">
                <h2 class="{{ $bandH }}">Brouillons à valider</h2>
                <a href="{{ route('comptabilite.journaux.index', ['status' => 'brouillon']) }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Voir tout →</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($drafts as $d)
                <a href="{{ route('comptabilite.journaux.show', $d) }}" class="block px-3 py-1.5 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[12px] font-semibold text-blue-600">{{ $d->number }}</span>
                        <span class="text-[11px] text-gray-500 tabular-nums">{{ $d->entry_date?->format('d/m/Y') }}</span>
                    </div>
                    <p class="text-[13px] text-gray-900 mt-0.5 truncate">{{ $d->description }}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        {{ $d->journalType?->code }} · {{ $fmt($d->total_debit) }} F
                        @if(!$d->isBalanced())
                            <span class="text-red-600 ml-1 font-semibold">⚠ déséquilibré</span>
                        @endif
                    </p>
                </a>
                @empty
                <p class="px-4 py-8 text-center text-gray-400 text-[13px]">✓ Aucun brouillon en attente.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Accès rapides --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $band }}"><h2 class="{{ $bandH }}">Accès rapides</h2></div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-1.5 p-3 text-[13px]">
            <a href="{{ route('comptabilite.journaux.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📒 Journaux</a>
            <a href="{{ route('comptabilite.grand-livre') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📖 Grand livre</a>
            <a href="{{ route('comptabilite.balance') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">⚖ Balance</a>
            <a href="{{ route('comptabilite.balance-auxiliaire') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">👥 Balance aux.</a>
            <a href="{{ route('comptabilite.bilan') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📊 Bilan</a>
            <a href="{{ route('comptabilite.compte-de-resultat') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📈 Résultat</a>
            <a href="{{ route('comptabilite.sig') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📐 SIG</a>
            <a href="{{ route('comptabilite.plan-comptable.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">🗂 Plan comptable</a>
            <a href="{{ route('comptabilite.lettrage.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">🔗 Lettrage</a>
            <a href="{{ route('comptabilite.rapprochement.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">🏦 Rapprochement</a>
            <a href="{{ route('comptabilite.tva.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📋 TVA</a>
            <a href="{{ route('comptabilite.fec.export') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📤 Export FEC</a>
            <a href="{{ route('comptabilite.periods.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">🔒 Périodes</a>
            <a href="{{ route('settings.fiscal-years.index') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">📅 Exercices</a>
            <a href="{{ route('comptabilite.parametres.edit') }}" class="border border-gray-200 rounded-[4px] px-2 py-2 hover:bg-emerald-50/50 hover:border-emerald-300 text-center transition-colors">⚙ Paramètres</a>
        </div>
    </div>

</div>
@endsection
