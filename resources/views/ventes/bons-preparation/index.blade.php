@extends('layouts.erp')
@section('title', 'Bons de chargement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Ventes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bons de chargement</span>
@endsection

@section('content')
<div class="space-y-3">

    <x-sales.module-nav />

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Total BP</p>
            <p class="text-[15px] font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En attente</p>
            <p class="text-[15px] font-bold text-amber-600 tabular-nums">{{ $summary['en_attente'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">En cours</p>
            <p class="text-[15px] font-bold text-blue-600 tabular-nums">{{ $summary['en_cours'] }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <p class="text-[12px] text-gray-500">Chargés</p>
            <p class="text-[15px] font-bold text-emerald-600 tabular-nums">{{ $summary['charge'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            {{-- [Ventes §4.3] Ces documents sont des bons de CHARGEMENT, pas des
                 bons de préparation quantifiés. Ils n'ont aucune ligne : ils
                 attestent qu'une commande a été chargée, sans dire quoi, en
                 quelle quantité ni depuis quel lot.
                 Conserver l'intitulé « Bons de préparation » les rendait
                 indistinguables du nouveau module quantifié — deux écrans, deux
                 modèles de données, un seul nom. --}}
            <h1 class="text-[20px] font-bold text-gray-900 leading-tight">Bons de chargement</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">
                {{ $bps->total() }} bon(s) · documents historiques sans lignes quantifiées
            </p>
            @can('bon_preparations.view')
            <p class="text-[12px] text-gray-400 mt-1">
                Les préparations quantifiées (lignes, allocations lot/bobine, contrôle) sont dans
                <a href="{{ route('ventes.preparations.index') }}" class="text-emerald-700 hover:underline font-medium">Préparations</a>.
            </p>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        @php $lblX = 'block text-[11px] font-bold text-gray-700 mb-1'; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
            <label class="{{ $lblX }}">Rechercher</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° BP ou commande…"
                   class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
            </div>

            <div>
            <label class="{{ $lblX }}">Statut</label>
            <select name="status" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les statuts</option>
                @foreach(['en_attente' => 'En attente', 'en_cours' => 'En cours', 'charge' => 'Chargé', 'annule' => 'Annulé'] as $v => $l)
                    <option value="{{ $v }}" {{ ($filters['status'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            </div>

            <div>
            <label class="{{ $lblX }}">Mode de règlement</label>
            <select name="payment_mode" class="w-full h-8 py-0 border border-gray-300 rounded-[4px] px-2.5 text-[12px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Tous les modes</option>
                <option value="cash"   {{ ($filters['payment_mode'] ?? '') === 'cash'   ? 'selected' : '' }}>Comptant</option>
                <option value="credit" {{ ($filters['payment_mode'] ?? '') === 'credit' ? 'selected' : '' }}>Crédit</option>
            </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'payment_mode']))
                <a href="{{ route('ventes.bons-preparation.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] px-2.5 py-1.5 rounded-[4px] transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Liste style SAGE X3 : grille dense, codes mono, workflow chargement --}}
    <div class="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12px] border-collapse">
                <thead>
                    <tr class="bg-[#3b4248] text-white text-[11px]">
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-32">N° BP</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden md:table-cell w-32">Commande</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap">Client</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden lg:table-cell w-24">Mode</th>
                        <th class="text-center font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap w-28">Statut</th>
                        <th class="text-left font-semibold px-3 py-1.5 uppercase tracking-wide whitespace-nowrap hidden md:table-cell w-32">Créé le</th>
                        <th class="px-3 py-2 w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bps as $bp)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">
                            <a href="{{ route('ventes.bons-preparation.show', $bp) }}" class="hover:underline font-semibold">{{ $bp->number }}</a>
                        </td>
                        <td class="px-3 py-1.5 hidden md:table-cell whitespace-nowrap">
                            @if($bp->order)
                                <a href="{{ route('ventes.commandes.show', $bp->order_id) }}" class="font-mono text-emerald-700 hover:underline text-[12px]">{{ $bp->order->number }}</a>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ $bp->order?->client?->displayName() ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-center hidden lg:table-cell">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[11px] font-bold
                                {{ $bp->payment_mode === 'cash' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $bp->payment_mode_label }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-center">
                            @php
                                $statusStyles = [
                                    'en_attente' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'en_cours'   => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'charge'     => 'bg-green-50 text-green-700 border-green-200',
                                    'annule'     => 'bg-red-50 text-red-700 border-red-200',
                                ];
                                $statusDots = [
                                    'en_attente' => 'bg-amber-500',
                                    'en_cours'   => 'bg-blue-500',
                                    'charge'     => 'bg-green-500',
                                    'annule'     => 'bg-red-500',
                                ];
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $statusStyles[$bp->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusDots[$bp->status] ?? 'bg-gray-400' }}"></span>
                                {{ $bp->status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden md:table-cell whitespace-nowrap">{{ $bp->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-1.5">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('ventes.bons-preparation.show', $bp) }}"
                                   class="p-1 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @can('bon_preparations.update')
                                @if($bp->status === 'en_attente')
                                <form method="POST" action="{{ route('ventes.bons-preparation.start-loading', $bp) }}"
                                      data-confirm="Démarrer le chargement du BP {{ addslashes($bp->number) }} ?">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Démarrer le chargement">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </form>
                                @elseif($bp->status === 'en_cours')
                                <form method="POST" action="{{ route('ventes.bons-preparation.finish-loading', $bp) }}"
                                      data-confirm="Terminer le chargement du BP {{ addslashes($bp->number) }} ?">
                                    @csrf
                                    <button type="submit"
                                            class="p-1 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Terminer le chargement">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                </svg>
                                <div>
                                    <p class="text-[13px] font-medium text-gray-600">Aucun bon de préparation trouvé</p>
                                    @if(request()->hasAny(['search', 'status', 'payment_mode']))
                                        <a href="{{ route('ventes.bons-preparation.index') }}" class="text-[13px] text-emerald-600 hover:text-emerald-700 mt-1 inline-block">Effacer les filtres</a>
                                    @else
                                        <p class="text-[12px] text-gray-400 mt-1">Les BP sont générés automatiquement à la validation des commandes.</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11px] text-gray-500">
            <span>{{ $bps->total() }} bon(s) de préparation</span>
            @if($bps->hasPages())<div>{{ $bps->appends($filters)->links() }}</div>@endif
        </div>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">{{ array_filter($filters ?? []) ? implode(', ', array_keys(array_filter($filters))) : 'Aucun' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
