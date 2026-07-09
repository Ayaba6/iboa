@extends('layouts.erp')
@section('title', 'Budgets de trésorerie')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Budgets</span>
@endsection

@section('content')
<div class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-[16px] font-bold text-gray-900">Budgets de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">Prévisionnel d'entrées/sorties · suivi Budget vs Réalisé</p>
        </div>
        @can('treasury.write')
        <a href="{{ route('tresorerie.budgets.create') }}" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau budget
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Nom</th>
                        <th class="text-center">Exercice</th>
                        <th class="text-right">Total prévu</th>
                        <th class="text-center">Statut</th>
                        <th class="text-left">Créé par</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($budgets as $b)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $b->name }}</td>
                        <td class="text-center tabular-nums text-gray-600">{{ $b->year }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($b->total_planned ?? 0, 0, ',', ' ') }}</td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[3px] text-[11px] font-medium {{ $b->status === 'valide' ? 'bg-emerald-100 text-emerald-700' : ($b->status === 'archive' ? 'bg-gray-100 text-gray-500' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($b->status) }}</span>
                        </td>
                        <td class="text-gray-500 text-xs">{{ $b->createdBy?->name ?? '—' }}</td>
                        <td class="text-right"><a href="{{ route('tresorerie.budgets.show', $b) }}" class="text-emerald-700 hover:underline text-xs font-medium">Voir →</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun budget de trésorerie.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($budgets->hasPages())<div class="px-3 py-1.5 border-t border-gray-100">{{ $budgets->links() }}</div>@endif
    </div>
</div>
@endsection
