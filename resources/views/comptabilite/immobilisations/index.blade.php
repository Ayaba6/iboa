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
    $company = currentCompany();
@endphp

<div class="space-y-3">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Immobilisations</h1>
            <p class="text-sm text-gray-500 mt-0.5">Registre des actifs fixes et suivi des amortissements SYSCOHADA</p>
        </div>
        <div class="flex items-center gap-1.5">
            <button type="button" onclick="window.print()"
                    class="h-8 inline-flex items-center border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">Imprimer</button>
            @can('accounting.write')
            <a href="{{ route('comptabilite.immobilisations.create') }}"
               class="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                + Nouvelle immobilisation
            </a>
            @endcan
        </div>
    </div>

    {{-- KPIs denses --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-1.5">
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Actifs au registre</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $assets->total() }}</p>
        </div>
        <div class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur brute</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-gray-900 leading-none">{{ $fmt($totalCost) }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-orange-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Amortissements cumulés</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-orange-600 leading-none">{{ $fmt($totalDepr) }} F</p>
        </div>
        <div class="bg-white rounded-[4px] border border-blue-200 px-3 py-2">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Valeur nette comptable</p>
            <p class="mt-0.5 text-[17px] font-bold tabular-nums text-blue-700 leading-none">{{ $fmt($vnc) }} F</p>
        </div>
    </div>

    {{-- Filtres une ligne --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2 flex flex-wrap items-center gap-2">
        <div class="relative">
            <select name="status" class="appearance-none h-8 py-0 pl-2 pr-7 border border-gray-300 rounded-[4px] text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                <option value="">Tous les statuts</option>
                @foreach($statusLabels as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>
        </div>
        <div class="relative">
            <select name="category" class="appearance-none h-8 py-0 pl-2 pr-7 border border-gray-300 rounded-[4px] text-[13px] bg-white focus:ring-1 focus:ring-emerald-400">
                <option value="">Toutes catégories</option>
                @foreach($categoryLabels as $val => $label)
                    <option value="{{ $val }}" @selected(($filters['category'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-600 pointer-events-none text-[12px]">&#9662;</span>
        </div>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, code, n° série…"
               class="h-8 border border-gray-300 rounded-[4px] px-2 text-[13px] w-52">
        <button type="submit" class="h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px] transition-colors">Rechercher</button>
        @if(array_filter($filters))
            <a href="{{ route('comptabilite.immobilisations.index') }}" class="h-8 inline-flex items-center px-2.5 border border-gray-300 text-gray-500 rounded-[4px] text-[12px] hover:bg-gray-50">✕</a>
        @endif
        <span class="ml-auto text-[12px] text-gray-400">{{ $assets->total() }} actif(s)</span>
    </form>

    {{-- Table dense X3 --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full text-[13px] border-collapse">
            <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left">Code</th>
                    <th class="px-3 py-1.5 text-left">Désignation</th>
                    <th class="px-3 py-1.5 text-left">Catégorie</th>
                    <th class="px-3 py-1.5 text-left">Mise en service</th>
                    <th class="px-3 py-1.5 text-right">Valeur brute (XOF)</th>
                    <th class="px-3 py-1.5 text-right">Amort. cumulé (XOF)</th>
                    <th class="px-3 py-1.5 text-right">VNC (XOF)</th>
                    <th class="px-3 py-1.5 text-center">Durée</th>
                    <th class="px-3 py-1.5 text-center">Statut</th>
                    <th class="px-3 py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($assets as $asset)
                @php
                    $cumul = $asset->depreciations->where('is_posted', true)->sum('depreciation_amount');
                    $vncA  = max(0, $asset->acquisition_cost - $cumul);
                    $pct   = $asset->acquisition_cost > 0 ? min(100, round($cumul / $asset->acquisition_cost * 100)) : 0;
                    $statusColors = [
                        'en_service'   => 'bg-emerald-100 text-emerald-700',
                        'cede'         => 'bg-orange-100 text-orange-700',
                        'mis_au_rebut' => 'bg-gray-100 text-gray-500',
                    ];
                @endphp
                <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                    <td class="px-3 py-1">
                        <a href="{{ route('comptabilite.immobilisations.show', $asset) }}"
                           class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[12.5px]">{{ $asset->code }}</a>
                    </td>
                    <td class="px-3 py-1 font-medium text-gray-900">
                        {{ $asset->name }}
                        @if($asset->serial_number)<span class="block text-[11px] text-gray-400 font-mono">{{ $asset->serial_number }}</span>@endif
                    </td>
                    <td class="px-3 py-1 text-gray-600 text-[12px]">{{ $categoryLabels[$asset->category] ?? $asset->category }}</td>
                    <td class="px-3 py-1 text-gray-600 tabular-nums">{{ $asset->commissioning_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1 text-right tabular-nums font-medium text-gray-900">{{ $fmt($asset->acquisition_cost) }}</td>
                    <td class="px-3 py-1 text-right tabular-nums text-orange-600">
                        {{ $fmt($cumul) }}
                        @if($pct > 0)
                            <div class="mt-0.5 h-1 bg-gray-100 rounded-full overflow-hidden w-16 ml-auto">
                                <div class="h-1 {{ $pct >= 100 ? 'bg-red-400' : 'bg-orange-400' }} rounded-full" style="width:{{ $pct }}%"></div>
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-right tabular-nums font-bold text-blue-700">{{ $fmt($vncA) }}</td>
                    <td class="px-3 py-1 text-center text-gray-600 text-[12px]">
                        @if($asset->useful_life_years > 0)
                            {{ $asset->useful_life_years }} ans
                        @else
                            <span class="text-gray-400 text-[11px]">Non amort.</span>
                        @endif
                    </td>
                    <td class="px-3 py-1 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $statusColors[$asset->status] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $statusLabels[$asset->status] ?? $asset->status }}
                        </span>
                    </td>
                    <td class="px-3 py-1 text-right">
                        <a href="{{ route('comptabilite.immobilisations.show', $asset) }}"
                           class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Détail →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-gray-400 text-[13px]">
                        Aucune immobilisation enregistrée.
                        @can('accounting.write')
                            <a href="{{ route('comptabilite.immobilisations.create') }}" class="text-emerald-700 hover:underline ml-1">Créer la première →</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($assets->isNotEmpty())
            <tfoot>
                <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold text-gray-900">
                    <td colspan="4" class="px-3 py-1.5 text-right text-[11px] uppercase text-gray-500">Total (registre)</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums">{{ $fmt($totalCost) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-orange-600">{{ $fmt($totalDepr) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-blue-700">{{ $fmt($vnc) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>

        @if($assets->hasPages())
            <div class="px-3 py-1.5 border-t border-gray-100">{{ $assets->links() }}</div>
        @endif
    </div>

    {{-- Barre de contexte pied de page --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ $company?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Filtre actif : <span class="text-white font-semibold">{{ array_filter($filters) ? implode(', ', array_keys(array_filter($filters))) : 'Aucun' }}</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

</div>
@endsection
