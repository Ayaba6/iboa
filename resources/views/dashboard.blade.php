@extends('layouts.erp')
@section('title', 'Tableau de bord')

@section('breadcrumb')
    <span class="text-gray-900 font-semibold">Tableau de bord</span>
@endsection

@section('content')
{{-- [ERP-THEME] Première vue migrée sur le système .erp-* (resources/css/erp-theme.css). --}}
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="erp-stack">

    {{-- ── Titre + actualisation ─────────────────────────────────────────── --}}
    <x-erp.page-header title="Tableau de bord">
        <span class="erp-text-muted" style="font-size:12px">Actualisé à {{ now()->format('H:i:s') }}</span>
        <button onclick="window.location.reload()" title="Actualiser" class="erp-btn erp-btn-light erp-btn-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        </button>
    </x-erp.page-header>

    {{-- ── Bandeau validations en attente ────────────────────────────────── --}}
    @if(($pendingCount ?? 0) > 0)
    <div class="erp-alert erp-alert-warning" style="align-items:center; justify-content:space-between">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <div>
                <strong>{{ $pendingCount }} document{{ $pendingCount > 1 ? 's' : '' }} en attente de votre validation</strong>
                <div class="erp-text-muted" style="font-size:12px">Devis, commandes, OF, rebuts, demandes d'achat… selon vos habilitations</div>
            </div>
        </div>
        <a href="{{ route('validations.index') }}" class="erp-btn erp-btn-outline">Traiter →</a>
    </div>
    @endif

    @can('reports.view')

    {{-- ── Carte profil + accès rapides ──────────────────────────────────── --}}
    <div class="erp-card">
        <div class="erp-card-body" style="display:flex; flex-wrap:wrap; align-items:center; gap:12px 24px">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-[6px] text-white" style="background:var(--erp-primary)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                <div>
                    <p style="font-size:14px; font-weight:700">{{ auth()->user()->name }}</p>
                    <p class="erp-text-muted" style="font-size:12px">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</p>
                </div>
            </div>
            <div style="border-left:1px solid var(--erp-border-light); padding-left:24px">
                <span class="erp-kpi-label" style="color:var(--erp-primary)">CA annuel</span>
                <span class="erp-kpi-value" style="font-size:20px; color:var(--erp-primary-dark)">{{ $fmt($revenueAnnee) }} <span style="font-size:12px">FCFA</span></span>
                @if($facturesEnRetard > 0)
                <span class="erp-kpi-sub" style="color:var(--erp-danger)">{{ $facturesEnRetard }} facture(s) en retard</span>
                @endif
            </div>
            <div x-data="{ t: new Date().toLocaleTimeString('fr-FR') }" x-init="setInterval(() => t = new Date().toLocaleTimeString('fr-FR'), 1000)"
                 class="hidden md:flex items-center gap-2 border rounded-[4px] px-3 py-1.5" style="border-color:var(--erp-border-color); background:var(--erp-bg-muted)">
                <span class="w-2 h-2 rounded-full" style="background:var(--erp-primary)"></span>
                <span class="font-mono tabular-nums" style="font-size:15px; font-weight:700" x-text="t">{{ now()->format('H:i:s') }}</span>
            </div>
            <div class="erp-right-toolbar" style="flex-wrap:wrap">
                <x-erp.button variant="outline" :href="route('ventes.factures.create')">+ Nouvelle facture</x-erp.button>
                <x-erp.button variant="outline" :href="route('ventes.devis.create')">Devis</x-erp.button>
                <x-erp.button variant="outline" :href="route('clients.create')">Nouveau client</x-erp.button>
                <x-erp.button variant="outline" :href="route('reports.index')">Rapports</x-erp.button>
            </div>
        </div>
    </div>

    {{-- ── Rangée KPI ────────────────────────────────────────────────────── --}}
    @php
        // Sparkline 7 jours : polyline normalisée 0-100 × 0-28
        $spark = collect($ca7Days ?? []);
        $sMax  = max(1, (int) $spark->max());
        $sPts  = $spark->values()->map(fn($v, $i) => round($i * (100 / max(1, $spark->count() - 1)), 1) . ',' . round(30 - ($v / $sMax) * 26, 1))->implode(' ');
        $trendBadge = function (array $t) {
            if ($t['value'] === null) return '';
            $cls  = $t['direction'] === 'up' ? 'erp-badge-success' : 'erp-badge-danger';
            $sign = $t['direction'] === 'up' ? '+' : '-';
            return "<span class=\"erp-badge {$cls}\">{$sign}{$t['value']} %</span>";
        };
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5" style="gap:6px">
        {{-- CA aujourd'hui --}}
        <div class="erp-kpi">
            <span class="erp-kpi-label">CA aujourd'hui</span>
            <span class="erp-kpi-value" style="font-size:20px">{{ $fmt($revenueJour) }} <span style="font-size:11px; color:var(--erp-text-muted)">FCFA</span></span>
            <span class="erp-kpi-sub">vs hier : {{ $fmt($revenuePrevJour ?? 0) }} FCFA {!! $trendBadge($trendJour) !!}</span>
            <svg viewBox="0 0 100 32" class="w-full h-8 mt-2" preserveAspectRatio="none">
                <polyline points="{{ $sPts }}" fill="none" stroke="var(--erp-primary)" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
        </div>
        {{-- CA ce mois --}}
        <div class="erp-kpi">
            <span class="erp-kpi-label">CA ce mois</span>
            <span class="erp-kpi-value" style="font-size:20px">{{ $fmt($revenueMois) }} <span style="font-size:11px; color:var(--erp-text-muted)">FCFA</span></span>
            <span class="erp-kpi-sub">{{ $nbFacturesMois }} facture(s) {!! $trendBadge($trendRevenue) !!}</span>
        </div>
        {{-- Encaissements --}}
        <div class="erp-kpi">
            <span class="erp-kpi-label">Encaissements</span>
            <span class="erp-kpi-value" style="font-size:20px">{{ $fmt($encaissementsMois) }} <span style="font-size:11px; color:var(--erp-text-muted)">FCFA</span></span>
            <span class="erp-kpi-sub">ce mois {!! $trendBadge($trendEncaissements) !!}</span>
        </div>
        {{-- Trésorerie --}}
        <div class="erp-kpi erp-kpi--success">
            <span class="erp-kpi-label">Trésorerie</span>
            <span class="erp-kpi-value" style="font-size:19px">{{ $fmt($soldeTresorerie) }} <span style="font-size:11px; color:var(--erp-text-muted)">FCFA</span></span>
            <span class="erp-kpi-sub">Solde total</span>
            @foreach($cashAccounts->take(2) as $acc)
            <div class="flex justify-between" style="font-size:11px; color:var(--erp-text-muted)"><span class="truncate mr-2">{{ $acc->name }}</span><span class="tabular-nums font-semibold whitespace-nowrap">{{ $fmt($acc->current_balance) }}</span></div>
            @endforeach
        </div>
        {{-- Alertes --}}
        <div class="erp-kpi" style="display:flex; flex-direction:column; gap:6px">
            <span class="erp-kpi-label">Alertes</span>
            @if($facturesEnRetard > 0)
            <div class="erp-alert erp-alert-danger" style="padding:6px 8px">
                <div style="font-size:11px"><strong>{{ $facturesEnRetard }} facture{{ $facturesEnRetard > 1 ? 's' : '' }} en retard</strong><div class="tabular-nums">{{ $fmt($montantEnRetard) }} FCFA</div></div>
            </div>
            @endif
            @if($ruptureStock > 0)
            <div class="erp-alert erp-alert-warning" style="padding:6px 8px">
                <div style="font-size:11px"><strong>{{ $ruptureStock }} rupture(s) de stock</strong><div>Voir les alertes stock</div></div>
            </div>
            @else
            <div class="erp-alert erp-alert-success" style="padding:6px 8px">
                <div style="font-size:11px"><strong>Stock OK</strong><div>Aucune rupture</div></div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Rangée tables 1 : validations / factures / alertes stock ─────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3" style="gap:6px">

        {{-- Mes validations en attente --}}
        <x-erp.card title="Mes validations en attente ({{ $pendingCount }})" :flush="true"
                    :footer="null">
            <x-erp.table>
                <x-slot:head><tr>
                    <th>Type document</th><th>Référence</th><th>Date</th><th>Tiers</th><th class="erp-num">Montant</th><th>Devise</th>
                </tr></x-slot:head>
                @forelse($mesValidations as $v)
                <tr>
                    <td>{{ $v['type'] }}</td>
                    <td><a href="{{ $v['url'] }}" class="font-mono" style="color:var(--erp-primary)">{{ $v['number'] }}</a></td>
                    <td class="erp-text-muted whitespace-nowrap">{{ optional($v['submitted_at'])->format('d/m/Y') ?? '—' }}</td>
                    <td class="truncate max-w-[140px]">{{ $v['tiers'] ?? '—' }}</td>
                    <td class="erp-num font-semibold">{{ $v['amount'] !== null ? $fmt($v['amount']) : '—' }}</td>
                    <td class="erp-text-muted">XOF</td>
                </tr>
                @empty
                <tr><td colspan="6" class="erp-table-empty">Aucune validation en attente.</td></tr>
                @endforelse
            </x-erp.table>
            <a href="{{ route('validations.index') }}" class="erp-card-footer" style="display:block; color:var(--erp-primary); font-weight:600">Voir toutes les validations</a>
        </x-erp.card>

        {{-- Dernières factures client --}}
        <x-erp.card title="Dernières factures client" :flush="true">
            <x-erp.table>
                <x-slot:head><tr>
                    <th>Référence</th><th>Date</th><th>Client</th><th class="erp-num">Montant HT</th><th>Statut</th>
                </tr></x-slot:head>
                @forelse($dernieresFactures as $f)
                <tr>
                    <td><a href="{{ route('ventes.factures.show', $f) }}" class="font-mono" style="color:var(--erp-primary)">{{ $f->number }}</a></td>
                    <td class="erp-text-muted whitespace-nowrap">{{ $f->issued_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="truncate max-w-[130px]">{{ $f->client?->name ?? '—' }}</td>
                    <td class="erp-num font-semibold">{{ $fmt($f->subtotal_ht) }}</td>
                    <td><x-erp.badge :status="$f->status" /></td>
                </tr>
                @empty
                <tr><td colspan="5" class="erp-table-empty">Aucune facture.</td></tr>
                @endforelse
            </x-erp.table>
            <a href="{{ route('ventes.factures.index') }}" class="erp-card-footer" style="display:block; color:var(--erp-primary); font-weight:600">Voir toutes les factures</a>
        </x-erp.card>

        {{-- Alertes stock --}}
        <x-erp.card title="Alertes stock" :flush="true">
            <x-erp.table>
                <x-slot:head><tr>
                    <th>Article</th><th>Désignation</th><th class="erp-num">Stock dispo.</th><th class="erp-num">Seuil</th><th>Unité</th>
                </tr></x-slot:head>
                @forelse($alertesStock as $p)
                <tr>
                    <td><a href="{{ route('products.show', $p) }}" class="font-mono" style="color:var(--erp-primary)">{{ $p->code_article ?: $p->reference ?: '—' }}</a></td>
                    <td class="truncate max-w-[160px]">{{ $p->name }}</td>
                    <td class="erp-num font-semibold" style="color:{{ ($p->sous_seuil ?? false) ? ((float)($p->stock_dispo ?? 0) <= 0 ? 'var(--erp-danger)' : 'var(--erp-warning)') : 'inherit' }}">{{ number_format((float) ($p->stock_dispo ?? 0), 2, ',', ' ') }}</td>
                    <td class="erp-num erp-text-muted">{{ number_format((float) ($p->seuil ?? ($p->reorder_point ?? $p->stock_min)), 2, ',', ' ') }}</td>
                    <td class="erp-text-muted uppercase">{{ $p->unit?->abbreviation ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="erp-table-empty">Aucune alerte stock.</td></tr>
                @endforelse
            </x-erp.table>
            <a href="{{ route('stocks.seuils') }}" class="erp-card-footer" style="display:block; color:var(--erp-primary); font-weight:600">Voir toutes les alertes stock</a>
        </x-erp.card>
    </div>

    {{-- ── Rangée tables 2 : trésorerie / historique ─────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2" style="gap:6px">

        {{-- Vue trésorerie --}}
        <x-erp.card title="Vue trésorerie" :flush="true">
            <x-erp.table>
                <x-slot:head><tr>
                    <th>Compte</th><th>Intitulé</th><th>Devise</th><th class="erp-num">Solde</th>
                </tr></x-slot:head>
                @forelse($cashAccounts as $acc)
                <tr>
                    <td class="font-mono uppercase" style="color:var(--erp-primary)">{{ str_replace(' ', '-', mb_strtoupper(mb_substr($acc->name, 0, 12))) }}</td>
                    <td>{{ $acc->name }}</td>
                    <td class="erp-text-muted">XOF</td>
                    <td class="erp-num font-semibold" style="color:{{ $acc->current_balance < 0 ? 'var(--erp-danger)' : 'inherit' }}">{{ $fmt($acc->current_balance) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="erp-table-empty">Aucun compte actif.</td></tr>
                @endforelse
            </x-erp.table>
            <a href="{{ route('tresorerie.dashboard') }}" class="erp-card-footer" style="display:block; color:var(--erp-primary); font-weight:600">Voir la trésorerie complète</a>
        </x-erp.card>

        {{-- Historique récent --}}
        <x-erp.card title="Actions récentes" :flush="true">
            <x-erp.table>
                <x-slot:head><tr>
                    <th>Type</th><th>Action</th><th>Date</th><th>Utilisateur</th>
                </tr></x-slot:head>
                @forelse($recentActivity as $log)
                <tr>
                    <td>{{ class_basename($log->model_type) }} <span class="erp-text-muted font-mono" style="font-size:11px">#{{ $log->model_id }}</span></td>
                    <td class="erp-text-muted">{{ ucfirst($log->action) }}</td>
                    <td class="erp-text-muted whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user_name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="erp-table-empty">Aucune activité récente.</td></tr>
                @endforelse
            </x-erp.table>
            <a href="{{ \Illuminate\Support\Facades\Route::has('audit.index') ? route('audit.index') : '#' }}" class="erp-card-footer" style="display:block; color:var(--erp-primary); font-weight:600">Voir tout l'historique</a>
        </x-erp.card>
    </div>

    @else
        {{-- Accueil neutre pour les profils opérationnels --}}
        <div class="erp-card">
            <div class="erp-card-body" style="text-align:center; padding:32px">
                <div class="w-14 h-14 mx-auto mb-4 rounded-[6px] flex items-center justify-center" style="background:var(--erp-primary-light)">
                    <svg class="w-7 h-7" style="color:var(--erp-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <h2 style="font-size:16px; font-weight:700">Bienvenue, {{ auth()->user()->name }}</h2>
                <p class="erp-text-muted" style="font-size:13px; max-width:28rem; margin:4px auto 0">
                    Utilisez le menu latéral pour accéder à vos modules.
                    Les documents en attente de votre action apparaissent ci-dessus et dans
                    <a href="{{ route('validations.index') }}" style="color:var(--erp-primary); font-weight:600">Mes validations</a>.
                </p>
            </div>
        </div>
    @endcan

</div>
@endsection
