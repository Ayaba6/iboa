@extends('layouts.erp')
@section('title', $client->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $client->name }}</span>
@endsection

@section('content')
@php
    $card = 'bg-white border border-gray-300 rounded-[4px]';
    $secH = 'px-4 py-1.5 border-b border-t border-gray-200 bg-[#eef5f0] text-[12px] font-bold text-emerald-900 uppercase tracking-wide';
    $lbl  = 'block text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-0.5';
    $val  = 'text-[13px] text-gray-800 font-medium';
    $row  = function ($label, $value) use ($lbl, $val) {
        $v = ($value === null || $value === '') ? '—' : e($value);
        return '<div class="min-w-0"><span class="'.$lbl.'">'.e($label).'</span><span class="'.$val.' block truncate">'.$v.'</span></div>';
    };
    $oui = fn ($b) => $b ? 'Oui' : 'Non';
    $f   = fn ($n) => $n !== null ? number_format((float) $n, 0, ',', ' ') : null;

    $tabs = [
        'general'      => 'Général',
        'adresses'     => 'Adresses',
        'commercial'   => 'Commercial',
        'finance'      => 'Finance',
        'livraison'    => 'Livraison',
        'contacts'     => 'Contacts',
        'comptabilite' => 'Comptabilité',
        'documents'    => 'Documents',
    ];
@endphp

