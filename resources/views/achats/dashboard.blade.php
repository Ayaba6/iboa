@extends('layouts.erp')
@section('title', 'Achats — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Achats</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-3">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Tableau de bord Achats</h1>
            <p class="text-sm text-gray-500">Vue d'ensemble · commandes, réceptions, factures, échéances, fournisseurs</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('achats.commandes.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">Commandes</a>
            <a href="{{ route('achats.factures-fournisseurs.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">Factures FF</a>
            <a href="{{ route('achats.rfq.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">📋 RFQ</a>
            <a href="{{ route('achats.approval.pending') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">✋ Approbations</a>
            <a href="{{ route('achats.schedules.upcoming') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">💰 Échéances</a>
            <a href="{{ route('achats.dashboard.matching') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">3-way matching</a>
            <a href="{{ route('achats.dashboard.suppliers') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-2.5 py-1.5 rounded-[4px]">📊 Évaluation FF</a>
            @can('purchase_orders.create')
            <a href="{{ route('achats.dashboard.restock-po') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-2.5 py-1.5 rounded-[4px]">⚡ Générer PO réappro</a>
            @endcan
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">PO en cours</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $kpis['open_po_count'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $fmt($kpis['open_po_value']) }} FCFA</p>
        </div>

        <a href="{{ route('achats.commandes.index') }}" class="bg-white rounded-[4px] border-2 border-blue-200 hover:border-blue-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-blue-600 uppercase">📦 À réceptionner</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-blue-700">{{ $kpis['awaiting_receipt'] }}</p>
            <p class="text-xs text-blue-500 mt-0.5">commandes confirmées</p>
        </a>

        <a href="{{ route('achats.factures-fournisseurs.index') }}" class="bg-white rounded-[4px] border-2 border-orange-200 hover:border-orange-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-orange-600 uppercase">💰 FF à payer</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-orange-700">{{ $kpis['invoices_to_pay_count'] }}</p>
            <p class="text-xs text-orange-500 mt-0.5">{{ $fmt($kpis['invoices_to_pay_amount']) }} FCFA</p>
        </a>

        <div class="bg-white rounded-[4px] border-2 {{ $kpis['overdue']>0 ? 'border-red-300' : 'border-gray-200' }} p-5">
            <p class="text-xs font-medium {{ $kpis['overdue']>0 ? 'text-red-600' : 'text-gray-500' }} uppercase">⏰ En retard</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ $kpis['overdue']>0 ? 'text-red-700' : 'text-gray-900' }}">{{ $kpis['overdue'] }}</p>
            <p class="text-xs {{ $kpis['overdue']>0 ? 'text-red-500' : 'text-gray-400' }} mt-0.5">{{ $fmt($kpis['overdue_amount']) }} FCFA</p>
        </div>

        <div class="bg-white rounded-[4px] border border-amber-200 p-5">
            <p class="text-xs font-medium text-amber-600 uppercase">Échéances 7j</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-amber-700">{{ $kpis['due_soon'] }}</p>
            <p class="text-xs text-amber-500 mt-0.5">factures à payer</p>
        </div>

        <a href="{{ route('achats.demandes-achat.index') }}" class="bg-white rounded-[4px] border border-gray-300 hover:border-gray-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-gray-500 uppercase">📝 DA en attente</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $kpis['pending_requests'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">demandes d'achat</p>
        </a>

        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Volume mois</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $fmt($kpis['month_volume']) }}</p>
            @if($kpis['volume_variation_pct'] !== null)
                @php $up = $kpis['volume_variation_pct'] >= 0; @endphp
                <p class="text-xs mt-0.5 {{ $up ? 'text-amber-600' : 'text-emerald-600' }}">
                    {{ $up ? '↑' : '↓' }} {{ abs($kpis['volume_variation_pct']) }} % vs mois -1
                </p>
            @else
                <p class="text-xs text-gray-400 mt-0.5">FCFA · {{ now()->translatedFormat('F') }}</p>
            @endif
        </div>

        {{-- [ACHATS-PRO] DPO — Days Payable Outstanding --}}
        <div class="bg-white rounded-[4px] border border-gray-300 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">📅 DPO</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums text-gray-900">{{ $kpis['dpo_days'] }} j</p>
            <p class="text-xs text-gray-400 mt-0.5">délai moyen de paiement fournisseurs</p>
        </div>

        <a href="{{ route('achats.dashboard.matching') }}" class="bg-white rounded-[4px] border-2 {{ ($matchingPreview['qty_count']+$matchingPreview['amount_count']) > 0 ? 'border-amber-300' : 'border-emerald-300' }} p-5 transition-colors block">
            <p class="text-xs font-medium {{ ($matchingPreview['qty_count']+$matchingPreview['amount_count']) > 0 ? 'text-amber-600' : 'text-emerald-600' }} uppercase">🔗 3-way matching</p>
            <p class="mt-1 text-[16px] font-bold tabular-nums {{ ($matchingPreview['qty_count']+$matchingPreview['amount_count']) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                {{ $matchingPreview['qty_count'] + $matchingPreview['amount_count'] }}
            </p>
            <p class="text-xs {{ ($matchingPreview['qty_count']+$matchingPreview['amount_count']) > 0 ? 'text-amber-500' : 'text-emerald-500' }} mt-0.5">écart(s) détecté(s)</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Échéances proches --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">⏰ Échéances 30 prochains jours</h2>
                <a href="{{ route('achats.factures-fournisseurs.index') }}" class="text-xs text-blue-600 hover:underline">Tout →</a>
            </div>
            @if($dueSoon->isEmpty())
                <div class="p-6 text-center text-emerald-700 text-sm">✓ Aucune échéance proche.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Facture</th>
                        <th class="px-3 py-1.5 text-left">Fournisseur</th>
                        <th class="px-3 py-1.5 text-right">Reste dû</th>
                        <th class="px-3 py-1.5 text-right">Échéance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($dueSoon as $inv)
                    <tr class="{{ $inv->is_overdue ? 'bg-red-50/40' : '' }}">
                        <td class="px-3 py-1.5 font-mono text-xs">
                            <a href="{{ route('achats.factures-fournisseurs.show', $inv->id) }}" class="text-blue-700 hover:underline">{{ $inv->number }}</a>
                            <div class="text-gray-400">{{ $inv->supplier_invoice_number ?? '' }}</div>
                        </td>
                        <td class="px-3 py-1.5 text-xs">{{ $inv->supplier_name }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium {{ $inv->is_overdue ? 'text-red-700' : 'text-orange-700' }}">
                            {{ $fmt($inv->remaining_amount) }}
                        </td>
                        <td class="px-3 py-1.5 text-right text-xs {{ $inv->is_overdue ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                            {{ $inv->due_at ? \Carbon\Carbon::parse($inv->due_at)->format('d/m/Y') : '—' }}
                            @if($inv->is_overdue) <span class="block text-xs text-red-500">(+{{ abs((int) $inv->days_to_due) }} j)</span>
                            @elseif($inv->days_to_due !== null) <span class="block text-xs text-gray-400">dans {{ (int) $inv->days_to_due }} j</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Top 5 fournisseurs (scorecards qualité) --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">🏆 Scorecards fournisseurs (12 mois)</h2>
                <a href="{{ route('achats.dashboard.suppliers') }}" class="text-xs text-blue-600 hover:underline">Évaluation complète →</a>
            </div>
            @if($topScorecards->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Aucun fournisseur actif.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Fournisseur</th>
                        <th class="px-3 py-1.5 text-right">Volume</th>
                        <th class="px-3 py-1.5 text-center">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($topScorecards as $s)
                    @php $gradeBg = ['A'=>'bg-emerald-100 text-emerald-800','B'=>'bg-blue-100 text-blue-800','C'=>'bg-amber-100 text-amber-800','D'=>'bg-orange-100 text-orange-800','E'=>'bg-red-100 text-red-800'][$s->grade ?? 'C']; @endphp
                    <tr>
                        <td class="px-3 py-1.5">
                            <p class="text-sm text-gray-900">{{ $s->name }}</p>
                            <p class="text-xs text-gray-500">{{ $s->po_count ?? 0 }} commande(s)</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($s->po_volume ?? 0) }}</td>
                        <td class="px-3 py-1.5 text-center">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $gradeBg }} text-xs font-bold">{{ $s->grade ?? '—' }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- [ACHATS-PRO] Pipeline PO par statut (funnel Odoo-style) --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">📊 Pipeline bons de commande — répartition par statut</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 p-4">
            @foreach($pipeline as $key => $stage)
                @php
                    $colors = [
                        'brouillon' => 'gray', 'confirmee' => 'blue', 'partiellement_recue' => 'amber',
                        'recue' => 'emerald', 'facture' => 'violet', 'annulee' => 'red',
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

    {{-- [ACHATS-PRO] Top fournisseurs (volume) + Top articles + Évolution --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Top fournisseurs par CA achats --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">💼 Top fournisseurs par CA achats — 12 mois</h2></div>
            @if($topSuppliers->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Aucun achat sur 12 mois.</div>
            @else
            <table class="w-full text-sm">
                <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                    <tr><th class="px-3 py-1.5 text-left">Fournisseur</th><th class="px-3 py-1.5 text-right">CA TTC</th><th class="px-3 py-1.5 text-right">Reste à payer</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($topSuppliers as $s)
                    <tr>
                        <td class="px-3 py-1.5">
                            <p class="text-sm">{{ $s->name }}</p>
                            <p class="text-xs text-gray-500">{{ $s->invoices_count }} facture(s)</p>
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($s->total_ttc) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $s->outstanding > 0 ? 'text-orange-700' : 'text-gray-400' }}">{{ $fmt($s->outstanding) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Top articles achetés --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">📦 Top articles achetés — 12 mois</h2></div>
            @if($topProducts->isEmpty())
                <div class="p-6 text-center text-gray-400 text-sm">Aucun achat.</div>
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
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($p->qty_bought, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ $fmt($p->total_ht) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- [ACHATS-PRO] Évolution mensuelle 12 mois --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">📈 Évolution mensuelle des achats — 12 mois</h2></div>
        @if($monthly->isEmpty())
            <div class="p-6 text-center text-gray-400 text-sm">Pas de données.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] uppercase tracking-wide font-bold text-emerald-900">
                <tr><th class="px-3 py-1.5 text-left">Mois</th><th class="px-3 py-1.5 text-right">CA TTC</th><th class="px-3 py-1.5 text-right"># factures</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @php $maxCa = $monthly->max('total_ttc') ?: 1; @endphp
                @foreach($monthly as $m)
                @php $pct = $m->total_ttc > 0 ? round($m->total_ttc / $maxCa * 100, 0) : 0; @endphp
                <tr>
                    <td class="px-3 py-1.5 text-xs">{{ \Carbon\Carbon::createFromFormat('Y-m', $m->month)->translatedFormat('M Y') }}</td>
                    <td class="px-3 py-1.5 text-right">
                        <div class="inline-flex items-center gap-2 justify-end">
                            <div class="w-16 bg-gray-200 rounded h-1.5"><div class="h-1.5 rounded bg-amber-500" style="width: {{ $pct }}%"></div></div>
                            <span class="tabular-nums text-xs font-medium">{{ $fmt($m->total_ttc) }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $m->invoices_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Preview écarts 3-way --}}
    @if($matchingPreview['qty_count'] > 0 || $matchingPreview['amount_count'] > 0)
    <div class="bg-white rounded-[4px] border-2 border-amber-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-amber-100 bg-amber-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-amber-800">🔗 Écarts 3-way matching ({{ $matchingPreview['qty_count'] + $matchingPreview['amount_count'] }})</h2>
            <a href="{{ route('achats.dashboard.matching') }}" class="text-xs text-amber-700 hover:underline">Détail complet →</a>
        </div>
        <div class="px-5 py-3 text-sm text-amber-700">
            @if($matchingPreview['qty_count'] > 0)
                <p>{{ $matchingPreview['qty_count'] }} écart(s) quantitatif(s) entre PO ↔ réception ↔ facturation.</p>
            @endif
            @if($matchingPreview['amount_count'] > 0)
                <p class="mt-1">{{ $matchingPreview['amount_count'] }} écart(s) de montant entre PO et facture liée.</p>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
