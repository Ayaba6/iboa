@extends('layouts.erp')
@section('title', 'Achats — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Achats</span>
@endsection

@section('content')
@php
    $fmt  = fn($n) => number_format((int) $n, 0, ',', ' ');
    $band = 'px-4 py-2 border-b border-gray-200 bg-[#eef5f0] flex items-center justify-between';
    $bandH = 'text-[12px] font-bold text-emerald-900 uppercase tracking-wide';
    $th   = 'bg-[#3b4248] text-[12px] font-semibold text-white';
    $zebra = 'odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors';
@endphp

<div class="space-y-3">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Tableau de bord Achats</h1>
            <p class="text-sm text-gray-500 mt-0.5">Vue d'ensemble · commandes, réceptions, factures, échéances, fournisseurs</p>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ route('achats.commandes.index') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">Commandes</a>
            <a href="{{ route('achats.factures-fournisseurs.index') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">Factures FF</a>
            <a href="{{ route('achats.rfq.index') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">RFQ</a>
            <a href="{{ route('achats.approval.pending') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">Approbations</a>
            <a href="{{ route('achats.schedules.upcoming') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">Échéances</a>
            <a href="{{ route('achats.dashboard.matching') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">3-way matching</a>
            <a href="{{ route('achats.dashboard.suppliers') }}" class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-2.5 rounded-[4px] transition-colors">Évaluation FF</a>
            @can('purchase_orders.create')
            <a href="{{ route('achats.dashboard.restock-po') }}" class="h-8 inline-flex items-center bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">⚡ Générer PO réappro</a>
            @endcan
        </div>
    </div>

    {{-- KPIs denses X3 --}}
    @php
        $ecartsMatching = $matchingPreview['qty_count'] + $matchingPreview['amount_count'];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-1.5">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">PO en cours</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $kpis['open_po_count'] }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">{{ $fmt($kpis['open_po_value']) }} F</p>
        </div>

        <a href="{{ route('achats.commandes.index') }}" class="bg-white rounded-[4px] border border-blue-200 hover:bg-blue-50/40 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wide">À réceptionner</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-blue-700 leading-none">{{ $kpis['awaiting_receipt'] }}</p>
            <p class="mt-0.5 text-[11px] text-blue-400">commandes confirmées</p>
        </a>

        <a href="{{ route('achats.factures-fournisseurs.index') }}" class="bg-white rounded-[4px] border border-orange-200 hover:bg-orange-50/40 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-orange-600 uppercase tracking-wide">FF à payer</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-orange-700 leading-none">{{ $kpis['invoices_to_pay_count'] }}</p>
            <p class="mt-0.5 text-[11px] text-orange-400">{{ $fmt($kpis['invoices_to_pay_amount']) }} F</p>
        </a>

        <div class="bg-white rounded-[4px] border {{ $kpis['overdue']>0 ? 'border-red-300' : 'border-gray-300' }} px-3 py-2">
            <p class="text-[10px] font-bold {{ $kpis['overdue']>0 ? 'text-red-600' : 'text-gray-500' }} uppercase tracking-wide">En retard</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums {{ $kpis['overdue']>0 ? 'text-red-700' : 'text-gray-900' }} leading-none">{{ $kpis['overdue'] }}</p>
            <p class="mt-0.5 text-[11px] {{ $kpis['overdue']>0 ? 'text-red-400' : 'text-gray-400' }}">{{ $fmt($kpis['overdue_amount']) }} F</p>
        </div>

        <div class="bg-white rounded-[4px] border border-amber-200 px-3 py-2">
            <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wide">Échéances 7j</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-amber-700 leading-none">{{ $kpis['due_soon'] }}</p>
            <p class="mt-0.5 text-[11px] text-amber-400">factures à payer</p>
        </div>

        <a href="{{ route('achats.demandes-achat.index') }}" class="bg-white rounded-[4px] border border-gray-300 hover:bg-gray-50 px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">DA en attente</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $kpis['pending_requests'] }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">demandes d'achat</p>
        </a>

        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Volume mois</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $fmt($kpis['month_volume']) }}</p>
            @if($kpis['volume_variation_pct'] !== null)
                @php $up = $kpis['volume_variation_pct'] >= 0; @endphp
                <p class="mt-0.5 text-[11px] {{ $up ? 'text-amber-600' : 'text-emerald-600' }}">
                    {{ $up ? '↑' : '↓' }} {{ abs($kpis['volume_variation_pct']) }} % vs mois -1
                </p>
            @else
                <p class="mt-0.5 text-[11px] text-gray-400">FCFA · {{ now()->translatedFormat('F') }}</p>
            @endif
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">DPO</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $kpis['dpo_days'] }} j</p>
            <p class="mt-0.5 text-[11px] text-gray-400">délai moyen paiement FF</p>
        </div>

        <a href="{{ route('achats.dashboard.matching') }}" class="bg-white rounded-[4px] border {{ $ecartsMatching > 0 ? 'border-amber-300 hover:bg-amber-50/40' : 'border-emerald-300 hover:bg-emerald-50/40' }} px-3 py-2 transition-colors block">
            <p class="text-[10px] font-bold {{ $ecartsMatching > 0 ? 'text-amber-600' : 'text-emerald-600' }} uppercase tracking-wide">3-way matching</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums {{ $ecartsMatching > 0 ? 'text-amber-700' : 'text-emerald-700' }} leading-none">{{ $ecartsMatching }}</p>
            <p class="mt-0.5 text-[11px] {{ $ecartsMatching > 0 ? 'text-amber-400' : 'text-emerald-400' }}">écart(s) détecté(s)</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">

        {{-- Échéances proches --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}">
                <h2 class="{{ $bandH }}">Échéances 30 prochains jours</h2>
                <a href="{{ route('achats.factures-fournisseurs.index') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Voir tout →</a>
            </div>
            @if($dueSoon->isEmpty())
                <p class="px-4 py-8 text-center text-emerald-700 text-[13px]">✓ Aucune échéance proche.</p>
            @else
            <table class="w-full text-[14px] border-collapse">
                <thead class="{{ $th }}">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Facture</th>
                        <th class="px-3 py-1.5 text-left">Fournisseur</th>
                        <th class="px-3 py-1.5 text-right">Reste dû</th>
                        <th class="px-3 py-1.5 text-right">Échéance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($dueSoon as $inv)
                    <tr class="{{ $zebra }} {{ $inv->is_overdue ? '!bg-red-50/60' : '' }}">
                        <td class="px-3 py-1 font-mono text-[12px]">
                            <a href="{{ route('achats.factures-fournisseurs.show', $inv->id) }}" class="text-blue-600 hover:text-blue-800 font-semibold">{{ $inv->number }}</a>
                            <span class="block text-gray-400">{{ $inv->supplier_invoice_number ?? '' }}</span>
                        </td>
                        <td class="px-3 py-1 text-gray-900">{{ $inv->supplier_name }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-bold {{ $inv->is_overdue ? 'text-red-700' : 'text-orange-700' }}">
                            {{ $fmt($inv->remaining_amount) }}
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums text-[12px] {{ $inv->is_overdue ? 'text-red-700 font-semibold' : 'text-gray-600' }}">
                            {{ $inv->due_at ? \Carbon\Carbon::parse($inv->due_at)->format('d/m/Y') : '—' }}
                            @if($inv->is_overdue) <span class="block text-[11px] text-red-500">(+{{ abs((int) $inv->days_to_due) }} j)</span>
                            @elseif($inv->days_to_due !== null) <span class="block text-[11px] text-gray-400">dans {{ (int) $inv->days_to_due }} j</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="text-white font-bold" style="background:#065f46">
                        <td colspan="2" class="px-3 py-1.5 text-right text-[11px] uppercase">Total reste dû</td>
                        <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($dueSoon->sum('remaining_amount')) }} F</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            @endif
        </div>

        {{-- Top 5 fournisseurs (scorecards qualité) --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}">
                <h2 class="{{ $bandH }}">Scorecards fournisseurs (12 mois)</h2>
                <a href="{{ route('achats.dashboard.suppliers') }}" class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Évaluation complète →</a>
            </div>
            @if($topScorecards->isEmpty())
                <p class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucun fournisseur actif.</p>
            @else
            <table class="w-full text-[14px] border-collapse">
                <thead class="{{ $th }}">
                    <tr>
                        <th class="px-3 py-1.5 text-left">Fournisseur</th>
                        <th class="px-3 py-1.5 text-right">Volume</th>
                        <th class="px-3 py-1.5 text-center">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($topScorecards as $s)
                    @php $gradeBg = ['A'=>'bg-emerald-100 text-emerald-800','B'=>'bg-blue-100 text-blue-800','C'=>'bg-amber-100 text-amber-800','D'=>'bg-orange-100 text-orange-800','E'=>'bg-red-100 text-red-800'][$s->grade ?? 'C']; @endphp
                    <tr class="{{ $zebra }}">
                        <td class="px-3 py-1">
                            <span class="font-medium text-gray-900">{{ $s->name }}</span>
                            <span class="block text-[11px] text-gray-500">{{ $s->po_count ?? 0 }} commande(s)</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums">{{ $fmt($s->po_volume ?? 0) }}</td>
                        <td class="px-3 py-1 text-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $gradeBg }} text-[11px] font-bold">{{ $s->grade ?? '—' }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Pipeline PO par statut --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $band }}">
            <h2 class="{{ $bandH }}">Pipeline bons de commande — répartition par statut</h2>
        </div>
        @php
            // [PIÈGE Tailwind] classes statiques — bg-{$c}-50 dynamique invisible au scanner
            $stageStyles = [
                'brouillon'            => 'bg-gray-50 border-gray-200 text-gray-700|text-gray-800|text-gray-500',
                'confirmee'            => 'bg-blue-50 border-blue-200 text-blue-700|text-blue-800|text-blue-500',
                'partiellement_recue'  => 'bg-amber-50 border-amber-200 text-amber-700|text-amber-800|text-amber-500',
                'recue'                => 'bg-emerald-50 border-emerald-200 text-emerald-700|text-emerald-800|text-emerald-500',
                'facture'              => 'bg-violet-50 border-violet-200 text-violet-700|text-violet-800|text-violet-500',
                'annulee'              => 'bg-red-50 border-red-200 text-red-700|text-red-800|text-red-500',
            ];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-6 gap-1.5 p-3">
            @foreach($pipeline as $key => $stage)
                @php [$box, $val, $sub] = explode('|', $stageStyles[$key] ?? $stageStyles['brouillon']); @endphp
                <div class="text-center px-3 py-2 rounded-[4px] border {{ $box }}">
                    <p class="text-[10px] font-bold uppercase tracking-wide">{{ $stage['label'] }}</p>
                    <p class="text-[17px] font-bold tabular-nums {{ $val }} mt-0.5 leading-none">{{ $stage['count'] }}</p>
                    <p class="text-[11px] {{ $sub }} mt-0.5">{{ $fmt($stage['total']) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Top fournisseurs (volume) + Top articles --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}"><h2 class="{{ $bandH }}">Top fournisseurs par CA achats — 12 mois</h2></div>
            @if($topSuppliers->isEmpty())
                <p class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucun achat sur 12 mois.</p>
            @else
            <table class="w-full text-[14px] border-collapse">
                <thead class="{{ $th }}">
                    <tr><th class="px-3 py-1.5 text-left">Fournisseur</th><th class="px-3 py-1.5 text-right">CA TTC</th><th class="px-3 py-1.5 text-right">Reste à payer</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($topSuppliers as $s)
                    <tr class="{{ $zebra }}">
                        <td class="px-3 py-1">
                            <span class="font-medium text-gray-900">{{ $s->name }}</span>
                            <span class="block text-[11px] text-gray-500">{{ $s->invoices_count }} facture(s)</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums font-bold">{{ $fmt($s->total_ttc) }}</td>
                        <td class="px-3 py-1 text-right tabular-nums {{ $s->outstanding > 0 ? 'text-orange-700 font-semibold' : 'text-gray-400' }}">{{ $fmt($s->outstanding) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="{{ $band }}"><h2 class="{{ $bandH }}">Top articles achetés — 12 mois</h2></div>
            @if($topProducts->isEmpty())
                <p class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucun achat.</p>
            @else
            <table class="w-full text-[14px] border-collapse">
                <thead class="{{ $th }}">
                    <tr><th class="px-3 py-1.5 text-left">Article</th><th class="px-3 py-1.5 text-right">Quantité</th><th class="px-3 py-1.5 text-right">CA HT</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($topProducts as $p)
                    <tr class="{{ $zebra }}">
                        <td class="px-3 py-1">
                            <span class="font-mono text-[12px] text-blue-600">{{ $p->reference }}</span>
                            <span class="block font-medium text-gray-900">{{ $p->name }}</span>
                        </td>
                        <td class="px-3 py-1 text-right tabular-nums">{{ number_format($p->qty_bought, 2, ',', ' ') }}</td>
                        <td class="px-3 py-1 text-right tabular-nums font-bold">{{ $fmt($p->total_ht) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- Évolution mensuelle 12 mois --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="{{ $band }}"><h2 class="{{ $bandH }}">Évolution mensuelle des achats — 12 mois</h2></div>
        @if($monthly->isEmpty())
            <p class="px-4 py-8 text-center text-gray-400 text-[13px]">Pas de données.</p>
        @else
        <table class="w-full text-[14px] border-collapse">
            <thead class="{{ $th }}">
                <tr><th class="px-3 py-1.5 text-left">Mois</th><th class="px-3 py-1.5 text-right">CA TTC</th><th class="px-3 py-1.5 text-right"># factures</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php $maxCa = $monthly->max('total_ttc') ?: 1; @endphp
                @foreach($monthly as $m)
                @php $pct = $m->total_ttc > 0 ? round($m->total_ttc / $maxCa * 100, 0) : 0; @endphp
                <tr class="{{ $zebra }}">
                    <td class="px-3 py-1 text-[13px]">{{ \Carbon\Carbon::createFromFormat('Y-m', $m->month)->translatedFormat('M Y') }}</td>
                    <td class="px-3 py-1 text-right">
                        <div class="inline-flex items-center gap-2 justify-end">
                            <div class="w-16 bg-gray-200 rounded h-1.5"><div class="h-1.5 rounded bg-amber-500" style="width: {{ $pct }}%"></div></div>
                            <span class="tabular-nums text-[13px] font-medium">{{ $fmt($m->total_ttc) }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums text-gray-600">{{ $m->invoices_count }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="text-white font-bold" style="background:#065f46">
                    <td class="px-3 py-1.5 text-right text-[11px] uppercase">Total 12 mois</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($monthly->sum('total_ttc')) }} F</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $monthly->sum('invoices_count') }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    {{-- Preview écarts 3-way --}}
    @if($matchingPreview['qty_count'] > 0 || $matchingPreview['amount_count'] > 0)
    <div class="bg-white rounded-[4px] border border-amber-300 overflow-hidden">
        <div class="px-4 py-2 border-b border-amber-200 bg-amber-50 flex items-center justify-between">
            <h2 class="text-[12px] font-bold text-amber-800 uppercase tracking-wide">Écarts 3-way matching ({{ $ecartsMatching }})</h2>
            <a href="{{ route('achats.dashboard.matching') }}" class="text-xs text-amber-700 hover:text-amber-900 font-medium">Détail complet →</a>
        </div>
        <div class="px-4 py-2.5 text-[13px] text-amber-700">
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
