@extends('layouts.erp')
@section('title', 'Paramétrage Vente')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramétrage Vente</span>
@endsection

@section('content')
@php
    $groups = [
        'Documents & numérotation' => [
            ['Numérotation vente', 'Formats DEV / CMD / BL / FAC / AVO — compteurs, audit, verrous', route('settings.sequences.index')],
            ['Exercices fiscaux', 'Périodes, clôture, exercice courant', route('settings.fiscal-years.index')],
            ['Modèles PDF', 'Mise en page, logo, cachet, signature, QR code', route('company.edit') . '#sec-documents'],
        ],
        'Clients & tarification' => [
            ['Clients', 'Fiches clients — blocage, plafond crédit, famille tarifaire, exonération', route('clients.index')],
            ['Tarifs de vente', 'Tarifs par article, client, unité (ML, m², kg, tonne, barre), validité', route('products.index')],
            ['Remises commerciales', 'Remises client / groupe / famille / volume — seuils de validation', route('settings.sales.discounts')],
        ],
        'Règlement & fiscalité' => [
            ['Conditions de paiement', 'Délais, fin de mois, acompte, échelonné, blocage impayé', route('settings.payment-terms.index')],
            ['Modes de règlement', 'Espèces, Mobile Money, virement — comptes trésorerie liés', route('settings.payment-methods.index')],
            ['Taux de TVA & retenues', 'TVA, retenues à la source, comptes GL 44xx', route('settings.tax-rates.index')],
        ],
        'Logistique & workflow' => [
            ['Dépôts de vente', 'Autorisations vente / stock / production par dépôt', route('stocks.warehouses.index')],
            ['Unités de mesure', 'ML, m², pièce, kg, tonne, barre — conversions', route('units.index')],
            ['Workflows & validations', 'Circuits devis / commande / BL / facture, rôles, notifications', route('validations.index')],
        ],
        'Général' => [
            ['Paramètres généraux vente', 'Réservation devis, facturation directe, prix plancher, seuil remise', route('settings.sales.settings')],
            ['Contrats commerciaux', 'Engagements pluriannuels, tarifs contractuels', route('ventes.contrats.index')],
            ['Synchronisations', 'Journal des flux vente → stock / compta / trésorerie', route('sync-logs.index')],
        ],
    ];
@endphp
<div class="space-y-4">

    <div class="bg-gradient-to-b from-[#eef5f0] to-white border border-gray-300 rounded-[4px] px-3 py-2.5">
        <h1 class="text-[17px] font-bold text-emerald-900">Paramétrage Vente</h1>
        <p class="text-[11.5px] text-gray-500">Configuration du cycle commercial — {{ $counts['discounts'] }} remise(s) active(s), {{ $counts['methods'] }} mode(s) de règlement</p>
    </div>

    @foreach($groups as $groupName => $entries)
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900">{{ $groupName }}</div>
        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-100">
            @foreach($entries as [$label, $desc, $url])
            <a href="{{ $url }}" class="block px-3 py-1.5 hover:bg-emerald-50/50 transition-colors group">
                <p class="text-[13.5px] font-semibold text-gray-800 group-hover:text-emerald-800 flex items-center gap-1.5">
                    {{ $label }}
                    <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </p>
                <p class="text-[11.5px] text-gray-500 mt-0.5 leading-snug">{{ $desc }}</p>
            </a>
            @endforeach
        </div>
    </div>
    @endforeach

</div>
@endsection
