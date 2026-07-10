@extends('layouts.erp')
@section('title', 'Cotisations sociales')
@section('breadcrumb')
    <a href="{{ route('rh.parametrage.edit') }}" class="hover:text-gray-700">Paramétrage</a>
    <span class="mx-1">/</span><span>Cotisations sociales</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-[16px] font-bold text-gray-900">Cotisations sociales</h1>
        <p class="text-sm text-gray-500 mt-1">CNSS, assurance, retraite — taux salarié et patronal</p>
    </div>
    <a href="{{ route('rh.cotisations.create') }}"
       class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouvelle cotisation
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-[4px] px-3 py-1.5 text-sm">{{ session('error') }}</div>
@endif

@if($contributions->isEmpty())
<div class="bg-white rounded-[4px] border border-gray-300 p-16 text-center">
    <p class="text-gray-400">Aucune cotisation définie.</p>
    <a href="{{ route('rh.cotisations.create') }}" class="mt-3 inline-block text-emerald-700 text-sm font-medium">+ Créer la première cotisation</a>
</div>
@else

{{-- Grouper par organisme --}}
@php
$orgLabels = ['cnss' => 'CNSS', 'assurance' => 'Assurance', 'retraite' => 'Retraite', 'mutuelle' => 'Mutuelle', 'autre' => 'Autre'];
$grouped = $contributions->groupBy('organisme');
@endphp

@foreach($grouped as $org => $items)
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm overflow-hidden mb-4">
    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700">{{ $orgLabels[$org] ?? ucfirst($org) }}</h2>
        <span class="text-xs text-gray-400">{{ $items->count() }} cotisation(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100">
                <tr class="text-left text-xs text-gray-400 font-semibold uppercase tracking-wide">
                    <th class="px-3 py-2.5">Code</th>
                    <th class="px-3 py-2.5">Libellé</th>
                    <th class="px-3 py-2.5 text-right">Salarié</th>
                    <th class="px-3 py-2.5 text-right">Patronal</th>
                    <th class="px-3 py-2.5">Base</th>
                    <th class="px-3 py-2.5 text-right">Plafond</th>
                    <th class="px-3 py-2.5 text-center">Statut</th>
                    <th class="px-3 py-2.5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($items as $c)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-1.5">
                        <code class="text-xs font-mono bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $c->code }}</code>
                    </td>
                    <td class="px-3 py-1.5 font-medium text-gray-800">{{ $c->libelle }}</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold text-emerald-800">{{ $c->taux_salarie }} %</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold text-orange-600">{{ $c->taux_employeur }} %</td>
                    <td class="px-3 py-1.5 text-xs text-gray-500">{{ $c->base_cotisable_label }}</td>
                    <td class="px-3 py-1.5 text-right text-xs font-mono text-gray-500">
                        @if($c->plafond)
                            {{ number_format($c->plafond, 0, ',', ' ') }} FCFA
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($c->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('rh.cotisations.edit', $c) }}"
                               class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">Modifier</a>
                            <form method="POST" action="{{ route('rh.cotisations.destroy', $c) }}"
                                  onsubmit="return confirm('Supprimer cette cotisation ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

{{-- Résumé charges --}}
<div class="bg-white rounded-[4px] border border-gray-300 shadow-sm p-5 mt-2">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Récapitulatif charges</p>
    <div class="grid grid-cols-3 gap-4">
        <div class="text-center">
            <p class="text-[16px] font-bold text-emerald-800">
                {{ $contributions->where('is_active', true)->sum('taux_salarie') }} %
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Total charges salarié</p>
        </div>
        <div class="text-center">
            <p class="text-[16px] font-bold text-orange-600">
                {{ $contributions->where('is_active', true)->sum('taux_employeur') }} %
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Total charges patronales</p>
        </div>
        <div class="text-center">
            <p class="text-[16px] font-bold text-gray-800">
                {{ $contributions->where('is_active', true)->sum(fn($c) => $c->taux_salarie + $c->taux_employeur) }} %
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Charge totale</p>
        </div>
    </div>
</div>
@endif
@endsection
