@extends('layouts.erp')
@section('title', 'Mouvements de stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Mouvements</span>
@endsection

@section('content')
@php
    $lbl = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk  = 'appearance-none w-full h-8 py-0 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
    $th  = 'px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide';
@endphp
<div class="space-y-4">

    {{-- Bandeau SAGE --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[17px] font-bold text-gray-900">Mouvements de stock</h1>
            <p class="text-[12px] text-gray-500">Actualisé à {{ now()->format('H:i:s') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('stocks.movement.create') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] flex items-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouveau mouvement
            </a>
        </div>
    </div>

    {{-- Filtres SAGE --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-3">
            <div>
                <label class="{{ $lbl }}">Dépôt</label>
                <div class="relative"><select name="warehouse_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Type mouvement</label>
                <div class="relative"><select name="type" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach(['entree'=>'Entrée','sortie'=>'Sortie','transfert'=>'Transfert','ajustement'=>'Ajustement','inventaire'=>'Inventaire','retour_client'=>'Retour client','retour_fournisseur'=>'Retour fournisseur'] as $tv => $tl)
                    <option value="{{ $tv }}" {{ ($filters['type'] ?? '') === $tv ? 'selected' : '' }}>{{ $tl }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Article</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, désignation…" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Référence document</label>
                <input type="text" name="ref" value="{{ $filters['ref'] ?? '' }}" placeholder="Ex. : 123 ou Reception" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Lot</label>
                <input type="text" name="lot" value="{{ $filters['lot'] ?? '' }}" placeholder="Ex. : LOT-000123" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">Utilisateur</label>
                <div class="relative"><select name="user_id" class="{{ $lk }}">
                    <option value="">Tous</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>{!! $caret !!}</div>
            </div>
            <div>
                <label class="{{ $lbl }}">Période du</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inp }}">
            </div>
            <div>
                <label class="{{ $lbl }}">au</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inp }}">
            </div>
            <div class="col-span-2 sm:col-span-3 xl:col-span-3 flex items-end justify-end gap-2">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Rechercher
                </button>
                <a href="{{ route('stocks.movements') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Réinitialiser
                </a>
                <a href="{{ route('stocks.movements', array_merge(request()->query(), ['export' => 1])) }}"
                   class="border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter
                </a>
                <a href="{{ route('stocks.movements-pdf', request()->query()) }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-[13px] font-semibold px-4 h-8 rounded-[4px] flex items-center transition-colors">PDF</a>
            </div>
        </div>
    </form>

    {{-- [FIX mouvement bloqué] Mouvements manuels en attente de déblocage --}}
    @if(($blockedManuals ?? collect())->isNotEmpty())
    <div class="bg-amber-50 border border-amber-300 rounded-[4px] px-3 py-1.5">
        <p class="text-[13px] font-bold text-amber-800 mb-2">⏸ {{ $blockedManuals->count() }} mouvement(s) manuel(s) bloqué(s) — stock non impacté tant que non débloqué</p>
        <div class="space-y-1.5">
            @foreach($blockedManuals as $bm)
            <div class="flex items-center justify-between text-[12.5px] text-amber-900">
                <span class="font-mono">{{ $bm->number }}</span>
                <span>{{ $bm->warehouseTo?->name }} · {{ count($bm->lines ?? []) }} ligne(s) · {{ $bm->creator?->name }}</span>
                <form method="POST" action="{{ route('stocks.movement.unblock', $bm) }}" data-confirm="Débloquer {{ $bm->number }} ? Le stock sera mis à jour immédiatement.">
                    @csrf
                    <button class="text-[12px] font-semibold text-white bg-amber-600 hover:bg-amber-700 px-3 py-1 rounded-full transition-colors">Débloquer</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- KPI SAGE --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach([
            ['label' => 'Entrées',     'value' => $kpis['entrees'],     'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50',  'icon' => 'M19 14l-7 7m0 0l-7-7m7 7V3'],
            ['label' => 'Sorties',     'value' => $kpis['sorties'],     'color' => 'text-red-500',     'bg' => 'bg-red-50',      'icon' => 'M5 10l7-7m0 0l7 7m-7-7v18'],
            ['label' => 'Transferts',  'value' => $kpis['transferts'],  'color' => 'text-blue-500',    'bg' => 'bg-blue-50',     'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
            ['label' => 'Ajustements', 'value' => $kpis['ajustements'], 'color' => 'text-amber-500',   'bg' => 'bg-amber-50',    'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
            ['label' => "Total mouvements aujourd'hui", 'value' => $kpis['aujourdhui'], 'color' => 'text-emerald-700', 'bg' => 'bg-[#eef5f0]', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ] as $kpi)
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded-[4px] {{ $kpi['bg'] }} flex items-center justify-center shrink-0">
                <svg class="w-4.5 h-4.5 {{ $kpi['color'] }}" style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate">{{ $kpi['label'] }}</p>
                <p class="text-[16px] font-bold text-gray-900 tabular-nums leading-tight">{{ number_format($kpi['value'], 0, ',', ' ') }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Liste des mouvements --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[13px] font-bold text-gray-900">Liste des mouvements ({{ $movements->total() }} lignes)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] border-collapse">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Date</th>
                        <th class="{{ $th }} text-left hidden sm:table-cell">Heure</th>
                        <th class="{{ $th }} text-left">Type mouvement</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Référence</th>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-left hidden xl:table-cell">Lot</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Dépôt source</th>
                        <th class="{{ $th }} text-left hidden md:table-cell">Dépôt destination</th>
                        <th class="{{ $th }} text-right">Quantité</th>
                        <th class="{{ $th }} text-left hidden lg:table-cell">Unité</th>
                        <th class="{{ $th }} text-right hidden xl:table-cell">Stock avant</th>
                        <th class="{{ $th }} text-right hidden xl:table-cell">Stock après</th>
                        <th class="{{ $th }} text-right hidden lg:table-cell">Coût unit.</th>
                        <th class="{{ $th }} text-left hidden xl:table-cell">Utilisateur</th>
                        <th class="{{ $th }} text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        @php
                            $qty       = (float) $movement->quantity;
                            $isInbound = in_array($movement->type, ['entree', 'retour_client']) || ($movement->type === 'ajustement' && $qty > 0);
                            $qtySign   = $isInbound ? '+' : '−';
                            $qtyClass  = $isInbound ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold';

                            $typeBadge = match($movement->type) {
                                'entree'             => ['label' => 'Entrée',        'class' => 'text-emerald-700', 'arrow' => 'M5 10l7-7m0 0l7 7m-7-7v18'],
                                'sortie'             => ['label' => 'Sortie',        'class' => 'text-red-600',     'arrow' => 'M19 14l-7 7m0 0l-7-7m7 7V3'],
                                'transfert'          => ['label' => 'Transfert',     'class' => 'text-blue-600',    'arrow' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
                                'ajustement'         => ['label' => 'Ajustement',    'class' => 'text-amber-600',   'arrow' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
                                'inventaire'         => ['label' => 'Inventaire',    'class' => 'text-gray-600',    'arrow' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                                'retour_client'      => ['label' => 'Retour client', 'class' => 'text-purple-600',  'arrow' => 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3'],
                                'retour_fournisseur' => ['label' => 'Retour fourn.', 'class' => 'text-yellow-600',  'arrow' => 'M8 9v1a4 4 0 004 4h4m0 0l-3-3m3 3l-3 3'],
                                default              => ['label' => ucfirst($movement->type), 'class' => 'text-gray-600', 'arrow' => 'M9 5l7 7-7 7'],
                            };
                        @endphp
                        <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                            <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $movement->occurred_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-500 tabular-nums hidden sm:table-cell">{{ $movement->occurred_at?->format('H:i:s') ?? '—' }}</td>
                            <td class="px-3 py-1.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 font-semibold {{ $typeBadge['class'] }}">
                                    {{ $typeBadge['label'] }}
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeBadge['arrow'] }}"/></svg>
                                </span>
                                <p class="text-[10px] text-gray-400">{{ $movement->reasonLabel() }}</p>
                            </td>
                            <td class="px-3 py-1.5 font-mono text-emerald-800 text-[12px] hidden lg:table-cell whitespace-nowrap">
                                @if($movement->reference_type && $movement->reference_id)
                                    {{ ucfirst(class_basename($movement->reference_type)) }} #{{ $movement->reference_id }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5">
                                <span class="font-medium text-gray-900">{{ $movement->product?->name ?? '—' }}</span>
                                @if($movement->product?->reference)
                                    <p class="text-[11px] text-emerald-800 font-mono">{{ $movement->product->reference }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 font-mono text-[12px] text-gray-600 hidden xl:table-cell whitespace-nowrap">{{ $movement->lot_number ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell whitespace-nowrap">
                                {{ $movement->fromWarehouse?->name ?? ($movement->isOutbound() ? $movement->warehouse?->name : null) ?? '—' }}
                            </td>
                            <td class="px-3 py-1.5 text-gray-600 hidden md:table-cell whitespace-nowrap">
                                {{ $movement->toWarehouse?->name ?? ($movement->isInbound() ? $movement->warehouse?->name : null) ?? '—' }}
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap {{ $qtyClass }}">
                                {{ $qtySign }}{{ number_format(abs($qty), 2, ',', ' ') }}
                            </td>
                            <td class="px-3 py-1.5 text-gray-500 text-[12px] hidden lg:table-cell">{{ $movement->product?->unit?->abbreviation ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden xl:table-cell whitespace-nowrap">{{ number_format($stockAvant[$movement->id] ?? 0, 2, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900 hidden xl:table-cell whitespace-nowrap">{{ number_format($stockApres[$movement->id] ?? 0, 2, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden lg:table-cell whitespace-nowrap">
                                @if($movement->unit_cost)
                                    {{ number_format((float) $movement->unit_cost, 0, ',', ' ') }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-gray-600 hidden xl:table-cell whitespace-nowrap">{{ $movement->createdBy?->name ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">Validé</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun mouvement trouvé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
            <span>{{ $movements->total() }} mouvement(s)</span>
            @if($movements->hasPages())<div>{{ $movements->appends($filters)->links() }}</div>@endif
        </div>
    </div>

    {{-- Panneaux bas : critiques + traçabilité --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- Derniers mouvements critiques --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center gap-2 px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <h2 class="text-[13px] font-bold text-gray-900">Derniers mouvements critiques ({{ $mouvementsCritiques->count() }})</h2>
            </div>
            <table class="w-full text-[12px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Date</th>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-left hidden sm:table-cell">Dépôt</th>
                        <th class="{{ $th }} text-right">Quantité</th>
                        <th class="{{ $th }} text-left hidden sm:table-cell">Motif</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mouvementsCritiques as $mc)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap">{{ $mc->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-1.5">
                            <span class="text-gray-900 font-medium">{{ $mc->product?->name ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-1.5 text-gray-600 hidden sm:table-cell">{{ $mc->warehouse?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ (float)$mc->quantity >= 0 && in_array($mc->type,['retour_client','ajustement','inventaire']) ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ number_format((float) $mc->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden sm:table-cell">{{ $mc->reasonLabel() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-[12px]">Aucun mouvement critique.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8]">
                <a href="{{ route('stocks.movements', ['type' => 'ajustement']) }}" class="text-[12px] text-emerald-700 hover:text-emerald-900 font-semibold">Voir tous les mouvements critiques</a>
            </div>
        </div>

        {{-- Traçabilité / Historique document --}}
        <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div class="flex items-center gap-2 px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <h2 class="text-[13px] font-bold text-gray-900">Traçabilité / Historique document</h2>
            </div>
            <table class="w-full text-[12px]">
                <thead class="bg-[#eef5f0] border-b border-gray-300">
                    <tr>
                        <th class="{{ $th }} text-left">Référence</th>
                        <th class="{{ $th }} text-left hidden sm:table-cell">Date</th>
                        <th class="{{ $th }} text-left">Article</th>
                        <th class="{{ $th }} text-right">Quantité</th>
                        <th class="{{ $th }} text-left hidden sm:table-cell">Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tracabilite as $tr)
                    <tr class="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                        <td class="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{{ ucfirst(class_basename($tr->reference_type)) }} #{{ $tr->reference_id }}</td>
                        <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap hidden sm:table-cell">{{ $tr->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-1.5 text-gray-900">{{ $tr->product?->name ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums font-semibold {{ $tr->isInbound() ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $tr->isInbound() ? '+' : '−' }}{{ number_format(abs((float) $tr->quantity), 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-500 hidden sm:table-cell">{{ $tr->createdBy?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-[12px]">Aucun document lié.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
