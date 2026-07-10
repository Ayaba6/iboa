@extends('layouts.erp')
@section('title', 'Tableau de bord Direction')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Direction</span>
@endsection

@section('content')
@php $secH = 'flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide'; @endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="bg-white border border-gray-300 rounded-[4px] px-3 py-2.5 flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Tableau de bord Direction</h1>
            <p class="text-[12px] text-gray-500">Synthèse exécutive — {{ now()->translatedFormat('F Y') }}</p>
        </div>
        <div class="text-right text-[11.5px] text-emerald-700 font-semibold">Actualisé à {{ now()->format('H:i:s') }}</div>
    </div>

    {{-- KPIs financiers --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Chiffre d'affaires (mois)</p>
            <p class="text-[22px] font-bold text-emerald-800 tabular-nums mt-1">{{ number_format($kpis['ca_month'], 0, ',', ' ') }} <span class="text-[11px] font-semibold text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Marge production (mois)</p>
            <p class="text-[22px] font-bold {{ $kpis['marge_month'] >= 0 ? 'text-emerald-700' : 'text-red-700' }} tabular-nums mt-1">{{ number_format($kpis['marge_month'], 0, ',', ' ') }} <span class="text-[11px] font-semibold text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Trésorerie (solde)</p>
            <p class="text-[22px] font-bold {{ $kpis['tresorerie'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} tabular-nums mt-1">{{ number_format($kpis['tresorerie'], 0, ',', ' ') }} <span class="text-[11px] font-semibold text-gray-400">FCFA</span></p>
        </div>
        <a href="{{ route('reports.impayes') }}" class="bg-red-50 border border-red-200 rounded-[4px] p-4 hover:bg-red-100 transition-colors">
            <p class="text-[11px] font-bold text-red-600 uppercase tracking-wide">Factures impayées</p>
            <p class="text-[22px] font-bold text-red-700 tabular-nums mt-1">{{ number_format($kpis['impayes_montant'], 0, ',', ' ') }} <span class="text-[11px] font-semibold text-red-400">FCFA</span></p>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ $kpis['impayes_count'] }} facture(s)</p>
        </a>
    </div>

    {{-- KPIs production --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}">
            <span>Production</span>
            <a href="{{ route('production.dashboard') }}" class="text-[12px] font-semibold text-emerald-700 hover:underline normal-case tracking-normal">Détail →</a>
        </div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-sky-50 border border-sky-200 rounded-[4px] p-3">
                <p class="text-[10.5px] font-bold text-sky-600 uppercase tracking-wide">OF en cours</p>
                <p class="text-[20px] font-bold text-sky-800 mt-1">{{ $kpis['of_en_cours'] }}</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-[4px] p-3">
                <p class="text-[10.5px] font-bold text-green-600 uppercase tracking-wide">OF terminés (mois)</p>
                <p class="text-[20px] font-bold text-green-800 mt-1">{{ $kpis['of_termine_month'] }}</p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-[4px] p-3">
                <p class="text-[10.5px] font-bold text-orange-600 uppercase tracking-wide">Mètres produits</p>
                <p class="text-[20px] font-bold text-orange-800 tabular-nums mt-1">{{ number_format($kpis['meters_month'], 0, ',', ' ') }}</p>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-[4px] p-3">
                <p class="text-[10.5px] font-bold text-emerald-600 uppercase tracking-wide">Rendement matière</p>
                <p class="text-[20px] font-bold text-emerald-800 tabular-nums mt-1">{{ $kpis['rendement'] !== null ? number_format($kpis['rendement'], 1, ',', ' ').' %' : '—' }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-3">
                <p class="text-[10.5px] font-bold text-amber-600 uppercase tracking-wide">Chutes (mois)</p>
                <p class="text-[20px] font-bold text-amber-800 tabular-nums mt-1">{{ number_format($kpis['waste_month'], 0, ',', ' ') }} kg</p>
            </div>
        </div>
    </div>

    {{-- Accès rapides aux dashboards par rôle --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $secH }}"><span>Accès rapides</span></div>
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('production.dashboard') }}" class="border border-gray-200 rounded-[4px] p-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition-colors">
                <p class="text-[13px] font-bold text-gray-900">🏭 Production</p>
                <p class="text-[11.5px] text-gray-500 mt-1">OF, rendement, coûts</p>
            </a>
            <a href="{{ route('ventes.dashboard') }}" class="border border-gray-200 rounded-[4px] p-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition-colors">
                <p class="text-[13px] font-bold text-gray-900">💼 Commercial</p>
                <p class="text-[11.5px] text-gray-500 mt-1">Devis, commandes, CA</p>
            </a>
            <a href="{{ route('comptabilite.dashboard') }}" class="border border-gray-200 rounded-[4px] p-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition-colors">
                <p class="text-[13px] font-bold text-gray-900">💰 Comptable</p>
                <p class="text-[11.5px] text-gray-500 mt-1">TVA, balance, créances</p>
            </a>
            <a href="{{ route('tresorerie.dashboard') }}" class="border border-gray-200 rounded-[4px] p-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition-colors">
                <p class="text-[13px] font-bold text-gray-900">🏦 Trésorerie</p>
                <p class="text-[11.5px] text-gray-500 mt-1">Encaissements, prévisions</p>
            </a>
        </div>
    </div>
</div>
@endsection
