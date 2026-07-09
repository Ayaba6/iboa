@extends('layouts.erp')
@section('title', 'Intégrations — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Tableau de bord</span>
@endsection

@section('content')
@php
    $fmt   = fn($n)   => number_format((int) $n, 0, ',', ' ');
    $fmtF  = fn($n)   => number_format((float) $n, 0, ',', ' ') . ' FCFA';
    $maxCalls = $sevenDays->max('calls') ?: 1;
@endphp

<div class="space-y-6" x-data="{ refreshing: false }">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tableau de bord Intégrations</h1>
            <p class="text-sm text-gray-500">Activité API, transactions et monitoring temps réel</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('integrations.transactions') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Transactions
            </a>
            <a href="{{ route('integrations.logs') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Logs API
            </a>
            <a href="{{ route('integrations.index') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">
                Connecteurs
            </a>
            @can('integrations.manage')
            <a href="{{ route('integrations.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-2 rounded-lg flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle intégration
            </a>
            @endcan
        </div>
    </div>

    {{-- ── Alertes erreurs ─────────────────────────────────────────────────── --}}
    @if($alertIntegrations->isNotEmpty())
    <div class="bg-red-50 border border-red-200 rounded-[4px] p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-red-800">
                    {{ $alertIntegrations->count() }} intégration{{ $alertIntegrations->count() > 1 ? 's' : '' }} en erreur
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($alertIntegrations as $alrt)
                    <a href="{{ route('integrations.show', $alrt) }}"
                       class="inline-flex items-center gap-1.5 bg-red-100 hover:bg-red-200 text-red-800 text-xs font-medium px-2.5 py-1 rounded-full transition-colors">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse inline-block"></span>
                        {{ $alrt->name }}
                        @if($alrt->last_error)
                        <span class="text-red-600 font-normal truncate max-w-[150px]">— {{ Str::limit($alrt->last_error, 40) }}</span>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── KPIs ─────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @php $kpis = [
            ['label' => "Appels aujourd'hui", 'value' => $stats['calls_today'],     'sub' => null,                                 'color' => 'gray'],
            ['label' => 'Succès',            'value' => $stats['calls_success'],    'sub' => $stats['calls_today'] > 0 ? round($stats['calls_success']/$stats['calls_today']*100).'%' : '—', 'color' => 'emerald'],
            ['label' => 'Échecs',            'value' => $stats['calls_failed'],     'sub' => null,                                 'color' => $stats['calls_failed'] > 0 ? 'red' : 'gray'],
            ['label' => 'Transactions',      'value' => $stats['tx_today'],         'sub' => $stats['tx_pending'].' en attente',   'color' => 'blue'],
            ['label' => "Montant confirmé",  'value' => $fmtF($stats['amount_confirmed']), 'sub' => "Semaine : ".$fmtF($stats['amount_week']), 'color' => 'indigo'],
        ]; @endphp
        @foreach($kpis as $k)
        <div class="bg-white rounded-[4px] border border-{{ $k['color'] === 'gray' ? 'gray' : $k['color'] }}-{{ $k['color'] === 'gray' ? '200' : '200' }} p-5">
            <p class="text-xs font-medium text-{{ $k['color'] }}-{{ $k['color'] === 'gray' ? '500' : '600' }} uppercase tracking-wide">{{ $k['label'] }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-{{ $k['color'] === 'gray' ? 'gray-900' : $k['color'].'-700' }}">{{ $k['value'] }}</p>
            @if($k['sub'])
            <p class="text-xs text-{{ $k['color'] === 'gray' ? 'gray-400' : $k['color'].'-500' }} mt-0.5">{{ $k['sub'] }}</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ── Graphe 7 jours ──────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-[4px] border border-gray-300 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-800">Activité des 7 derniers jours</h2>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-400 inline-block"></span>Succès</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-400 inline-block"></span>Échecs</span>
            </div>
        </div>
        <div class="flex items-end gap-2 h-24">
            @foreach($sevenDays as $day)
            @php
                $successH = $day['calls'] > 0 ? round($day['success'] / $maxCalls * 100) : 0;
                $failH    = $day['calls'] > 0 ? round($day['failed']  / $maxCalls * 100) : 0;
            @endphp
            <div class="flex-1 flex flex-col items-center gap-0.5">
                <div class="w-full flex flex-col justify-end gap-px" style="height: 80px;">
                    @if($day['calls'] > 0)
                    <div class="w-full rounded-t-sm bg-emerald-400 transition-all" style="height: {{ $successH }}%"></div>
                    @if($failH > 0)
                    <div class="w-full bg-red-400" style="height: {{ $failH }}%"></div>
                    @endif
                    @else
                    <div class="w-full rounded-sm bg-gray-100" style="height: 6px"></div>
                    @endif
                </div>
                <span class="text-[10px] text-gray-400">{{ $day['label'] }}</span>
                <span class="text-[10px] font-medium text-gray-600">{{ $day['calls'] }}</span>
            </div>
            @endforeach
        </div>

        {{-- Montants semaine --}}
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-end gap-2 h-12">
            @php $maxAmount = $sevenDays->max('amount') ?: 1; @endphp
            @foreach($sevenDays as $day)
            @php $amountH = $day['amount'] > 0 ? max(4, round($day['amount'] / $maxAmount * 100)) : 0; @endphp
            <div class="flex-1 flex flex-col items-center">
                <div class="w-full flex flex-col justify-end" style="height: 36px;">
                    <div class="w-full rounded-t-sm bg-emerald-200 transition-all" style="height: {{ $amountH }}%"
                         title="{{ number_format($day['amount'], 0, ',', ' ') }} FCFA"></div>
                </div>
            </div>
            @endforeach
        </div>
        <p class="text-[10px] text-gray-400 mt-1">Montants confirmés (FCFA) — 7 jours</p>
    </div>

    {{-- ── État des connecteurs ─────────────────────────────────────────────── --}}
    @if($integrations->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">État des connecteurs</h2>
            <a href="{{ route('integrations.index') }}" class="text-xs text-blue-600 hover:underline">Voir tous</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 divide-x divide-y divide-gray-100">
            @foreach($integrations as $intg)
            @php $sc = $intg->statusColor(); @endphp
            <a href="{{ route('integrations.show', $intg) }}"
               class="p-4 hover:bg-gray-50 transition-colors group">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-lg">{{ $intg->typeIcon() }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $intg->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $intg->provider }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                            @if($sc === 'emerald') bg-emerald-100 text-emerald-700
                            @elseif($sc === 'red')  bg-red-100 text-red-700
                            @elseif($sc === 'amber') bg-amber-100 text-amber-700
                            @else bg-gray-100 text-gray-500 @endif">
                            <span class="w-1.5 h-1.5 rounded-full inline-block
                                @if($sc === 'emerald') bg-emerald-500 {{ $intg->is_active ? 'animate-pulse' : '' }}
                                @elseif($sc === 'red')  bg-red-500
                                @elseif($sc === 'amber') bg-amber-500
                                @else bg-gray-400 @endif"></span>
                            {{ $intg->statusLabel() }}
                        </span>
                        @if($intg->mode === 'sandbox')
                        <span class="text-[9px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-1.5 rounded">SANDBOX</span>
                        @endif
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                    <span>{{ $intg->logs_count ?? 0 }} appels</span>
                    <span>{{ $intg->external_transactions_count ?? 0 }} tx</span>
                    @if($intg->last_success_at)
                    <span>ok {{ $intg->last_success_at->diffForHumans() }}</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Deux colonnes : logs + transactions ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Derniers logs ─────────────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">Derniers appels API</h2>
                <a href="{{ route('integrations.logs') }}" class="text-xs text-blue-600 hover:underline">Tout voir</a>
            </div>
            @if($recentLogs->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400">Aucun appel API enregistré</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-500">Service</th>
                            <th class="px-3 py-2 text-left text-gray-500">Méthode / Endpoint</th>
                            <th class="px-3 py-2 text-center text-gray-500">OK</th>
                            <th class="px-3 py-2 text-right text-gray-500">ms</th>
                            <th class="px-3 py-2 text-right text-gray-500">Heure</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentLogs as $log)
                        @php $mc = $log->methodColor(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-medium text-gray-700 truncate max-w-[90px]">{{ $log->service }}</td>
                            <td class="px-3 py-2">
                                <span class="font-mono text-[10px] font-bold text-{{ $mc }}-700 bg-{{ $mc }}-50 px-1.5 py-0.5 rounded mr-1">{{ $log->method }}</span>
                                <span class="text-gray-500 truncate">{{ Str::limit($log->endpoint, 25) }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($log->success)
                                    <svg class="w-3.5 h-3.5 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                @else
                                    <svg class="w-3.5 h-3.5 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $log->durationLabel() }}</td>
                            <td class="px-3 py-2 text-right text-gray-400 whitespace-nowrap">{{ $log->created_at->format('H:i:s') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Transactions récentes ─────────────────────────────────── --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">Transactions récentes</h2>
                <a href="{{ route('integrations.transactions') }}" class="text-xs text-blue-600 hover:underline">Tout voir</a>
            </div>
            @if($recentTransactions->isEmpty())
                <div class="p-10 text-center text-sm text-gray-400">Aucune transaction externe</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-500">Référence</th>
                            <th class="px-3 py-2 text-left text-gray-500">Provider</th>
                            <th class="px-3 py-2 text-right text-gray-500">Montant</th>
                            <th class="px-3 py-2 text-center text-gray-500">Statut</th>
                            <th class="px-3 py-2 text-right text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentTransactions as $tx)
                        @php $sc = $tx->statusColor(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono text-gray-700 text-[10px]">
                                @if($tx->invoice)
                                <a href="{{ route('ventes.factures.show', $tx->invoice) }}" class="text-emerald-700 hover:underline">{{ $tx->invoice->number }}</a>
                                @else
                                {{ Str::limit($tx->internal_reference, 18) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-500">{{ $tx->provider }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900">
                                {{ number_format($tx->amount, 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-medium
                                    bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                    {{ $tx->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-400 whitespace-nowrap">{{ $tx->created_at->format('d/m H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Latence moyenne ──────────────────────────────────────────────────── --}}
    @if($stats['avg_latency_ms'] > 0)
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 flex items-center gap-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-xs text-gray-500">Latence moyenne aujourd'hui</p>
            <p class="text-sm font-bold text-gray-900">
                @if($stats['avg_latency_ms'] >= 1000)
                    {{ round($stats['avg_latency_ms'] / 1000, 1) }}s
                @else
                    {{ round($stats['avg_latency_ms']) }}ms
                @endif
                <span class="font-normal text-gray-400 ml-1">
                    @if($stats['avg_latency_ms'] < 500) — excellent
                    @elseif($stats['avg_latency_ms'] < 1500) — acceptable
                    @else — lent, vérifier le réseau
                    @endif
                </span>
            </p>
        </div>
        @if($stats['tx_pending'] > 0)
        <div class="ml-auto flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse inline-block"></span>
            <span class="text-xs font-medium text-amber-800">{{ $stats['tx_pending'] }} transaction{{ $stats['tx_pending'] > 1 ? 's' : '' }} en attente</span>
            <a href="{{ route('integrations.transactions', ['status' => 'pending']) }}" class="text-xs text-amber-600 hover:underline">Voir</a>
        </div>
        @endif
    </div>
    @endif

</div>
@endsection
