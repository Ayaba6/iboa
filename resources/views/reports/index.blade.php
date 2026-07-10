@extends('layouts.erp')
@section('title', 'Rapports & États')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapports</span>
@endsection

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Rapports & États</h1>
        <p class="text-sm text-gray-500 mt-1">Sélectionnez un rapport pour analyser vos données — Excel & PDF disponibles</p>
    </div>

    {{-- ── VENTES ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            Ventes
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @php
            $ventesCards = [
                ['route' => 'reports.ca',               'color' => 'indigo',  'title' => 'Chiffre d\'affaires',    'desc' => 'Évolution du CA par période, par client — export Excel & PDF.'],
                ['route' => 'reports.journal-ventes',   'color' => 'indigo',  'title' => 'Journal des ventes',     'desc' => 'Liste chronologique de toutes les factures émises — export Excel & PDF.'],
                ['route' => 'reports.sales-performance','color' => 'violet',  'title' => 'Performance commerciale','desc' => 'CA par commercial, panier moyen, taux d\'encaissement.'],
                ['route' => 'reports.liste-factures',   'color' => 'blue',    'title' => 'Liste des factures',     'desc' => 'Toutes les factures avec filtres multi-critères — export Excel & PDF.'],
                ['route' => 'reports.liste-devis',      'color' => 'sky',     'title' => 'Liste des devis',        'desc' => 'Tous les devis par période, statut et client — export Excel & PDF.'],
                ['route' => 'reports.liste-commandes',  'color' => 'cyan',    'title' => 'Liste des commandes',    'desc' => 'Toutes les commandes clients filtrables — export Excel & PDF.'],
                ['route' => 'reports.margins',          'color' => 'emerald', 'title' => 'Analyse des marges',     'desc' => 'Marge brute par produit, taux de marge, coût vs vente.'],
            ];
            @endphp

            @foreach($ventesCards as $card)
            <a href="{{ route($card['route']) }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-{{ $card['color'] }}-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-{{ $card['color'] }}-700 transition-colors">{{ $card['title'] }}</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-{{ $card['color'] }}-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </section>

    {{-- ── ACHATS ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Achats
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @php
            $achatsCards = [
                ['route' => 'reports.achats',                    'color' => 'orange', 'title' => 'Achats fournisseurs',   'desc' => 'Analyse des factures fournisseurs par période — évolution et tendances.'],
                ['route' => 'suppliers.journal-achats',          'color' => 'orange', 'title' => 'Journal des achats',    'desc' => 'Liste chronologique des factures d\'achat — export Excel & PDF.'],
                ['route' => 'suppliers.balance',                 'color' => 'amber',  'title' => 'Balance fournisseurs',  'desc' => 'Soldes, montants facturés et réglés par fournisseur.'],
                ['route' => 'suppliers.balance-agee',            'color' => 'red',    'title' => 'Balance âgée fournisseurs', 'desc' => 'Ancienneté des dettes par tranche de jours.'],
                ['route' => 'suppliers.factures-impayees',       'color' => 'rose',   'title' => 'Impayés fournisseurs',  'desc' => 'Factures fournisseurs avec solde restant dû.'],
            ];
            @endphp

            @foreach($achatsCards as $card)
            <a href="{{ route($card['route']) }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-{{ $card['color'] }}-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-{{ $card['color'] }}-700 transition-colors">{{ $card['title'] }}</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-{{ $card['color'] }}-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </section>

    {{-- ── STOCKS ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Stocks
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @php
            $stocksCards = [
                ['route' => 'reports.etat-stocks',       'color' => 'teal',   'title' => 'État des stocks',          'desc' => 'Niveaux actuels, quantités disponibles et valeur de stock par article.'],
                ['route' => 'reports.mouvements-stock',  'color' => 'teal',   'title' => 'Mouvements de stock',      'desc' => 'Entrées, sorties et transferts sur une période — export Excel & PDF.'],
            ];
            @endphp

            @foreach($stocksCards as $card)
            <a href="{{ route($card['route']) }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-{{ $card['color'] }}-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-{{ $card['color'] }}-700 transition-colors">{{ $card['title'] }}</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-{{ $card['color'] }}-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </section>

    {{-- ── CLIENTS ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Clients & Créances
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @php
            $clientsCards = [
                ['route' => 'clients.releve',        'color' => 'indigo', 'title' => 'Relevé client',           'desc' => 'Relevé de compte complet d\'un client sur une période.'],
                ['route' => 'clients.balance-agee',  'color' => 'rose',   'title' => 'Balance âgée clients',    'desc' => 'Ancienneté des créances clients par tranche (à échoir, 1–30j, 31–60j…).'],
                ['route' => 'clients.grand-livre',   'color' => 'violet', 'title' => 'Grand livre client',      'desc' => 'Toutes les écritures comptables d\'un client.'],
                ['route' => 'reports.impayes',       'color' => 'red',    'title' => 'État des impayés',        'desc' => 'Factures avec solde restant dû, triage par retard — export Excel & PDF.'],
                ['route' => 'reports.aging-receivables','color' => 'rose', 'title' => 'Âge des créances',       'desc' => 'Buckets d\'ancienneté à échoir, 1–30j, 31–60j, 61–90j, +90j.'],
            ];
            @endphp

            @foreach($clientsCards as $card)
            <a href="{{ route($card['route']) }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-{{ $card['color'] }}-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-{{ $card['color'] }}-700 transition-colors">{{ $card['title'] }}</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-{{ $card['color'] }}-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </section>

    {{-- ── FOURNISSEURS ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            Fournisseurs & Dettes
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @php
            $foursCards = [
                ['route' => 'suppliers.releve',       'color' => 'orange', 'title' => 'Relevé fournisseur',      'desc' => 'Relevé de compte complet d\'un fournisseur sur une période.'],
                ['route' => 'suppliers.grand-livre',  'color' => 'amber',  'title' => 'Grand livre fournisseur', 'desc' => 'Toutes les écritures comptables d\'un fournisseur.'],
            ];
            @endphp

            @foreach($foursCards as $card)
            <a href="{{ route($card['route']) }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-{{ $card['color'] }}-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-{{ $card['color'] }}-700 transition-colors">{{ $card['title'] }}</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-{{ $card['color'] }}-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </section>

    {{-- ── TVA & FISCALITÉ ── --}}
    <section>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            TVA & Fiscalité
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            <a href="{{ route('reports.etat-tva') }}"
               class="group bg-white rounded-[4px] border border-gray-200 p-5 shadow-sm hover:shadow-md hover:border-purple-300 transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-purple-700 transition-colors">État de TVA</h3>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-purple-500 transition-colors flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">TVA collectée par taux vs TVA déductible sur achats — solde à reverser ou crédit.</p>
            </a>

        </div>
    </section>

</div>
@endsection