<div x-data="{ tab: 'general' }" class="space-y-3">

    {{-- ═══ Bandeau titre ═════════════════════════════════════════════════════ --}}
    <div class="{{ $card }} px-3 py-2.5 flex items-center gap-3">
        <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 text-[15px] font-bold">
            {{ strtoupper(substr($client->name, 0, 2)) }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="text-[16px] font-bold text-gray-900 truncate">{{ $client->name }}</h1>
                <span class="bg-purple-50 text-purple-700 text-[10px] font-semibold px-2 py-0.5 rounded-full capitalize">{{ $client->type ?: 'client' }}</span>
                @if($client->is_active)
                    <span class="bg-green-50 text-green-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Actif</span>
                @else
                    <span class="bg-red-50 text-red-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Inactif</span>
                @endif
                @if($client->is_blocked)
                    <span class="bg-red-100 text-red-700 text-[10px] font-semibold px-2 py-0.5 rounded-full">Bloqué</span>
                @endif
            </div>
            <div class="flex items-center flex-wrap gap-x-3 gap-y-0.5 mt-0.5 text-[11.5px] text-gray-400">
                <span class="font-mono">{{ $client->code }}</span>
                @if($client->email)<span>{{ $client->email }}</span>@endif
                @if($client->phone)<span>{{ $client->phone }}</span>@endif
                @if($client->city)<span>{{ $client->city }}</span>@endif
            </div>
        </div>
        <a href="{{ route('clients.edit', $client) }}"
           class="flex-shrink-0 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-4 py-1.5 rounded-[4px] transition-colors">
            Modifier
        </a>
    </div>

    {{-- ═══ Bandeau KPI ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Total facturé</p>
            <p class="text-[15px] font-bold text-gray-700 mt-0.5">{{ $f($totalInvoiced) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Total payé</p>
            <p class="text-[15px] font-bold text-emerald-600 mt-0.5">{{ $f($totalPaid) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Solde dû</p>
            <p class="text-[15px] font-bold {{ $balance > 0 ? 'text-red-600' : 'text-gray-700' }} mt-0.5">{{ $f($balance) }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="{{ $card }} px-3 py-2.5">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Limite de crédit</p>
            <p class="text-[15px] font-bold text-gray-700 mt-0.5">{{ $client->credit_limit ? $f($client->credit_limit) : '—' }} <span class="text-[11px] font-normal text-gray-400">FCFA</span></p>
        </div>
    </div>

    {{-- ═══ Fiche à onglets ═══════════════════════════════════════════════════ --}}
    <div class="{{ $card }} overflow-hidden">
        <div class="flex items-stretch border-b border-gray-200 overflow-x-auto bg-gray-50/70">
            @foreach($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 text-[12.5px] font-semibold border-b-2 whitespace-nowrap transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ── Général ── --}}
        <div x-show="tab === 'general'" class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Code', $client->code) !!}
            {!! $row('Type', ucfirst($client->type ?? '')) !!}
            {!! $row('Raison sociale', $client->name) !!}
            {!! $row('Nom commercial', $client->trade_name) !!}
            {!! $row('Civilité', $client->civility) !!}
            {!! $row('IFU', $client->ifu) !!}
            {!! $row('N° contribuable', $client->numero_contribuable) !!}
            {!! $row('RCCM', $client->rccm) !!}
            {!! $row("Secteur d'activité", $client->secteur_activite) !!}
            {!! $row('Catégorie', $client->category) !!}
            {!! $row('Groupe client', $client->groupe_client) !!}
            {!! $row('Devise', $client->currency) !!}
            {!! $row('Langue', $client->language) !!}
            {!! $row('Site web', $client->website) !!}
            {!! $row('Actif', $oui($client->is_active)) !!}
            <div class="col-span-2 md:col-span-3"><span class="{{ $lbl }}">Notes</span><span class="{{ $val }}">{{ $client->notes ?: '—' }}</span></div>
        </div>

        {{-- ── Adresses ── --}}
        <div x-show="tab === 'adresses'" x-cloak>
            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                {!! $row('Boîte postale', $client->boite_postale) !!}
                {!! $row('Adresse', $client->address) !!}
                {!! $row('Complément', $client->address_line2) !!}
                {!! $row('Quartier', $client->quartier) !!}
                {!! $row('Ville', $client->city) !!}
                {!! $row('Code postal', $client->postal_code) !!}
                {!! $row('Région', $client->region) !!}
                {!! $row('Pays', $client->country) !!}
                {!! $row('GPS', $client->gps_lat ? $client->gps_lat.', '.$client->gps_lng : null) !!}
            </div>
            @if($client->addresses->isNotEmpty())
                <div class="{{ $secH }}">Adresses secondaires</div>
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Libellé</th>
                        <th class="px-4 py-1.5 text-left">Adresse</th>
                        <th class="px-4 py-1.5 text-left">Ville</th>
                        <th class="px-4 py-1.5 text-left">Pays</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($client->addresses as $addr)
                        <tr>
                            <td class="px-4 py-1.5 text-gray-900">{{ $addr->label ?? $addr->type ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $addr->address ?? $addr->line1 ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $addr->city ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $addr->country ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Commercial ── --}}
        <div x-show="tab === 'commercial'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Commercial affecté', $client->assignedCommercial?->name) !!}
            {!! $row('Représentant', $client->salesRep?->name) !!}
            {!! $row('Canal', $client->canal) !!}
            {!! $row('Zone commerciale', $client->zone_commerciale) !!}
            {!! $row('Famille tarifaire', $client->famille_tarifaire) !!}
            {!! $row('Remise par défaut', $client->default_discount !== null ? $client->default_discount.' %' : null) !!}
            {!! $row('Commande bloquée', $oui($client->blocage_commande)) !!}
        </div>

        {{-- ── Finance ── --}}
        <div x-show="tab === 'finance'" x-cloak>
            <div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                {!! $row('Limite de crédit', $client->credit_limit ? $f($client->credit_limit).' FCFA' : null) !!}
                {!! $row('Encours autorisé', $client->encours_autorise ? $f($client->encours_autorise).' FCFA' : null) !!}
                {!! $row('Compte collectif', $client->compte_collectif) !!}
                {!! $row('Mode de règlement', $client->payment_mode) !!}
                {!! $row('Délai paiement (j)', $client->payment_days) !!}
                {!! $row('Conditions', $client->payment_terms ?? $client->condition_paiement) !!}
                {!! $row('Régime fiscal', $client->tax_regime) !!}
                {!! $row('Soumis TVA', $oui($client->soumis_tva)) !!}
                {!! $row('Exonéré TVA', $oui($client->is_tax_exempt)) !!}
                {!! $row("Motif d'exonération", $client->tax_exemption_reason) !!}
                {!! $row('Facturable', $oui($client->is_facturable)) !!}
            </div>

            @if($client->invoices->isNotEmpty())
                <div class="{{ $secH }} flex items-center justify-between">
                    <span>Factures récentes</span>
                    <a href="{{ route('ventes.factures.index', ['client_id' => $client->id]) }}" class="text-emerald-700 hover:text-emerald-800 normal-case tracking-normal text-[11px]">Voir toutes →</a>
                </div>
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Référence</th>
                        <th class="px-4 py-1.5 text-left">Date</th>
                        <th class="px-4 py-1.5 text-right">Montant TTC</th>
                        <th class="px-4 py-1.5 text-left">Statut</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($client->invoices as $inv)
                        <tr>
                            <td class="px-4 py-1.5"><a href="{{ route('ventes.factures.show', $inv) }}" class="font-mono text-emerald-700 hover:text-emerald-800">{{ $inv->number }}</a></td>
                            <td class="px-4 py-1.5 text-gray-500">{{ $inv->issued_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-right font-medium tabular-nums">{{ number_format($inv->total_ttc, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-1.5"><span class="text-[10.5px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize">{{ str_replace('_', ' ', $inv->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Livraison ── --}}
        <div x-show="tab === 'livraison'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Dépôt de livraison', $client->depotLivraison?->name) !!}
            {!! $row('Mode de livraison', $client->mode_livraison) !!}
            {!! $row('Transporteur', $client->transporteur) !!}
            {!! $row('Délai livraison', $client->delai_livraison) !!}
            {!! $row('Livrable', $oui($client->is_livrable)) !!}
            <div class="col-span-2 md:col-span-3"><span class="{{ $lbl }}">Adresse de livraison par défaut</span><span class="{{ $val }}">{{ $client->adresse_livraison_defaut ?: '—' }}</span></div>
        </div>

        {{-- ── Contacts ── --}}
        <div x-show="tab === 'contacts'" x-cloak>
            @if($client->contacts->isNotEmpty())
                <table class="w-full text-[12.5px]">
                    <thead><tr class="text-[10px] font-bold text-gray-500 uppercase">
                        <th class="px-4 py-1.5 text-left">Nom</th>
                        <th class="px-4 py-1.5 text-left">Fonction</th>
                        <th class="px-4 py-1.5 text-left">Téléphone</th>
                        <th class="px-4 py-1.5 text-left">Email</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($client->contacts as $c)
                        <tr>
                            <td class="px-4 py-1.5 text-gray-900">{{ $c->name ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $c->function ?? $c->role ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $c->phone ?? $c->mobile ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">{{ $c->email ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-4 py-4 text-center text-gray-400 text-[12.5px]">Aucun contact</div>
            @endif

            <div class="{{ $secH }}">Dernières interactions</div>
            @if($client->interactions->isNotEmpty())
                <ul class="divide-y divide-gray-100">
                    @foreach($client->interactions as $it)
                    <li class="px-3 py-1.5 flex items-start gap-3">
                        <span class="text-[10.5px] font-semibold px-1.5 py-0.5 rounded-full bg-indigo-50 text-indigo-600 capitalize flex-shrink-0">{{ $it->type ?? 'note' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[12.5px] text-gray-800 truncate">{{ $it->summary ?? $it->note ?? $it->description ?? '—' }}</p>
                            <p class="text-[10.5px] text-gray-400">{{ $it->occurred_at?->format('d/m/Y') ?? '' }}{{ $it->user ? ' · '.$it->user->name : '' }}</p>
                        </div>
                    </li>
                    @endforeach
                </ul>
            @else
                <div class="px-4 py-4 text-center text-gray-400 text-[12.5px]">Aucune interaction enregistrée</div>
            @endif
        </div>

        {{-- ── Comptabilité ── --}}
        <div x-show="tab === 'comptabilite'" x-cloak class="p-4 grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
            {!! $row('Compte tiers', $client->compte_tiers) !!}
            {!! $row('Échéance', $client->echeance) !!}
            {!! $row('Banque', $client->banque) !!}
            {!! $row('IBAN / RIB', $client->rib_iban) !!}
            {!! $row('N° de compte', $client->numero_compte) !!}
            {!! $row('SWIFT / BIC', $client->swift) !!}
            {!! $row('Solde comptable', $client->balance !== null ? $f($client->balance).' FCFA' : null) !!}
        </div>

        {{-- ── Documents ── --}}
        <div x-show="tab === 'documents'" x-cloak class="p-6 text-center text-gray-400 text-[12.5px]">
            Aucun document attaché.
        </div>
    </div>

</div>
@endsection
