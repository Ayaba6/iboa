@extends('layouts.erp')
@section('title', 'Tableau de bord Ventes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ventes</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Tableau de bord Ventes</h1>
            <p class="text-sm text-gray-500">Indicateurs clés · pipeline · top clients/articles · échéances</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ventes.devis.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">📋 Devis</a>
            <a href="{{ route('ventes.commandes.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">📦 Commandes</a>
            <a href="{{ route('ventes.factures.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">🧾 Factures</a>
            @can('quotes.create')
            <a href="{{ route('ventes.devis.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-2.5 py-1.5 rounded-[4px]">+ Devis</a>
            @endcan
        </div>
    </div>

    {{-- ── KPIs Production (Vente ↔ Production) ───────────────────────────── --}}
    @if(isset($prodKpis))
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="{{ route('production.orders.index', ['status' => 'en_cours']) }}" class="bg-sky-50 border border-sky-200 rounded-[4px] p-4 hover:bg-sky-100 transition-colors">
            <p class="text-xs font-medium text-sky-600 uppercase tracking-wider">Commandes en production</p>
            <p class="text-[16px] font-bold text-sky-800 mt-1">{{ $prodKpis['en_production'] }}</p>
        </a>
        <div class="bg-green-50 border border-green-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Prêtes à livrer</p>
            <p class="text-[16px] font-bold text-green-800 mt-1">{{ $prodKpis['pretes_a_livrer'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">PF produit, non livré</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Livrées non facturées</p>
            <p class="text-[16px] font-bold text-amber-800 mt-1">{{ $prodKpis['livrees_non_facturees'] }}</p>
        </div>
        <div class="bg-[#eef5f0] border border-emerald-200 rounded-[4px] p-4">
            <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Transfo devis → commande</p>
            <p class="text-[16px] font-bold text-emerald-900 tabular-nums mt-1">{{ number_format($prodKpis['taux_transfo'], 1, ',', ' ') }} %</p>
        </div>
    </div>
    @endif

    {{-- ── KPIs Workflow Validation — alertes temps réel ───────────────────── --}}
    @if($workflowKpis['total_pending'] > 0 || $workflowKpis['recently_rejected'] > 0)
    <div class="rounded-[4px] border-2 border-yellow-300 bg-yellow-50 p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-yellow-600 text-lg">🔒</span>
            <h2 class="text-sm font-semibold text-yellow-800">Validation interne — Documents en attente</h2>
            @if($workflowKpis['total_pending'] > 0)
                <span class="ml-auto inline-flex items-center rounded-full bg-yellow-500 px-2.5 py-0.5 text-xs font-semibold text-white animate-pulse">
                    {{ $workflowKpis['total_pending'] }} en attente
                </span>
            @endif
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @if($workflowKpis['quotes_en_attente'] > 0)
            <a href="{{ route('ventes.devis.index', ['status' => 'en_attente_validation']) }}" class="rounded-[4px] bg-white border border-yellow-200 p-3 text-center hover:border-yellow-400 transition-colors">
                <div class="text-[17px] font-bold text-yellow-700">{{ $workflowKpis['quotes_en_attente'] }}</div>
                <div class="text-xs text-yellow-600 mt-0.5">Devis</div>
            </a>
            @endif
            @if($workflowKpis['orders_en_attente'] > 0)
            <a href="{{ route('ventes.commandes.index', ['status' => 'en_attente_validation']) }}" class="rounded-[4px] bg-white border border-yellow-200 p-3 text-center hover:border-yellow-400 transition-colors">
                <div class="text-[17px] font-bold text-yellow-700">{{ $workflowKpis['orders_en_attente'] }}</div>
                <div class="text-xs text-yellow-600 mt-0.5">Commandes</div>
            </a>
            @endif
            @if($workflowKpis['deliveries_en_attente'] > 0)
            <a href="{{ route('ventes.bons-livraison.index', ['status' => 'en_attente_validation']) }}" class="rounded-[4px] bg-white border border-yellow-200 p-3 text-center hover:border-yellow-400 transition-colors">
                <div class="text-[17px] font-bold text-yellow-700">{{ $workflowKpis['deliveries_en_attente'] }}</div>
                <div class="text-xs text-yellow-600 mt-0.5">BL</div>
            </a>
            @endif
            @if($workflowKpis['invoices_en_attente'] > 0)
            <a href="{{ route('ventes.factures.index', ['status' => 'en_attente_validation']) }}" class="rounded-[4px] bg-white border border-yellow-200 p-3 text-center hover:border-yellow-400 transition-colors">
                <div class="text-[17px] font-bold text-yellow-700">{{ $workflowKpis['invoices_en_attente'] }}</div>
                <div class="text-xs text-yellow-600 mt-0.5">Factures</div>
            </a>
            @endif
            @if($workflowKpis['credit_notes_en_attente'] > 0)
            <a href="{{ route('ventes.avoirs.index', ['status' => 'en_attente_validation']) }}" class="rounded-[4px] bg-white border border-yellow-200 p-3 text-center hover:border-yellow-400 transition-colors">
                <div class="text-[17px] font-bold text-yellow-700">{{ $workflowKpis['credit_notes_en_attente'] }}</div>
                <div class="text-xs text-yellow-600 mt-0.5">Avoirs</div>
            </a>
            @endif
            @if($workflowKpis['recently_rejected'] > 0)
            <div class="rounded-[4px] bg-orange-50 border border-orange-200 p-3 text-center">
                <div class="text-[17px] font-bold text-orange-700">{{ $workflowKpis['recently_rejected'] }}</div>
                <div class="text-xs text-orange-600 mt-0.5">Refusés (7j)</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- KPIs principaux --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">CA HT {{ now()->translatedFormat('F Y') }}</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $fmt($kpis['ca_month']) }}</p>
            @if($kpis['ca_variation_pct'] !== null)
                @php $up = $kpis['ca_variation_pct'] >= 0; @endphp
                <p class="text-xs mt-0.5 {{ $up ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $up ? '↑' : '↓' }} {{ abs($kpis['ca_variation_pct']) }} % vs mois précédent
                </p>
            @else
                <p class="text-xs text-gray-400 mt-0.5">FCFA</p>
            @endif
        </div>

        <a href="{{ route('ventes.factures.index') }}" class="bg-white rounded-[4px] border-2 border-orange-200 hover:border-orange-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-orange-600 uppercase">💰 Encours clients</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-orange-700">{{ $fmt($kpis['outstanding']) }}</p>
            <p class="text-xs text-orange-500 mt-0.5">FCFA non payés</p>
        </a>

        <div class="bg-white rounded-[4px] border-2 {{ $kpis['overdue_amount'] > 0 ? 'border-red-300' : 'border-gray-200' }} p-5">
            <p class="text-xs font-medium {{ $kpis['overdue_amount'] > 0 ? 'text-red-600' : 'text-gray-500' }} uppercase">⏰ En retard</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ $kpis['overdue_amount'] > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $fmt($kpis['overdue_amount']) }}</p>
            <p class="text-xs {{ $kpis['overdue_amount'] > 0 ? 'text-red-500' : 'text-gray-400' }} mt-0.5">{{ $kpis['overdue_count'] }} facture(s)</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">📅 DSO</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $kpis['dso_days'] }} j</p>
            <p class="text-xs text-gray-400 mt-0.5">délai moyen de paiement</p>
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">🎯 Conversion devis</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ $kpis['conversion_rate'] >= 50 ? 'text-emerald-700' : ($kpis['conversion_rate'] >= 25 ? 'text-amber-700' : 'text-red-700') }}">
                {{ $kpis['conversion_rate'] }} %
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $kpis['quotes_accepted'] }} / {{ $kpis['quotes_sent'] }} acceptés</p>
        </div>

        <a href="{{ route('ventes.commandes.index') }}" class="bg-white rounded-[4px] border border-gray-200 hover:border-gray-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-blue-600 uppercase">📦 Commandes actives</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-blue-700">{{ $kpis['orders_in_progress'] }}</p>
            <p class="text-xs text-blue-500 mt-0.5">à livrer/facturer</p>
        </a>

        @if($kpis['draft_invoices'] > 0)
        <a href="{{ route('ventes.factures.index', ['status' => 'brouillon']) }}" class="bg-white rounded-[4px] border-2 border-amber-200 hover:border-amber-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-amber-600 uppercase">📝 Brouillons à valider</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-amber-700">{{ $kpis['draft_invoices'] }}</p>
            <p class="text-xs text-amber-500 mt-0.5">factures en attente</p>
        </a>
        @else
        <div class="bg-white rounded-[4px] border border-emerald-200 p-5">
            <p class="text-xs font-medium text-emerald-600 uppercase">✓ Brouillons</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-emerald-700">0</p>
            <p class="text-xs text-emerald-500 mt-0.5">aucune en attente</p>
        </div>
        @endif

        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">CA HT mois -1</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-700">{{ $fmt($kpis['ca_prev_month']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">référence comparative</p>
        </div>

        {{-- CA Année en cours --}}
        <div class="bg-white rounded-[4px] border border-emerald-200 p-5">
            <p class="text-xs font-medium text-emerald-700 uppercase">📊 CA HT {{ now()->year }}</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-emerald-900">{{ $fmt($kpis['ca_year']) }}</p>
            @if($kpis['ca_year_variation'] !== null)
                @php $yearUp = $kpis['ca_year_variation'] >= 0; @endphp
                <p class="text-xs mt-0.5 {{ $yearUp ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $yearUp ? '↑' : '↓' }} {{ abs($kpis['ca_year_variation']) }} % vs {{ now()->year - 1 }}
                </p>
            @else
                <p class="text-xs text-emerald-500 mt-0.5">cumulé depuis le 1er janv.</p>
            @endif
        </div>

        {{-- Panier moyen --}}
        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">🛒 Panier moyen</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $fmt($kpis['avg_basket_month']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA TTC · ce mois</p>
        </div>

        {{-- Nouveaux clients --}}
        <div class="bg-white rounded-[4px] border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">👥 Nouveaux clients</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ $kpis['new_clients_month'] > 0 ? 'text-emerald-700' : 'text-gray-400' }}">
                {{ $kpis['new_clients_month'] }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">ce mois</p>
        </div>
    </div>

    {{-- Pipeline devis --}}
    <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]">
            <h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Pipeline devis — répartition par statut</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-7 gap-2 p-4">
            @foreach($pipeline as $key => $stage)
                @php
                    $colors = [
                        'brouillon' => 'gray', 'envoye' => 'blue', 'accepte' => 'emerald',
                        'converti'  => 'violet', 'refuse' => 'red', 'expire' => 'amber', 'annule' => 'red',
                    ];
                    $c = $colors[$key] ?? 'gray';
                @endphp
                <div class="text-center p-3 rounded-[4px] bg-{{ $c }}-50 border border-{{ $c }}-200">
                    <p class="text-xs text-{{ $c }}-700 font-medium">{{ $stage['label'] }}</p>
                    <p class="text-[16px] font-bold tabular-nums text-{{ $c }}-800 mt-1">{{ $stage['count'] }}</p>
                    <p class="text-xs text-{{ $c }}-600 mt-0.5">{{ $fmt($stage['total']) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Échéances --}}
        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0] flex items-center justify-between">
                <h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Échéances 30 prochains jours</h2>
                <a href="{{ route('ventes.factures.index') }}" class="text-xs text-blue-600 hover:underline">Tout →</a>
            </div>
            @if($dueSoon->isEmpty())
                <div class="p-6 text-center text-emerald-700 text-sm">✓ Aucune échéance proche.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Facture</th><th class="px-3 py-1.5 text-left">Client</th><th class="px-3 py-1.5 text-right">Reste dû</th><th class="px-3 py-1.5 text-right">Échéance</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($dueSoon as $inv)
                    <tr class="{{ $inv->is_overdue ? 'bg-red-50/40' : '' }}">
                        <td class="px-3 py-1.5 font-mono text-xs"><a href="{{ route('ventes.factures.show', $inv->id) }}" class="text-blue-700 hover:underline">{{ $inv->number }}</a></td>
                        <td class="px-3 py-1.5 text-xs">{{ $inv->client_name }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium {{ $inv->is_overdue ? 'text-red-700' : 'text-orange-700' }}">{{ $fmt($inv->remaining_amount) }}</td>
                        <td class="px-3 py-1.5 text-right text-xs {{ $inv->is_overdue ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                            {{ $inv->due_at ? \Carbon\Carbon::parse($inv->due_at)->format('d/m/Y') : '—' }}
                            @if($inv->is_overdue) <span class="block text-xs text-red-500">+{{ abs((int) $inv->days_to_due) }} j</span>
                            @elseif($inv->days_to_due !== null) <span class="block text-xs text-gray-400">{{ (int) $inv->days_to_due }} j</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Top clients --}}
        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Top clients — 12 mois</h2></div>
            @if($topClients->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Aucune vente sur 12 mois.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Client</th><th class="px-3 py-1.5 text-right">CA HT</th><th class="px-3 py-1.5 text-right">Encours</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($topClients as $c)
                    <tr>
                        <td class="px-3 py-1.5">
                            <p class="text-sm">{{ $c->name }}</p>
                            <p class="text-xs text-gray-500">{{ $c->invoices_count }} facture(s)</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($c->total_ht) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $c->outstanding > 0 ? 'text-orange-700' : 'text-gray-400' }}">{{ $fmt($c->outstanding) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Top articles + Évolution --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Top articles vendus — 12 mois</h2></div>
            @if($topProducts->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Aucune vente.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Article</th><th class="px-3 py-1.5 text-right">Quantité</th><th class="px-3 py-1.5 text-right">CA HT</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($topProducts as $p)
                    <tr>
                        <td class="px-3 py-1.5">
                            <span class="font-mono text-xs text-blue-700">{{ $p->reference }}</span>
                            <p class="text-sm">{{ $p->name }}</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($p->qty_sold, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($p->total_ht) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="bg-white rounded-[4px] border border-gray-200 overflow-hidden">
            <div class="px-3 py-1.5 border-b border-gray-200 bg-[#eef5f0]"><h2 class="text-[12px] font-bold text-emerald-900 uppercase tracking-wide">Évolution mensuelle — 12 mois</h2></div>
            @if($monthly->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Pas de données.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Mois</th><th class="px-3 py-1.5 text-right">CA HT</th><th class="px-3 py-1.5 text-right"># factures</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @php $maxCa = $monthly->max('total_ht') ?: 1; @endphp
                    @foreach($monthly as $m)
                    @php $pct = $m->total_ht > 0 ? round($m->total_ht / $maxCa * 100, 0) : 0; @endphp
                    <tr>
                        <td class="px-3 py-1.5 text-xs">
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $m->month)->translatedFormat('M Y') }}
                        </td>
                        <td class="px-3 py-1.5 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <div class="w-16 bg-gray-200 rounded h-1.5"><div class="h-1.5 rounded bg-blue-500" style="width: {{ $pct }}%"></div></div>
                                <span class="tabular-nums text-xs font-medium">{{ $fmt($m->total_ht) }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $m->invoices_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

</div>
@endsection
