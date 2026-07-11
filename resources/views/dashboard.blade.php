@extends('layouts.erp')
@section('title', 'Tableau de bord')

@section('breadcrumb')
    <span class="text-gray-900 font-semibold">Tableau de bord</span>
@endsection

@section('content')
{{-- [Maquette X3] Tableau de bord : 4 KPI + 4 compteurs + 3 graphiques + 2 tables + barre contexte --}}
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $company = currentCompany();
    $fy = $company?->currentFiscalYear;
    // Flèche + couleur de tendance (maquette : vert ↗ / rouge ↘ ; inversé pour les décaissements)
    $trendBadge = function (?array $t, bool $inverse = false) {
        if (! $t || $t['value'] === null) return '';
        $up   = $t['direction'] === 'up';
        $good = $inverse ? ! $up : $up;
        $cls  = $good ? 'text-emerald-600' : 'text-red-600';
        $arr  = $up ? '&#8599;' : '&#8600;';
        return "<span class=\"$cls font-semibold\">$arr " . ($up ? '+' : '-') . number_format($t['value'], 1, ',', ' ') . ' %</span>';
    };
@endphp

<div class="space-y-3">

    {{-- ═══ Titre + actions ═══ --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight inline-flex items-center gap-2">
                Tableau de bord
                <svg class="w-4 h-4 text-gray-300 hover:text-amber-400 cursor-pointer transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </h1>
            <p class="text-[12.5px] text-gray-500">Vue synthétique de l'activité de l'entreprise</p>
        </div>
        <div class="flex items-center gap-1.5">
            <button onclick="window.location.reload()"
                    class="h-8 inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Actualiser
            </button>
            <a href="{{ route('direction.dashboard') }}"
               class="h-8 inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Personnaliser
            </a>
            <a href="{{ route('direction.dashboard') }}" title="Plus d'options"
               class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[14px] font-bold px-3 rounded-[4px] transition-colors">…</a>
            <a href="{{ route('ventes.factures.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 rounded-[4px] transition-colors">
                Ajouter un widget <span class="text-[14px] leading-none">+</span>
            </a>
        </div>
    </div>

    {{-- ═══ Bandeau documents en attente [maquette : jaune pâle] ═══ --}}
    @if(($pendingCount ?? 0) > 0)
    <div class="flex items-center justify-between gap-3 bg-amber-50 border border-amber-200 rounded-[4px] px-4 py-2.5">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <div>
                <p class="text-[13px] font-bold text-gray-900">Documents en attente de validation</p>
                <p class="text-[12px] text-gray-600">Vous avez {{ $pendingCount }} document{{ $pendingCount > 1 ? 's' : '' }} en attente de validation.</p>
            </div>
        </div>
        <a href="{{ route('validations.index') }}"
           class="h-8 inline-flex items-center border border-amber-300 bg-white hover:bg-amber-100 text-[12px] font-medium text-gray-800 px-3 rounded-[4px] transition-colors whitespace-nowrap">Voir les documents</a>
    </div>
    @endif

    @can('reports.view')

    {{-- ═══ Rangée 1 : 4 KPI financiers [maquette : icône ronde + montant + tendance] ═══ --}}
    @php
        $kpiCards = [
            ['label' => "Chiffre d'affaires (HT)",  'value' => $caHtMois,          'trend' => $trendCaHt,          'inv' => false, 'ic' => 'bg-blue-100 text-blue-600',       'path' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['label' => 'Trésorerie disponible',    'value' => $soldeTresorerie,   'trend' => null,                'inv' => false, 'ic' => 'bg-emerald-100 text-emerald-600', 'path' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Encaissements (mois)',     'value' => $encaissementsMois, 'trend' => $trendEncaissements, 'inv' => false, 'ic' => 'bg-indigo-100 text-indigo-600',   'path' => 'M16 17l-4 4m0 0l-4-4m4 4V3'],
            ['label' => 'Décaissements (mois)',     'value' => $decaissementsMois, 'trend' => $trendDecaissements, 'inv' => true,  'ic' => 'bg-orange-100 text-orange-600',   'path' => 'M8 7l4-4m0 0l4 4m-4-4v18'],
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
        @foreach($kpiCards as $k)
        <div class="bg-white border border-gray-300 rounded-[4px] px-4 py-3 flex items-center gap-3">
            <span class="w-11 h-11 rounded-full {{ $k['ic'] }} flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $k['path'] }}"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-[12px] font-semibold text-gray-600">{{ $k['label'] }}</p>
                <p class="text-[20px] font-bold text-gray-900 tabular-nums leading-tight">{{ $fmt($k['value']) }} F</p>
                <p class="text-[11px] text-gray-400">
                    {{ ucfirst(now()->locale('fr')->isoFormat('MMMM YYYY')) }}
                    @if($k['trend']) &nbsp;{!! $trendBadge($k['trend'], $k['inv']) !!} <span class="text-gray-400">vs {{ ucfirst(now()->subMonth()->locale('fr')->isoFormat('MMMM')) }}</span>@endif
                </p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ═══ Rangée 2 : 4 compteurs opérationnels [maquette : chevron lien] ═══ --}}
    @php
        $counterCards = [
            ['label' => 'Commandes en attente', 'value' => $nbCommandesEnCours, 'sub' => $fmt($montantCommandesEnCours) . ' F', 'subCls' => 'text-gray-500', 'url' => route('ventes.commandes.index'), 'ic' => 'bg-blue-50 text-blue-600', 'path' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'OF en cours', 'value' => $ofEnCours, 'sub' => $ofEnRetard > 0 ? "Dont $ofEnRetard en retard" : 'Aucun retard', 'subCls' => $ofEnRetard > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600', 'url' => route('production.orders.index'), 'ic' => 'bg-emerald-50 text-emerald-600', 'path' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            ['label' => 'Stock critique', 'value' => $stockCritique, 'sub' => 'Articles à réappro.', 'subCls' => $stockCritique > 0 ? 'text-red-600 font-semibold' : 'text-gray-500', 'url' => route('stocks.index'), 'ic' => 'bg-amber-50 text-amber-600', 'path' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['label' => 'Alertes qualité', 'value' => $alertesQualite, 'sub' => $alertesQualite > 0 ? 'Non traitées' : 'Rien à signaler', 'subCls' => $alertesQualite > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600', 'url' => route('production.orders.index'), 'ic' => 'bg-red-50 text-red-500', 'path' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
        @foreach($counterCards as $c)
        <a href="{{ $c['url'] }}" class="bg-white border border-gray-300 rounded-[4px] px-4 py-3 flex items-center gap-3 hover:border-emerald-400 transition-colors group">
            <span class="w-10 h-10 rounded-[6px] {{ $c['ic'] }} flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $c['path'] }}"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[12px] font-semibold text-gray-600">{{ $c['label'] }}</p>
                <p class="text-[20px] font-bold text-gray-900 tabular-nums leading-tight">{{ $fmt($c['value']) }}</p>
                @if($c['sub'])<p class="text-[11px] {{ $c['subCls'] }}">{{ $c['sub'] }}</p>@endif
            </div>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-emerald-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endforeach
    </div>

    {{-- ═══ Rangée 3 : 3 graphiques [barres CA 12 mois · courbe trésorerie 30j · donut familles] ═══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-2">
        <div class="bg-white border border-gray-300 rounded-[4px] p-3">
            <div class="flex items-center justify-between mb-1">
                <p class="text-[13px] font-bold text-gray-800">Évolution du chiffre d'affaires <span class="font-normal text-gray-500">(HT)</span></p>
                <span class="text-[11px] text-gray-400 border border-gray-200 rounded-[3px] px-2 py-0.5">Année en cours</span>
            </div>
            <div id="x3-chart-ca" class="min-h-[220px]"></div>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-3">
            <div class="flex items-center justify-between mb-1">
                <p class="text-[13px] font-bold text-gray-800">Trésorerie <span class="font-normal text-gray-500">(disponible)</span></p>
                <span class="text-[11px] text-gray-400 border border-gray-200 rounded-[3px] px-2 py-0.5">30 derniers jours</span>
            </div>
            <div id="x3-chart-treso" class="min-h-[220px]"></div>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-3">
            <div class="flex items-center justify-between mb-1">
                <p class="text-[13px] font-bold text-gray-800">Répartition du CA par famille <span class="font-normal text-gray-500">(YTD)</span></p>
                <span class="text-[11px] text-gray-400 border border-gray-200 rounded-[3px] px-2 py-0.5">Année en cours</span>
            </div>
            <div id="x3-chart-familles" class="min-h-[220px]"></div>
        </div>
    </div>

    {{-- ═══ Rangée 4 : Activités récentes · Alertes et points de vigilance ═══ --}}
    @php
        // Badge par type de document (Activités récentes)
        $typeBadges = [
            'Order'           => ['Commande client', 'bg-blue-50 text-blue-700 border-blue-200'],
            'Invoice'         => ['Facture ventes',  'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'DeliveryNote'    => ['Bon de livraison','bg-sky-50 text-sky-700 border-sky-200'],
            'PurchaseOrder'   => ['Achat',           'bg-violet-50 text-violet-700 border-violet-200'],
            'Reception'       => ['Achat',           'bg-violet-50 text-violet-700 border-violet-200'],
            'SupplierInvoice' => ['Achat',           'bg-violet-50 text-violet-700 border-violet-200'],
            'ProductionOrder' => ['OF',              'bg-lime-50 text-lime-700 border-lime-200'],
            'Quote'           => ['Devis',           'bg-indigo-50 text-indigo-700 border-indigo-200'],
            'ClientPayment'   => ['Encaissement',    'bg-teal-50 text-teal-700 border-teal-200'],
            'Client'          => ['Client',          'bg-gray-50 text-gray-700 border-gray-200'],
            'JournalEntry'    => ['Écriture',        'bg-amber-50 text-amber-700 border-amber-200'],
            'CreditNote'      => ['Avoir',           'bg-rose-50 text-rose-700 border-rose-200'],
            'StockTransfer'   => ['Transfert stock', 'bg-cyan-50 text-cyan-700 border-cyan-200'],
        ];
        $niveauBadges = [
            'critique' => ['Critique',    'bg-red-600 text-white'],
            'alerte'   => ['Alerte',      'bg-amber-100 text-amber-800 border border-amber-300'],
            'info'     => ['Information', 'bg-blue-50 text-blue-700 border border-blue-200'],
        ];
        // Libellés français des actions d'audit (maquette : « Commande client créée »…)
        $actionLabels = [
            'created' => 'créé(e)', 'updated' => 'modifié(e)', 'modified' => 'modifié(e)',
            'deleted' => 'supprimé(e)', 'validated' => 'validé(e)', 'sent' => 'envoyé(e)',
            'paid' => 'payé(e)', 'confirmed' => 'confirmé(e)', 'submitted' => 'soumis(e)',
            'cancelled' => 'annulé(e)', 'launched' => 'lancé(e)',
        ];
        $frAction = function (string $action, string $tLabel) use ($actionLabels) {
            $a = strtolower($action);
            foreach ($actionLabels as $en => $fr) if (str_contains($a, $en)) return "$tLabel $fr";
            return $tLabel . ' — ' . ucfirst(str_replace('_', ' ', $action));
        };
    @endphp
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-2">
        {{-- Activités récentes --}}
        <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-200"><p class="text-[13px] font-bold text-gray-800">Activités récentes</p></div>
            <table class="w-full text-[12.5px]">
                <thead class="bg-[#3b4248]">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase whitespace-nowrap">Date/Heure</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Type</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Description</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase whitespace-nowrap">Tiers / Référence</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Utilisateur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentActivity->take(6) as $a)
                    @php
                        $base = class_basename($a->model_type ?? '');
                        [$tLabel, $tCls] = $typeBadges[$base] ?? [$base ?: '—', 'bg-gray-50 text-gray-600 border-gray-200'];
                    @endphp
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-gray-500 tabular-nums whitespace-nowrap">{{ $a->created_at->format('d/m H:i') }}</td>
                        <td class="px-3 py-1.5"><span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium border {{ $tCls }} whitespace-nowrap">{{ $tLabel }}</span></td>
                        <td class="px-3 py-1.5 text-gray-700">{{ $frAction($a->action, $tLabel) }}</td>
                        <td class="px-3 py-1.5">
                            @if($a->doc_ref)<span class="block font-mono text-[11px] text-blue-700 whitespace-nowrap">{{ $a->doc_ref }}</span>@endif
                            @if($a->tiers)<span class="block text-gray-500 text-[11px] whitespace-nowrap">{{ Str::limit($a->tiers, 16) }}</span>@endif
                            @if(! $a->doc_ref && ! $a->tiers)<span class="text-gray-400">—</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ Str::limit($a->user_name ?? '—', 12) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune activité récente.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-2 border-t border-gray-100">
                <a href="{{ route('audit.index') }}" class="text-[12px] font-medium text-emerald-700 hover:text-emerald-900">Voir toutes les activités →</a>
            </div>
        </div>

        {{-- Alertes et points de vigilance --}}
        <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div class="px-4 py-2 border-b border-gray-200"><p class="text-[13px] font-bold text-gray-800">Alertes et points de vigilance</p></div>
            <table class="w-full text-[12.5px]">
                <thead class="bg-[#3b4248]">
                    <tr>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Date</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Niveau</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Message</th>
                        <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Module</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alertesVigilance as $al)
                    @php [$nLabel, $nCls] = $niveauBadges[$al['niveau']]; @endphp
                    <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-gray-500 tabular-nums whitespace-nowrap">{{ now()->format('d/m/Y') }}</td>
                        <td class="px-3 py-1.5"><span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-semibold {{ $nCls }}">{{ $nLabel }}</span></td>
                        <td class="px-3 py-1.5 text-gray-700"><a href="{{ $al['url'] }}" class="hover:text-emerald-700">{{ $al['message'] }}</a></td>
                        <td class="px-3 py-1.5 text-gray-500">{{ $al['module'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucune alerte — tout est sous contrôle.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-2 border-t border-gray-100">
                <a href="{{ route('validations.index') }}" class="text-[12px] font-medium text-emerald-700 hover:text-emerald-900">Voir toutes les alertes →</a>
            </div>
        </div>
    </div>

    {{-- ═══ Scripts graphiques (ApexCharts différé — même mécanisme hard-load/Turbo que l'ancien dashboard) ═══ --}}
    <script>
    (function () {
        function run() {
            const charts = [];
            const fmtF = v => new Intl.NumberFormat('fr-FR').format(Math.round(v)) + ' F';
            const render = (sel, opts) => { const el = document.querySelector(sel); if (!el) return; const c = new ApexCharts(el, opts); c.render(); charts.push(c); };

            render('#x3-chart-ca', {
                chart: { type: 'bar', height: 230, toolbar: { show: false } },
                series: [
                    { name: 'CA {{ now()->year - 1 }}', data: @json($caAnnuel['prev']) },
                    { name: 'CA {{ now()->year }}',     data: @json($caAnnuel['cur']) },
                ],
                xaxis: { categories: @json($caAnnuel['labels']), labels: { style: { fontSize: '10px' } } },
                yaxis: { labels: { formatter: v => v >= 1e6 ? (v / 1e6).toFixed(1) + ' M' : (v >= 1e3 ? (v / 1e3).toFixed(0) + ' k' : v) } },
                colors: ['#93c5fd', '#1d4ed8'],
                plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
                dataLabels: { enabled: false },
                legend: { fontSize: '11px' },
                tooltip: { y: { formatter: fmtF } },
                grid: { strokeDashArray: 3 },
            });

            render('#x3-chart-treso', {
                chart: { type: 'line', height: 230, toolbar: { show: false } },
                series: [{ name: 'Trésorerie', data: @json($treso30) }],
                xaxis: { categories: @json($treso30Labels), tickAmount: 6, labels: { style: { fontSize: '10px' } } },
                yaxis: { labels: { formatter: v => Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + ' M' : (v / 1e3).toFixed(0) + ' k' } },
                colors: ['#059669'],
                stroke: { curve: 'smooth', width: 2.5 },
                markers: { size: 3 },
                dataLabels: { enabled: false },
                tooltip: { y: { formatter: fmtF } },
                grid: { strokeDashArray: 3 },
            });

            @if(count($caParFamille))
            render('#x3-chart-familles', {
                chart: { type: 'donut', height: 230 },
                series: @json(array_column($caParFamille, 'value')),
                labels: @json(array_column($caParFamille, 'label')),
                colors: ['#1d4ed8', '#059669', '#f59e0b', '#8b5cf6', '#9ca3af'],
                // [Maquette] Pourcentages dans la légende, pas sur le donut (ils se chevauchaient).
                // Légende en bas : à droite, le donut se faisait rogner dans la carte étroite (1/3 écran).
                legend: {
                    position: 'bottom', horizontalAlign: 'left', fontSize: '11px', itemMargin: { horizontal: 8, vertical: 2 },
                    formatter: (label, opts) => {
                        const v = opts.w.globals.series[opts.seriesIndex];
                        const t = opts.w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                        const pct = t > 0 ? (v / t * 100).toFixed(1).replace('.', ',') : '0';
                        return label + ' — ' + pct + ' %';
                    },
                },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '68%', labels: { show: true,
                    name: { fontSize: '11px', color: '#6b7280', offsetY: -4 },
                    value: { fontSize: '14px', fontWeight: 700, offsetY: 2,
                             formatter: v => { v = parseFloat(v); return v >= 1e6 ? (v / 1e6).toFixed(1).replace('.', ',') + ' M F' : new Intl.NumberFormat('fr-FR').format(v) + ' F'; } },
                    total: { show: true, label: 'Total HT', fontSize: '11px', color: '#6b7280',
                             formatter: w => { const t = w.globals.seriesTotals.reduce((a, b) => a + b, 0); return t >= 1e6 ? (t / 1e6).toFixed(2).replace('.', ',') + ' M F' : new Intl.NumberFormat('fr-FR').format(t) + ' F'; } },
                } } } },
                tooltip: { y: { formatter: fmtF } },
            });
            @else
            const elFam = document.querySelector('#x3-chart-familles');
            if (elFam) elFam.innerHTML = '<p class="text-[12px] text-gray-400 text-center pt-16">Aucune vente sur l\'exercice.</p>';
            @endif

            window.__turboCleanups = window.__turboCleanups || [];
            window.__turboCleanups.push(() => charts.forEach(c => { try { c.destroy(); } catch (e) {} }));
        }
        if (window.ApexCharts) { run(); } else { document.addEventListener('turbo:load', run, { once: true }); }
    }());
    </script>

    @else
    <div class="bg-white border border-gray-300 rounded-[4px] p-8 text-center text-gray-500 text-[13px]">
        Bienvenue. Vos validations en attente s'affichent ci-dessus — les indicateurs financiers sont réservés aux profils habilités.
    </div>
    @endcan

    {{-- ═══ Barre de contexte pied de page [maquette] ═══ --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Exercice : <span class="text-white font-semibold">{{ $fy?->starts_at?->year ?? now()->year }}</span></span>
        @if($fy)<span class="border-l border-white/10 pl-6">Période du <span class="text-white font-semibold">{{ $fy->starts_at?->format('d/m/Y') }}</span> au <span class="text-white font-semibold">{{ $fy->ends_at?->format('d/m/Y') }}</span></span>@endif
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
        <span class="border-l border-white/10 pl-6 inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> En ligne</span>
    </div>

</div>
@endsection
