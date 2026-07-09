@extends('layouts.erp')
@section('title', 'Logs API')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Logs API</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Logs API</h1>
            <p class="text-sm text-gray-500">Historique complet des appels entrants et sortants</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('integrations.dashboard') }}"
               class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-2 rounded-lg font-medium">
                Tableau de bord
            </a>
            <a href="{{ route('integrations.transactions') }}"
               class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-2 rounded-lg font-medium">
                Transactions
            </a>
        </div>
    </div>

    {{-- KPI bar --}}
    @php
        $totalToday = $logs->total();
        $successCount = $logs->getCollection()->where('success', true)->count();
        $failCount = $logs->getCollection()->where('success', false)->count();
        $avgMs = $logs->getCollection()->avg('duration_ms');
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase">Affichés</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $logs->total() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-emerald-200 p-4 text-center">
            <p class="text-xs font-medium text-emerald-600 uppercase">Succès (page)</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $successCount }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-red-200 p-4 text-center">
            <p class="text-xs font-medium text-red-600 uppercase">Erreurs (page)</p>
            <p class="text-2xl font-bold text-red-700 mt-1">{{ $failCount }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-blue-200 p-4 text-center">
            <p class="text-xs font-medium text-blue-600 uppercase">Latence moy.</p>
            <p class="text-2xl font-bold text-blue-700 mt-1">
                @if($avgMs)
                    @if($avgMs >= 1000)
                        {{ number_format($avgMs / 1000, 1) }}s
                    @else
                        {{ round($avgMs) }}ms
                    @endif
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('integrations.logs') }}"
          class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Intégration</label>
                <select name="integration_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                    <option value="">Toutes</option>
                    @foreach($integrations as $intg)
                        <option value="{{ $intg->id }}" {{ request('integration_id') == $intg->id ? 'selected' : '' }}>
                            {{ $intg->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Résultat</label>
                <select name="success"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                    <option value="">Tous</option>
                    <option value="1" {{ request('success') === '1' ? 'selected' : '' }}>Succès</option>
                    <option value="0" {{ request('success') === '0' ? 'selected' : '' }}>Erreur</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Direction</label>
                <select name="direction"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                    <option value="">Toutes</option>
                    <option value="outbound" {{ request('direction') === 'outbound' ? 'selected' : '' }}>Sortant</option>
                    <option value="inbound"  {{ request('direction') === 'inbound'  ? 'selected' : '' }}>Entrant</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date début</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date fin</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                <a href="{{ route('integrations.logs') }}"
                   class="px-3 py-2 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm rounded-lg transition-colors" title="Réinitialiser">
                    ✕
                </a>
            </div>
        </div>
    </form>

    {{-- Logs table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">

        @if($logs->isEmpty())
        <div class="p-16 text-center">
            <div class="w-14 h-14 rounded-[4px] bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900">Aucun log</h3>
            <p class="text-sm text-gray-500 mt-1">Aucun appel API ne correspond aux filtres sélectionnés.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Méthode / Endpoint</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Intégration</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Statut HTTP</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Durée</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide hidden lg:table-cell">Direction</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Date</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Résultat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" x-data="{ expanded: null }">
                    @foreach($logs as $log)
                    @php
                        $methodColors = [
                            'GET'     => 'bg-blue-100 text-blue-700',
                            'POST'    => 'bg-emerald-100 text-emerald-700',
                            'PUT'     => 'bg-amber-100 text-amber-700',
                            'PATCH'   => 'bg-amber-100 text-amber-700',
                            'DELETE'  => 'bg-red-100 text-red-700',
                            'WEBHOOK' => 'bg-violet-100 text-violet-700',
                        ];
                        $mc = $methodColors[$log->method ?? 'GET'] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <tr class="hover:bg-gray-50 cursor-pointer transition-colors"
                        @click="expanded = (expanded === {{ $log->id }}) ? null : {{ $log->id }}">

                        {{-- Method + Endpoint --}}
                        <td class="px-4 py-3 max-w-xs">
                            <div class="flex items-center gap-2">
                                <svg class="w-3 h-3 text-gray-400 flex-shrink-0 transition-transform"
                                     :class="expanded === {{ $log->id }} ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $mc }} flex-shrink-0">
                                    {{ $log->method ?? 'GET' }}
                                </span>
                                <span class="font-mono text-xs text-gray-600 truncate" title="{{ $log->endpoint }}">
                                    {{ Str::limit($log->endpoint, 50) }}
                                </span>
                            </div>
                        </td>

                        {{-- Integration --}}
                        <td class="px-4 py-3">
                            @if($log->integration)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-base leading-none">{{ $log->integration->typeIcon() }}</span>
                                    <span class="text-xs text-gray-700 truncate max-w-[120px]" title="{{ $log->integration->name }}">
                                        {{ $log->integration->name }}
                                    </span>
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">Inconnue</span>
                            @endif
                        </td>

                        {{-- HTTP Status --}}
                        <td class="px-4 py-3">
                            @if($log->status_code)
                            <span class="font-mono font-semibold text-xs
                                {{ $log->status_code < 300 ? 'text-emerald-600' : ($log->status_code < 500 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ $log->status_code }}
                            </span>
                            @else
                            <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Duration --}}
                        <td class="px-4 py-3">
                            @if($log->duration_ms !== null)
                                @php
                                    $ms = $log->duration_ms;
                                    $durClass = $ms < 500 ? 'text-emerald-600' : ($ms < 2000 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <span class="text-xs font-mono {{ $durClass }}">
                                    @if($ms >= 1000)
                                        {{ number_format($ms/1000, 2) }}s
                                    @else
                                        {{ round($ms) }}ms
                                    @endif
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Direction --}}
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span class="text-xs font-medium
                                {{ ($log->direction ?? 'outbound') === 'inbound' ? 'text-violet-600' : 'text-blue-600' }}">
                                {{ ($log->direction ?? 'outbound') === 'inbound' ? '← Entrant' : '→ Sortant' }}
                            </span>
                        </td>

                        {{-- Date --}}
                        <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                            {{ $log->created_at->format('d/m H:i:s') }}
                        </td>

                        {{-- Result --}}
                        <td class="px-4 py-3">
                            @if($log->success)
                                <span class="inline-flex items-center gap-1 text-emerald-600">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span class="text-xs font-medium">OK</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-red-600">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    <span class="text-xs font-medium">Erreur</span>
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Expanded payload/response --}}
                    <tr x-show="expanded === {{ $log->id }}" x-cloak class="bg-gray-50">
                        <td colspan="7" class="px-8 py-4">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                                {{-- Request Payload --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Requête envoyée</p>
                                        @if($log->ip_address)
                                        <span class="text-[10px] text-gray-400 font-mono">IP : {{ $log->ip_address }}</span>
                                        @endif
                                    </div>
                                    @if($log->request_payload)
                                    <pre class="bg-gray-100 border border-gray-200 rounded-lg p-3 text-[10px] text-gray-700 overflow-x-auto max-h-48 leading-relaxed">{{ is_array($log->request_payload) ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (is_string($log->request_payload) ? json_encode(json_decode($log->request_payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}') }}</pre>
                                    @else
                                    <p class="text-xs text-gray-400 italic">Aucun payload</p>
                                    @endif
                                </div>

                                {{-- Response --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Réponse reçue</p>
                                        @if($log->error_message && !$log->success)
                                        <span class="text-[10px] font-medium text-red-600 truncate max-w-[200px]" title="{{ $log->error_message }}">
                                            {{ Str::limit($log->error_message, 40) }}
                                        </span>
                                        @endif
                                    </div>
                                    @if($log->response_body)
                                    <pre class="bg-gray-100 border border-gray-200 rounded-lg p-3 text-[10px] text-gray-700 overflow-x-auto max-h-48 leading-relaxed">{{ is_array($log->response_body) ? json_encode($log->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (is_string($log->response_body) ? json_encode(json_decode($log->response_body), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—') }}</pre>
                                    @elseif($log->error_message)
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-700">
                                        {{ $log->error_message }}
                                    </div>
                                    @else
                                    <p class="text-xs text-gray-400 italic">Aucune réponse</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Extra metadata --}}
                            <div class="mt-3 flex flex-wrap gap-4 text-[10px] text-gray-400">
                                @if($log->created_at)
                                <span>{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                                @endif
                                @if($log->user_agent)
                                <span class="font-mono truncate max-w-xs" title="{{ $log->user_agent }}">{{ Str::limit($log->user_agent, 60) }}</span>
                                @endif
                                @if($log->job_id)
                                <span>Job ID : <span class="font-mono">{{ $log->job_id }}</span></span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
        <div class="px-4 py-4 border-t border-gray-100 flex items-center justify-between gap-4">
            <p class="text-xs text-gray-500">
                {{ $logs->firstItem() }}–{{ $logs->lastItem() }} sur {{ $logs->total() }} entrées
            </p>
            <div class="flex items-center gap-1">
                @if($logs->onFirstPage())
                    <span class="px-3 py-1.5 text-xs text-gray-300 border border-gray-200 rounded-lg">←</span>
                @else
                    <a href="{{ $logs->previousPageUrl() }}"
                       class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">←</a>
                @endif

                @foreach($logs->getUrlRange(max(1, $logs->currentPage()-2), min($logs->lastPage(), $logs->currentPage()+2)) as $page => $url)
                    @if($page == $logs->currentPage())
                        <span class="px-3 py-1.5 text-xs bg-emerald-700 text-white border border-blue-600 rounded-lg">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">{{ $page }}</a>
                    @endif
                @endforeach

                @if($logs->hasMorePages())
                    <a href="{{ $logs->nextPageUrl() }}"
                       class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">→</a>
                @else
                    <span class="px-3 py-1.5 text-xs text-gray-300 border border-gray-200 rounded-lg">→</span>
                @endif
            </div>
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
