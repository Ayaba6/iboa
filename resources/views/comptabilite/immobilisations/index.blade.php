@extends('layouts.erp')
@section('title', 'Immobilisations')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.dashboard') }}" class="hover:text-gray-700">Comptabilité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Immobilisations</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int)$n, 0, ',', ' ');
    $vnc = $totalCost - $totalDepr;
@endphp

<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Immobilisations</h1>
            <p class="text-sm text-gray-500 mt-0.5">Registre des actifs fixes et suivi des amortissements SYSCOHADA</p>
        </div>
        @can('accounting.write')
        <a href="{{ route('comptabilite.immobilisations.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle immobilisation
        </a>
        @endcan
    </div>


    {{-- KPIs --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Valeur brute</p>
            <p class="text-[16px] font-bold text-gray-900 mt-1">{{ $fmt($totalCost) }} <span class="text-sm font-normal text-gray-500">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Amortissements cumulés</p>
            <p class="text-[16px] font-bold text-orange-600 mt-1">{{ $fmt($totalDepr) }} <span class="text-sm font-normal text-gray-500">FCFA</span></p>
        </div>
        <div class="bg-white border border-gray-300 rounded-[4px] p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Valeur nette comptable</p>
            <p class="text-[16px] font-bold text-blue-600 mt-1">{{ $fmt($vnc) }} <span class="text-sm font-normal text-gray-500">FCFA</span></p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Tous les statuts</option>
                @foreach($statusLabels as $val => $label)
                    <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Catégorie</label>
            <select name="category" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
                <option value="">Toutes catégories</option>
                @foreach($categoryLabels as $val => $label)
                    <option value="{{ $val }}" {{ ($filters['category'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-700 mb-1">Recherche</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, code…"
                   class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-500">
        </div>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-[4px]">Filtrer</button>
        @if(array_filter($filters))
            <a href="{{ route('comptabilite.immobilisations.index') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2">✕ Réinitialiser</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
        <table class="tbl tbl-sticky w-full">
            <thead>
                <tr>
                    <th class="text-left">Code / Désignation</th>
                    <th class="text-left">Catégorie</th>
                    <th class="text-left">Mise en service</th>
                    <th class="text-right">Valeur brute</th>
                    <th class="text-right">Amort. cumulé</th>
                    <th class="text-right">VNC</th>
                    <th class="text-center">Durée</th>
                    <th class="text-center">Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                @php
                    $cumul = $asset->depreciations->where('is_posted', true)->sum('depreciation_amount');
                    $vnc   = max(0, $asset->acquisition_cost - $cumul);
                    $pct   = $asset->acquisition_cost > 0 ? min(100, round($cumul / $asset->acquisition_cost * 100)) : 0;
                    $statusColors = [
                        'en_service'   => 'bg-emerald-100 text-emerald-700',
                        'cede'         => 'bg-orange-100 text-orange-700',
                        'mis_au_rebut' => 'bg-gray-100 text-gray-500',
                    ];
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="">
                        <p class="font-semibold text-gray-900">{{ $asset->name }}</p>
                        <p class="text-xs text-gray-500">{{ $asset->code }}</p>
                    </td>
                    <td class=" text-gray-600">{{ $categoryLabels[$asset->category] ?? $asset->category }}</td>
                    <td class=" text-gray-600">{{ $asset->commissioning_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class=" text-right tabular-nums font-medium text-gray-900">{{ $fmt($asset->acquisition_cost) }}</td>
                    <td class=" text-right tabular-nums text-orange-600">
                        {{ $fmt($cumul) }}
                        @if($pct > 0)
                            <div class="mt-1 h-1 bg-gray-100 rounded-full overflow-hidden w-20 ml-auto">
                                <div class="h-1 {{ $pct >= 100 ? 'bg-red-400' : 'bg-orange-400' }} rounded-full" style="width:{{ $pct }}%"></div>
                            </div>
                        @endif
                    </td>
                    <td class=" text-right tabular-nums font-semibold text-blue-600">{{ $fmt($vnc) }}</td>
                    <td class=" text-center text-gray-600">
                        @if($asset->useful_life_years > 0)
                            {{ $asset->useful_life_years }} ans
                        @else
                            <span class="text-gray-400 text-xs">Non amort.</span>
                        @endif
                    </td>
                    <td class=" text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $statusColors[$asset->status] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $statusLabels[$asset->status] ?? $asset->status }}
                        </span>
                    </td>
                    <td class=" text-right">
                        <a href="{{ route('comptabilite.immobilisations.show', $asset) }}"
                           class="text-xs text-blue-600 hover:underline font-medium">Voir →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-16 text-center text-gray-400">
                        Aucune immobilisation enregistrée.
                        @can('accounting.write')
                            <a href="{{ route('comptabilite.immobilisations.create') }}" class="text-blue-600 hover:underline ml-1">Créer la première →</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if($assets->hasPages())
            <div class="px-3 py-1.5 border-t border-gray-100">{{ $assets->links() }}</div>
        @endif
    </div>

</div>
@endsection
