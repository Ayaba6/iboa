@extends('layouts.erp')
@section('title', 'Transactions externes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('integrations.index') }}" class="hover:text-gray-700">Intégrations</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Transactions</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transactions externes</h1>
            <p class="text-sm text-gray-500">Historique complet des paiements mobiles et transferts API</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('integrations.dashboard') }}"
               class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-2 rounded-lg font-medium">
                Tableau de bord
            </a>
            <a href="{{ route('integrations.logs') }}"
               class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-2 rounded-lg font-medium">
                Logs API
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- KPI bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $transactions->total() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-emerald-200 p-4 text-center">
            <p class="text-xs font-medium text-emerald-600 uppercase">Confirmées</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($totals['confirmed_sum'] ?? 0, 0, ',', ' ') }}</p>
            <p class="text-xs text-emerald-500 mt-0.5">XOF</p>
        </div>
        <div class="bg-white rounded-[4px] border border-amber-200 p-4 text-center">
            <p class="text-xs font-medium text-amber-600 uppercase">En attente</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $totals['pending_count'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-red-200 p-4 text-center">
            <p class="text-xs font-medium text-red-600 uppercase">Échouées</p>
            <p class="text-2xl font-bold text-red-700 mt-1">{{ $totals['failed_count'] ?? 0 }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('integrations.transactions') }}"
          class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
                <select name="status"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                    <option value="">Tous</option>
                    <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>En attente</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmé</option>
                    <option value="failed"    {{ request('status') === 'failed'    ? 'selected' : '' }}>Échoué</option>
                    <option value="refunded"  {{ request('status') === 'refunded'  ? 'selected' : '' }}>Remboursé</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fournisseur</label>
                <select name="provider"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                    <option value="">Tous</option>
                    @foreach($providers as $prov)
                        <option value="{{ $prov }}" {{ request('provider') === $prov ? 'selected' : '' }}>
                            {{ $prov }}
                        </option>
                    @endforeach
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
                <a href="{{ route('integrations.transactions') }}"
                   class="px-3 py-2 border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm rounded-lg transition-colors" title="Réinitialiser">
                    ✕
                </a>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">

        @if($transactions->isEmpty())
        <div class="p-16 text-center">
            <div class="w-14 h-14 rounded-[4px] bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900">Aucune transaction</h3>
            <p class="text-sm text-gray-500 mt-1">Aucune transaction ne correspond aux filtres sélectionnés.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Référence</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Montant</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Fournisseur</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Statut</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide hidden lg:table-cell">Téléphone</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide hidden xl:table-cell">Tentatives</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Date</th>
                        <th class="text-right px-4 py-3 text-[11px] font-bold text-emerald-900 uppercase tracking-wide tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" x-data="{ expanded: null }">
                    @foreach($transactions as $tx)
                    @php
                        $statusColors = [
                            'confirmed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                            'failed'    => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500'],
                            'pending'   => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-400'],
                            'refunded'  => ['bg' => 'bg-violet-100',  'text' => 'text-violet-700',  'dot' => 'bg-violet-500'],
                        ];
                        $sc = $statusColors[$tx->status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dot' => 'bg-gray-400'];
                        $statusLabels = ['confirmed' => 'Confirmé', 'failed' => 'Échoué', 'pending' => 'En attente', 'refunded' => 'Remboursé'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                        @click="expanded = (expanded === {{ $tx->id }}) ? null : {{ $tx->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-3 h-3 text-gray-400 transition-transform"
                                     :class="expanded === {{ $tx->id }} ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-mono text-xs text-gray-700">{{ $tx->internal_reference }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-semibold text-gray-900">{{ number_format($tx->amount, 0, ',', ' ') }}</span>
                            <span class="text-xs text-gray-400 ml-1">{{ $tx->currency ?? 'XOF' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-gray-700">{{ $tx->provider }}</span>
                            @if($tx->integration)
                                <span class="text-[10px] font-bold ml-1 px-1 py-0.5 rounded
                                    {{ $tx->integration->isSandbox() ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $tx->integration->isSandbox() ? 'SBX' : 'PROD' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} inline-block {{ $tx->status === 'pending' ? 'animate-pulse' : '' }}"></span>
                                {{ $statusLabels[$tx->status] ?? ucfirst($tx->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ $tx->phone_number ?? '—' }}</td>
                        <td class="px-4 py-3 hidden xl:table-cell">
                            @if(($tx->retry_count ?? 0) > 0)
                                <span class="text-amber-600 font-medium">{{ $tx->retry_count }}×</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
                            {{ $tx->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-right" @click.stop>
                            <div class="flex items-center justify-end gap-2">
                                @if($tx->invoice_id)
                                <a href="{{ route('ventes.factures.show', $tx->invoice_id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-medium" title="Voir la facture">
                                    Facture
                                </a>
                                @endif
                                @if(($tx->status === 'failed' || $tx->status === 'pending') && ($tx->retry_count ?? 0) < 5)
                                <form method="POST"
                                      action="{{ route('integrations.transactions.retry', $tx) }}"
                                      onsubmit="return confirm('Relancer cette transaction ?')">
                                    @csrf
                                    <button type="submit"
                                            class="text-xs text-amber-600 hover:text-amber-800 font-medium">
                                        Relancer
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Expanded detail row --}}
                    <tr x-show="expanded === {{ $tx->id }}" x-cloak class="bg-gray-50">
                        <td colspan="8" class="px-8 py-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div>
                                    <p class="font-semibold text-gray-600 mb-2 uppercase tracking-wide">Identifiants</p>
                                    <dl class="space-y-1 text-gray-700">
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Réf. interne</dt><dd class="font-mono">{{ $tx->internal_reference }}</dd></div>
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Réf. externe</dt><dd class="font-mono">{{ $tx->external_reference ?? '—' }}</dd></div>
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Commande</dt><dd>{{ $tx->provider_data['order_id'] ?? '—' }}</dd></div>
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Initié par</dt><dd>{{ $tx->initiated_by ?? '—' }}</dd></div>
                                    </dl>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-600 mb-2 uppercase tracking-wide">Horodatage</p>
                                    <dl class="space-y-1 text-gray-700">
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Créée le</dt><dd>{{ $tx->created_at->format('d/m/Y H:i:s') }}</dd></div>
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Validée le</dt><dd>{{ $tx->transacted_at ? $tx->transacted_at->format('d/m/Y H:i:s') : '—' }}</dd></div>
                                        @if($tx->last_retry_at)
                                        <div class="flex gap-2"><dt class="text-gray-400 w-28 flex-shrink-0">Dernière retry</dt><dd>{{ $tx->last_retry_at->format('d/m/Y H:i:s') }}</dd></div>
                                        @endif
                                    </dl>
                                </div>
                                @if($tx->failure_reason || $tx->provider_data || $tx->notes)
                                <div>
                                    <p class="font-semibold text-gray-600 mb-2 uppercase tracking-wide">Détails</p>
                                    @if($tx->failure_reason)
                                    <div class="mb-2">
                                        <p class="text-red-600 font-medium mb-1">Raison d'échec :</p>
                                        <p class="text-red-500 bg-red-50 rounded p-2 font-mono">{{ $tx->failure_reason }}</p>
                                    </div>
                                    @endif
                                    @if($tx->provider_data)
                                    <div>
                                        <p class="text-gray-500 font-medium mb-1">Données fournisseur :</p>
                                        <pre class="bg-gray-100 rounded p-2 text-gray-600 overflow-x-auto text-[10px]">{{ json_encode(is_array($tx->provider_data) ? $tx->provider_data : json_decode($tx->provider_data, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    @endif
                                    @if($tx->notes)
                                    <div class="mt-1">
                                        <p class="text-gray-500 font-medium mb-1">Notes :</p>
                                        <p class="text-gray-600 italic">{{ $tx->notes }}</p>
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($transactions->hasPages())
        <div class="px-4 py-4 border-t border-gray-100 flex items-center justify-between gap-4">
            <p class="text-xs text-gray-500">
                {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} sur {{ $transactions->total() }} transactions
            </p>
            <div class="flex items-center gap-1">
                @if($transactions->onFirstPage())
                    <span class="px-3 py-1.5 text-xs text-gray-300 border border-gray-200 rounded-lg">←</span>
                @else
                    <a href="{{ $transactions->previousPageUrl() }}"
                       class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">←</a>
                @endif

                @foreach($transactions->getUrlRange(max(1, $transactions->currentPage()-2), min($transactions->lastPage(), $transactions->currentPage()+2)) as $page => $url)
                    @if($page == $transactions->currentPage())
                        <span class="px-3 py-1.5 text-xs bg-emerald-700 text-white border border-blue-600 rounded-lg">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">{{ $page }}</a>
                    @endif
                @endforeach

                @if($transactions->hasMorePages())
                    <a href="{{ $transactions->nextPageUrl() }}"
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
