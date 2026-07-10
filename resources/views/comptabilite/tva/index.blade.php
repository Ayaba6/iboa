@extends('layouts.erp')
@section('title', 'Déclarations TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Déclarations TVA</span>
@endsection

@section('content')
<div class="space-y-3">

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Déclarations TVA</h1>
        @can('accounting.write')
        <a href="{{ route('comptabilite.tva.create') }}"
           class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 py-1.5 rounded-[4px] transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle déclaration
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-[4px] border border-gray-300 px-3 py-2">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-1.5">
            <select name="status" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="soumis"    {{ ($filters['status'] ?? '') === 'soumis'    ? 'selected' : '' }}>Soumis</option>
                <option value="paye"      {{ ($filters['status'] ?? '') === 'paye'      ? 'selected' : '' }}>Payé</option>
            </select>
            <select name="period_type" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                <option value="">Toutes périodes</option>
                <option value="mensuel"      {{ ($filters['period_type'] ?? '') === 'mensuel'      ? 'selected' : '' }}>Mensuel</option>
                <option value="trimestriel"  {{ ($filters['period_type'] ?? '') === 'trimestriel'  ? 'selected' : '' }}>Trimestriel</option>
            </select>
            <select name="year" class="h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-violet-500 focus:border-violet-500">
                <option value="">Toutes années</option>
                @foreach(range(date('Y'), date('Y') - 3) as $y)
                <option value="{{ $y }}" {{ ($filters['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <div class="flex gap-1.5">
                <button type="submit" class="flex-1 h-8 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-medium px-3 rounded-[4px]">Filtrer</button>
                @if(array_filter($filters))
                <a href="{{ route('comptabilite.tva.index') }}" class="h-8 flex items-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-[12px] px-2.5 rounded-[4px]">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Période</th>
                        <th class="text-left">Type</th>
                        <th class="text-right">TVA Collectée</th>
                        <th class="text-right">TVA Déductible</th>
                        <th class="text-right">TVA Due</th>
                        <th class="text-right">Reste à payer</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($declarations as $d)
                    <tr>
                        <td class="font-mono font-semibold text-violet-600">{{ $d->number }}</td>
                        <td>
                            <p class="font-medium text-gray-800">{{ $d->period_label }}</p>
                            <p class="text-[10.5px] text-gray-400">{{ $d->period_start?->format('d/m/Y') }} → {{ $d->period_end?->format('d/m/Y') }}</p>
                        </td>
                        <td class="text-gray-500 capitalize">{{ $d->period_type }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($d->tva_collectee, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($d->tva_deductible, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums font-semibold {{ $d->tva_due > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ $d->tva_due > 0 ? number_format($d->tva_due, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-right tabular-nums font-semibold {{ $d->remaining > 0 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $d->remaining > 0 ? number_format($d->remaining, 0, ',', ' ') : '✓ 0' }}
                        </td>
                        <td>
                            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'soumis' => 'bg-blue-100 text-blue-700', 'paye' => 'bg-green-100 text-green-700']; @endphp
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $colors[$d->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $d->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('comptabilite.tva.show', $d) }}" class="text-violet-600 hover:text-violet-800 text-[11px] font-semibold">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-[12.5px]">Aucune déclaration TVA.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($declarations->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-[#f7faf8]">{{ $declarations->links() }}</div>
        @endif
    </div>

</div>
@endsection
