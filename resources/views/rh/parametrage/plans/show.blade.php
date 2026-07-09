@extends('layouts.erp')
@section('title', $plan->code . ' — Rubriques')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.plans.index') }}" class="hover:text-gray-700">Plans</a>
    <span class="mx-1">/</span><span>{{ $plan->code }}</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <h1 class="text-[16px] font-bold text-gray-900">{{ $plan->libelle }}</h1>
            <code class="text-sm font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $plan->code }}</code>
            @if($plan->is_default)
                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-emerald-100 text-emerald-800">Par défaut</span>
            @endif
            @if(!$plan->is_active)
                <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
            @endif
        </div>
        <p class="text-sm text-gray-500">
            {{ $plan->pays }}{{ $plan->devise ? ' · '.$plan->devise : '' }}
            @if($plan->description) — {{ $plan->description }} @endif
        </p>
    </div>
    <a href="{{ route('rh.plans.edit', $plan) }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
        Modifier le plan
    </a>
</div>

{{-- Rubriques --}}
@php
$rubriques = $plan->rubrics()->orderBy('display_order')->orderBy('code')->get();
$byCategorie = $rubriques->groupBy('categorie');
$catLabels = [
    'salaire' => 'Salaire', 'prime' => 'Primes', 'indemnite' => 'Indemnités',
    'absence' => 'Absences', 'avance' => 'Avances', 'pret' => 'Prêts',
    'impot' => 'Impôts', 'cnss' => 'CNSS', 'avantage' => 'Avantages en nature',
    'autre' => 'Autre',
];
@endphp

@if($rubriques->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-12 text-center">
    <p class="text-gray-400">Aucune rubrique dans ce plan.</p>
    <p class="text-xs text-gray-400 mt-1">Les rubriques se créent depuis la page <strong>Rubriques de paie</strong>.</p>
</div>
@else

{{-- Stats rapides --}}
<div class="grid grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
        <p class="text-[16px] font-bold text-gray-900">{{ $rubriques->count() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Rubriques</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
        <p class="text-[16px] font-bold text-green-600">{{ $rubriques->where('sens', 'addition')->count() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Gains</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
        <p class="text-[16px] font-bold text-red-500">{{ $rubriques->where('sens', 'deduction')->count() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Déductions</p>
    </div>
    <div class="bg-white rounded-[4px] border border-gray-300 p-4 text-center">
        <p class="text-[16px] font-bold text-emerald-700">{{ $rubriques->where('is_active', true)->count() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Actives</p>
    </div>
</div>

@foreach($byCategorie as $cat => $items)
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden mb-4">
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700">{{ $catLabels[$cat] ?? ucfirst($cat) }}</h2>
        <span class="text-xs text-gray-400">{{ $items->count() }} rubrique(s)</span>
    </div>
    <table class="w-full text-sm">
        <thead class="border-b border-gray-100">
            <tr class="text-left text-xs text-gray-400 font-semibold uppercase tracking-wide">
                <th class="px-3 py-2.5">#</th>
                <th class="px-3 py-2.5">Code</th>
                <th class="px-3 py-2.5">Libellé</th>
                <th class="px-3 py-2.5">Type calcul</th>
                <th class="px-3 py-2.5 text-center">Sens</th>
                <th class="px-3 py-2.5 text-center">Net</th>
                <th class="px-3 py-2.5 text-center">CNSS</th>
                <th class="px-3 py-2.5 text-center">IUTS</th>
                <th class="px-3 py-2.5 text-center">Statut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($items->sortBy('display_order') as $r)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-3 py-2.5 text-xs text-gray-400">{{ $r->display_order }}</td>
                <td class="px-3 py-2.5">
                    <code class="text-xs font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ $r->code }}</code>
                </td>
                <td class="px-3 py-2.5 font-medium text-gray-800">{{ $r->libelle }}</td>
                <td class="px-3 py-2.5 text-xs text-gray-500">{{ ucfirst($r->calc_type) }}</td>
                <td class="px-3 py-2.5 text-center">
                    @if($r->sens === 'addition')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">+</span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-600">−</span>
                    @endif
                </td>
                <td class="px-3 py-2.5 text-center text-xs">{{ $r->is_in_net ? '✓' : '' }}</td>
                <td class="px-3 py-2.5 text-center text-xs">{{ $r->is_cnss_base ? '✓' : '' }}</td>
                <td class="px-3 py-2.5 text-center text-xs">{{ $r->is_iuts_base ? '✓' : '' }}</td>
                <td class="px-3 py-2.5 text-center">
                    @if($r->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach
@endif
@endsection
