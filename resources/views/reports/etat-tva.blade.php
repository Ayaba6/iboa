@extends('layouts.erp')
@section('title', 'État de TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('reports.index') }}" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">État de TVA</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">État de TVA</h1>
            <p class="text-sm text-gray-500 mt-0.5">TVA collectée (ventes) et TVA déductible (achats) — FCFA</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export Excel
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-[4px] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 p-4">
        <div class="grid grid-cols-2 gap-3 max-w-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Du</label>
                <input type="date" name="from" value="{{ $from }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Au</label>
                <input type="date" name="to" value="{{ $to }}" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm focus:ring-1 focus:ring-emerald-400">
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium px-3 py-1.5 rounded-[4px]">Appliquer</button>
            <a href="{{ route('reports.etat-tva') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-2.5 py-1.5 rounded-[4px]">Réinitialiser</a>
        </div>
    </form>

    {{-- Résumé solde --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-[4px] border border-emerald-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">TVA Collectée</p>
                    <p class="text-[16px] font-bold text-emerald-800 mt-1">{{ number_format($totalCollectee, 0, ',', ' ') }} F</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sur vos ventes</p>
                </div>
                <div class="w-10 h-10 bg-emerald-100 rounded-[4px] flex items-center justify-center text-emerald-700 font-bold text-lg">+</div>
            </div>
        </div>
        <div class="bg-white rounded-[4px] border border-emerald-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">TVA Déductible</p>
                    <p class="text-[16px] font-bold text-emerald-700 mt-1">{{ number_format($totalDeductible, 0, ',', ' ') }} F</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sur vos achats</p>
                </div>
                <div class="w-10 h-10 bg-emerald-100 rounded-[4px] flex items-center justify-center text-emerald-600 font-bold text-lg">-</div>
            </div>
        </div>
        <div class="rounded-[4px] border-2 p-5 {{ $solde >= 0 ? 'bg-amber-50 border-amber-400' : 'bg-emerald-50 border-emerald-400' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold {{ $solde >= 0 ? 'text-amber-700' : 'text-emerald-700' }} uppercase tracking-wide">
                        {{ $solde >= 0 ? 'TVA à décaisser' : 'Crédit de TVA' }}
                    </p>
                    <p class="text-[16px] font-bold {{ $solde >= 0 ? 'text-amber-800' : 'text-emerald-800' }} mt-1">
                        {{ number_format(abs($solde), 0, ',', ' ') }} F
                    </p>
                    <p class="text-xs {{ $solde >= 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-0.5">
                        {{ $solde >= 0 ? 'À reverser à l\'administration' : 'À reporter ou rembourser' }}
                    </p>
                </div>
                <div class="w-10 h-10 {{ $solde >= 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }} rounded-[4px] flex items-center justify-center font-bold text-lg">=</div>
            </div>
        </div>
    </div>

    {{-- TVA Collectée détail --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 bg-[#eef5f0] border-b border-emerald-100">
            <h2 class="text-sm font-bold text-emerald-800">TVA Collectée — Détail par taux</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-emerald-700 text-white">
                <tr>
                    <th class="px-3 py-1.5 text-center font-semibold w-24">Taux</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Base HT</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Montant TVA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tvaCollectee as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 text-center">
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-bold bg-emerald-100 text-emerald-900">
                            {{ $row->taux }}%
                        </span>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($row->base_ht, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-emerald-800">{{ number_format($row->montant_tva, 0, ',', ' ') }} F</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">Aucune TVA collectée sur cette période</td>
                </tr>
                @endforelse
            </tbody>
            @if($tvaCollectee->count())
            <tfoot class="bg-indigo-900 text-white font-bold">
                <tr>
                    <td class="px-3 py-1.5 text-center">TOTAL</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($tvaCollectee->sum('base_ht'), 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($totalCollectee, 0, ',', ' ') }} F</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- TVA Déductible --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="px-3 py-1.5 bg-emerald-50 border-b border-emerald-100">
            <h2 class="text-sm font-bold text-emerald-700">TVA Déductible — Factures fournisseurs</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-emerald-700 text-white">
                <tr>
                    <th class="px-3 py-1.5 text-left font-semibold">Description</th>
                    <th class="px-3 py-1.5 text-right font-semibold">Base HT achats</th>
                    <th class="px-3 py-1.5 text-right font-semibold">TVA déductible</th>
                </tr>
            </thead>
            <tbody>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-1.5 text-gray-700">Factures fournisseurs validées sur la période</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700">{{ number_format($tvaDeductible?->base_ht ?? 0, 0, ',', ' ') }} F</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-emerald-700">{{ number_format($totalDeductible, 0, ',', ' ') }} F</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
@endsection
