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
    $fmtF  = fn($n)   => number_format((float) $n, 0, ',', ' ');
    $maxCalls = $sevenDays->max('calls') ?: 1;
    $successRate = $stats['calls_today'] > 0 ? round($stats['calls_success'] / $stats['calls_today'] * 100) : null;
@endphp

<div class="space-y-3">

    {{-- ══ Barre titre + actions (pattern Sage X3) ══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Intégrations — Tableau de bord</h1>
            <p class="text-xs text-gray-400 mt-0.5">Activité API, transactions externes et monitoring — {{ now()->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            @can('integrations.manage')
            <a href="{{ route('integrations.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                + Nouvelle intégration
            </a>
            @endcan
            <a href="{{ route('integrations.transactions') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Transactions
            </a>
            <a href="{{ route('integrations.logs') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Logs API
            </a>
            <a href="{{ route('integrations.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-[4px] text-sm font-medium transition-colors">
                Connecteurs
            </a>
        </div>
    </div>

    {{-- ══ Alertes intégrations en erreur ════════════════════════════════════ --}}
    @if($alertIntegrations->isNotEmpty())
    <div class="bg-red-50 border border-red-300 rounded-[4px] p-4">
        <p class="text-sm font-semibold text-red-800">
            {{ $alertIntegrations->count() }} intégration{{ $alertIntegrations->count() > 1 ? 's' : '' }} en erreur
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($alertIntegrations as $alrt)
            <a href="{{ route('integrations.show', $alrt) }}"
               class="inline-flex items-center gap-1.5 bg-red-100 hover:bg-red-200 text-red-800 text-xs font-medium px-2.5 py-1 rounded-[3px] transition-colors">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse inline-block"></span>
                {{ $alrt->name }}
                @if($alrt->last_error)
                <span class="text-red-600 font-normal truncate max-w-[150px]" title="{{ $alrt->last_error }}">— {{ Str::limit($alrt->last_error, 40) }}</span>
                @endif
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ Synthèse KPIs (pattern X3 : barre grid divide-x) ═════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 grid grid-cols-2 lg:grid-cols-5 divide-x divide-gray-200">
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Appels aujourd'hui</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-gray-900 mt-0.5">{{ $fmt($stats['calls_today']) }}</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Succès</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-emerald-700 mt-0.5">{{ $fmt($stats['calls_success']) }}</p>
            <p class="text-[10px] text-gray-400">{{ $successRate !== null ? $successRate . ' %' : '—' }}</p>
        </div>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Échecs</p>
            <p class="text-[15px] font-bold font-mono tabular-nums {{ $stats['calls_failed'] > 0 ? 'text-red-600' : 'text-gray-800' }} mt-0.5">{{ $fmt($stats['calls_failed']) }}</p>
        </div>
        <a href="{{ route('integrations.transactions') }}" class="p-3 text-center hover:bg-[#eef5f0]/40 transition-colors">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Transactions</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ $fmt($stats['tx_today']) }}</p>
            <p class="text-[10px] {{ $stats['tx_pending'] > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">{{ $stats['tx_pending'] }} en attente</p>
        </a>
        <div class="p-3 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-semibold tracking-wide">Montant confirmé</p>
            <p class="text-[15px] font-bold font-mono tabular-nums text-blue-700 mt-0.5">{{ $fmtF($stats['amount_confirmed']) }} <span class="text-[10px] font-normal text-gray-400">FCFA</span></p>
            <p class="text-[10px] text-gray-400">semaine : {{ $fmtF($stats['amount_week']) }} FCFA</p>
        </div>
    </div>

    {{-- ══ 1. Activité des 7 derniers jours ══════════════════════════════════ --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">1. Activité des 7 derniers jours</p>
            <div class="flex items-center gap-3 text-[11px] text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-[2px] bg-emerald-500 inline-block"></span>Succès</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-[2px] bg-red-500 inline-block"></span>Échecs</span>
            </div>
        </div>
        <div class="p-4">
            <div class="flex items-end gap-2 h-24">
                @foreach($sevenDays as $day)
                @php
                    $successH = $day['calls'] > 0 ? round($day['success'] / $maxCalls * 100) : 0;
                    $failH    = $day['calls'] > 0 ? round($day['failed']  / $maxCalls * 100) : 0;
                @endphp
                <div class="flex-1 flex flex-col items-center gap-0.5">
                    <div class="w-full flex flex-col justify-end gap-px" style="height: 80px;">
                        @if($day['calls'] > 0)
                        <div class="w-full rounded-t-sm bg-emerald-500 transition-all" style="height: {{ $successH }}%"></div>
                        @if($failH > 0)
                        <div class="w-full bg-red-500" style="height: {{ $failH }}%"></div>
                        @endif
                        @else
                        <div class="w-full rounded-sm bg-gray-100" style="height: 6px"></div>
                        @endif
                    </div>
                    <span class="text-[10px] text-gray-400">{{ $day['label'] }}</span>
                    <span class="text-[10px] font-medium font-mono tabular-nums text-gray-600">{{ $day['calls'] }}</span>
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
                             title="{{ $fmtF($day['amount']) }} FCFA"></div>
                    </div>
                </div>
                @endforeach
            </div>
            <p class="text-[10px] text-gray-400 mt-1">Montants confirmés (FCFA) — 7 jours</p>
        </div>
    </div>

    {{-- ══ 2. État des connecteurs ═══════════════════════════════════════════ --}}
    @if($integrations->isNotEmpty())
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
            <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">2. État des connecteurs</p>
            <a href="{{ route('integrations.index') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Voir tous →</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 divide-x divide-y divide-gray-100">
            @foreach($integrations as $intg)
            @php $sc = $intg->statusColor(); @endphp
            <a href="{{ route('integrations.show', $intg) }}"
               class="p-3 hover:bg-[#eef5f0]/40 transition-colors text-[12.5px]">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate" title="{{ $intg->name }}">{{ $intg->name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $intg->provider }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium
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
                        <span class="text-[9px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-1.5 rounded-[2px]">SANDBOX</span>
                        @endif
                    </div>
                </div>
                <div class="mt-1.5 flex items-center gap-3 text-xs text-gray-400 font-mono tabular-nums">
                    <span>{{ $intg->logs_count ?? 0 }} appels</span>
                    <span>{{ $intg->external_transactions_count ?? 0 }} tx</span>
                    @if($intg->last_success_at)
                    <span class="font-sans">ok {{ $intg->last_success_at->diffForHumans() }}</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- ══ 3. Derniers appels API ════════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">3. Derniers appels API</p>
                <a href="{{ route('integrations.logs') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Tout voir →</a>
            </div>
            @if($recentLogs->isEmpty())
                <div class="px-4 py-6 text-center text-sm text-gray-400">Aucun appel API enregistré.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-[#eef5f0]/70">
                        <tr>
                            <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Service</th>
                            <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Méthode / Endpoint</th>
                            <th class="px-3 py-1.5 text-center text-[10px] font-bold text-emerald-900 uppercase tracking-wide">OK</th>
                            <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">ms</th>
                            <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Heure</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentLogs as $log)
                        @php $mc = $log->methodColor(); @endphp
                        <tr class="even:bg-gray-50/40 hover:bg-[#eef5f0]/40 transition-colors">
                            <td class="px-3 py-1.5 font-medium text-gray-700 truncate max-w-[90px]" title="{{ $log->service }}">{{ $log->service }}</td>
                            <td class="px-3 py-1.5">
                                <span class="font-mono text-[10px] font-bold text-{{ $mc }}-700 bg-{{ $mc }}-50 px-1.5 py-0.5 rounded-[2px] mr-1">{{ $log->method }}</span>
                                <span class="text-gray-500" title="{{ $log->endpoint }}">{{ Str::limit($log->endpoint, 25) }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                @if($log->success)
                                    <span class="text-emerald-600 font-bold">✓</span>
                                @else
                                    <span class="text-red-600 font-bold">✕</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500">{{ $log->durationLabel() }}</td>
                            <td class="px-3 py-1.5 text-right text-gray-400 whitespace-nowrap font-mono tabular-nums">{{ $log->created_at->format('H:i:s') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ══ 4. Transactions récentes ══════════════════════════════════════ --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-[#eef5f0] border-b border-emerald-100">
                <p class="text-[11px] font-bold text-emerald-900 uppercase tracking-wide">4. Transactions récentes</p>
                <a href="{{ route('integrations.transactions') }}" class="text-[11px] text-emerald-600 hover:text-emerald-800 font-medium">Tout voir →</a>
            </div>
            @if($recentTransactions->isEmpty())
                <div class="px-4 py-6 text-center text-sm text-gray-400">Aucune transaction externe.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-[#eef5f0]/70">
                        <tr>
                            <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Référence</th>
                            <th class="px-3 py-1.5 text-left text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Provider</th>
                            <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Montant</th>
                            <th class="px-3 py-1.5 text-center text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Statut</th>
                            <th class="px-3 py-1.5 text-right text-[10px] font-bold text-emerald-900 uppercase tracking-wide">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentTransactions as $tx)
                        @php $sc = $tx->statusColor(); @endphp
                        <tr class="even:bg-gray-50/40 hover:bg-[#eef5f0]/40 transition-colors">
                            <td class="px-3 py-1.5 font-mono text-[11px]">
                                @if($tx->invoice)
                                <a href="{{ route('ventes.factures.show', $tx->invoice) }}" class="text-emerald-700 font-semibold hover:underline">{{ $tx->invoice->number }}</a>
                                @else
                                <span class="text-gray-700" title="{{ $tx->internal_reference }}">{{ Str::limit($tx->internal_reference, 18) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-gray-500">{{ $tx->provider }}</td>
                            <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold text-blue-700">
                                {{ number_format($tx->amount, 0, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <span class="inline-flex px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                    {{ $tx->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-400 whitespace-nowrap font-mono tabular-nums">{{ $tx->created_at->format('d/m H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Footer contexte (pattern X3) ══════════════════════════════════════ --}}
    <div class="flex items-center justify-between bg-gray-900 text-gray-200 rounded-[4px] px-4 py-2 text-xs">
        <div class="flex items-center gap-4 flex-wrap">
            <span>Société : <strong class="text-white">{{ currentCompany()?->name }}</strong></span>
            <span>Module : <strong class="text-white">Intégrations — Tableau de bord</strong></span>
            @if($stats['avg_latency_ms'] > 0)
            <span>Latence moy. : <strong class="text-white">{{ $stats['avg_latency_ms'] >= 1000 ? round($stats['avg_latency_ms'] / 1000, 1) . ' s' : round($stats['avg_latency_ms']) . ' ms' }}</strong></span>
            @endif
            @if($stats['tx_pending'] > 0)
            <a href="{{ route('integrations.transactions', ['status' => 'pending']) }}" class="text-amber-300 hover:text-amber-200 font-semibold">{{ $stats['tx_pending'] }} tx en attente →</a>
            @endif
        </div>
        <div class="flex items-center gap-4">
            <span>Utilisateur : <strong class="text-white">{{ auth()->user()?->name }}</strong></span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </div>

</div>
@endsection
