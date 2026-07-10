@extends('layouts.erp')
@section('title', $integration->name . ' — Détails')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $integration->name }}</span>
@endsection

@section('content')
@php
    $sc  = $integration->statusColor();
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
@endphp
<div class="space-y-6" x-data="{ tab: 'logs', pingLoading: false, pingResult: null }">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-gray-100 border border-gray-200 flex items-center justify-center text-2xl flex-shrink-0">
                {{ $integration->typeIcon() }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold text-gray-900">{{ $integration->name }}</h1>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                        @if($sc === 'emerald') bg-emerald-100 text-emerald-700
                        @elseif($sc === 'red')  bg-red-100 text-red-700
                        @elseif($sc === 'amber') bg-amber-100 text-amber-700
                        @else bg-gray-100 text-gray-500 @endif">
                        <span class="w-1.5 h-1.5 rounded-full inline-block
                            @if($sc === 'emerald') bg-emerald-500 {{ $integration->is_active ? 'animate-pulse' : '' }}
                            @elseif($sc === 'red')  bg-red-500
                            @elseif($sc === 'amber') bg-amber-400
                            @else bg-gray-400 @endif"></span>
                        {{ $integration->statusLabel() }}
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded
                        @if($integration->isProduction()) bg-emerald-50 text-emerald-700 border border-emerald-200
                        @else bg-amber-50 text-amber-600 border border-amber-200 @endif">
                        {{ $integration->isProduction() ? 'PRODUCTION' : 'SANDBOX' }}
                    </span>
                    @if(! $integration->is_active)
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">Inactif</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mt-0.5">{{ $integration->typeLabel() }} · {{ $integration->provider }}</p>
                @if($integration->last_error)
                <p class="text-xs text-red-600 mt-1 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ Str::limit($integration->last_error, 100) }}
                </p>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-2 sm:flex-shrink-0">
            {{-- Ping AJAX --}}
            <button
                @click="pingLoading = true; pingResult = null;
                    fetch('{{ route('integrations.ping', $integration) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => { pingResult = d; pingLoading = false; })
                    .catch(() => { pingResult = { success: false, message: 'Erreur réseau' }; pingLoading = false; })"
                :disabled="pingLoading"
                class="inline-flex items-center gap-1.5 border border-blue-300 text-blue-700 hover:bg-blue-50 disabled:opacity-50 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
                <svg x-show="!pingLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <svg x-show="pingLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span x-text="pingLoading ? 'Test...' : 'Ping'"></span>
            </button>

            @if($integration->isSandbox())
            <a href="{{ route('integrations.simulate', $integration) }}"
               class="inline-flex items-center gap-1.5 border border-violet-300 text-violet-700 hover:bg-violet-50 text-sm font-medium px-3 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Simuler
            </a>
            @endif

            @if($integration->provider === 'fiscal_bf')
            <a href="{{ route('integrations.fiscal.index', $integration) }}"
               class="inline-flex items-center gap-1.5 border border-green-300 text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-400 dark:hover:bg-green-900/20 text-sm font-medium px-3 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Fiscal DGI
            </a>
            @endif

            <a href="{{ route('integrations.edit', $integration) }}"
               class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">
                Modifier
            </a>

            <form method="POST" action="{{ route('integrations.toggle', $integration) }}" class="inline">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-1.5 text-sm font-medium px-3 py-2 rounded-lg border transition-colors
                        {{ $integration->is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">
                    {{ $integration->is_active ? 'Désactiver' : 'Activer' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Ping result --}}
    <div x-show="pingResult !== null" x-transition x-cloak
         :class="pingResult?.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'"
         class="border rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <span x-text="pingResult?.success ? '✅' : '❌'"></span>
        <span x-text="pingResult?.message ?? 'Résultat inconnu'"></span>
        <button @click="pingResult = null" class="ml-auto text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
    </div>

    {{-- ── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @php $statsKpis = [
            ['label' => 'Appels total',    'value' => number_format($stats['total_calls'], 0, ',', ' ')],
            ['label' => 'Taux succès',     'value' => $successRate !== null ? $successRate . '%' : '—'],
            ['label' => 'Montant confirmé','value' => $fmt($stats['total_amount']) . ' FCFA'],
            ['label' => 'Tx en attente',   'value' => $stats['pending_tx'],  'warn' => $stats['pending_tx'] > 0],
        ]; @endphp
        @foreach($statsKpis as $k)
        <div class="bg-white rounded-[4px] border {{ ($k['warn'] ?? false) ? 'border-amber-200' : 'border-gray-200' }} p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase">{{ $k['label'] }}</p>
            <p class="text-xl font-bold {{ ($k['warn'] ?? false) ? 'text-amber-700' : 'text-gray-900' }} mt-1">{{ $k['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Webhook URL ──────────────────────────────────────────────────────── --}}
    @if($integration->hasWebhook())
    <div class="bg-blue-50 border border-blue-200 rounded-[4px] p-4" x-data="{ copied: false }">
        <p class="text-xs font-semibold text-blue-800 uppercase tracking-wide mb-2">URL Webhook — à configurer chez le fournisseur</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 text-xs font-mono bg-white border border-blue-200 rounded-lg px-3 py-2 text-blue-900 break-all">{{ $integration->webhookUrl() }}</code>
            <button @click="navigator.clipboard.writeText('{{ $integration->webhookUrl() }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                class="flex-shrink-0 inline-flex items-center gap-1 border border-blue-300 text-blue-700 hover:bg-blue-100 text-xs font-medium px-2.5 py-2 rounded-lg transition-colors">
                <span x-show="!copied"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></span>
                <span x-show="copied" class="text-emerald-600">✓</span>
                <span x-text="copied ? 'Copié !' : 'Copier'"></span>
            </button>
        </div>
    </div>
    @endif

    {{-- ── Onglets ─────────────────────────────────────────────────────────── --}}
    <div>
        <nav class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
            @foreach([
                'logs'         => "Logs API ({$stats['calls_today']} auj.)",
                'transactions' => "Transactions ({$stats['total_tx']})",
                'config'       => 'Configuration',
            ] as $t => $tLabel)
            <button @click="tab = '{{ $t }}'"
                :class="tab === '{{ $t }}' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all">
                {{ $tLabel }}
            </button>
            @endforeach
        </nav>

        {{-- Tab: Logs --}}
        <div x-show="tab === 'logs'" class="mt-4">
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Historique des appels API</h2>
                    <span class="text-xs text-gray-400">{{ $stats['total_calls'] }} total · {{ $stats['failed_calls'] }} échecs</span>
                </div>
                @if($logs->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-400">Aucun appel API enregistré</div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" x-data="{ expanded: null }">
                        <thead class="bg-[#eef5f0] border-b border-gray-300">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Méthode</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Endpoint</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">HTTP</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Sens</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">OK</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Durée</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($logs as $log)
                            @php $mc = $log->methodColor(); @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $log->id }} ? null : {{ $log->id }}">
                                <td class="px-4 py-2">
                                    <span class="font-mono text-[10px] font-bold text-{{ $mc }}-700 bg-{{ $mc }}-50 px-1.5 py-0.5 rounded">{{ $log->method }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-600 font-mono truncate max-w-[200px]">{{ $log->endpoint }}</td>
                                <td class="px-4 py-2 text-center text-gray-500">{{ $log->status_code ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded {{ $log->direction === 'inbound' ? 'bg-violet-50 text-violet-600' : 'bg-blue-50 text-blue-600' }}">{{ $log->direction === 'inbound' ? 'IN' : 'OUT' }}</span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if($log->success)
                                        <span class="text-emerald-500 font-bold">✓</span>
                                    @else
                                        <span class="text-red-500 font-bold">✗</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $log->durationLabel() }}</td>
                                <td class="px-4 py-2 text-right text-gray-400 whitespace-nowrap">{{ $log->created_at->format('d/m H:i:s') }}</td>
                            </tr>
                            <tr x-show="expanded === {{ $log->id }}" x-cloak>
                                <td colspan="7" class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                                    <div class="grid grid-cols-2 gap-3 text-xs">
                                        <div>
                                            <p class="font-semibold text-gray-600 mb-1">Payload</p>
                                            <pre class="bg-white border border-gray-200 rounded p-2 overflow-auto max-h-36 text-[10px] text-gray-700">{{ json_encode($log->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-600 mb-1">Réponse</p>
                                            <pre class="bg-white border border-gray-200 rounded p-2 overflow-auto max-h-36 text-[10px] text-gray-700">{{ json_encode($log->response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        @if($log->error_message)
                                        <div class="col-span-2 text-red-600">⚠ {{ $log->error_message }}</div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
                @endif
                @endif
            </div>
        </div>

        {{-- Tab: Transactions --}}
        <div x-show="tab === 'transactions'" class="mt-4" x-cloak>
            <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">Transactions</h2>
                </div>
                @if($transactions->isEmpty())
                    <div class="p-10 text-center text-sm text-gray-400">Aucune transaction enregistrée</div>
                @else
                <table class="w-full text-xs">
                    <thead class="bg-[#eef5f0] border-b border-gray-300">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Réf interne</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Facture</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Téléphone</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Montant</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Statut</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Tentatives</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Date</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($transactions as $tx)
                        @php $sc2 = $tx->statusColor(); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-[10px] text-gray-700">{{ $tx->internal_reference }}</td>
                            <td class="px-4 py-2">
                                @if($tx->invoice)
                                <a href="{{ route('ventes.factures.show', $tx->invoice) }}" class="text-emerald-700 hover:underline font-medium">{{ $tx->invoice->number }}</a>
                                @else <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ $tx->phone_number ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium">{{ number_format($tx->amount, 0, ',', ' ') }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-{{ $sc2 }}-100 text-{{ $sc2 }}-700">{{ $tx->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-2 text-center text-gray-400">{{ $tx->retry_count ?? 0 }}</td>
                            <td class="px-4 py-2 text-right text-gray-400 whitespace-nowrap">{{ $tx->created_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($tx->canRetry())
                                <form method="POST" action="{{ route('integrations.transactions.retry', $tx) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[10px] text-amber-600 hover:text-amber-800 font-medium hover:underline">Relancer</button>
                                </form>
                                @else <span class="text-[10px] text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @if($tx->failure_reason)
                        <tr><td colspan="8" class="px-4 py-1 bg-red-50 text-[10px] text-red-600">⚠ {{ $tx->failure_reason }}</td></tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
                @if($transactions->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $transactions->links() }}</div>
                @endif
                @endif
            </div>
        </div>

        {{-- Tab: Config --}}
        <div x-show="tab === 'config'" class="mt-4" x-cloak>
            <div class="bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Configuration</h2>
                    <a href="{{ route('integrations.edit', $integration) }}" class="text-xs text-blue-600 hover:underline">Modifier</a>
                </div>
                @php $rows = [
                    ['Type',           $integration->typeLabel()],
                    ['Fournisseur',    $integration->provider],
                    ['URL de base',    $integration->base_url ?? '—'],
                    ['URL sandbox',    $integration->sandbox_base_url ?? '—'],
                    ['Mode',           strtoupper($integration->mode)],
                    ['Timeout',        ($integration->timeout_seconds ?? 30) . 's'],
                    ['Actif',          $integration->is_active ? 'Oui' : 'Non'],
                    ['Alertes admin',  $integration->notify_on_error ? 'Activées' : 'Désactivées'],
                    ['Nb erreurs',     $integration->error_count ?? 0],
                    ['Dernière erreur',$integration->last_error_at?->format('d/m/Y H:i') ?? '—'],
                ]; @endphp
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                    @foreach($rows as [$label, $value])
                    <div>
                        <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $value }}</dd>
                    </div>
                    @endforeach
                </dl>
                @if($integration->extra_config)
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Extra config</p>
                    <pre class="text-[11px] font-mono bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-auto">{{ json_encode($integration->extra_config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                @endif
                @if($integration->last_error)
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-red-600 mb-1">Dernière erreur</p>
                    <p class="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg p-3">{{ $integration->last_error }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
